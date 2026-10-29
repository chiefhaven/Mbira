<?php

namespace Modules\EIS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Services\EisSubscriptionAccess;

class RetryAllFailedTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 3600;

    protected $businessId;
    protected $limit;

    public function __construct(?int $businessId = null, int $limit = 100)
    {
        $this->businessId = $businessId;
        $this->limit = $limit;
    }

    public function handle(): void
    {
        try {
            Log::info('RetryAllFailedTransactionsJob started', [
                'business_id' => $this->businessId,
                'limit' => $this->limit
            ]);

            $query = DB::table('eis_failed_transactions')
                ->where('next_retry_at', '<=', now())
                ->orderBy('next_retry_at');

            if ($this->businessId) {
                $query->where('business_id', $this->businessId);
            }

            $subscriptionAccess = app(EisSubscriptionAccess::class);
            $eligibleBusinessIds = (clone $query)->distinct()->pluck('business_id')->filter(
                fn ($businessId) => $subscriptionAccess->allows((int) $businessId)
            )->values();
            if ($eligibleBusinessIds->isEmpty()) {
                Log::info('No subscribed businesses have failed transactions to retry.');
                return;
            }

            $failed = $query->whereIn('business_id', $eligibleBusinessIds)->limit($this->limit)->get();

            if ($failed->isEmpty()) {
                Log::info('No failed transactions to retry.');
                return;
            }

            Log::info("Found {$failed->count()} failed transactions to retry.");

            foreach ($failed as $record) {
                SubmitOfflineSalesJob::dispatch($record->transaction_id, $record->business_id);

                DB::table('eis_failed_transactions')
                    ->where('id', $record->id)
                    ->update([
                        'attempts' => $record->attempts + 1,
                        'next_retry_at' => now()->addMinutes(30 * ($record->attempts + 1)),
                        'updated_at' => now(),
                    ]);
            }

            Log::info('All failed transactions dispatched for retry.');

        } catch (\Exception $e) {
            Log::error('RetryAllFailedTransactionsJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function tags(): array
    {
        return [
            'retry_all_failed_transactions',
            'business:' . ($this->businessId ?? 'all'),
        ];
    }
}