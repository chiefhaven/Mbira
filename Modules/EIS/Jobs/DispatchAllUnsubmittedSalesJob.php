<?php

namespace Modules\EIS\Jobs;

use App\Transaction;
use App\EisSetting;
use App\EisSale;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Modules\EIS\Models\EisSetting as ModelsEisSetting;
use Modules\EIS\Services\EisSubscriptionAccess;
use Modules\EIS\Services\Sales\SaleSubmissionRule;

class DispatchAllUnsubmittedSalesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $tries = 3;
    public $backoff = 60;
    public $timeout = 3600;

    protected $businessId;
    protected $chunkSize;
    protected $dateFrom;
    protected $dateTo;
    protected $locationIds;

    public function __construct(
        ?int $businessId = null,
        int $chunkSize = 50,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?array $locationIds = null
    ) {
        $this->businessId = $businessId;
        $this->chunkSize = $chunkSize;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->locationIds = $locationIds;
    }

    public function handle(): void
    {
        try {
            Log::info('DispatchAllUnsubmittedSalesJob started', [
                'business_id' => $this->businessId,
                'chunk_size' => $this->chunkSize,
                'date_from' => $this->dateFrom,
                'date_to' => $this->dateTo
            ]);

            $transactionIds = $this->getUnsubmittedTransactionIds();

            if (empty($transactionIds)) {
                Log::info('No unsubmitted transactions found');
                return;
            }

            $total = count($transactionIds);
            Log::info("Found {$total} unsubmitted transactions");

            // Create batch jobs using chunked IDs
            $batchJobs = [];
            $chunks = array_chunk($transactionIds, $this->chunkSize);
            
            foreach ($chunks as $index => $chunk) {
                $batchJobs[] = new ProcessSalesChunkJob($chunk, $index + 1);
            }

            // Dispatch batch
            if (!empty($batchJobs)) {
                $batch = Bus::batch($batchJobs)
                    ->name('EIS Sale Submission Batch - Business: ' . ($this->businessId ?? 'All'))
                    ->onQueue('default')
                    ->allowFailures(false)
                    
                    ->then(function ($batch) {
                        // All jobs completed successfully
                        Log::info('All EIS submission batches completed successfully', [
                            'batch_id' => $batch->id
                        ]);
                    })
                    ->catch(function ($batch, $e) {
                        // A job failed
                        Log::error('EIS submission batch failed', [
                            'batch_id' => $batch->id,
                            'error' => $e->getMessage()
                        ]);
                    })
                    ->finally(function ($batch) {
                        // The batch has finished executing
                        Log::info('EIS submission batch finished', [
                            'batch_id' => $batch->id,
                            'total_jobs' => $batch->totalJobs,
                            'failed_jobs' => $batch->failedJobs,
                            'pending_jobs' => $batch->pendingJobs
                        ]);
                    })
                    ->dispatch();

                Log::info('DispatchAllUnsubmittedSalesJob dispatched batch', [
                    'batch_id' => $batch->id,
                    'total_transactions' => $total,
                    'total_batches' => count($batchJobs)
                ]);
            }

        } catch (\Exception $e) {
            Log::error('DispatchAllUnsubmittedSalesJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function getUnsubmittedTransactionIds(): array
    {
        // Get businesses with valid EIS settings
        $subscriptionAccess = app(EisSubscriptionAccess::class);
        $validBusinessIds = ModelsEisSetting::where('status', 1)
            ->whereNotNull('tpin')
            ->whereNotNull('device_id')
            ->whereNotNull('jwt_token')
            ->pluck('business_id')
            ->unique()
            ->filter(fn ($businessId) => $subscriptionAccess->allows((int) $businessId))
            ->toArray();

        if (empty($validBusinessIds)) {
            Log::info('No businesses with valid EIS settings found');
            return [];
        }

        $query = Transaction::query()
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereNotNull('invoice_no')
            ->whereIn('business_id', $validBusinessIds)
            ->whereDoesntHave('eisSale', function ($query) {
                $query->where('status', 'submitted');
            });

        // Paid in full: the same rule that initiates a submission when a sale is saved.
        SaleSubmissionRule::constrainPaid($query);
        if ($this->locationIds !== null) {
            $query->whereIn('location_id', $this->locationIds);
        }

        if ($this->businessId) {
            $query->where('business_id', $this->businessId);
        }

        if ($this->dateFrom) {
            $query->whereDate('transaction_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('transaction_date', '<=', $this->dateTo);
        }

        // Order and get only IDs to reduce memory usage
        return $query->orderBy('transaction_date', 'asc')
            ->pluck('id')
            ->toArray();
    }

    public function tags(): array
    {
        return [
            'dispatch_all_unsubmitted_sales',
            'business:' . ($this->businessId ?? 'all'),
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DispatchAllUnsubmittedSalesJob failed permanently', [
            'business_id' => $this->businessId,
            'error' => $exception->getMessage()
        ]);
    }
}