<?php

namespace Modules\EIS\Jobs;

use App\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Services\Sales\SaleSubmissionService;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\EisSubscriptionAccess;

class SubmitSaleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 3600; // retry after 1 hour

    public function __construct(
        public int $transactionId
    ) {}

    public function handle(SaleSubmissionService $service)
    {
        $transaction = Transaction::find($this->transactionId);

        if (!$transaction) {
            Log::warning('EIS job skipped: transaction not found', [
                'transaction_id' => $this->transactionId
            ]);
            return;
        }

        if (!app(EisSubscriptionAccess::class)->allows((int) $transaction->business_id)) {
            Log::info('EIS sale submission skipped because the business package does not include EIS.', [
                'business_id' => $transaction->business_id,
                'transaction_id' => $transaction->id,
            ]);
            return;
        }

        $settings = \Modules\EIS\Services\Terminal\TerminalContext::forSale($transaction);

        if (!$settings) {
            Log::info('EIS not enabled for business', [
                'business_id' => $transaction->business_id
            ]);
            return;
        }

        try {
            $service->submit($transaction, $settings, true);

        } catch (\Throwable $e) {

            Log::error('EIS submission failed in job', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            // IMPORTANT: rethrow so Laravel retry system kicks in
            throw $e;
        }
    }

    public function failed(\Throwable $e)
    {
        Log::critical('EIS job permanently failed', [
            'transaction_id' => $this->transactionId,
            'error' => $e->getMessage(),
        ]);
    }
}