<?php

namespace Modules\EIS\Jobs;

use App\Transaction;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Services\EisSubscriptionAccess;

class ProcessSalesChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $tries = 3;
    public $backoff = 30;
    public $timeout = 600;

    protected $transactionIds;
    protected $chunkNumber;

    public function __construct(array $transactionIds, int $chunkNumber = 1)
    {
        $this->transactionIds = $transactionIds;
        $this->chunkNumber = $chunkNumber;
    }

    public function handle(): void
    {
        $subscriptionAccess = app(EisSubscriptionAccess::class);
        Log::info('ProcessSalesChunkJob started', [
            'chunk_number' => $this->chunkNumber,
            'transaction_count' => count($this->transactionIds)
        ]);

        foreach ($this->transactionIds as $transactionId) {
            try {
                $transaction = Transaction::find($transactionId);
                
                if (!$transaction) {
                    Log::warning('Transaction not found', [
                        'transaction_id' => $transactionId,
                        'chunk' => $this->chunkNumber
                    ]);
                    continue;
                }

                if (!$subscriptionAccess->allows((int) $transaction->business_id)) {
                    Log::info('EIS sale batch item skipped because the business package does not include EIS.', [
                        'business_id' => $transaction->business_id,
                        'transaction_id' => $transaction->id,
                    ]);
                    continue;
                }

                // Check if still unsubmitted (avoid duplicate processing)
                if ($transaction->eisSale && $transaction->eisSale->status === 'submitted') {
                    Log::info('Transaction already submitted, skipping', [
                        'transaction_id' => $transactionId,
                        'chunk' => $this->chunkNumber
                    ]);
                    continue;
                }

                // Dispatch individual submission job
                SubmitOfflineSalesJob::dispatch(
                    $transaction->id,
                    $transaction->business_id
                )->onQueue('eis-submission');

                Log::debug('Dispatched SubmitOfflineSalesJob', [
                    'transaction_id' => $transactionId,
                    'chunk' => $this->chunkNumber
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to process transaction', [
                    'transaction_id' => $transactionId,
                    'chunk' => $this->chunkNumber,
                    'error' => $e->getMessage()
                ]);
                
                // Continue with the next transaction
                continue;
            }
        }

        Log::info('ProcessSalesChunkJob completed', [
            'chunk_number' => $this->chunkNumber,
            'processed_count' => count($this->transactionIds)
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessSalesChunkJob failed permanently', [
            'chunk_number' => $this->chunkNumber,
            'transaction_ids' => $this->transactionIds,
            'error' => $exception->getMessage()
        ]);
    }
}