<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Sales\RestaurantBillService;
use App\Services\Sales\SavedSplitException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RestaurantBillController extends Controller
{
    public function handle(Request $request, $action, $id = 0)
    {
        $service = new RestaurantBillService(DB::connection(), $request->attributes->get('connector_till_id'));
        $user = $request->user();
        $location = (int) $request->query('location_id');
        try {
            if (in_array($action, ['config', 'board', 'queue', 'approvals'], true)) {
                $request->validate(['location_id' => 'required|integer|min:1', 'page' => 'sometimes|integer|min:1']);
            }
            $result = match ($action) {
                'config' => $service->config($user, $location),
                'board' => $service->board($user, $location, (int) $request->query('page', 1)),
                'queue' => $service->queue($user, $location, (string) $request->query('station')),
                'approvals' => $service->approvals($user, $location),
                'create' => $service->create($user, $request->all()),
                'preview' => $service->preview($user, (int) $id, true),
                'edit' => $service->edit($user, (int) $id, $request->all(), true),
                'approve' => $service->approve($user, (int) $id, $request->all()),
                'dispatch' => $service->dispatch($user, (int) $id, $request->all()),
                'transition' => $service->transition($user, (int) $id, $request->all()),
                'ticket' => $service->ticket($user, (int) $id, $request->all()),
                'pay' => $service->pay($user, (int) $id, $request->all()),
                'retry-fiscal' => $service->retryFiscal($user, (int) $id),
            };
            return response()->json(['data' => $result]);
        } catch (SavedSplitException $e) {
            return response()->json(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]], $e->httpStatus);
        }
    }
}
