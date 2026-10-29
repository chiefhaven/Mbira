<?php

namespace Modules\EIS\Console\Commands;

use Illuminate\Console\Command;
use App\Transaction;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Sales\DirectSaleSubmissionService;
use Modules\EIS\Services\EisSubscriptionAccess;

class DispatchAllUnsubmittedSalesCommand extends Command
{
    protected $signature = 'eis:dispatch-unsubmitted
                            {--business-id= : Business ID to process}
                            {--date-from= : Start date (YYYY-MM-DD)}
                            {--date-to= : End date (YYYY-MM-DD)}
                            {--dry-run : Show eligible transactions without submitting them}';

    protected $description = 'Submit all eligible unsubmitted sales to EIS directly';

    public function handle(DirectSaleSubmissionService $submission, EisSubscriptionAccess $subscriptionAccess)
    {
        $businessId = $this->option('business-id');
        $dateFrom = $this->option('date-from');
        $dateTo = $this->option('date-to');
        $dryRun = $this->option('dry-run');

        if ($businessId && !$subscriptionAccess->allows((int) $businessId)) {
            $this->error('The selected business package does not include the EIS module.');
            return 1;
        }

        $this->info('Starting direct submission of unsubmitted sales...');

        // Get unsubmitted transactions count
        $query = $this->buildTransactionQuery($businessId, $dateFrom, $dateTo);
        $total = $query->count();

        if ($total === 0) {
            $this->info('No unsubmitted transactions found.');
            return 0;
        }

        $this->info("Found {$total} unsubmitted transactions.");

        if ($dryRun) {
            $this->table(
                ['Transaction ID', 'Invoice No', 'Business ID', 'Date'],
                $query->limit(20)->get()->map(function ($transaction) {
                    return [
                        $transaction->id,
                        $transaction->invoice_no,
                        $transaction->business_id,
                        $transaction->transaction_date->format('Y-m-d H:i:s')
                    ];
                })->toArray()
            );

            if ($total > 20) {
                $this->info('... and ' . ($total - 20) . ' more transactions.');
            }

            $this->info('Dry run completed. Nothing was submitted.');
            return 0;
        }

        $submitted = 0;
        $failed = 0;
        $query->orderBy('id')->chunkById(100, function ($transactions) use ($submission, &$submitted, &$failed) {
            foreach ($transactions as $transaction) {
                $result = $submission->submit($transaction);
                if ($result['status'] === 'submitted') {
                    $submitted++;
                    $this->line("Submitted {$transaction->invoice_no} directly to EIS.");
                } elseif ($result['status'] !== 'skipped') {
                    $failed++;
                    $this->error("Failed {$transaction->invoice_no}: ".($result['message'] ?: 'EIS did not confirm submission.'));
                }
            }
        });
        $this->info("Direct submission finished: {$submitted} submitted, {$failed} failed.");

        return $failed === 0 ? 0 : 1;
    }

    protected function buildTransactionQuery($businessId, $dateFrom, $dateTo)
    {
        $validBusinessIds = EisSetting::where('status', 1)
            ->whereNotNull('tpin')
            ->whereNotNull('device_id')
            ->whereNotNull('jwt_token')
            ->pluck('business_id')
            ->unique()
            ->filter(fn ($businessId) => app(EisSubscriptionAccess::class)->allows((int) $businessId));
        $query = Transaction::where('type', 'sell')
            ->where('status', 'final')
            ->whereNotNull('invoice_no')
            ->where('payment_status', 'paid')
            ->whereIn('business_id', $validBusinessIds)
            ->whereDoesntHave('eisSale', function ($query) {
                $query->where('status', 'submitted');
            });

        if ($businessId) {
            $query->where('business_id', $businessId);
        }

        if ($dateFrom) {
            $query->where('transaction_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('transaction_date', '<=', $dateTo);
        }

        $query->whereHas('business', function ($query) {
            $query->whereHas('eisTerminals', function ($query) {
                $query->where('status', 1)
                    ->whereNotNull('tpin')
                    ->whereNotNull('device_id');
            });
        });

        return $query;
    }
}