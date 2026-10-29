<?php

namespace Modules\Connector\Transformers;

use App\BusinessLocation;
use App\Utils\BusinessUtil;
use App\Utils\Util;
use Illuminate\Http\Resources\Json\JsonResource;

class SellResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        $array = parent::toArray($request);

        if (($array['type'] ?? null) === 'sell' && ($array['status'] ?? null) === 'final'
            && in_array($array['payment_status'] ?? null, ['due', 'partial'], true)) {
            $businessId = $array['business_id'];
            $locationId = $array['location_id'];
            $key = 'connector.due_invoice_heading.'.$businessId.'.'.$locationId;
            if (! $request->attributes->has($key)) {
                $location = BusinessLocation::where('business_id', $businessId)->findOrFail($locationId);
                $layout = app(BusinessUtil::class)->invoiceLayout($businessId, $location->invoice_layout_id);
                $heading = null;
                if ($layout !== null) {
                    $heading = $layout->invoice_heading;
                    if (! empty($layout->invoice_heading_not_paid)) {
                        $heading .= ' '.$layout->invoice_heading_not_paid;
                    }
                }
                $request->attributes->set($key, $heading);
            }
            $array['invoice_heading'] = $request->attributes->get($key);
        }

        if (isset($array['eis_sale']) && is_array($array['eis_sale'])) {
            // Expose only the submitted invoice identifier, never the fiscal request payload.
            $payload = $array['eis_sale']['request_payload'] ?? null;
            $invoiceNumber = is_array($payload) ? ($payload['invoiceHeader']['invoiceNumber'] ?? null) : null;
            $array['eis_sale']['eis_invoice_number'] = is_string($invoiceNumber) && trim($invoiceNumber) !== ''
                ? trim($invoiceNumber) : null;
            unset($array['eis_sale']['request_payload']);
        }

        foreach ($array['sell_lines'] as $key => $value) {
            //check if mapping exists
            if (isset($value['sell_line_purchase_lines'])) {
                $purchase_lines = [];
                foreach ($value['sell_line_purchase_lines'] as $sell_line_purchase_line) {
                    //check mapped purchase line
                    if (isset($sell_line_purchase_line['purchase_line'])) {

                        //get purchase details of the sell line
                        $purchase_lines[] = [
                            'purchase_price' => $sell_line_purchase_line['purchase_line']['purchase_price'],
                            'pp_inc_tax' => $sell_line_purchase_line['purchase_line']['purchase_price_inc_tax'],
                            'lot_number' => $sell_line_purchase_line['purchase_line']['lot_number'],
                        ];
                    }
                }
                //unset mapping and set purchase details
                unset($array['sell_lines'][$key]['sell_line_purchase_lines']);
                $array['sell_lines'][$key]['purchase_price'] = $purchase_lines;
            }
        }

        $commonUtil = new Util;
        // Legacy token initialization is locked and writes only the token, never the sale graph.
        $token = $array['invoice_token'] ?? null;
        if (empty($token)) {
            $array['invoice_url'] = $commonUtil->getInvoiceUrl($array['id'], $array['business_id']);
            $token = \App\Transaction::where('business_id', $array['business_id'])
                ->where('id', $array['id'])->value('invoice_token');
            $array['invoice_token'] = $token;
        } else {
            $array['invoice_url'] = route($array['is_quotation'] == 1 ? 'show_quote' : 'show_invoice', ['token' => $token]);
        }
        $array['payment_link'] = $array['payment_status'] != 'paid' && $array['status'] == 'final'
            ? route('invoice_payment', ['token' => $token]) : '';

        return $array;
    }
}
