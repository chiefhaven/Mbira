<?php

namespace Modules\Connector\Transformers;

use Illuminate\Http\Resources\Json\Resource;
use App\Utils\Util;

class SellResource extends Resource
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

        $commonUtil = new Util;
        $array['invoice_url'] = $commonUtil->getInvoiceUrl($array['id'], $array['business_id']);
        $array['payment_link'] = $commonUtil->getInvoicePaymentLink($array['id'], $array['business_id']);

        return $array;
    }
}
