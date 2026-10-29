<?php

namespace Modules\EIS\Listeners;

use App\Events\StockAdjustmentCreatedOrModified;
use Modules\EIS\Services\Stock\StockOutboundService;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Services\EisSubscriptionAccess;

class QueueStockAdjustmentToEis
{
    public function __construct(private StockOutboundService $service) {}

    public function handle(StockAdjustmentCreatedOrModified $event): void
    {
        if ($event->action !== 'added' || !$event->eisAdjustmentReason) {
            return;
        }

        $businessId = (int) $event->stockAdjustment->business_id;
        if (!app(EisSubscriptionAccess::class)->allows($businessId)) {
            Log::info('EIS stock adjustment queue skipped because the business package does not include EIS.', [
                'business_id' => $businessId,
                'stock_adjustment_id' => $event->stockAdjustment->id,
            ]);
            return;
        }

        $this->service->enqueueLocalAdjustment(
            $event->stockAdjustment,
            $event->eisAdjustmentReason,
            $event->eisTerminalId,
            $event->eisSiteId
        );
    }
}
