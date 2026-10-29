<?php

namespace Modules\EIS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Transaction;
use Modules\EIS\Services\Sales\SaleSubmissionService;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\EisSubscriptionAccess;

class RetryFailedSaleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    public function handle(SaleSubmissionService $service)
    {
        $subscriptionAccess = app(EisSubscriptionAccess::class);
        $due = DB::table('eis_failed_transactions')
            ->where('next_retry_at', '<=', now())
            ->where('attempts', '<', 5)
            ->distinct()
            ->pluck('business_id')
            ->filter(fn ($businessId) => $subscriptionAccess->allows((int) $businessId))
            ->values();
        if ($due->isEmpty()) {
            return;
        }

        // 1. LIMIT BATCH SIZE (CRITICAL)
        $failed = DB::table('eis_failed_transactions')
            ->whereIn('business_id', $due)
            ->where('next_retry_at', '<=', now())
            ->where('attempts', '<', 5)
            ->limit(50)
            ->get();

        foreach ($failed as $row) {
            // 2. Prevent race condition
            $updated = DB::table('eis_failed_transactions')
                ->where('id', $row->id)
                ->where('next_retry_at', '<=', now())
                ->where('attempts', '<', 5)
                ->update([
                    'next_retry_at' => now()->addMinutes(1), // temporary lock
                ]);

            if (!$updated) {
                continue;
            }

            try {
                $transaction = Transaction::where('business_id', $row->business_id)->find($row->transaction_id);

                if (!$transaction) {
                    throw new \InvalidArgumentException('Retry transaction does not belong to the business terminal.');
                }

                $settings = \Modules\EIS\Services\Terminal\TerminalContext::forSale($transaction);

                if (!$settings) {
                    continue;
                }

                $result = $service->submit($transaction, $settings, true);
                if ($result->status !== 'submitted') {
                    throw new \RuntimeException($result->error_message ?: 'Sale has not been submitted; retry record retained.');
                }

                // SUCCESS → remove
                DB::table('eis_failed_transactions')
                    ->where('id', $row->id)
                    ->delete();

            } catch (\Throwable $e) {

                $attempts = $row->attempts + 1;

                DB::table('eis_failed_transactions')
                    ->where('id', $row->id)
                    ->update([
                        'attempts' => $attempts,
                        'next_retry_at' => now()->addMinutes(pow(2, $attempts)),
                        'error_message' => $e->getMessage(),
                    ]);
            }
        }
    }
}