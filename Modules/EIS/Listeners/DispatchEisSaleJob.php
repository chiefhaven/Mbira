<?php

namespace Modules\EIS\Listeners;

use Modules\EIS\Jobs\SubmitOfflineSalesJob;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Events\SaleCompleted;
use Modules\EIS\Services\Sales\SaleSubmissionRule;
use Modules\EIS\Services\EisSubscriptionAccess;

class DispatchEisSaleJob
{
    public function handle(SaleCompleted $event): void
    {
        if (! SaleSubmissionRule::qualifies($event->transaction)) {
            Log::info('Queued EIS submission skipped for an unpaid or non-final sale.', [
                'transaction_id' => $event->transaction->id,
            ]);
            return;
        }

        if (!app(EisSubscriptionAccess::class)->allows((int) $event->transaction->business_id)) {
            Log::info('Queued EIS submission skipped because the business package does not include EIS.', [
                'transaction_id' => $event->transaction->id,
                'business_id' => $event->transaction->business_id,
            ]);
            return;
        }

        SubmitOfflineSalesJob::dispatch(
            (int) $event->transaction->id,
            (int) $event->transaction->business_id,
            $event->originalCheckout
        )->onQueue('eis-submission');
        Log::info('EIS sale submission queued after transaction commit.', [
            'transaction_id' => $event->transaction->id,
            'business_id' => $event->transaction->business_id,
        ]);
    }
}