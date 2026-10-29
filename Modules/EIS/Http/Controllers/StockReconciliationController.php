<?php

namespace Modules\EIS\Http\Controllers;

use App\BusinessLocation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Stock\StockInventoryClient;
use Modules\EIS\Services\Stock\StockReconciliationService;

class StockReconciliationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->can('business_settings.access') || $user->can('stock_adjustment.view'), 403);
        $locations = BusinessLocation::where('business_id', $user->business_id);
        $permitted = $user->permitted_locations((int) $user->business_id);
        if ($permitted !== 'all') {
            $locations->whereIn('id', $permitted);
        }
        $locations = $locations->get(['id', 'name']);
        $terminals = EisSetting::where('business_id', $user->business_id)->whereIn('location_id', $locations->pluck('id'))
            ->get(['id', 'device_id', 'location_id']);
        return view('eis::stock.reconcile', compact('locations', 'terminals'));
    }

    public function preview(Request $request, StockInventoryClient $client)
    {
        $input = $request->validate(['terminal_id' => 'required|integer|min:1']);
        $setting = EisSetting::where('business_id', $request->user()->business_id)->findOrFail($input['terminal_id']);
        $this->authorizeLocation($request, (int) $setting->location_id);
        return $this->result(fn () => (new StockReconciliationService(DB::connection(), $client))
            ->preview((int) $setting->business_id, (int) $setting->id, (int) $request->user()->id));
    }

    public function apply(Request $request, int $snapshotId, StockInventoryClient $client)
    {
        $snapshot = DB::table('eis_stock_reconciliations')->where('business_id', $request->user()->business_id)
            ->where('id', $snapshotId)->first();
        abort_unless($snapshot, 404);
        $this->authorizeLocation($request, (int) $snapshot->location_id);
        abort_unless($request->user()->can('stock_adjustment.create'), 403);
        return $this->result(fn () => (new StockReconciliationService(DB::connection(), $client))
            ->apply((int) $snapshot->business_id, $snapshotId, (int) $request->user()->id));
    }

    private function authorizeLocation(Request $request, int $locationId): void
    {
        $user = $request->user();
        abort_unless($user->can('business_settings.access') || $user->can('stock_adjustment.view'), 403);
        BusinessLocation::where('business_id', $user->business_id)->findOrFail($locationId);
        $permitted = $user->permitted_locations((int) $user->business_id);
        abort_unless($permitted === 'all' || in_array($locationId, $permitted), 403);
    }

    private function result(callable $work)
    {
        try {
            $snapshot = $work();
            return response()->json(['success' => true, 'data' => [
                'id' => $snapshot->id, 'status' => $snapshot->status,
                'lines' => json_decode($snapshot->lines, true, 512, JSON_THROW_ON_ERROR),
            ]]);
        } catch (\InvalidArgumentException | \UnexpectedValueException $e) {
            \Log::warning('EIS stock reconciliation blocked', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
