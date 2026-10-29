<?php

namespace Modules\EIS\Services\Stock;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Terminal\TerminalContext;
use Modules\EIS\Services\Utilities\EisUtilitiesClient;
use Modules\EIS\Services\EisSubscriptionAccess;

class StockToolsService
{
    public function __construct(private StockOperationsClient $client) {}

    public function prepare(EisSetting $setting, string $operation, array $input, string $key, int $actorId): object
    {
        app(EisSubscriptionAccess::class)->assertAllowed((int) $setting->business_id);

        $this->storage();
        if (!in_array($operation, StockToolsOperations::MUTATIONS, true) || !Str::isUuid($key)) {
            throw new \InvalidArgumentException('A supported stock mutation and valid request identifier are required.');
        }
        $payload = StockToolsOperations::payload($setting, $operation, $input);
        TerminalContext::assertActive($setting);
        return DB::transaction(function () use ($setting, $operation, $payload, $key, $actorId) {
            DB::table('business')->where('id', $setting->business_id)->lockForUpdate()->first();
            $setting = EisSetting::where('business_id', $setting->business_id)->lockForUpdate()->findOrFail($setting->id);
            TerminalContext::assertActive($setting);
            $this->location($setting);
            if ($operation === 'warehouse-transfer' && $payload['toSiteId'] !== TerminalContext::site($setting)) {
                throw new \InvalidArgumentException('Terminal destination changed. Reload the form before preparing this request.');
            }
            $existing = DB::table('eis_stock_tool_requests')->where('request_key', $key)->first();
            $json = json_encode($payload, JSON_THROW_ON_ERROR);
            if ($existing) {
                if ((int) $existing->business_id !== (int) $setting->business_id
                    || (int) $existing->eis_setting_id !== (int) $setting->id || $existing->operation !== $operation
                    || $existing->payload !== $json || (int) $existing->created_by !== $actorId) {
                    throw new \InvalidArgumentException('This request identifier already belongs to a different stock request. Review the saved request, or use Start a different request only for a genuine new operation.');
                }
                return $existing;
            }
            $identityValue = match ($operation) {
                'submit-conversion' => $payload['productionBatchId'],
                'add-product' => !empty($payload['barcode']) ? $payload['barcode']
                    : [$payload['hsCode'], $payload['name'], $payload['description'], $payload['uom']],
                default => $key,
            };
            $identity = hash('sha256', json_encode([$setting->business_id, EisUtilitiesClient::baseUrl(),
                $setting->tpin, $operation, $identityValue], JSON_THROW_ON_ERROR));
            if (DB::table('eis_stock_tool_requests')->where('identity', $identity)->exists()) {
                throw new \InvalidArgumentException('This production batch or product was already prepared. Review the existing request instead of duplicating it.');
            }
            $id = DB::table('eis_stock_tool_requests')->insertGetId([
                'business_id' => $setting->business_id, 'location_id' => $setting->location_id,
                'eis_setting_id' => $setting->id, 'created_by' => $actorId, 'request_key' => $key,
                'identity' => $identity, 'operation' => $operation, 'status' => 'prepared',
                'endpoint' => EisUtilitiesClient::baseUrl(), 'tin' => $setting->tpin,
                'site_id' => TerminalContext::site($setting), 'device_id' => $setting->device_id,
                'payload' => $json, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return DB::table('eis_stock_tool_requests')->find($id);
        });
    }

    public function submit(EisSetting $setting, int $id, int $actorId): object
    {
        app(EisSubscriptionAccess::class)->assertAllowed((int) $setting->business_id);

        $this->storage();
        if (DB::connection()->transactionLevel() !== 0) {
            throw new \InvalidArgumentException('Send reviewed stock tools outside an existing database transaction.');
        }
        [$row, $claimed, $setting] = DB::transaction(function () use ($setting, $id, $actorId) {
            DB::table('business')->where('id', $setting->business_id)->lockForUpdate()->first();
            $fresh = EisSetting::where('business_id', $setting->business_id)->lockForUpdate()->findOrFail($setting->id);
            TerminalContext::assertActive($fresh);
            $row = DB::table('eis_stock_tool_requests')->where('business_id', $fresh->business_id)
                ->where('eis_setting_id', $fresh->id)->where('id', $id)->lockForUpdate()->first();
            if (!$row) { throw new \InvalidArgumentException('Stock request does not belong to this terminal.'); }
            if ($row->status !== 'prepared') { return [$row, false, $fresh]; }
            $this->location($fresh);
            if ($row->endpoint !== EisUtilitiesClient::baseUrl() || $row->tin !== $fresh->tpin
                || $row->site_id !== TerminalContext::site($fresh) || $row->device_id !== $fresh->device_id
                || (int) $row->location_id !== (int) $fresh->location_id) {
                throw new \InvalidArgumentException('Saved endpoint or terminal ownership changed. Do not send this request.');
            }
            self::assertSettled($fresh, $id);
            if (DB::getSchemaBuilder()->hasTable('eis_stock_operations')
                && DB::table('eis_stock_operations')->where('business_id', $fresh->business_id)
                    ->whereIn('status', StockOutboundService::UNSETTLED_STATUSES)->lockForUpdate()->exists()) {
                throw new \InvalidArgumentException('Resolve queued or unresolved EIS stock outbox operations before sending taxpayer stock tools.');
            }
            if (DB::getSchemaBuilder()->hasTable('eis_utility_requests')) {
                $uploads = DB::table('eis_utility_requests')->where('business_id', $fresh->business_id)
                    ->where('endpoint', $row->endpoint)->where('tin', $row->tin)
                    ->where('operation', 'taxpayer-initial-inventory-upload')
                    ->whereIn('status', ['sending', 'unknown', 'needs_review', 'accepted'])->lockForUpdate()->get();
                $finalized = $uploads->contains(fn ($upload) => $upload->status === 'accepted'
                    && json_decode($upload->payload, true, 512, JSON_THROW_ON_ERROR)['isLastBatch'] === true);
                if ($uploads->contains(fn ($upload) => $upload->status !== 'accepted' || !$finalized)) {
                    throw new \InvalidArgumentException('Finish or verify the staged initial-inventory upload before sending taxpayer stock tools.');
                }
            }
            DB::table('eis_stock_tool_requests')->where('id', $id)->update([
                'status' => 'sending', 'submitted_by' => $actorId, 'submitted_at' => now(), 'updated_at' => now(),
            ]);
            return [$row, true, $fresh];
        });
        if (!$claimed) { return $row; }
        $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
        try {
            $result = $this->client->submitTool($setting, $row->operation, $payload);
        } catch (\Throwable $exception) {
            Log::error('EIS stock tool outcome unknown', ['request_id' => $id, 'operation' => $row->operation,
                'exception' => get_class($exception)]);
            $result = ['outcome' => 'unknown', 'response' => []];
        }
        $status = $result['outcome'];
        if ($row->operation === 'add-product' && $status === 'submitted') {
            $data = $result['response']['data'] ?? null;
            if (!is_array($data) || !is_int($data['productId'] ?? null) || $data['productId'] <= 0
                || !is_string($data['barcode'] ?? null) || trim($data['barcode']) === ''
                || (!empty($payload['barcode']) && $data['barcode'] !== $payload['barcode'])
                || ($data['hsCode'] ?? null) !== $payload['hsCode'] || ($data['name'] ?? null) !== $payload['name']
                || ($data['description'] ?? null) !== $payload['description']
                || ($data['uom'] ?? null) !== $payload['uom']) {
                $status = 'needs_review';
            }
        }
        $message = $status === 'submitted' ? 'EIS accepted the request. No local stock, manufacturing, purchase or catalog records were changed.'
            : 'EIS result: '.$status.'. Do not resend an uncertain request. Verify with MRA before further stock operations.';
        DB::table('eis_stock_tool_requests')->where('id', $id)->update([
            'status' => $status, 'response' => json_encode($result['response'], JSON_THROW_ON_ERROR),
            'result_message' => $message, 'updated_at' => now(),
        ]);
        return DB::table('eis_stock_tool_requests')->find($id);
    }

    public function verify(int $businessId, int $id, int $actorId, string $notes): object
    {
        app(EisSubscriptionAccess::class)->assertAllowed($businessId);

        $this->storage();
        if (mb_strlen(trim($notes)) < 20) {
            throw new \InvalidArgumentException('Record the MRA reference and confirmed outcome in at least 20 characters.');
        }
        return DB::transaction(function () use ($businessId, $id, $actorId, $notes) {
            DB::table('business')->where('id', $businessId)->lockForUpdate()->first();
            $row = DB::table('eis_stock_tool_requests')->where('business_id', $businessId)->where('id', $id)->lockForUpdate()->first();
            if (!$row || !in_array($row->status, ['unknown', 'needs_review'], true)) {
                throw new \InvalidArgumentException('Only completed uncertain requests can be marked verified. A sending request must be investigated; never reset it.');
            }
            DB::table('eis_stock_tool_requests')->where('id', $id)->update([
                'status' => 'verified', 'verified_by' => $actorId, 'verified_at' => now(),
                'verification_notes' => trim($notes), 'updated_at' => now(),
            ]);
            return DB::table('eis_stock_tool_requests')->find($id);
        });
    }

    private function storage(): void
    {
        foreach (['business', 'business_locations', 'eis_settings', 'eis_stock_tool_requests'] as $table) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                throw new \InvalidArgumentException('Run EIS stock-tool migrations before preparing or sending requests.');
            }
            if (DB::connection()->getDriverName() === 'mysql') {
                $engine = DB::selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
                if (!$engine || strcasecmp($engine->engine, 'InnoDB') !== 0) {
                    throw new \InvalidArgumentException('Reviewed stock requests require InnoDB storage: '.$table);
                }
            }
        }
    }

    public static function assertSettled(EisSetting $setting, ?int $exceptId = null): void
    {
        if (!DB::getSchemaBuilder()->hasTable('eis_stock_tool_requests')) { return; }
        $query = DB::table('eis_stock_tool_requests')->where('business_id', $setting->business_id)
            ->where('endpoint', EisUtilitiesClient::baseUrl())->where('tin', $setting->tpin)
            ->whereIn('status', ['sending', 'unknown', 'needs_review']);
        if ($exceptId !== null) { $query->where('id', '!=', $exceptId); }
        if ($query->lockForUpdate()->exists()) {
            throw new \InvalidArgumentException('Verify the unresolved taxpayer stock-tool request before sending further stock operations.');
        }
    }

    private function location(EisSetting $setting): void
    {
        $location = DB::table('business_locations')->where('business_id', $setting->business_id)
            ->where('id', $setting->location_id)->lockForUpdate()->first();
        if (!$location || (string) $location->eis_site_id !== TerminalContext::site($setting)) {
            throw new \InvalidArgumentException('Terminal site must match its assigned business location before sending stock tools.');
        }
    }
}
