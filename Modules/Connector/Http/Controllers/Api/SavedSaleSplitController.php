<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Sales\SavedSaleSplitService;
use App\Services\Sales\SavedSplitException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SavedSaleSplitController extends Controller
{
    public function preview(Request $request, $id)
    {
        return $this->splitResponse(function () use ($request, $id) {
            return (new SavedSaleSplitService(DB::connection()))->preview($id, $request->user());
        });
    }

    public function split(Request $request, $id)
    {
        return $this->splitResponse(function () use ($request, $id) {
            return (new SavedSaleSplitService(DB::connection()))->split($id, $request->user(), $request->all());
        });
    }

    public function checkoutPreview(Request $request, $id)
    {
        return $this->splitResponse(function () use ($request, $id) {
            return (new SavedSaleSplitService(DB::connection()))->checkoutPreview($id, $request->user());
        });
    }

    public function checkout(Request $request, $id)
    {
        return $this->splitResponse(function () use ($request, $id) {
            return (new SavedSaleSplitService(DB::connection(), $request->attributes->get('connector_till_id')))->checkout($id, $request->user(), $request->all());
        });
    }

    public function additionsPreview(Request $request, $id)
    {
        return $this->splitResponse(function () use ($request, $id) {
            return (new SavedSaleSplitService(DB::connection()))->additionsPreview($id, $request->user());
        });
    }

    public function addItems(Request $request, $id)
    {
        return $this->splitResponse(function () use ($request, $id) {
            return (new SavedSaleSplitService(DB::connection()))->addItems($id, $request->user(), $request->all());
        });
    }

    public function editItems(Request $request, $id)
    {
        return $this->splitResponse(function () use ($request, $id) {
            return (new SavedSaleSplitService(DB::connection()))->editItems($id, $request->user(), $request->all());
        });
    }

    private function splitResponse(callable $work)
    {
        try {
            return response()->json(['data' => $work()]);
        } catch (SavedSplitException $e) {
            return response()->json(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]], $e->httpStatus);
        }
    }
}