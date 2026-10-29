<?php

namespace Modules\EIS\Http\Controllers;

use App\BusinessLocation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Stock\StockOutboundService;
use Modules\EIS\Services\Terminal\TerminalContext;

class StockOutboundController extends Controller
{
    public function __construct(private StockOutboundService $service)
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId($request);
        abort_unless(
            $request->user()->can('business_settings.access')
                || $request->user()->can('stock_adjustment.create')
                || $request->user()->can('stock_transfer.create')
                || $request->user()->can('purchase.create'),
            403
        );
        $locations = $this->permittedLocations($request, $businessId)
            ->join('eis_settings as setting', function ($join) {
                $join->on('setting.id', '=', 'business_locations.default_eis_setting_id')
                    ->on('setting.business_id', '=', 'business_locations.business_id')
                    ->on('setting.location_id', '=', 'business_locations.id');
            })
            ->orderBy('business_locations.name')
            ->get([
                'business_locations.id', 'business_locations.name',
                'setting.branch_id as site_id', 'setting.device_id',
            ]);
        $permittedLocationIds = $this->permittedLocations($request, $businessId)->pluck('id');
        $products = DB::table('eis_product_maps as map')
            ->join('products', 'products.id', '=', 'map.product_id')
            ->where('map.business_id', $businessId)
            ->where('products.business_id', $businessId)
            ->whereNull('map.deleted_at')
            ->orderBy('products.name')
            ->get(['map.product_id', 'map.eis_product_id as barcode', 'products.name']);
        $operations = DB::table('eis_stock_operations as operation')
            ->join('business_locations as location', 'location.id', '=', 'operation.location_id')
            ->leftJoin('business_locations as destination', 'destination.id', '=', 'operation.destination_location_id')
            ->where('operation.business_id', $businessId)
            ->where(function ($query) use ($permittedLocationIds) {
                $query->whereIn('operation.location_id', $permittedLocationIds)
                    ->orWhereIn('operation.destination_location_id', $permittedLocationIds);
            })
            ->orderByDesc('operation.id')->limit(50)
            ->get([
                'operation.id', 'operation.location_id', 'operation.operation_type',
                'operation.status', 'operation.error_message', 'operation.created_at',
                'operation.submitted_at', 'location.name as location_name',
                'operation.verification_notes',
                'destination.name as destination_name',
            ]);
        $lineStatusGroups = DB::table('eis_stock_operation_lines')
            ->whereIn('eis_stock_operation_id', $operations->pluck('id'))
            ->get(['eis_stock_operation_id', 'status'])
            ->groupBy('eis_stock_operation_id');
        $operations->each(function ($operation) use ($lineStatusGroups) {
            $operation->line_summary = ($lineStatusGroups->get($operation->id) ?? collect())
                ->countBy('status')
                ->map(fn ($count, $status) => $count.' '.$status)
                ->implode(', ');
        });
        $productOptions = $products->map(fn ($product) => [
            'id' => $product->product_id, 'name' => $product->name, 'barcode' => $product->barcode,
        ])->values();
        return view('eis::stock.outbound', compact('locations', 'productOptions', 'operations'));
    }

    public function reasons(Request $request, int $locationId)
    {
        $businessId = $this->businessId($request);
        $location = $this->permittedLocations($request, $businessId)->findOrFail($locationId);
        $setting = $this->settingForLocation($businessId, $location);
        try {
            return response()->json(['success' => true, 'data' => $this->service->adjustmentReasons($setting)]);
        } catch (\Throwable $exception) {
            \Log::warning('EIS stock reasons unavailable', ['business_id' => $businessId, 'location_id' => $locationId, 'exception' => get_class($exception)]);
            return response()->json([
                'success' => false,
                'message' => 'EIS adjustment reasons are unavailable. No stock adjustment was sent.',
            ], 422);
        }
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId($request);
        abort_unless(
            $request->user()->can('business_settings.access')
                || $request->user()->can('stock_adjustment.create')
                || $request->user()->can('stock_transfer.create')
                || $request->user()->can('purchase.create'),
            403
        );
        $input = $request->validate([
            'location_id' => 'required|integer',
            'operation_type' => 'required|in:adjustment,transfer,informal_purchase',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.unit_of_measure' => 'nullable|string|max:50',
            'items.*.is_finished_product' => 'required_if:operation_type,informal_purchase|boolean',
            'adjustment_type' => 'required_if:operation_type,adjustment|in:Increase,Decrease',
            'adjustment_reason' => 'required_if:operation_type,adjustment|string|max:500',
            'taxpayer_remarks' => 'nullable|string|max:1000',
            'to_location_id' => 'required_if:operation_type,transfer|integer',
            'supplier_id' => 'required_if:operation_type,informal_purchase|integer|min:1',
            'received_by' => 'required_if:operation_type,informal_purchase|string|max:255',
            'receiving_date' => 'required_if:operation_type,informal_purchase|date_format:Y-m-d',
            'delivery_note_number' => 'nullable|string|max:255',
            'purchase_order_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);
        $permission = ['adjustment' => 'stock_adjustment.create', 'transfer' => 'stock_transfer.create',
            'informal_purchase' => 'purchase.create'][$input['operation_type']];
        abort_unless($request->user()->can('business_settings.access') || $request->user()->can($permission), 403);
        $location = $this->permittedLocations($request, $businessId)->findOrFail($input['location_id']);
        $setting = $this->settingForLocation($businessId, $location);
        $siteId = TerminalContext::site($setting);
        try {
            TerminalContext::assertActive($setting);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'location_id' => 'Activate the source EIS terminal before creating an outbound stock operation.',
            ]);
        }
        $targetSite = null;
        $destinationLocationId = null;

        if ($input['operation_type'] === 'transfer') {
            $target = $this->permittedLocations($request, $businessId)->findOrFail($input['to_location_id']);
            if ((int) $target->id === (int) $location->id) {
                throw ValidationException::withMessages(['to_location_id' => 'Choose a different destination site.']);
            }
            $targetSite = $this->settingForLocation($businessId, $target);
            $targetSite = TerminalContext::site($targetSite);
            $destinationLocationId = (int) $target->id;
        }

        if ($input['operation_type'] === 'adjustment') {
            $reasons = $this->service->adjustmentReasons($setting);
            if (!in_array($input['adjustment_reason'], $reasons, true)) {
                throw ValidationException::withMessages(['adjustment_reason' => 'Choose a current reason returned by EIS.']);
            }
        }

        $items = [];
        foreach ($input['items'] as $item) {
            $quantity = \Modules\EIS\Services\Products\OpeningStockInitializer::decimal($item['quantity']);
            $price = \Modules\EIS\Services\Products\OpeningStockInitializer::decimal($item['unit_price']);
            if ($quantity === null || $price === null || bccomp($quantity, '99999999999999.9999', 4) > 0) {
                throw ValidationException::withMessages(['items' => 'Use exact, nonnegative quantities/prices with at most four decimals within the stock ledger limit.']);
            }
            $maps = DB::table('eis_product_maps as map')
                ->join('products', 'products.id', '=', 'map.product_id')
                ->where('map.business_id', $businessId)
                ->where('map.product_id', $item['product_id'])
                ->where('products.business_id', $businessId)
                ->whereNull('map.deleted_at')
                ->limit(2)->get(['map.eis_product_id as barcode', 'products.name']);
            $map = $maps->count() === 1 ? $maps->first() : null;
            if (!$map || !is_string($map->barcode) || trim($map->barcode) === '') {
                throw ValidationException::withMessages(['items' => 'Choose a product with a valid EIS stock code.']);
            }
            $items[] = [
                'product_id' => (int) $item['product_id'],
                'barcode' => $map->barcode,
                'description' => $map->name,
                'quantity' => (float) $quantity,
                'unit_price' => (float) $price,
                'unit_of_measure' => trim($item['unit_of_measure'] ?? '') ?: 'Each',
                'is_finished_product' => (bool) ($item['is_finished_product'] ?? true),
            ];
        }

        if ($input['operation_type'] === 'adjustment') {
            $linePayloads = array_map(function ($item) use ($input, $siteId) {
                $payload = [
                    'barcode' => $item['barcode'],
                    'quantity' => $item['quantity'],
                    'adjustmentReason' => $input['adjustment_reason'],
                    'adjustmentType' => $input['adjustment_type'],
                    'siteId' => $siteId,
                ];
                if (!empty($input['taxpayer_remarks'])) {
                    $payload['taxpayerRemarks'] = $input['taxpayer_remarks'];
                }

                return [
                    'product_id' => $item['product_id'],
                    'barcode' => $item['barcode'],
                    'quantity' => $item['quantity'],
                    'payload' => $payload,
                ];
            }, $items);
            $requestPayload = ['siteId' => $siteId, 'lines' => array_column($linePayloads, 'payload')];
            $type = 'adjustment';
        } elseif ($input['operation_type'] === 'transfer') {
            $apiItems = array_map(fn ($item) => [
                'barcode' => $item['barcode'],
                'quantity' => $item['quantity'],
                'price' => $item['unit_price'],
            ], $items);
            $requestPayload = [
                'fromWarehouseToSite' => false,
                'siteToWarehouse' => false,
                'fromSiteId' => $siteId,
                'toSiteId' => $targetSite,
                'items' => $apiItems,
            ];
            $linePayloads = array_map(fn ($item, $apiItem) => [
                'product_id' => $item['product_id'],
                'barcode' => $item['barcode'],
                'quantity' => $item['quantity'],
                'payload' => $apiItem,
            ], $items, $apiItems);
            $type = 'transfer';
        } else {
            $apiItems = array_map(fn ($item) => [
                'itemCode' => $item['barcode'],
                'description' => $item['description'],
                'quantityOrdered' => $item['quantity'],
                'quantityReceived' => $item['quantity'],
                'unitOfMeasure' => $item['unit_of_measure'],
                'unitPrice' => $item['unit_price'],
                'totalPrice' => round($item['quantity'] * $item['unit_price'], 2),
                'isFinishedProduct' => $item['is_finished_product'],
            ], $items);
            $requestPayload = [
                'supplierId' => (int) $input['supplier_id'],
                'receivingDate' => \Carbon\Carbon::parse($input['receiving_date'])->toIso8601String(),
                'receivedBy' => $input['received_by'],
                'totalItems' => count($apiItems),
                'totalQuantity' => array_sum(array_column($apiItems, 'quantityReceived')),
                'totalValue' => round(array_sum(array_column($apiItems, 'totalPrice')), 2),
                'items' => $apiItems,
            ];
            foreach (['delivery_note_number' => 'deliveryNoteNumber', 'purchase_order_number' => 'purchaseOrderNumber', 'notes' => 'notes'] as $field => $apiField) {
                if (!empty($input[$field])) {
                    $requestPayload[$apiField] = $input[$field];
                }
            }
            $linePayloads = array_map(fn ($item, $apiItem) => [
                'product_id' => $item['product_id'],
                'barcode' => $item['barcode'],
                'quantity' => $item['quantity'],
                'payload' => $apiItem,
            ], $items, $apiItems);
            $type = 'informal_purchase';
        }

        $id = $this->service->enqueue(
            $businessId,
            (int) $location->id,
            (int) $setting->id,
            $type,
            $siteId,
            $requestPayload,
            $linePayloads,
            (int) $request->user()->id,
            null,
            $destinationLocationId
        );

        return redirect()->route('eis.stock.outbound.index')
            ->with('status', 'Stock operation queued for EIS submission. Operation #'.$id.'.');
    }

    public function dispatchQueued(Request $request, int $operationId)
    {
        $businessId = $this->businessId($request);
        abort_unless(
            $request->user()->can('business_settings.access')
                || $request->user()->can('stock_adjustment.create')
                || $request->user()->can('stock_transfer.create')
                || $request->user()->can('purchase.create'),
            403
        );
        $permitted = $request->user()->permitted_locations($businessId);
        $permittedIds = $permitted === 'all' ? ['all'] : array_map('intval', (array) $permitted);
        if (!$this->service->dispatchQueued($operationId, $businessId, $permittedIds)) {
            throw ValidationException::withMessages([
                'operation' => 'Only a queued, never-started EIS operation in a permitted location can be dispatched.',
            ]);
        }

        return redirect()->route('eis.stock.outbound.index')
            ->with('status', 'Queued operation #'.$operationId.' was dispatched. Submitted or ambiguous operations cannot be retried.');
    }

    public function verify(Request $request, int $operationId)
    {
        $businessId = $this->businessId($request);
        abort_unless($request->user()->can('business_settings.access'), 403);
        $input = $request->validate([
            'verification_notes' => 'required|string|min:20|max:2000',
            'confirmed' => 'accepted',
        ]);
        $permitted = $request->user()->permitted_locations($businessId);
        try {
            $this->service->verifyOutcome($businessId, $operationId, (int) $request->user()->id,
                $input['verification_notes'], $permitted === 'all' ? ['all'] : array_map('intval', (array) $permitted));
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['operation' => $exception->getMessage()]);
        }
        return redirect()->route('eis.stock.outbound.index')->with('status',
            'Verification recorded. No EIS request was resent. Compare/reconcile stock at all affected sites now.');
    }

    private function businessId(Request $request): int
    {
        abort_unless($request->user(), 401);
        $businessId = (int) $request->user()->business_id;
        abort_unless($businessId > 0, 403);

        return $businessId;
    }

    private function permittedLocations(Request $request, int $businessId)
    {
        $query = BusinessLocation::where('business_locations.business_id', $businessId);
        $permitted = $request->user()->permitted_locations($businessId);
        if ($permitted !== 'all') {
            $query->whereIn('business_locations.id', $permitted);
        }

        return $query;
    }

    private function settingForLocation(int $businessId, BusinessLocation $location): EisSetting
    {
        if ($location->default_eis_setting_id) {
            return TerminalContext::settings(
                $businessId,
                null,
                (int) $location->default_eis_setting_id,
                (int) $location->id
            );
        }
        $settings = EisSetting::where('business_id', $businessId)
            ->where('location_id', $location->id)->limit(2)->get();
        abort_unless($settings->count() === 1, 422, 'Select a default EIS terminal for this location first.');

        return TerminalContext::settings($businessId, null, (int) $settings->first()->id, (int) $location->id);
    }
}
