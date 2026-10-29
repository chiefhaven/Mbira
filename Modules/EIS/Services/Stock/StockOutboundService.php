<?php

namespace Modules\EIS\Services\Stock;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Jobs\SubmitStockOperationJob;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Terminal\TerminalContext;
use Modules\EIS\Services\EisSubscriptionAccess;

class StockOutboundService
{
    public const UNSETTLED_STATUSES = ['queued', 'submitting', 'awaiting_approval', 'partial', 'unknown'];

    public function __construct(private StockOperationsClient $client) {}

    public function adjustmentReasons(EisSetting $setting): array
    {
        return $this->client->adjustmentReasons($setting);
    }

    public function enqueue(
        int $businessId,
        int $locationId,
        int $terminalId,
        string $operationType,
        string $siteId,
        array $requestPayload,
        array $linePayloads,
        ?int $actorId = null,
        ?int $sourceStockAdjustmentId = null,
        ?int $destinationLocationId = null
    ): int {
        app(EisSubscriptionAccess::class)->assertAllowed($businessId);

        if (!in_array($operationType, ['adjustment', 'transfer', 'informal_purchase'], true) || $linePayloads === []) {
            throw new \InvalidArgumentException('A supported stock operation with at least one item is required.');
        }
        $setting = TerminalContext::settings($businessId, null, $terminalId, $locationId);
        if (TerminalContext::site($setting) !== $siteId) {
            throw new \InvalidArgumentException('The selected EIS terminal site changed; recreate this stock operation.');
        }
        if ($operationType === 'transfer') {
            $destination = $destinationLocationId
                ? DB::table('business_locations')->where('business_id', $businessId)->where('id', $destinationLocationId)->first()
                : null;
            if (!$destination || (int) $destinationLocationId === $locationId
                || (string) $destination->eis_site_id !== (string) ($requestPayload['toSiteId'] ?? '')) {
                throw new \InvalidArgumentException('Transfer destination must be an exact EIS site mapped to another business location.');
            }
        } elseif ($destinationLocationId !== null) {
            throw new \InvalidArgumentException('Only site-to-site transfers may specify a destination location.');
        }
        $this->assertPayload($operationType, $siteId, $requestPayload, $linePayloads);

        $operationId = DB::transaction(function () use (
            $businessId, $locationId, $terminalId, $operationType, $siteId,
            $requestPayload, $linePayloads, $actorId, $sourceStockAdjustmentId, $destinationLocationId, $setting
        ) {
            DB::table('business')->where('id', $businessId)->lockForUpdate()->first();
            StockToolsService::assertSettled($setting);
            DB::table('business_locations')->where('business_id', $businessId)
                ->whereIn('id', array_filter([$locationId, $destinationLocationId]))->orderBy('id')->lockForUpdate()->get();
            $now = now();
            $id = DB::table('eis_stock_operations')->insertGetId([
                'business_id' => $businessId,
                'location_id' => $locationId,
                'destination_location_id' => $destinationLocationId,
                'eis_setting_id' => $terminalId,
                'source_stock_adjustment_id' => $sourceStockAdjustmentId,
                'operation_type' => $operationType,
                'status' => 'queued',
                'site_id' => $siteId,
                'request_payload' => json_encode($requestPayload, JSON_THROW_ON_ERROR),
                'created_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($linePayloads as $line) {
                DB::table('eis_stock_operation_lines')->insert([
                    'eis_stock_operation_id' => $id,
                    'product_id' => $line['product_id'] ?? null,
                    'barcode' => (string) $line['barcode'],
                    'quantity' => $line['quantity'],
                    'status' => ($line['initial_status'] ?? null) === 'rejected' ? 'rejected' : 'queued',
                    'request_payload' => json_encode($line['payload'], JSON_THROW_ON_ERROR),
                    'error_message' => $line['error_message'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            if (!DB::table('eis_stock_operation_lines')->where('eis_stock_operation_id', $id)
                ->where('status', 'queued')->exists()) {
                DB::table('eis_stock_operations')->where('id', $id)->update([
                    'status' => 'rejected',
                    'error_message' => 'No stock operation lines had a valid EIS product mapping.',
                    'updated_at' => $now,
                ]);
            }

            return $id;
        });

        if (DB::table('eis_stock_operation_lines')->where('eis_stock_operation_id', $operationId)
            ->where('status', 'queued')->exists()) {
            SubmitStockOperationJob::dispatch($operationId)->onQueue('eis-stock-operations')->afterCommit();
        }

        return $operationId;
    }

    public function verifyOutcome(int $businessId, int $operationId, int $actorId, string $notes, array $permitted): void
    {
        app(EisSubscriptionAccess::class)->assertAllowed($businessId);

        if (mb_strlen(trim($notes)) < 20) {
            throw new \InvalidArgumentException('Record the EIS verification reference and confirmed outcome (at least 20 characters).');
        }
        DB::transaction(function () use ($businessId, $operationId, $actorId, $notes, $permitted) {
            $operation = DB::table('eis_stock_operations')->where('business_id', $businessId)
                ->where('id', $operationId)->lockForUpdate()->first();
            if (!$operation || !in_array($operation->status, ['unknown', 'partial', 'awaiting_approval'], true)
                || ($permitted !== ['all'] && (!in_array((int) $operation->location_id, $permitted, true)
                    || ($operation->destination_location_id && !in_array((int) $operation->destination_location_id, $permitted, true))))) {
                throw new \InvalidArgumentException('Only unresolved operations at your permitted locations may be verified. A running worker must finish first.');
            }
            DB::table('eis_stock_operations')->where('id', $operationId)->update([
                'status' => 'verified', 'verified_by' => $actorId, 'verified_at' => now(),
                'verification_notes' => trim($notes), 'updated_at' => now(),
            ]);
        });
    }

    public function dispatchQueued(int $operationId, int $businessId, array $permittedLocationIds): bool
    {
        if (!app(EisSubscriptionAccess::class)->allows($businessId)) {
            return false;
        }

        $operation = DB::table('eis_stock_operations')->where('id', $operationId)
            ->where('business_id', $businessId)->first();
        if (!$operation || $operation->status !== 'queued'
            || ($permittedLocationIds !== ['all']
                && (!in_array((int) $operation->location_id, $permittedLocationIds, true)
                    || ($operation->destination_location_id
                        && !in_array((int) $operation->destination_location_id, $permittedLocationIds, true))))) {
            return false;
        }
        SubmitStockOperationJob::dispatch($operationId)->onQueue('eis-stock-operations')->afterCommit();

        return true;
    }

    public function enqueueLocalAdjustment(
        object $transaction,
        string $reason,
        ?int $terminalId,
        ?string $siteId
    ): ?int
    {
        if ($transaction->type !== 'stock_adjustment' || $transaction->source === 'eis_reconciliation') {
            return null;
        }
        if (!app(EisSubscriptionAccess::class)->allows((int) $transaction->business_id)) {
            return null;
        }

        if ($terminalId === null || $siteId === null) {
            return null;
        }
        $existingOperation = DB::table('eis_stock_operations')
            ->where('source_stock_adjustment_id', $transaction->id)->value('id');
        if ($existingOperation) {
            return (int) $existingOperation;
        }
        $setting = TerminalContext::settings(
            (int) $transaction->business_id,
            null,
            $terminalId,
            (int) $transaction->location_id
        );
        if (TerminalContext::site($setting) !== $siteId) {
            throw new \InvalidArgumentException('The selected EIS site changed while saving the stock adjustment.');
        }
        $lines = [];
        foreach (DB::table('stock_adjustment_lines')->where('transaction_id', $transaction->id)->get() as $line) {
            $maps = DB::table('eis_product_maps')->where('business_id', $transaction->business_id)
                ->where('product_id', $line->product_id)->whereNull('deleted_at')->limit(2)->get();
            $product = DB::table('products')->where('business_id', $transaction->business_id)->where('id', $line->product_id)->first();
            $barcode = $maps->count() === 1 ? $maps->first()->eis_product_id : null;
            if (!$product || !is_string($barcode) || trim($barcode) === '') {
                $lines[] = [
                    'product_id' => (int) $line->product_id,
                    'barcode' => 'unmapped-'.$line->product_id,
                    'quantity' => $line->quantity,
                    'payload' => [],
                    'initial_status' => 'rejected',
                    'error_message' => 'Product has no valid EIS stock barcode mapping.',
                ];
                continue;
            }
            $payload = [
                'barcode' => $barcode,
                'quantity' => (float) $line->quantity,
                'adjustmentReason' => $reason,
                'adjustmentType' => 'Decrease',
                'siteId' => $siteId,
            ];
            $lines[] = [
                'product_id' => (int) $line->product_id,
                'barcode' => $barcode,
                'quantity' => $line->quantity,
                'payload' => $payload,
            ];
        }
        if ($lines === []) {
            return null;
        }

        $id = $this->enqueue(
            (int) $transaction->business_id,
            (int) $transaction->location_id,
            (int) $setting->id,
            'adjustment',
            $siteId,
            ['siteId' => $siteId, 'lines' => array_column($lines, 'payload')],
            $lines,
            (int) $transaction->created_by,
            (int) $transaction->id
        );

        return $id;
    }

    public function submit(int $operationId): void
    {
        $operation = DB::table('eis_stock_operations')->where('id', $operationId)->first();
        if (!$operation || $operation->status !== 'queued') {
            return;
        }
        app(EisSubscriptionAccess::class)->assertAllowed((int) $operation->business_id);

        $updated = DB::transaction(function () use ($operation, $operationId) {
            DB::table('business')->where('id', $operation->business_id)->lockForUpdate()->first();
            $setting = EisSetting::where('business_id', $operation->business_id)
                ->where('location_id', $operation->location_id)->find($operation->eis_setting_id);
            try {
                if ($setting) { StockToolsService::assertSettled($setting); }
            } catch (\InvalidArgumentException $exception) {
                DB::table('eis_stock_operations')->where('id', $operationId)->where('status', 'queued')
                    ->update(['error_message' => $exception->getMessage(), 'updated_at' => now()]);
                Log::warning('EIS stock outbox blocked by unresolved stock tools', ['operation_id' => $operationId]);
                return 0;
            }
            return DB::table('eis_stock_operations')->where('id', $operationId)->where('status', 'queued')
                ->update(['status' => 'submitting', 'updated_at' => now()]);
        });
        if ($updated !== 1) {
            return;
        }

        try {
            $setting = TerminalContext::settings(
                (int) $operation->business_id,
                null,
                (int) $operation->eis_setting_id,
                (int) $operation->location_id
            );
            if (TerminalContext::site($setting) !== $operation->site_id) {
                throw new \InvalidArgumentException('The terminal site changed before this operation could be sent.');
            }
            TerminalContext::assertActive($setting);
            if ($operation->operation_type === 'transfer') {
                $payload = json_decode($operation->request_payload, true, 512, JSON_THROW_ON_ERROR);
                $destination = DB::table('business_locations')->where('business_id', $operation->business_id)
                    ->where('id', $operation->destination_location_id)->first();
                if (!$destination || (string) $destination->eis_site_id !== (string) ($payload['toSiteId'] ?? '')) {
                    throw new \InvalidArgumentException('Transfer destination site changed before submission.');
                }
            }
        } catch (\InvalidArgumentException $exception) {
            DB::table('eis_stock_operations')->where('id', $operationId)->update([
                'status' => 'rejected',
                'error_message' => 'Operation was not sent: terminal scope or activation is no longer valid.',
                'updated_at' => now(),
            ]);
            DB::table('eis_stock_operation_lines')->where('eis_stock_operation_id', $operationId)
                ->where('status', 'queued')->update([
                    'status' => 'rejected',
                    'error_message' => 'Operation was not sent because terminal validation failed.',
                    'updated_at' => now(),
                ]);
            return;
        }

        if ($operation->operation_type === 'adjustment') {
            $this->submitAdjustments($operation, $setting);
            return;
        }

        $payload = json_decode($operation->request_payload, true, 512, JSON_THROW_ON_ERROR);
        foreach (DB::table('eis_stock_operation_lines')->where('eis_stock_operation_id', $operationId)
            ->where('status', 'queued')->get() as $line) {
            DB::table('eis_stock_operation_lines')->where('id', $line->id)->update([
                'status' => 'submitting',
                'updated_at' => now(),
            ]);
        }
        try {
            $result = $operation->operation_type === 'transfer'
                ? $this->client->submitTransfer($setting, $payload)
                : $this->client->submitInformalPurchase($setting, $payload);
            $this->finishBulk($operation, $result);
        } catch (\Throwable $exception) {
            Log::error('EIS bulk stock submission has an unknown outcome', [
                'operation_id' => $operationId, 'exception' => get_class($exception),
            ]);
            $this->finishBulk($operation, [
                'outcome' => 'unknown',
                'response' => [],
                'message' => 'The network outcome is unknown. Do not resubmit this operation.',
            ]);
        }
    }

    public function markJobFailureUnknown(int $operationId): void
    {
        DB::table('eis_stock_operations')->where('id', $operationId)->whereIn('status', ['queued', 'submitting'])
            ->update([
                'status' => 'unknown',
                'error_message' => 'The worker stopped before a conclusive EIS result was recorded. Do not resubmit.',
                'updated_at' => now(),
            ]);
        DB::table('eis_stock_operation_lines')->where('eis_stock_operation_id', $operationId)
            ->whereIn('status', ['queued', 'submitting'])->update([
                'status' => 'unknown',
                'error_message' => 'The worker stopped before a conclusive EIS result was recorded.',
                'updated_at' => now(),
            ]);
    }

    private function submitAdjustments(object $operation, EisSetting $setting): void
    {
        $lines = DB::table('eis_stock_operation_lines')->where('eis_stock_operation_id', $operation->id)
            ->where('status', 'queued')->get();
        foreach ($lines as $line) {
            DB::table('eis_stock_operation_lines')->where('id', $line->id)->update([
                'status' => 'submitting',
                'updated_at' => now(),
            ]);
            try {
                $payload = json_decode($line->request_payload, true, 512, JSON_THROW_ON_ERROR);
                $result = $this->client->submitAdjustment($setting, $payload);
            } catch (\Throwable $exception) {
                Log::error('EIS stock adjustment has an unknown outcome', [
                    'operation_id' => $operation->id, 'line_id' => $line->id, 'exception' => get_class($exception),
                ]);
                $result = [
                    'outcome' => 'unknown',
                    'response' => [],
                    'message' => 'The network outcome is unknown. Do not resubmit this operation.',
                ];
            }
            $this->saveLineResult($line->id, $result);
            if ($result['outcome'] === 'unknown') {
                DB::table('eis_stock_operation_lines')->where('eis_stock_operation_id', $operation->id)
                    ->where('status', 'queued')->update([
                        'status' => 'rejected',
                        'error_message' => 'Not sent because an earlier line has an unknown outcome.',
                        'updated_at' => now(),
                    ]);
                $this->updateOperationStatus((int) $operation->id);
                return;
            }
        }
        $this->updateOperationStatus((int) $operation->id);
    }

    private function finishBulk(object $operation, array $result): void
    {
        $now = now();
        $status = $result['outcome'] === 'submitted' && $operation->operation_type === 'informal_purchase'
            ? 'awaiting_approval'
            : $result['outcome'];
        DB::table('eis_stock_operation_lines')->where('eis_stock_operation_id', $operation->id)
            ->where('status', 'submitting')->update([
                'status' => $status,
                'response_payload' => json_encode($result['response'] ?? [], JSON_THROW_ON_ERROR),
                'error_message' => $result['message'],
                'updated_at' => $now,
            ]);
        DB::table('eis_stock_operations')->where('id', $operation->id)->update([
            'response_payload' => json_encode($result['response'] ?? [], JSON_THROW_ON_ERROR),
            'error_message' => $result['message'],
            'submitted_at' => $result['outcome'] === 'submitted' ? $now : null,
            'updated_at' => $now,
        ]);
        DB::table('eis_stock_operations')->where('id', $operation->id)->update([
            'status' => $status,
            'updated_at' => $now,
        ]);
    }

    private function saveLineResult(int $lineId, array $result): void
    {
        DB::table('eis_stock_operation_lines')->where('id', $lineId)->update([
            'status' => $result['outcome'],
            'response_payload' => json_encode($result['response'] ?? [], JSON_THROW_ON_ERROR),
            'error_message' => $result['message'],
            'updated_at' => now(),
        ]);
        if ($result['outcome'] === 'submitted') {
            DB::table('eis_stock_operations')->where('id', DB::table('eis_stock_operation_lines')
                ->where('id', $lineId)->value('eis_stock_operation_id'))->update([
                'submitted_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function updateOperationStatus(int $operationId): void
    {
        $lines = DB::table('eis_stock_operation_lines')->where('eis_stock_operation_id', $operationId)
            ->get(['status', 'error_message']);
        $statuses = $lines->pluck('status');
        $submitted = $statuses->filter(fn ($status) => $status === 'submitted')->count();
        $awaitingApproval = $statuses->filter(fn ($status) => $status === 'awaiting_approval')->count();
        $unknown = $statuses->contains(fn ($status) => in_array($status, ['unknown', 'submitting'], true));
        $queued = $statuses->contains(fn ($status) => $status === 'queued');
        $rejected = $statuses->contains(fn ($status) => $status === 'rejected');
        if ($queued) {
            $status = 'submitting';
        } elseif ($submitted === $statuses->count()) {
            $status = 'submitted';
        } elseif ($awaitingApproval === $statuses->count()) {
            $status = 'awaiting_approval';
        } elseif ($submitted > 0) {
            $status = 'partial';
        } elseif ($awaitingApproval > 0) {
            $status = 'partial';
        } elseif ($unknown) {
            $status = 'unknown';
        } else {
            $status = 'rejected';
        }
        $message = null;
        if ($status === 'partial') {
            $message = sprintf(
                'Partial result: %d line(s) accepted, %d rejected and %d unknown. Review line statuses; do not retry.',
                $submitted,
                $statuses->filter(fn ($lineStatus) => $lineStatus === 'rejected')->count(),
                $statuses->filter(fn ($lineStatus) => in_array($lineStatus, ['unknown', 'submitting'], true))->count()
            );
        } elseif ($status === 'unknown') {
            $message = 'EIS outcome is unknown. Do not retry this operation.';
        } elseif ($status === 'rejected') {
            $rejection = $lines->first(fn ($line) => !empty($line->error_message));
            $message = $rejection->error_message ?? 'EIS rejected all stock operation lines.';
        }
        DB::table('eis_stock_operations')->where('id', $operationId)->update([
            'status' => $status,
            'error_message' => $message,
            'updated_at' => now(),
        ]);
    }

    private function assertPayload(string $type, string $siteId, array $payload, array $lines): void
    {
        foreach ($lines as $line) {
            $quantity = \Modules\EIS\Services\Products\OpeningStockInitializer::stockSource(['quantity' => $line['quantity'] ?? null])['quantity'];
            if (!is_string($line['barcode'] ?? null) || trim($line['barcode']) === ''
                || $quantity === null || bccomp($quantity, '0', 4) <= 0 || bccomp($quantity, '99999999999999.9999', 4) > 0
                || !is_array($line['payload'] ?? null)) {
                throw new \InvalidArgumentException('Stock operation lines require an EIS barcode, positive quantity and immutable payload.');
            }
        }
        if ($type === 'adjustment') {
            if (($payload['siteId'] ?? null) !== $siteId || count($payload['lines'] ?? []) !== count($lines)) {
                throw new \InvalidArgumentException('Adjustment payload must match the selected EIS site and item lines.');
            }
            foreach ($lines as $index => $line) {
                if (($line['initial_status'] ?? null) === 'rejected') {
                    continue;
                }
                $request = $line['payload'];
                if (($request['siteId'] ?? null) !== $siteId
                    || ($request['barcode'] ?? null) !== $line['barcode']
                    || (float) ($request['quantity'] ?? 0) !== (float) $line['quantity']
                    || !in_array($request['adjustmentType'] ?? null, ['Increase', 'Decrease'], true)
                    || !is_string($request['adjustmentReason'] ?? null) || trim($request['adjustmentReason']) === '') {
                    throw new \InvalidArgumentException('Each adjustment line needs the documented EIS fields and a selected reason.');
                }
            }
        } elseif ($type === 'transfer') {
            if (($payload['fromWarehouseToSite'] ?? null) !== false
                || ($payload['siteToWarehouse'] ?? null) !== false
                || ($payload['fromSiteId'] ?? null) !== $siteId
                || !is_string($payload['toSiteId'] ?? null) || $payload['toSiteId'] === $siteId
                || count($payload['items'] ?? []) !== count($lines)) {
                throw new \InvalidArgumentException('Site-to-site transfer must use distinct configured EIS sites.');
            }
            foreach ($lines as $index => $line) {
                $item = $payload['items'][$index] ?? [];
                if (($item['barcode'] ?? null) !== $line['barcode']
                    || (float) ($item['quantity'] ?? 0) !== (float) $line['quantity']
                    || !is_numeric($item['price'] ?? null)) {
                    throw new \InvalidArgumentException('Transfer items must match the stored operation lines.');
                }
            }
        } elseif (!isset($payload['supplierId'], $payload['receivingDate'], $payload['receivedBy'], $payload['totalItems'], $payload['totalValue'])
            || !is_array($payload['items'] ?? null) || count($payload['items']) !== count($lines)) {
            throw new \InvalidArgumentException('Informal purchase payload is missing required goods receiving fields.');
        } else {
            foreach ($lines as $index => $line) {
                $item = $payload['items'][$index] ?? [];
                if (($item['itemCode'] ?? null) !== $line['barcode']
                    || (float) ($item['quantityReceived'] ?? 0) !== (float) $line['quantity']
                    || !isset($item['description'], $item['quantityOrdered'], $item['unitOfMeasure'],
                        $item['unitPrice'], $item['totalPrice'], $item['isFinishedProduct'])) {
                    throw new \InvalidArgumentException('Goods receiving items must match the stored operation lines.');
                }
            }
        }
    }
}
