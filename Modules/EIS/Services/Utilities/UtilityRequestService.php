<?php

namespace Modules\EIS\Services\Utilities;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Terminal\TerminalContext;
use Modules\EIS\Services\EisSubscriptionAccess;

class UtilityRequestService
{
    public function __construct(private EisUtilitiesClient $client) {}

    public function prepare(EisSetting $setting, string $operation, array $payload, int $actorId): object
    {
        app(EisSubscriptionAccess::class)->assertAllowed((int) $setting->business_id);

        if (!in_array($operation, UtilityOperations::MUTATIONS, true)) {
            throw new \InvalidArgumentException('Only mutating utilities require a reviewed request.');
        }
        $payload = UtilityOperations::payload($setting, $operation, $payload);
        TerminalContext::assertActive($setting);
        $receiptId = $operation === 'cancel-receipt' ? self::assertReceipt($setting, $payload['receiptNumber']) : null;
        return DB::transaction(function () use ($setting, $operation, $payload, $actorId, $receiptId) {
            DB::table('business')->where('id', $setting->business_id)->lockForUpdate()->first();
            if ($operation === 'cancel-receipt' && DB::getSchemaBuilder()->hasTable('eis_sale_adjustments')
                && DB::table('eis_sale_adjustments')->where('business_id', $setting->business_id)->where('sale_id', $receiptId)->exists()) {
                throw new \InvalidArgumentException('This sale has a linked fiscal return adjustment. Do not void the original invoice; verify its note chain with MRA.');
            }
            $identityPayload = $operation === 'cancel-receipt' ? ['transaction_id' => $receiptId] : $payload;
            $identity = hash('sha256', json_encode([
                $setting->business_id, $this->endpoint(), $setting->tpin, $operation, $this->canonical($identityPayload),
            ], JSON_THROW_ON_ERROR));
            $existing = DB::table('eis_utility_requests')->where('identity', $identity)->first();
            if ($existing) { return $existing; }
            $id = DB::table('eis_utility_requests')->insertGetId([
                'business_id' => $setting->business_id, 'location_id' => $setting->location_id,
                'eis_setting_id' => $setting->id, 'created_by' => $actorId, 'operation' => $operation,
                'status' => 'prepared', 'endpoint' => $this->endpoint(), 'tin' => $setting->tpin,
                'site_id' => TerminalContext::site($setting), 'device_id' => $setting->device_id,
                'identity' => $identity, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return DB::table('eis_utility_requests')->find($id);
        });
    }

    public function submit(EisSetting $setting, int $requestId, int $actorId): object
    {
        app(EisSubscriptionAccess::class)->assertAllowed((int) $setting->business_id);

        if (DB::connection()->transactionLevel() !== 0) {
            throw new \InvalidArgumentException('Submit reviewed utilities outside an existing database transaction so the send claim is durable.');
        }
        TerminalContext::assertActive($setting);
        [$request, $claimed, $setting] = DB::transaction(function () use ($setting, $requestId, $actorId) {
            DB::table('business')->where('id', $setting->business_id)->lockForUpdate()->first();
            $setting = EisSetting::where('business_id', $setting->business_id)->whereKey($setting->id)
                ->lockForUpdate()->firstOrFail();
            TerminalContext::assertActive($setting);
            $row = DB::table('eis_utility_requests')->where('business_id', $setting->business_id)
                ->where('eis_setting_id', $setting->id)->where('id', $requestId)->lockForUpdate()->first();
            if (!$row) { throw new \InvalidArgumentException('Utility request does not belong to this business terminal.'); }
            if ($row->status !== 'prepared') { return [$row, false, $setting]; }
            if ($row->endpoint !== $this->endpoint() || $row->site_id !== TerminalContext::site($setting)
                || $row->device_id !== $setting->device_id || $row->tin !== $setting->tpin
                || (int) $row->location_id !== (int) $setting->location_id) {
                throw new \InvalidArgumentException('Endpoint or terminal ownership changed. Do not submit this saved request.');
            }
            if ($row->operation === 'taxpayer-initial-inventory-upload') {
                \Modules\EIS\Services\Stock\StockToolsService::assertSettled($setting);
                $this->assertInitialUploadSequence($row);
            } else {
                $saleId = self::assertReceipt($setting, json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR)['receiptNumber']);
                if (DB::getSchemaBuilder()->hasTable('eis_sale_adjustments')
                    && DB::table('eis_sale_adjustments')->where('business_id', $setting->business_id)->where('sale_id', $saleId)->exists()) {
                    throw new \InvalidArgumentException('This sale has a linked fiscal return adjustment. Verify its note chain instead of voiding the original invoice.');
                }
            }
            DB::table('eis_utility_requests')->where('id', $row->id)->update([
                'status' => 'sending', 'submitted_by' => $actorId, 'submitted_at' => now(), 'updated_at' => now(),
            ]);
            $row->status = 'sending';
            return [$row, true, $setting];
        });
        if (!$claimed) {
            return $request;
        }
        $payload = json_decode($request->payload, true, 512, JSON_THROW_ON_ERROR);
        try {
            $result = $this->client->send($setting, $request->operation, $payload);
        } catch (\Throwable $exception) {
            Log::error('EIS utility mutation outcome unknown', [
                'request_id' => $requestId, 'operation' => $request->operation, 'exception' => get_class($exception),
            ]);
            $result = ['outcome' => 'unknown', 'response' => []];
        }
        $status = $result['outcome'];
        if ($request->operation === 'cancel-receipt' && $status === 'accepted') {
            $data = $result['response']['data'] ?? null;
            if (!is_array($data) || !is_string($data['invoiceNumber'] ?? null)
                || trim($data['invoiceNumber']) === '' || !is_string($data['approvalStatus'] ?? null)
                || trim($data['approvalStatus']) === '') {
                $status = 'needs_review';
            }
        }
        if ($request->operation === 'taxpayer-initial-inventory-upload' && $status === 'accepted') {
            $data = $result['response']['data'] ?? null;
            if (!is_array($data) || !is_int($data['totalItems'] ?? null)
                || !is_int($data['mappedItems'] ?? null) || !is_int($data['unmappedItems'] ?? null)
                || !is_bool($data['isPartialUpload'] ?? null)
                || $data['totalItems'] < 0 || $data['mappedItems'] < 0 || $data['unmappedItems'] < 0
                || $data['unmappedItems'] > 0 || !empty($data['skippedItems'])
                || ($payload['isLastBatch'] && ($data['isPartialUpload']
                    || $data['totalItems'] < count($payload['products'])
                    || $data['mappedItems'] !== $data['totalItems']))) {
                $status = 'needs_review';
            }
        }
        $message = match ($status) {
            'accepted' => $request->operation === 'cancel-receipt'
                ? 'EIS accepted the void request. Approval is separate; the local sale and stock were not changed.'
                : 'EIS accepted this initial-inventory batch. No local stock was changed.',
            'rejected' => 'EIS rejected the request. Review the saved result; it will not be resent.',
            'needs_review' => 'EIS returned an incomplete or partial result. Verify this request with MRA before taking further action.',
            default => 'The EIS outcome is unknown. Do not resend. Verify the result with MRA first.',
        };
        DB::table('eis_utility_requests')->where('id', $requestId)->update([
            'status' => $status, 'response' => json_encode($result['response'], JSON_THROW_ON_ERROR),
            'result_message' => $message, 'updated_at' => now(),
        ]);
        return DB::table('eis_utility_requests')->find($requestId);
    }

    private function assertInitialUploadSequence(object $request): void
    {
        $payload = json_decode($request->payload, true, 512, JSON_THROW_ON_ERROR);
        $previous = DB::table('eis_utility_requests')->where('business_id', $request->business_id)
            ->where('endpoint', $request->endpoint)->where('tin', $request->tin)
            ->where('operation', 'taxpayer-initial-inventory-upload')->where('id', '!=', $request->id)
            ->whereIn('status', ['sending', 'unknown', 'needs_review', 'accepted'])->lockForUpdate()->get();
        foreach ($previous as $batch) {
            $data = json_decode($batch->payload, true, 512, JSON_THROW_ON_ERROR);
            if ($batch->status !== 'accepted') {
                throw new \InvalidArgumentException('Resolve initial inventory batch #'.$batch->id.' with MRA before sending another batch. Never blindly resend.');
            }
            if ($data['isLastBatch']) {
                throw new \InvalidArgumentException('A final initial-inventory batch was already accepted for this taxpayer.');
            }
            if (array_intersect(array_column($data['products'], 'barCode'), array_column($payload['products'], 'barCode'))) {
                throw new \InvalidArgumentException('A barcode in this batch was already uploaded. Do not duplicate initial inventory.');
            }
        }
    }

    private function endpoint(): string
    {
        return EisUtilitiesClient::baseUrl();
    }

    public static function assertReceipt(EisSetting $setting, string $number): int
    {
        $receipt = DB::table('eis_sales as e')->join('transactions as t', 't.id', '=', 'e.transaction_id')
            ->where('e.business_id', $setting->business_id)->where('t.business_id', $setting->business_id)
            ->where('e.eis_setting_id', $setting->id)->where('e.location_id', $setting->location_id)
            ->where('t.location_id', $setting->location_id)->where('e.status', 'submitted')
            ->where(fn ($q) => $q->where('e.receipt_number', $number)->orWhere('e.fiscal_invoice_number', $number)
                ->orWhere('e.eis_invoice_number', $number))->select('e.transaction_id')->first();
        if (!$receipt) {
            throw new \InvalidArgumentException('Select a submitted receipt belonging to this terminal and location.');
        }
        return (int) $receipt->transaction_id;
    }

    private function canonical(array $value): array
    {
        if (!array_is_list($value)) { ksort($value); }
        foreach ($value as &$item) {
            if (is_array($item)) { $item = $this->canonical($item); }
        }
        return $value;
    }
}
