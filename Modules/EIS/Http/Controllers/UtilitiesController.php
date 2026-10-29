<?php

namespace Modules\EIS\Http\Controllers;

use App\BusinessLocation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Terminal\TerminalContext;
use Modules\EIS\Services\Utilities\EisUtilitiesClient;
use Modules\EIS\Services\Utilities\UtilityOperations;
use Modules\EIS\Services\Utilities\UtilityRequestService;
use Modules\EIS\Services\Utilities\VoidReceiptSynchronizer;

class UtilitiesController extends Controller
{
    public function execute(Request $request, EisUtilitiesClient $client, UtilityRequestService $mutations)
    {
        $input = $request->validate([
            'terminal_id' => 'required|integer|min:1', 'operation' => 'required|string',
            'payload' => 'required|string|max:8000000',
        ]);
        $this->authorizeAccount($request, $input['operation']);
        $setting = $this->terminal($request, (int) $input['terminal_id']);
        try {
            $object = json_decode($input['payload'], false, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return response()->json(['success' => false, 'message' => 'Payload must be a valid JSON object.'], 422);
        }
        abort_unless(is_object($object), 422, 'Payload must be a JSON object, not a list.');
        $payload = UtilityOperations::payload($setting, $input['operation'],
            json_decode($input['payload'], true, 64, JSON_THROW_ON_ERROR));
        $this->authorizeOperation($request, $setting, $input['operation'], $payload);
        if ($input['operation'] === 'get-void-receipts') {
            $schema = DB::getSchemaBuilder();
            if (!$schema->hasTable('eis_sales')
                || collect(['void_status', 'void_approval_status', 'void_synced_at', 'void_response_payload'])
                    ->contains(fn ($column) => !$schema->hasColumn('eis_sales', $column))) {
                return response()->json(['success' => false, 'message' => 'Run the EIS migrations before syncing void receipts.'], 503);
            }
        }
        if (in_array($input['operation'], UtilityOperations::MUTATIONS, true)) {
            try {
                $row = $mutations->prepare($setting, $input['operation'], $payload, (int) $request->user()->id);
            } catch (\InvalidArgumentException $exception) {
                return $this->blocked($exception->getMessage());
            }
            return response()->json(['success' => true, 'prepared' => true, 'data' => $this->reviewData($row)]);
        }
        try {
            $result = $client->send($setting, $input['operation'], $payload);
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            Log::warning('EIS utility lookup unavailable', ['operation' => $input['operation'], 'terminal_id' => $setting->id]);
            return response()->json(['success' => false, 'message' => 'EIS could not be reached. No successful lookup was confirmed.'], 502);
        } catch (\InvalidArgumentException $exception) {
            return $this->blocked($exception->getMessage());
        }
        if ($input['operation'] === 'get-void-receipts' && $result['outcome'] === 'accepted'
            && $request->user()->permitted_locations() !== 'all') {
            $items = $result['response']['data']['items'] ?? null;
            if (!is_array($items) || !array_is_list($items)
                || collect($items)->contains(fn ($item) => !is_array($item)
                    || ($item['invoiceNumber'] ?? null) !== $payload['invoiceNumber'])) {
                Log::warning('EIS void lookup did not respect the authorized receipt filter', ['terminal_id' => $setting->id]);
                return response()->json(['success' => false,
                    'message' => 'EIS did not return a verifiable receipt-scoped result. Ask an all-location administrator to check.'], 502);
            }
        }
        if ($input['operation'] === 'get-void-receipts' && $result['outcome'] === 'accepted') {
            $items = $result['response']['data']['items'] ?? null;
            if (!is_array($items) || !array_is_list($items)) {
                Log::warning('EIS void lookup returned an invalid item list', ['terminal_id' => $setting->id]);
                return response()->json(['success' => false,
                    'message' => 'EIS returned an invalid void receipt list. No local invoices were changed.'], 502);
            }
            try {
                $result['sync'] = app(VoidReceiptSynchronizer::class)->sync($setting, $items);
            } catch (\UnexpectedValueException $exception) {
                Log::warning('EIS void receipt sync rejected invalid data', [
                    'terminal_id' => $setting->id, 'reason' => $exception->getMessage(),
                ]);
                return response()->json(['success' => false,
                    'message' => 'EIS returned incomplete void receipt data. No local invoices were changed.'], 502);
            }
        }
        $success = $result['outcome'] === 'accepted';
        return response()->json(['success' => $success, 'data' => $result,
            'message' => $success ? 'EIS utility response received.' : 'EIS rejected the lookup or returned an inconclusive response.'],
            $success ? 200 : 502);
    }

    public function show(Request $request, int $requestId)
    {
        $row = $this->record($request, $requestId);
        $setting = $this->terminal($request, (int) $row->eis_setting_id);
        $this->authorizeOperation($request, $setting, $row->operation, json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR));
        return response()->json(['success' => true, 'data' => $this->reviewData($row)]);
    }

