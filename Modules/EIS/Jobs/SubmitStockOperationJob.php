<?php

namespace Modules\EIS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\EIS\Services\Stock\StockOutboundService;
use Modules\EIS\Services\EisSubscriptionAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubmitStockOperationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $operationId) {}

    public function handle(StockOutboundService $service): void
    {
        $businessId = DB::table('eis_stock_operations')->where('id', $this->operationId)->value('business_id');
        if (!$businessId || !app(EisSubscriptionAccess::class)->allows((int) $businessId)) {
            Log::info('EIS stock operation skipped because the business package does not include EIS.', [
                'operation_id' => $this->operationId,
                'business_id' => $businessId,
            ]);
            return;
        }

        $service->submit($this->operationId);
    }

    public function failed(\Throwable $exception): void
    {
        app(StockOutboundService::class)->markJobFailureUnknown($this->operationId);
    }
}
