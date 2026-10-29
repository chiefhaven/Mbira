<?php

namespace Modules\EIS\Services\Sync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Services\Products\EisProductClient;
use Modules\EIS\Services\Products\OpeningStockInitializer;
use Modules\EIS\Services\Products\ProductTransformer;
use Modules\EIS\Services\Products\ProductUpsertService;
use Modules\EIS\Services\EisSubscriptionAccess;

class ProductSyncService
{
    public function __construct(
        protected EisProductClient $client,
        protected ProductTransformer $transformer,
        protected ProductUpsertService $upserter
    ) {}

    public function sync($settings, int $businessId)
    {
        app(EisSubscriptionAccess::class)->assertAllowed($businessId);

        if (!is_object($settings) || $businessId <= 0
            || (!is_int($settings->business_id ?? null) && !is_string($settings->business_id ?? null))
            || (string) $settings->business_id !== (string) $businessId) {
            throw new \InvalidArgumentException('EIS product settings must belong to the import business.');
        }
        $passedSite = EisProductClient::requestSiteId($settings);
        $storedSettings = \Modules\EIS\Services\Terminal\TerminalContext::settings(
            $businessId,
            isset($settings->jwt_token) ? $settings->jwt_token : null,
            isset($settings->id) ? (int) $settings->id : null,
            isset($settings->location_id) ? (int) $settings->location_id : null
        );
        $settings = clone $storedSettings;
        $sentSite = EisProductClient::requestSiteId($settings);
        if ($passedSite !== $sentSite) {
            throw new \InvalidArgumentException('EIS product request does not match the business terminal site.');
        }
        $locationId = (int) ($storedSettings->location_id ?? 0);
        if ($locationId <= 0) {
            throw new \InvalidArgumentException('EIS product import requires a terminal mapped to a business location.');
        }
        $requestSite = OpeningStockInitializer::siteId(['siteId' => $sentSite]);
        if ($requestSite === null || $requestSite !== (string) $sentSite) {
            throw new \InvalidArgumentException('EIS product request requires an exact valid site.');
        }
        $catalog = [];
        $pageHashes = [];
        for ($page = 1; ; $page++) {
            $response = $this->client->fetch($settings, $page);
            if (!is_array($response) || !array_key_exists('data', $response)
                || !is_array($response['data']) || !array_is_list($response['data'])
                || (array_key_exists('success', $response) && !in_array($response['success'], [true, 1], true))
                || !empty($response['error']) || !empty($response['errors'])
                || (array_key_exists('has_more', $response) && !in_array($response['has_more'], [true, false, 0, 1], true))) {
                throw new \UnexpectedValueException('EIS product pull returned an invalid or unsuccessful catalog page.');
            }
            $items = $response['data'];
            $hasMore = (bool) ($response['has_more'] ?? false);
            Log::info('EIS product page fetched', [
                'page' => $page,
                'count' => count($items),
            ]);
            $hash = hash('sha256', json_encode($items, JSON_THROW_ON_ERROR));
            if (($hasMore && !$items) || isset($pageHashes[$hash])) {
                throw new \UnexpectedValueException('EIS product pagination is incomplete or repeating.');
            }
            $pageHashes[$hash] = true;
            foreach ($items as $item) {
                if (!is_array($item)) {
                    throw new \UnexpectedValueException('EIS product pull contains an invalid product.');
                }
                $eisId = $item['productCode']
                    ?? $item['id']
                    ?? null;
                if ((!is_string($eisId) && !is_int($eisId)) || trim((string) $eisId) === '') {
                    throw new \UnexpectedValueException('EIS product pull contains a product without an identifier.');
                }
                $eisId = (string) $eisId;
                if (isset($catalog[$eisId])) {
                    throw new \UnexpectedValueException('EIS product pull contains duplicate product identifiers.');
                }
                $data = $this->transformer->fromEis($item, $eisId, $requestSite);
                if ($data['site_id'] !== $requestSite || $data['opening_stock_source']['quantity'] === null) {
                    $reason = $data['site_id'] !== $requestSite
                        ? 'invalid_or_mismatched_site'
                        : $data['opening_stock_source']['reason'];
                    $values = [];
                    foreach (['quantity', 'stock', 'stockQuantity', 'siteId', 'site_id', 'locationId'] as $field) {
                        if (array_key_exists($field, $item)) {
                            $value = $item[$field];
                            $values[$field] = [
                                'type' => get_debug_type($value),
                                'value' => is_scalar($value) || $value === null ? substr((string) $value, 0, 100) : null,
                            ];
                        }
                    }
                    Log::error('EIS product catalog validation failed', [
                        'business_id' => $businessId, 'page' => $page, 'eis_id' => $eisId,
                        'reason' => $reason, 'request_site' => $requestSite,
                        'stock_and_site_fields' => $values,
                        'serialize_precision' => ini_get('serialize_precision'),
                    ]);
                    throw new \UnexpectedValueException('EIS product pull rejected '.$eisId.': '.$reason
                        .'. Catalog and stock were not changed. See the EIS product catalog validation failed log entry.');
                }
                $catalog[$eisId] = $data;
            }
            if (!$hasMore) {
                break;
            }
        }

        $this->requireTransactionalStorage();
        DB::transaction(function () use ($businessId, $requestSite, $locationId, $settings, $catalog) {
            if (!DB::table('business')->where('id', $businessId)->lockForUpdate()->first()) {
                throw new \UnexpectedValueException('EIS import business does not exist.');
            }
            if (!DB::table('business_locations')->where('id', $locationId)
                ->where('business_id', $businessId)->where('eis_site_id', $requestSite)->exists()) {
                throw new \UnexpectedValueException('EIS terminal location must match its configured business location.');
            }

            foreach ($catalog as $eisId => $data) {
                $this->upserter->upsert($businessId, $data, (string) $eisId, $settings, $locationId);
            }

            Log::info('EIS product catalog reconciled', [
                'business_id' => $businessId, 'site_id' => $requestSite,
                'location_id' => $locationId, 'product_count' => count($catalog),
            ]);
        });
    }

    private function requireTransactionalStorage(): void
    {
        $db = DB::connection();
        if ($db->getDriverName() === 'sqlite') {
            return;
        }
        if ($db->getDriverName() !== 'mysql') {
            throw new \RuntimeException('EIS catalog reconciliation requires MySQL InnoDB or SQLite.');
        }
        foreach (['business', 'products', 'eis_product_maps', 'product_variations', 'variations',
            'business_locations', 'product_locations', 'variation_location_details'] as $table) {
            $row = $db->selectOne(
                'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$db->getTablePrefix().$table]
            );
            if (!$row || strtoupper((string) $row->engine) !== 'INNODB') {
                throw new \RuntimeException('EIS catalog reconciliation requires InnoDB storage for '.$table.'. Run migrations before syncing.');
            }
        }
    }
}