    public function submit(Request $request, int $requestId, UtilityRequestService $service)
    {
        $request->validate(['confirmed' => 'required|accepted', 'real_inventory' => 'sometimes|accepted']);
        $row = $this->record($request, $requestId);
        $setting = $this->terminal($request, (int) $row->eis_setting_id);
        $this->authorizeOperation($request, $setting, $row->operation, json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR));
        if ($row->operation === 'taxpayer-initial-inventory-upload') {
            $request->validate(['real_inventory' => 'required|accepted']);
        }
        try {
            $row = $service->submit($setting, $requestId, (int) $request->user()->id);
        } catch (\InvalidArgumentException $exception) {
            return $this->blocked($exception->getMessage());
        }
        return response()->json(['success' => $row->status === 'accepted', 'data' => $this->reviewData($row),
            'message' => $row->result_message ?: 'This request has already started; it was not resent.'],
            $row->status === 'accepted' ? 200 : 409);
    }

    private function authorizeAccount(Request $request, ?string $operation = null): void
    {
        $user = $request->user();
        abort_unless($user && $user->business_id
            && ($user->can('business_settings.access')
                || ($operation === 'cancel-receipt' && $user->can('sell.delete'))), 403);
    }

    private function terminal(Request $request, int $id): EisSetting
    {
        $setting = TerminalContext::settings((int) $request->user()->business_id, null, $id);
        BusinessLocation::where('business_id', $setting->business_id)->findOrFail($setting->location_id);
        $permitted = $request->user()->permitted_locations();
        abort_unless($permitted === 'all' || in_array((int) $setting->location_id, array_map('intval', (array) $permitted), true), 403);
        return $setting;
    }

    private function authorizeOperation(Request $request, EisSetting $setting, string $operation, array $payload): void
    {
        if (in_array($operation, ['product-status', 'taxpayer-initial-inventory-upload'], true)) {
            abort_unless($request->user()->permitted_locations() === 'all', 403,
                'Taxpayer-wide utilities require access to all business locations.');
        }
        if ($operation === 'taxpayer-initial-inventory-upload') {
            abort_unless($request->user()->can('stock_adjustment.create'), 403);
        }
        if ($operation === 'cancel-receipt') {
            abort_unless($request->user()->can('sell.delete'), 403);
            $this->assertReceipt($setting, $payload['receiptNumber']);
        }
        if ($operation === 'get-void-receipts' && $request->user()->permitted_locations() !== 'all') {
            abort_unless(!empty($payload['invoiceNumber']), 403, 'Filter void requests by a receipt from this terminal.');
            $this->assertReceipt($setting, $payload['invoiceNumber']);
        }
    }

    private function assertReceipt(EisSetting $setting, string $number): void
    {
        try {
            UtilityRequestService::assertReceipt($setting, $number);
        } catch (\InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }
    }

    private function record(Request $request, int $id): object
    {
        $row = DB::table('eis_utility_requests')->where('business_id', $request->user()->business_id)->where('id', $id)->first();
        abort_unless($row, 404);
        $this->authorizeAccount($request, $row->operation);
        $permitted = $request->user()->permitted_locations();
        abort_unless($permitted === 'all' || in_array((int) $row->location_id, array_map('intval', (array) $permitted), true), 403);
        return $row;
    }

    private function reviewData(object $row): array
    {
        return [
            'id' => $row->id, 'terminal_id' => $row->eis_setting_id, 'operation' => $row->operation,
            'location_id' => $row->location_id, 'endpoint' => $row->endpoint, 'tin' => $row->tin,
            'site_id' => $row->site_id, 'device_id' => $row->device_id,
            'created_by' => $row->created_by, 'submitted_by' => $row->submitted_by,
            'created_at' => $row->created_at, 'submitted_at' => $row->submitted_at,
            'status' => $row->status, 'payload' => json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR),
            'response' => $row->response === null ? null : json_decode($row->response, true, 512, JSON_THROW_ON_ERROR),
            'message' => $row->result_message,
        ];
    }

    private function blocked(string $message)
    {
        Log::warning('EIS utility request blocked', ['reason' => $message]);
        return response()->json(['success' => false, 'message' => $message], 422);
    }
}
