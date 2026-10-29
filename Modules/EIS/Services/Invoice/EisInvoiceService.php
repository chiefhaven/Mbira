<?php
namespace Modules\EIS\Services\Invoice;

use Modules\EIS\Services\Http\EisHttpClient;
use Modules\EIS\Models\EisSetting;

class EisInvoiceService
{
    public function __construct(
        protected EisHttpClient $client
    ) {}

    public function submit($sale, EisSetting $setting)
    {
        \Modules\EIS\Services\Terminal\TerminalContext::assertBusiness($setting, (int) $sale->business_id);
        $setting = \Modules\EIS\Services\Terminal\TerminalContext::forSale($sale);
        $payload = [
            'invoice_no' => $sale->reference_no,
            'total' => $sale->final_total,
            'items' => $sale->items->map(fn ($i) => [
                'name' => $i->product->name,
                'qty' => $i->quantity,
                'price' => $i->unit_price,
            ]),
        ];

        return $this->client->post(
            '/saleTransaction',
            $payload,
            $setting
        );
    }
}