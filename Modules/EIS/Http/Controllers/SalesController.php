<?php

namespace Modules\EIS\Http\Controllers;

use App\BusinessLocation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Sales\SaleAdjustmentService;
use Modules\EIS\Services\Sales\SaleOutcomeRecoveryService;
use Modules\EIS\Services\Sales\SalesApiClient;
use Modules\EIS\Services\Terminal\TerminalContext;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize($request);
        $locations = BusinessLocation::where('business_id', $request->user()->business_id);
        $permitted = $request->user()->permitted_locations((int) $request->user()->business_id);
        if ($permitted !== 'all') { $locations->whereIn('id', $permitted); }
        $locations = $locations->get(['id', 'name']);
        $sales = DB::table('eis_sales as e')->join('transactions as t', 't.id', '=', 'e.transaction_id')
            ->where('e.business_id', $request->user()->business_id)->where('t.business_id', $request->user()->business_id)
            ->whereIn('e.location_id', $locations->pluck('id'))->whereIn('t.location_id', $locations->pluck('id'))
            ->where('t.type', 'sell')->where('t.status', 'final')->where('t.payment_status', 'paid')
            ->whereIn('e.status', ['pending', 'submitted', 'failed'])
            ->orderByDesc('e.id')->limit(50)
            ->get(['e.id', 'e.transaction_id', 'e.invoice_number', 'e.eis_invoice_number', 'e.status',
                'e.receipt_number', 'e.error_message', 'e.location_id', 'e.eis_setting_id', 'e.submitted_at']);
        $adjustments = DB::table('eis_sale_adjustments')->where('business_id', $request->user()->business_id)
            ->whereIn('location_id', $locations->pluck('id'))->orderByDesc('id')->limit(50)->get();
        return view('eis::sales.index', compact('locations', 'sales', 'adjustments'));
    }

    public function lookup(Request $request, SalesApiClient $client)
    {
        $this->authorize($request);
        $input = $request->validate(['terminal_id' => 'required|integer|min:1',
            'operation' => 'required|in:last-submitted-online-transaction,last-submitted-offline-transaction,get-invoice-by-number',
            'invoice_number' => 'nullable|string|max:1000']);
        $setting = $this->terminal($request, (int) $input['terminal_id']);
        $payload = [];
        if ($input['operation'] === 'get-invoice-by-number') {
            $request->validate(['invoice_number' => 'required|string|max:1000']);
            $number = $this->ownedNumber($setting, $input['invoice_number']);
            abort_unless($number, 422, 'Select a tracked fiscal invoice or accepted note belonging to this terminal.');
            $payload = ['invoiceNumber' => $number];
        }
        try {
            $result = $client->send($setting, $input['operation'], $payload);
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            Log::warning('EIS sales lookup unavailable', ['terminal_id' => $setting->id, 'operation' => $input['operation']]);
            return response()->json(['success' => false, 'message' => 'EIS could not be reached. No invoice was confirmed.'], 502);
        } catch (\InvalidArgumentException $exception) {
            return $this->blocked($exception->getMessage());
        }
        $data = $result['response']['data'] ?? [];
        $header = $data['invoiceHeader'] ?? [];
        if ($result['outcome'] !== 'accepted' || ($header['siteId'] ?? null) !== TerminalContext::site($setting)
            || ($header['sellerTIN'] ?? null) !== $setting->tpin
            || !is_string($header['invoiceNumber'] ?? null)
            || (isset($payload['invoiceNumber']) && $header['invoiceNumber'] !== $payload['invoiceNumber'])
            || !is_array($data['invoiceLineItems'] ?? null) || !is_array($data['invoiceSummary'] ?? null)) {
            Log::warning('EIS sales lookup invalid or outside terminal scope', ['terminal_id' => $setting->id]);
            return response()->json(['success' => false, 'message' => 'EIS did not return a verifiable invoice for this terminal/site.'], 502);
        }
        if ($this->ownedNumber($setting, $header['invoiceNumber']) !== $header['invoiceNumber']) {
            Log::warning('EIS returned an invoice without matching local terminal history', ['terminal_id' => $setting->id]);
            return response()->json(['success' => false,
                'message' => 'The returned invoice is not linked to this terminal locally. Verify historical routing before viewing it.'], 502);
        }
        return response()->json(['success' => true, 'data' => $result['response']]);
    }

    public function show(Request $request, int $id)
    {
        return response()->json(['success' => true, 'data' => $this->review($this->record($request, $id))]);
    }

    public function syncSale(Request $request, int $id, SaleOutcomeRecoveryService $service)
    {
        $this->authorize($request);
        $sale = DB::table('eis_sales as e')->join('transactions as t', 't.id', '=', 'e.transaction_id')
            ->where('e.business_id', $request->user()->business_id)
            ->where('t.business_id', $request->user()->business_id)
            ->where('e.id', $id)->whereColumn('t.location_id', 'e.location_id')
            ->first(['e.id', 'e.transaction_id', 'e.business_id', 'e.location_id', 'e.eis_setting_id']);
        abort_unless($sale, 404);
        $permitted = $request->user()->permitted_locations((int) $request->user()->business_id);
        abort_unless($permitted === 'all'
            || in_array((int) $sale->location_id, array_map('intval', (array) $permitted), true), 403);

        $setting = EisSetting::where('business_id', $request->user()->business_id)
            ->where('location_id', $sale->location_id)->findOrFail($sale->eis_setting_id);
        $trackedSale = \Modules\EIS\Models\EisSale::where('business_id', $sale->business_id)->findOrFail($sale->id);
        try {
            $result = $service->sync($trackedSale, $setting);
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            Log::warning('EIS individual sale sync unavailable', ['eis_sale_id' => $id, 'terminal_id' => $setting->id]);
            return response()->json(['success' => false, 'message' => 'EIS could not be reached. The local sale was not changed.'], 502);
        } catch (\InvalidArgumentException $exception) {
            return $this->blocked($exception->getMessage());
        } catch (\UnexpectedValueException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 409);
        }
        return response()->json(['success' => true, 'data' => $result,
            'message' => $result['receipt_available']
                ? 'Invoice and official receipt details synchronized from EIS.'
                : ($result['offline_receipt_preserved']
                    ? 'Invoice synchronized from EIS. EIS returned no validation URL; the original offline QR code and receipt details were kept.'
                    : 'Invoice synchronized from EIS. No official receipt number was returned; receipt fields now reflect the EIS response.')]);
    }

    public function submit(Request $request, int $id, SaleAdjustmentService $service)
    {
        $row = $this->record($request, $id);
        abort_unless($request->user()->can('access_sell_return'), 403,
            'Sending fiscal adjustments requires full sales-return permission.');
        $request->validate(['confirmed' => 'required|accepted']);
        try {
            $row = $service->submit($this->terminal($request, (int) $row->eis_setting_id), $id, (int) $request->user()->id);
        } catch (\InvalidArgumentException $exception) {
            return $this->blocked($exception->getMessage());
        }
        return response()->json(['success' => $row->status === 'accepted', 'data' => $this->review($row),
            'message' => $row->result_message ?: 'This request has started and was not resent.'], $row->status === 'accepted' ? 200 : 409);
    }

    private function record(Request $request, int $id): object
    {
        $this->authorize($request);
        $row = DB::table('eis_sale_adjustments')->where('business_id', $request->user()->business_id)->where('id', $id)->first();
        abort_unless($row, 404);
        $permitted = $request->user()->permitted_locations((int) $request->user()->business_id);
        abort_unless($permitted === 'all' || in_array((int) $row->location_id, array_map('intval', (array) $permitted), true), 403);
        $this->terminal($request, (int) $row->eis_setting_id);
        return $row;
    }

    private function terminal(Request $request, int $id): EisSetting
    {
        $setting = EisSetting::where('business_id', $request->user()->business_id)->findOrFail($id);
        BusinessLocation::where('business_id', $setting->business_id)->findOrFail($setting->location_id);
        $permitted = $request->user()->permitted_locations((int) $request->user()->business_id);
        abort_unless($permitted === 'all' || in_array((int) $setting->location_id, array_map('intval', (array) $permitted), true), 403);
        return $setting;
    }

    private function authorize(Request $request): void
    {
        abort_unless($request->user() && $request->user()->business_id && $request->user()->can('business_settings.access'), 403);
    }

    private function ownedNumber(EisSetting $setting, string $number): ?string
    {
        $sale = DB::table('eis_sales as e')->join('transactions as t', 't.id', '=', 'e.transaction_id')
            ->where('e.business_id', $setting->business_id)->where('t.business_id', $setting->business_id)
            ->where('e.eis_setting_id', $setting->id)->where('e.location_id', $setting->location_id)
            ->where('t.location_id', $setting->location_id)->whereIn('e.status', ['submitted', 'pending', 'failed'])
            ->where(fn ($query) => $query->where('e.receipt_number', $number)->orWhere('e.fiscal_invoice_number', $number)
                ->orWhere('e.eis_invoice_number', $number))->first(['e.eis_invoice_number', 'e.fiscal_invoice_number']);
        if ($sale) { return $sale->eis_invoice_number ?: $sale->fiscal_invoice_number; }
        return DB::table('eis_sale_adjustments')->where('business_id', $setting->business_id)
            ->where('eis_setting_id', $setting->id)->where('location_id', $setting->location_id)
            ->where('status', 'accepted')->where('invoice_number', $number)->value('invoice_number');
    }

    private function review(object $row): array
    {
        return ['id' => $row->id, 'sale_id' => $row->sale_id, 'return_id' => $row->return_id,
            'terminal_id' => $row->eis_setting_id, 'location_id' => $row->location_id, 'endpoint' => $row->endpoint,
            'site_id' => $row->site_id, 'tin' => $row->tin, 'device_id' => $row->device_id,
            'status' => $row->status, 'note_type' => $row->note_type, 'invoice_number' => $row->invoice_number,
            'created_by' => $row->created_by, 'submitted_by' => $row->submitted_by, 'submitted_at' => $row->submitted_at,
            'payload' => json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR),
            'response' => $row->response ? json_decode($row->response, true, 512, JSON_THROW_ON_ERROR) : null,
            'message' => $row->result_message];
    }

    private function blocked(string $message)
    {
        Log::warning('EIS sales operation blocked', ['reason' => $message]);
        return response()->json(['success' => false, 'message' => $message], 422);
    }
}
