<?php

namespace Modules\EIS\Services\Stock;

use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Products\OpeningStockInitializer;
use Modules\EIS\Services\Terminal\TerminalContext;
use Modules\EIS\Services\Utilities\EisUtilitiesClient;

class StockInventoryClient
{
    public function __construct(private EisUtilitiesClient $client = new EisUtilitiesClient()) {}

    public function fetch(EisSetting $setting): array
    {
        $base = rtrim((string) config('eis.base_url'), '/');
        if ($base === '' || !$setting->jwt_token || !$setting->tpin) {
            throw new \InvalidArgumentException('EIS stock retrieval requires configured endpoint, token and taxpayer TIN.');
        }
        $site = TerminalContext::site($setting);
        // InventoryRequest has no pagination fields in the published EIS contract.
        $result = $this->client->send($setting, 'get-terminal-site-products', ['tin' => $setting->tpin, 'siteId' => $site]);
        $response = $result['response'];
        if ($result['outcome'] !== 'accepted'
            || !is_array($response['data'] ?? null) || !array_is_list($response['data'])) {
            throw new \UnexpectedValueException('EIS returned an unsuccessful or invalid site inventory response.');
        }
        $inventory = [];
        foreach ($response['data'] as $item) {
            if (!is_array($item) || !is_string($item['productCode'] ?? null) || trim($item['productCode']) === ''
                || isset($inventory[$item['productCode']])
                || OpeningStockInitializer::scopedSiteId($item, $site) !== $site) {
                throw new \UnexpectedValueException('EIS inventory contains duplicate, invalid or foreign-site products.');
            }
            $source = OpeningStockInitializer::stockSource($item);
            if ($source['quantity'] === null) {
                throw new \UnexpectedValueException('EIS inventory has an invalid stock quantity for '.$item['productCode'].'.');
            }
            $inventory[$item['productCode']] = $source['quantity'];
        }
        ksort($inventory, SORT_STRING);
        return $inventory;
    }
}
