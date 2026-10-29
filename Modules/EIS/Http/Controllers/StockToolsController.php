<?php

namespace Modules\EIS\Http\Controllers;

use App\BusinessLocation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Stock\StockOperationsClient;
use Modules\EIS\Services\Stock\StockToolsOperations;
use Modules\EIS\Services\Stock\StockToolsService;

class StockToolsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize($request);
        $locations = BusinessLocation::where('business_id', $request->user()->business_id)->get(['id', 'name']);
        $terminals = EisSetting::where('business_id', $request->user()->business_id)
            ->whereIn('location_id', $locations->pluck('id'))->get(['id', 'location_id', 'device_id']);
        $operations = StockToolsOperations::definitions();
        $history = DB::table('eis_stock_tool_requests')->where('business_id', $request->user()->business_id)->orderByDesc('id')->limit(50)
            ->get(['id', 'operation', 'eis_setting_id', 'status', 'result_message']);
        $requestKey = (string) Str::uuid();
        return view('eis::stock.tools', compact('locations', 'terminals', 'operations', 'history', 'requestKey'));
    }

    public function execute(Request $request, StockOperationsClient $client, StockToolsService $service)
    {
        $this->authorize($request);
        $input = $request->validate(['terminal_id' => 'required|integer|min:1', 'operation' => 'required|string',
            'payload' => 'required|string|max:500000', 'request_key' => 'required|uuid']);
        $setting = $this->terminal($request, (int) $input['terminal_id']);
        try {
            $object = json_decode($input['payload'], false, 64, JSON_THROW_ON_ERROR);
            abort_unless(is_object($object), 422, 'Use a JSON object for request fields.');
            $fields = json_decode($input['payload'], true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return $this->blocked('Use a valid JSON object for request fields.');
        }
        $this->authorizeMutation($request, $input['operation']);
        if (in_array($input['operation'], StockToolsOperations::MUTATIONS, true)) {
            try {
                $row = $service->prepare($setting, $input['operation'], $fields, $input['request_key'], (int) $request->user()->id);
            } catch (\InvalidArgumentException $exception) { return $this->blocked($exception->getMessage()); }
            return response()->json(['success' => true, 'prepared' => true, 'data' => $this->review($row)]);
        }
        $payload = StockToolsOperations::payload($setting, $input['operation'], $fields);
        try {
            $data = $client->lookup($setting, $input['operation'], $payload);
        } catch (\Illuminate\Http\Client\ConnectionException | \UnexpectedValueException | \InvalidArgumentException $exception) {
            Log::warning('EIS stock lookup unavailable', ['operation' => $input['operation'], 'terminal_id' => $setting->id,
                'exception' => get_class($exception)]);
            return response()->json(['success' => false, 'message' => 'EIS stock lookup failed or returned invalid data. No local records changed.'], 502);
        }
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function show(Request $request, int $id)
    {
        return response()->json(['success' => true, 'data' => $this->review($this->record($request, $id))]);
    }

    public function submit(Request $request, int $id, StockToolsService $service)
    {
        $row = $this->record($request, $id);
        $request->validate(['confirmed' => 'required|accepted']);
        try { $row = $service->submit($this->terminal($request, (int) $row->eis_setting_id), $id, (int) $request->user()->id); }
        catch (\InvalidArgumentException $exception) { return $this->blocked($exception->getMessage()); }
        return response()->json(['success' => in_array($row->status, ['submitted', 'verified'], true),
            'data' => $this->review($row), 'message' => $row->result_message ?: 'Already started; no resend.'],
            in_array($row->status, ['submitted', 'verified'], true) ? 200 : 409);
    }

    public function verify(Request $request, int $id, StockToolsService $service)
    {
        $this->record($request, $id);
        $input = $request->validate(['confirmed' => 'required|accepted', 'notes' => 'required|string|min:20|max:2000']);
        try { $row = $service->verify((int) $request->user()->business_id, $id, (int) $request->user()->id, $input['notes']); }
        catch (\InvalidArgumentException $exception) { return $this->blocked($exception->getMessage()); }
        return response()->json(['success' => true, 'data' => $this->review($row),
            'message' => 'External verification recorded. Request was not resent; reconcile actual stock and import catalog separately.']);
    }

    private function authorize(Request $request): void
    {
        abort_unless($request->user() && $request->user()->business_id && $request->user()->can('business_settings.access'), 403);
        abort_unless($request->user()->permitted_locations((int) $request->user()->business_id) === 'all', 403,
            'Taxpayer warehouse, production and catalog operations require access to all business locations.');
    }

    private function authorizeMutation(Request $request, string $operation): void
    {
        $permission = match ($operation) {
            'warehouse-transfer' => 'stock_transfer.create',
            'submit-conversion' => 'stock_adjustment.create',
            'add-product' => 'product.create',
            default => null,
        };
        if ($permission) { abort_unless($request->user()->can($permission), 403); }
    }

    private function terminal(Request $request, int $id): EisSetting
    {
        $setting = EisSetting::where('business_id', $request->user()->business_id)->findOrFail($id);
        BusinessLocation::where('business_id', $setting->business_id)->findOrFail($setting->location_id);
        return $setting;
    }

    private function record(Request $request, int $id): object
    {
        $this->authorize($request);
        $row = DB::table('eis_stock_tool_requests')->where('business_id', $request->user()->business_id)->where('id', $id)->first();
        abort_unless($row, 404);
        $this->authorizeMutation($request, $row->operation);
        $this->terminal($request, (int) $row->eis_setting_id);
        return $row;
    }

    private function review(object $row): array
    {
        $data = (array) $row;
        unset($data['identity'], $data['request_key']);
        $data['payload'] = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
        $data['response'] = $row->response ? json_decode($row->response, true, 512, JSON_THROW_ON_ERROR) : null;
        return $data;
    }

    private function blocked(string $message)
    {
        Log::warning('EIS stock tool blocked', ['reason' => $message]);
        return response()->json(['success' => false, 'message' => $message], 422);
    }
}
