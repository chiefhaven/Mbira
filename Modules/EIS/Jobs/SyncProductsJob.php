<?php

namespace Modules\EIS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Models\EisConfiguration;
use Modules\EIS\Models\EisTerminalConfiguration;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Stock\StockInventoryClient;
use Modules\EIS\Services\Stock\StockReconciliationService;
use Modules\EIS\Services\Sync\ProductSyncService;
use Modules\EIS\Services\Terminal\TerminalContext;
use Modules\EIS\Services\EisSubscriptionAccess;

class SyncProductsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public $uniqueFor = 3600;

    public function __construct(
        public int $businessId,
        public ?int $terminalId = null,
        public bool $reconcileStock = false,
        public ?int $actorId = null,
        public bool $allTerminals = false
    ) {}

    public function uniqueId(): string
    {
        return implode(':', [
            $this->businessId,
            $this->terminalId ?? 'all',
            $this->reconcileStock ? 'catalog-and-stock' : ($this->allTerminals ? 'all-catalog' : 'catalog'),
        ]);
    }

    public function handle(ProductSyncService $service, StockInventoryClient $stockClient): void
    {
        if (!app(EisSubscriptionAccess::class)->allows($this->businessId)) {
            Log::info('EIS product sync skipped because the business package does not include EIS.', [
                'business_id' => $this->businessId,
                'terminal_id' => $this->terminalId,
            ]);
            return;
        }

        if (!$this->allTerminals && !$this->reconcileStock) {
            Log::info('Products syncing', [
                'business_id' => $this->businessId,
                'terminal_id' => $this->terminalId,
            ]);
            $settings = TerminalContext::settings($this->businessId, null, $this->terminalId);
            $service->sync($settings, $this->businessId);
            return;
        }

        if (!$this->actorId || $this->actorId <= 0) {
            throw new \InvalidArgumentException('Automatic EIS stock reconciliation requires the requesting user for its audit trail.');
        }

        $settingsList = EisSetting::where('business_id', $this->businessId)
            ->where('status', 1)
            ->when($this->terminalId !== null, fn ($query) => $query->whereKey($this->terminalId))
            ->orderBy('id')
            ->get();
        $failures = [];
        $activeCount = 0;
        $reconciliation = new StockReconciliationService(DB::connection(), $stockClient);

        foreach ($settingsList as $settings) {
            try {
                $configuration = EisConfiguration::where('business_id', $this->businessId)
                    ->where('eis_setting_id', $settings->id)->firstOrFail();
                $terminal = EisTerminalConfiguration::where('configuration_id', $configuration->id)->firstOrFail();
                if (!TerminalContext::isOperational((bool) $settings->status, $terminal->is_active)) {
                    Log::info('Skipping automatic EIS stock reconciliation for inactive terminal', [
                        'business_id' => $this->businessId,
                        'terminal_id' => $settings->id,
                        'location_id' => $settings->location_id,
                    ]);
                    continue;
                }
                $activeCount++;

                $service->sync($settings, $this->businessId);
                if (!$this->reconcileStock) {
                    Log::info('Automatic EIS catalog sync completed', [
                        'business_id' => $this->businessId,
                        'terminal_id' => $settings->id,
                        'location_id' => $settings->location_id,
                    ]);
                    continue;
                }
                $snapshot = $reconciliation->preview($this->businessId, (int) $settings->id, $this->actorId);
                $applied = $reconciliation->apply($this->businessId, (int) $snapshot->id, $this->actorId);
                Log::info('Automatic EIS stock reconciliation completed', [
                    'business_id' => $this->businessId,
                    'terminal_id' => $settings->id,
                    'location_id' => $settings->location_id,
                    'snapshot_id' => $applied->id,
                    'status' => $applied->status,
                ]);
            } catch (\Throwable $exception) {
                $failures[] = 'terminal '.$settings->id.': '.$exception->getMessage();
                Log::error('Automatic EIS product and stock sync failed', [
                    'business_id' => $this->businessId,
                    'terminal_id' => $settings->id,
                    'location_id' => $settings->location_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($failures) {
            throw new \RuntimeException('Automatic EIS product and stock sync failed: '.implode('; ', $failures));
        }
        if ($activeCount === 0) {
            throw new \RuntimeException('Automatic EIS product and stock sync found no active terminals for this business.');
        }
    }
}