<?php

/** PHP CLI only: bare Container/Capsule, SQLite :memory:, no Laravel/.env/network. */
require dirname(__DIR__, 2).'/vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Modules\EIS\Services\Products\OpeningStockInitializer;
use Modules\EIS\Services\Products\ProductTransformer;
use Modules\EIS\Services\Products\ProductUpsertService;
use Modules\EIS\Services\Products\EisProductClient;
use Modules\EIS\Services\Sync\ProductSyncService;

final class OpeningSyntheticClient extends EisProductClient
{
    public array $requests = [];
    public function __construct(private array $pages) {}
    public function fetch($settings, int $page = 1) {
        $this->requests[] = ['business' => $settings->business_id, 'site' => self::requestSiteId($settings), 'page' => $page];
        return $this->pages[$page - 1] ?? ['data' => []];
    }
}

final class OpeningTestLog
{
    public array $warnings = [];
    public function __call($method, $args) {
        if (in_array($method, ['warning', 'error'], true)) { $this->warnings[] = $args; }
    }
}

/** Exercise engine checks with synthetic metadata only; SQL still runs on SQLite. */
final class OpeningEngineConnection extends Illuminate\Database\SQLiteConnection
{
    public string $badTable = 'eis_product_maps';
    public function getDriverName() { return 'mysql'; }
    public function selectOne($query, $bindings = [], $useReadPdo = true) {
        if (str_contains($query, 'information_schema.TABLES')) {
            return (object) ['engine' => $bindings[0] === $this->badTable ? 'MyISAM' : 'InnoDB'];
        }
        return parent::selectOne($query, $bindings, $useReadPdo);
    }
}
$checks = 0;
function verify($condition, $message) {
    global $checks;
    ++$checks;
    if (!$condition) { throw new RuntimeException('FAIL: '.$message); }
}
function openingFixture() {
    $capsule = new Capsule(new Container());
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $capsule->setEventDispatcher(new Illuminate\Events\Dispatcher($capsule->getContainer()));
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    Illuminate\Database\Eloquent\Model::clearBootedModels();
    $container = $capsule->getContainer();
    Container::setInstance($container);
    $container->instance('db', $capsule->getDatabaseManager());
    $container['config']['eis.system_user_id'] = 7;
    $log = new OpeningTestLog();
    $container->instance('log', $log);
    Facade::setFacadeApplication($container);
    Facade::clearResolvedInstances();
    $db = $capsule->getConnection();
    verify($db->getDriverName() === 'sqlite' && $db->getDatabaseName() === ':memory:', 'isolated fixture');
    $tables = [
        'business' => [],
        'products' => ['business_id', 'type', 'created_by', 'enable_stock', 'name', 'product_description', 'sku', 'tax',
            'eis_product_id', 'eis_last_synced_at', 'unit_id', 'expiry_period', 'deleted_at'],
        'eis_settings' => ['business_id', 'location_id', 'site_id', 'branch_id', 'jwt_token'],
        'product_variations' => ['product_id', 'variation_template_id', 'name', 'is_dummy'],
        'variations' => ['product_id', 'product_variation_id', 'name', 'default_sell_price', 'default_purchase_price',
            'dpp_inc_tax', 'sell_price_inc_tax', 'sub_sku', 'profit_percent', 'deleted_at'],
        'business_locations' => ['business_id', 'eis_site_id', 'name'],
        'product_locations' => ['product_id', 'location_id'],
        'units' => ['business_id', 'short_name', 'actual_name'],
        'tax_rates' => ['business_id', 'configuration_id', 'tax_rate_id', 'name', 'is_activated', 'rate', 'deleted_at'],
        'eis_configurations' => ['business_id', 'eis_setting_id', 'location_id'],
        'eis_product_maps' => ['business_id', 'product_id', 'eis_product_id', 'sku', 'last_synced_at', 'meta', 'deleted_at'],
        'transactions' => ['business_id', 'location_id', 'type', 'status', 'payment_status', 'opening_stock_product_id',
            'created_by', 'transaction_date', 'total_before_tax', 'final_total', 'tax_amount', 'additional_notes'],
        'purchase_lines' => ['transaction_id', 'product_id', 'variation_id', 'quantity', 'pp_without_discount',
            'purchase_price', 'purchase_price_inc_tax', 'item_tax', 'tax_id', 'quantity_sold', 'quantity_adjusted',
            'quantity_returned', 'mfg_quantity_used'],
        'variation_location_details' => ['product_id', 'product_variation_id', 'variation_id', 'location_id', 'qty_available'],
        'transaction_sell_lines' => ['transaction_id', 'product_id', 'variation_id', 'quantity'],
        'stock_adjustment_lines' => ['transaction_id', 'product_id', 'variation_id', 'quantity'],
        'transaction_sell_lines_purchase_lines' => ['sell_line_id', 'purchase_line_id', 'quantity', 'qty_returned'],
    ];
    foreach ($tables as $table => $columns) {
        $db->getSchemaBuilder()->create($table, function (Blueprint $t) use ($table, $columns) {
            $t->increments('id');
            foreach (array_merge($columns, ['created_at', 'updated_at']) as $column) {
                $t->text($column)->nullable(); // Exact decimal strings, not SQLite REAL affinity.
            }
            if ($table === 'eis_product_maps') {
                $t->unique(['business_id', 'eis_product_id']);
                $t->unique(['business_id', 'product_id']);
            }
        });
    }
    $db->table('business')->insert([['id' => 1], ['id' => 2]]);
    $db->table('eis_settings')->insert(['id' => 1, 'business_id' => 1, 'location_id' => 2, 'site_id' => 'SITE-A']);
    $db->table('eis_configurations')->insert(['id' => 1, 'business_id' => 1, 'eis_setting_id' => 1, 'location_id' => 2]);
    $db->table('business_locations')->insert([
        ['id' => 2, 'business_id' => 1, 'eis_site_id' => 'SITE-A', 'name' => 'One'],
        ['id' => 3, 'business_id' => 2, 'eis_site_id' => 'SITE-A', 'name' => 'Other tenant'],
        ['id' => 4, 'business_id' => 1, 'eis_site_id' => 'SITE-B', 'name' => 'Two'],
    ]);
    $db->table('units')->insert(['id' => 1, 'business_id' => 1, 'short_name' => 'pcs', 'actual_name' => 'Pieces']);
    $db->table('tax_rates')->insert(['id' => 1, 'business_id' => 1, 'configuration_id' => 1,
        'tax_rate_id' => 'A', 'name' => 'Zero', 'is_activated' => 1, 'rate' => '0']);
    return [$db, new ProductUpsertService(), $log];
}
function importItem($service, array $overrides = [], $eisId = 'EIS-ONE', int $locationId = 2, int $terminalId = 1) {
    $raw = array_replace(['productCode' => 'SKU-ONE', 'productName' => 'Synthetic item', 'siteId' => 'SITE-A',
        'quantity' => '10.1234', 'price' => '5.00', 'cost' => '2.00', 'isProduct' => true, 'taxRateId' => 'A'], $overrides);
    $settings = (object) ['id' => $terminalId, 'business_id' => 1, 'location_id' => $locationId, 'site_id' => 'SITE-A'];
    return $service->upsert(1, (new ProductTransformer())->fromEis($raw, $eisId), $eisId, $settings, $locationId);
}
function graph($db) {
    $out = [];
    foreach (['transactions', 'purchase_lines', 'variation_location_details', 'transaction_sell_lines',
        'stock_adjustment_lines', 'transaction_sell_lines_purchase_lines'] as $table) {
        $out[$table] = $db->table($table)->orderBy('id')->get()->toJson();
    }
    return $out;
}
function seedMapped($db, $availability = null) {
    $db->table('products')->insert(['id' => 10, 'business_id' => 1, 'type' => 'single', 'enable_stock' => 1,
        'created_by' => 7, 'name' => 'Legacy', 'sku' => 'SKU-ONE', 'eis_product_id' => 'EIS-ONE', 'tax' => 1]);
    $db->table('product_variations')->insert(['id' => 11, 'product_id' => 10, 'is_dummy' => 1]);
    $db->table('variations')->insert(['id' => 12, 'product_id' => 10, 'product_variation_id' => 11,
        'default_purchase_price' => '2.0000', 'dpp_inc_tax' => '2.0000', 'sell_price_inc_tax' => '5.0000']);
    $db->table('eis_product_maps')->insert(['id' => 13, 'business_id' => 1, 'product_id' => 10,
        'eis_product_id' => 'EIS-ONE', 'sku' => 'SKU-ONE', 'meta' => '{"unrelated":"retain"}']);
    if ($availability !== null) {
        $db->table('variation_location_details')->insert(['product_id' => 10, 'product_variation_id' => 11,
            'variation_id' => 12, 'location_id' => 2, 'qty_available' => $availability]);
    }
}
function directInit($db, $source = ['quantity' => '10.1234']) {
    return $db->transaction(function () use ($db, $source) {
        return (new OpeningStockInitializer($db))->initialize(1, 10, 12, 2, 'EIS-ONE', 'SITE-A', $source);
    });
}

foreach (['10.1234', '0', '0.0000', '999999999999999999.9999'] as $quantity) {
    verify(OpeningStockInitializer::decimal($quantity) === bcadd($quantity, '0', 4), 'exact normalized decimal '.$quantity);
}
foreach ([null, false, true, [], 0.0, 1.2345, '-1', '1.23456', '1e3', ' 1', 'NaN', 'Infinity', '1000000000000000000'] as $value) {
    verify(OpeningStockInitializer::decimal($value) === null, 'invalid decimal rejected');
}
verify(OpeningStockInitializer::stockSource([])['quantity'] === null, 'absence is not zero');
verify(OpeningStockInitializer::stockSource(['stock' => '0'])['quantity'] === '0.0000', 'explicit zero');
verify(OpeningStockInitializer::stockSource(['quantity' => '1', 'stock' => '2'])['quantity'] === null, 'conflicting quantities');
verify(OpeningStockInitializer::siteId(['siteId' => 'A', 'site_id' => 'B']) === null, 'conflicting sites');
verify(OpeningStockInitializer::siteId(['location' => ['id' => 'SITE-A']]) === 'SITE-A', 'explicit nested site');

foreach ([[0.0, '0.0000'], [1.5, '1.5000'], [12.5, '12.5000'], [1e9, '1000000000.0000'],
    [0.0001, '0.0001'], [12.3456, '12.3456']] as [$value, $expected]) {
    verify(OpeningStockInitializer::stockSource(['quantity' => $value])['quantity'] === $expected, 'bounded canonical decoded float accepted');
    [$db, $service] = openingFixture();
    importItem($service, ['quantity' => $value, 'cost' => '0']);
    verify($db->table('purchase_lines')->value('quantity') === $expected, 'float transformer/importer ledger integration');
}
foreach ([NAN, INF, -INF, -0.5, 1e9 + 0.1, 1e20, 1e-7, 12.34567, '1e3', '1.5e0'] as $value) {
    verify(OpeningStockInitializer::stockSource(['quantity' => $value])['quantity'] === null, 'unsafe float/scientific stock rejected');
    [$db, $service] = openingFixture();
    importItem($service, ['quantity' => $value]);
    verify($db->table('purchase_lines')->count() === 0, 'unsafe float never becomes rounded opening');
}
verify(OpeningStockInitializer::stockSource(['quantity' => 1.5, 'stock' => '1.5000'])['quantity'] === '1.5000', 'float/string aliases agree');
verify(OpeningStockInitializer::stockSource(['quantity' => 1.5, 'stock' => '1.5001'])['quantity'] === null, 'float/string conflicting aliases blocked');
verify(OpeningStockInitializer::stockSource(['quantity' => 1000000001])['quantity'] === '1000000001.0000', 'integer limit is not the conservative float limit');
verify(OpeningStockInitializer::stockSource(['quantity' => PHP_INT_MAX])['quantity'] === null, 'integer DECIMAL bound remains strict');
verify(OpeningStockInitializer::scopedSiteId(['siteId' => 'SITE-A'], 'SITE-A') === 'SITE-A', 'matching explicit site accepted');
verify(OpeningStockInitializer::scopedSiteId(['location' => ['id' => 'SITE-A']], 'SITE-A') === 'SITE-A', 'matching explicit nested site accepted');
$precision = ini_get('serialize_precision');
ini_set('serialize_precision', '4');
verify(OpeningStockInitializer::stockSource(['quantity' => 12.34567])['quantity'] === null, 'encoder precision cannot round unsafe float into accepted stock');
ini_set('serialize_precision', $precision);

// Real sync -> transformer -> importer, with request settings and client wholly synthetic.
foreach ([['site_id' => 'SITE-A', 'branch_id' => 'SITE-B'], ['site_id' => null, 'branch_id' => 'SITE-A'],
    ['branch_id' => 'SITE-A']] as $siteSettings) {
    [$db, $service] = openingFixture();
    $settings = (object) (['id' => 1, 'location_id' => 2, 'business_id' => '1'] + $siteSettings);
    $client = new OpeningSyntheticClient([
        ['data' => [['productCode' => 'SYNC-ONE', 'productName' => 'Missing item site', 'quantity' => 12.5, 'cost' => '2', 'isProduct' => true]], 'has_more' => true],
        ['data' => []],
    ]);
    (new ProductSyncService($client, new ProductTransformer(), $service))->sync($settings, 1);
    verify(count($client->requests) === 2 && $client->requests[0]['site'] === 'SITE-A'
        && $client->requests[1]['site'] === 'SITE-A', 'exact site sent on both pages, null-only branch fallback');
    verify($db->table('purchase_lines')->count() === 1 && $db->table('purchase_lines')->value('quantity') === '12.5000'
        && $db->table('transactions')->value('location_id') === '2', 'missing item site inherits trusted request and remains one-time');
}
[$db, $service] = openingFixture();
$client = new OpeningSyntheticClient([['data' => [['productCode' => 'SYNC-UNKNOWN', 'quantity' => 1.5, 'isProduct' => true]]]]);
try {
    (new ProductSyncService($client, new ProductTransformer(), $service))->sync((object) ['id' => 1, 'location_id' => 2,
        'business_id' => 1, 'site_id' => 'UNKNOWN', 'branch_id' => 'SITE-A'], 1);
    throw new RuntimeException('expected unknown terminal site rejection');
} catch (InvalidArgumentException $e) {
    verify(!$client->requests && $db->table('purchase_lines')->count() === 0
        && $db->table('product_locations')->count() === 0, 'unknown site cannot be used for a selected terminal');
}
foreach ([['siteId' => 'SITE-B'], ['siteId' => null], ['siteId' => []], ['siteId' => false],
    ['siteId' => ''], ['siteId' => 'SITE-A', 'locationId' => 'SITE-B'], ['location' => null],
    ['location' => ['id' => null]]] as $override) {
    [$db, $service] = openingFixture();
    $client = new OpeningSyntheticClient([['data' => [array_replace(['productCode' => 'SYNC-ONE',
        'productName' => 'Unsafe site', 'quantity' => 1.5, 'isProduct' => true], $override)]]]);
    try {
        (new ProductSyncService($client, new ProductTransformer(), $service))->sync((object) ['id' => 1,
            'location_id' => 2, 'business_id' => 1, 'site_id' => 'SITE-A'], 1);
        throw new RuntimeException('expected invalid item site rejection');
    } catch (UnexpectedValueException $e) {
    }
    verify($db->table('products')->count() === 0 && $db->table('purchase_lines')->count() === 0
        && $db->table('product_locations')->count() === 0, 'explicit invalid/conflicting item site rejects the catalog');
}
foreach ([['business_id' => 2, 'site_id' => 'SITE-A'], ['site_id' => 'SITE-A'],
    ['business_id' => true, 'site_id' => 'SITE-A'], ['business_id' => 1, 'site_id' => '', 'branch_id' => 'SITE-A'],
    ['business_id' => 1, 'site_id' => []], ['business_id' => 1, 'site_id' => ' SITE-A ']] as $values) {
    [$db, $service] = openingFixture();
    $client = new OpeningSyntheticClient([]);
    try {
        (new ProductSyncService($client, new ProductTransformer(), $service))->sync((object) $values, 1);
        throw new RuntimeException('expected untrusted settings refusal');
    } catch (InvalidArgumentException $e) {
        verify(!$client->requests && $db->table('products')->count() === 0, 'untrusted settings rejected before request/import');
    }
}
[$db, $service] = openingFixture();
$data = (new ProductTransformer())->fromEis(['quantity' => 1.5, 'isProduct' => true], 'UNSCOPED');
verify($data['opening_stock_site_id'] === null, 'unscoped transform does not invent request context');

// Exercise the actual POST builder using an in-process HTTP fake, not the network.
$dbContainer = Container::getInstance();
$dbContainer['config']['eis.base_url'] = 'https://synthetic.invalid';
$http = new Illuminate\Http\Client\Factory();
Illuminate\Support\Facades\Http::swap($http);
$http->fake(function ($request) use ($http) { return $http->response(['data' => []], 200); });
$settings = (object) ['business_id' => 1, 'site_id' => null, 'branch_id' => 'SITE-A', 'tpin' => 'SYNTHETIC', 'jwt_token' => 'SYNTHETIC'];
(new EisProductClient())->fetch($settings, 2);
$recorded = $http->recorded();
verify($recorded->count() === 1 && $recorded[0][0]->method() === 'POST'
    && $recorded[0][0]->url() === 'https://synthetic.invalid/utilities/get-terminal-site-products'
    && $recorded[0][0]['siteId'] === 'SITE-A' && $recorded[0][0]['page'] === 2, 'actual client POST uses the shared exact request site');

foreach (['10.1234', '0.0000'] as $quantity) {
    [$db, $service] = openingFixture();
    $product = importItem($service, ['quantity' => $quantity]);
    verify($db->table('transactions')->count() === 1 && $db->table('purchase_lines')->count() === 1, 'one opening supply including zero');
    $header = $db->table('transactions')->first();
    $line = $db->table('purchase_lines')->first();
    $available = $db->table('variation_location_details')->first();
    verify($header->type === 'opening_stock' && $header->status === 'received' && $header->payment_status === 'paid', 'received opening header');
    verify($header->business_id == 1 && $header->location_id == 2 && $header->opening_stock_product_id == $product->id, 'exact supply scope');
    verify($line->transaction_id == $header->id && $line->product_id == $product->id && $line->variation_id == $available->variation_id, 'complete allocation graph');
    verify($line->quantity === $quantity && $available->qty_available === $quantity && $line->quantity_sold === '0.0000', 'exact opening quantities');
    verify($header->final_total === bcmul($quantity, '2', 4), 'exact total');
    $metaBefore = $db->table('eis_product_maps')->value('meta');
    $before = graph($db);
    importItem($service, ['quantity' => '999.0000', 'price' => '9.00', 'cost' => '3.00', 'productName' => 'Refreshed catalogue']);
    verify(graph($db) === $before && $db->table('eis_product_maps')->value('meta') === $metaBefore, 'changed remote snapshot never changes supply/marker');
    verify($db->table('variations')->value('sell_price_inc_tax') === '9', 'price refresh independent of stock');
    verify($db->table('variations')->value('default_purchase_price') === '3', 'purchase price refresh independent of opening line cost');
    verify($db->table('products')->value('name') === 'Refreshed catalogue', 'catalogue metadata refresh');
    if ($quantity !== '0.0000') {
        $db->table('transactions')->insert(['id' => 100, 'business_id' => 1, 'location_id' => 2, 'type' => 'sell', 'status' => 'final']);
        $sellId = $db->table('transaction_sell_lines')->insertGetId(['transaction_id' => 100, 'product_id' => $product->id,
            'variation_id' => $line->variation_id, 'quantity' => '3.1000']);
        $db->table('purchase_lines')->where('id', $line->id)->update(['quantity_sold' => '3.1000', 'quantity_adjusted' => '0.1000']);
        $db->table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => $sellId, 'purchase_line_id' => $line->id, 'quantity' => '3.1000']);
        $db->table('variation_location_details')->update(['qty_available' => '6.9234']);
        $before = graph($db);
        importItem($service, ['quantity' => '10.1234']);
        importItem($service, ['quantity' => '500']);
        verify(graph($db) === $before, 'sold/consumed availability and mappings never restored');
    }
    $before = graph($db);
    try {
        importItem($service, ['siteId' => 'SITE-B']);
        throw new RuntimeException('expected site drift rejection');
    } catch (InvalidArgumentException $e) {
    }
    verify(graph($db) === $before, 'site drift never adds another opening');
    importItem($service, ['quantity' => null]);
    verify(graph($db) === $before, 'missing source on later pull never changes initialized graph');
}

foreach ([null, '-1', '1.12345', 'junk', 1.23456] as $quantity) {
    [$db, $service] = openingFixture();
    importItem($service, ['quantity' => $quantity]);
    verify($db->table('products')->count() === 1 && $db->table('variations')->count() === 1, 'invalid stock still imports catalogue');
    verify($db->table('purchase_lines')->count() === 0 && $db->table('variation_location_details')->count() === 0, 'invalid stock not initialized');
    verify($db->table('eis_product_maps')->value('meta') === null, 'invalid stock not marked zero');
    importItem($service, ['quantity' => '7.0001']);
    verify($db->table('purchase_lines')->value('quantity') === '7.0001', 'later explicit valid source initializes clean map');
}
[$db, $service] = openingFixture();
$data = (new ProductTransformer())->fromEis(['productCode' => 'SKU-ONE', 'productName' => 'Missing', 'siteId' => 'SITE-A', 'isProduct' => true], 'EIS-ONE');
verify($data['opening_stock_source']['reason'] === 'missing_stock', 'transform retains missing stock');
$service->upsert(1, $data, 'EIS-ONE', (object) ['id' => 1, 'business_id' => 1,
    'location_id' => 2, 'site_id' => 'SITE-A'], 2);
verify($db->table('purchase_lines')->count() === 0, 'missing source not initialized');

foreach ([['isProduct' => false], ['type' => 'variable'], ['variations' => [['quantity' => '1'], ['quantity' => '2']]],
    ['siteId' => null], ['siteId' => 'UNKNOWN'], ['siteId' => 'SITE-A', 'site_id' => 'SITE-B']] as $override) {
    [$db, $service] = openingFixture();
    $invalidSite = array_key_exists('siteId', $override);
    $rejected = false;
    try {
        importItem($service, $override);
    } catch (InvalidArgumentException $e) {
        $rejected = true;
    } catch (UnexpectedValueException $e) {
        $rejected = true;
    }
    $unsupported = ($override['type'] ?? null) === 'variable' || isset($override['variations']);
    verify(!$rejected || $invalidSite || $unsupported, 'only invalid site or ambiguous variation rejects the direct import');
    verify($db->table('products')->count() === ($invalidSite || $unsupported ? 0 : 1),
        'unsupported stock case leaves catalog unchanged');
    verify($db->table('purchase_lines')->count() === 0 && $db->table('variation_location_details')->count() === 0, 'unsafe/nonstock case no stock');
}
[$db, $service] = openingFixture();
$db->table('business_locations')->insert(['id' => 5, 'business_id' => 1, 'eis_site_id' => 'SITE-A']);
$db->table('eis_settings')->insert(['id' => 2, 'business_id' => 1, 'location_id' => 5, 'site_id' => 'SITE-A']);
$db->table('eis_configurations')->insert(['id' => 2, 'business_id' => 1, 'eis_setting_id' => 2, 'location_id' => 5]);
$db->table('tax_rates')->insert(['id' => 2, 'business_id' => 1, 'configuration_id' => 2,
    'tax_rate_id' => 'A', 'name' => 'Zero', 'is_activated' => 1, 'rate' => '0']);
$product = importItem($service, ['quantity' => '4.0000']);
$legacyMeta = json_decode($db->table('eis_product_maps')->value('meta'), true);
$legacyMarker = $legacyMeta['eis_opening_stock_v1_by_location']['2'];
unset($legacyMeta['eis_opening_stock_v1_by_location']);
$legacyMeta['eis_opening_stock_v1'] = $legacyMarker;
$db->table('eis_product_maps')->update(['meta' => json_encode($legacyMeta, JSON_THROW_ON_ERROR)]);
importItem($service, ['quantity' => '7.0000'], 'EIS-ONE', 5, 2);
$locationMeta = json_decode($db->table('eis_product_maps')->value('meta'), true);
verify($db->table('products')->count() === 1 && $db->table('eis_product_maps')->count() === 1
    && $db->table('transactions')->where('location_id', 2)->count() === 1
    && $db->table('transactions')->where('location_id', 5)->count() === 1
    && $db->table('variation_location_details')->where('product_id', $product->id)->where('location_id', 2)->value('qty_available') === '4.0000'
    && $db->table('variation_location_details')->where('product_id', $product->id)->where('location_id', 5)->value('qty_available') === '7.0000'
    && $locationMeta['eis_opening_stock_v1'] === $legacyMarker
    && isset($locationMeta['eis_opening_stock_v1_by_location']['5']),
    'A shared product has separate immutable opening ledgers and markers for each selected same-site location');
$beforeMultiLocationRepull = graph($db);
importItem($service, ['quantity' => '99.0000'], 'EIS-ONE', 5, 2);
importItem($service, ['quantity' => '100.0000']);
verify(graph($db) === $beforeMultiLocationRepull,
    'Repulls at either location preserve each location-specific opening ledger and quantity');

foreach (['positive', 'negative', 'sell_sentinel', 'purchase', 'adjustment', 'opening_header', 'multiple_variations',
    'duplicate_stock', 'wrong_stock_product', 'corrupt_meta'] as $case) {
    [$db, $service, $log] = openingFixture();
    seedMapped($db, $case === 'positive' ? '8.0000' : ($case === 'negative' ? '-1.0000' : '0.0000'));
    if ($case === 'sell_sentinel') {
        $db->table('transaction_sell_lines')->insert(['id' => 51, 'transaction_id' => 99, 'product_id' => 10, 'variation_id' => 12, 'quantity' => '2']);
        $db->table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 51, 'purchase_line_id' => 0, 'quantity' => '2']);
    }
    if ($case === 'purchase') { $db->table('purchase_lines')->insert(['product_id' => 10, 'variation_id' => 12, 'quantity' => '8']); }
    if ($case === 'adjustment') { $db->table('stock_adjustment_lines')->insert(['product_id' => 10, 'variation_id' => 12, 'quantity' => '1']); }
    if ($case === 'opening_header') { $db->table('transactions')->insert(['opening_stock_product_id' => 10, 'type' => 'opening_stock']); }
    if ($case === 'multiple_variations') { $db->table('variations')->insert(['product_id' => 10, 'product_variation_id' => 11]); }
    if ($case === 'duplicate_stock') { $db->table('variation_location_details')->insert(['product_id' => 10, 'variation_id' => 12, 'product_variation_id' => 11, 'location_id' => 2, 'qty_available' => '0.0000']); }
    if ($case === 'wrong_stock_product') { $db->table('variation_location_details')->update(['product_id' => 999]); }
    if ($case === 'other_stock_location') { $db->table('variation_location_details')->update(['location_id' => 4]); }
    if ($case === 'corrupt_meta') { $db->table('eis_product_maps')->update(['meta' => 'broken']); }
    $before = graph($db);
    $metaBefore = $db->table('eis_product_maps')->value('meta');
    try {
        importItem($service);
    } catch (UnexpectedValueException $e) {
        verify($case === 'multiple_variations', 'only an ambiguous legacy variation aborts its upsert');
    }
    verify(graph($db) === $before, 'legacy graph preserved: '.$case);
    verify($db->table('eis_product_maps')->value('product_id') == 10 && $db->table('eis_product_maps')->value('meta') === $metaBefore, 'legacy mapping unchanged: '.$case);
    verify(count($log->warnings) > 0, 'actionable legacy diagnosis: '.$case);
}

[$db, $service] = openingFixture();
seedMapped($db, '0.0000');
importItem($service);
$meta = json_decode($db->table('eis_product_maps')->value('meta'), true);
verify($meta['unrelated'] === 'retain'
    && isset($meta['eis_opening_stock_v1_by_location']['2']), 'clean zero map initializes, unrelated meta retained');
verify($db->table('variation_location_details')->count() === 1, 'clean zero row updated not duplicated');

foreach ([null, '8.0000'] as $available) {
    [$db, $service] = openingFixture();
    seedMapped($db, $available);
    $db->table('eis_product_maps')->delete(); // Synthetic in-memory fixture only.
    $db->table('products')->update(['eis_product_id' => null]);
    $before = graph($db);
    importItem($service);
    importItem($service);
    verify(graph($db) === $before, 'ordinary existing stock neither doubled nor claimed across retries');
    verify(isset(json_decode($db->table('eis_product_maps')->value('meta'), true)['eis_opening_stock_excluded']), 'durable catalogue-only exclusion');
}

foreach (['cross_tenant', 'missing_product', 'other_eis', 'cross_tenant_map'] as $case) {
    [$db, $service] = openingFixture();
    seedMapped($db, '8.0000');
    if ($case === 'cross_tenant') { $db->table('products')->update(['business_id' => 2]); }
    if ($case === 'missing_product') { $db->table('eis_product_maps')->update(['product_id' => 999]); }
    if ($case === 'other_eis') { $db->table('products')->update(['eis_product_id' => 'OTHER']); }
    if ($case === 'cross_tenant_map') { $db->table('eis_product_maps')->update(['business_id' => 2]); }
    $before = graph($db);
    $mapsBefore = $db->table('eis_product_maps')->get()->toJson();
    try { importItem($service); throw new RuntimeException('expected conflict'); }
    catch (RuntimeException $e) { verify($e->getMessage() !== 'expected conflict', 'identity conflict rejected '.$case); }
    verify(graph($db) === $before && $db->table('eis_product_maps')->get()->toJson() === $mapsBefore, 'identity conflict leaves graph/maps untouched');
}

foreach (['purchase_line_failure', 'marker_failure'] as $case) {
    [$db, $service] = openingFixture();
    $table = $case === 'purchase_line_failure' ? 'purchase_lines' : 'eis_product_maps';
    $event = $case === 'purchase_line_failure' ? 'INSERT' : 'UPDATE OF meta';
    $db->unprepared("CREATE TRIGGER fail_write BEFORE $event ON $table BEGIN SELECT RAISE(ABORT, 'synthetic rollback'); END");
    try { importItem($service); throw new RuntimeException('expected write failure'); }
    catch (Illuminate\Database\QueryException $e) { verify(str_contains($e->getMessage(), 'synthetic rollback'), 'injected graph failure'); }
    verify($db->table('products')->count() === 0 && $db->table('eis_product_maps')->count() === 0
        && $db->table('transactions')->count() === 0 && $db->table('purchase_lines')->count() === 0
        && $db->table('variation_location_details')->count() === 0, 'entire catalogue/supply/marker rollback');
    $db->unprepared('DROP TRIGGER fail_write');
    importItem($service);
    verify($db->table('purchase_lines')->count() === 1, 'successful retry after rollback');
}

[$db] = openingFixture();
seedMapped($db);
try {
    (new OpeningStockInitializer($db))->initialize(1, 10, 12, 2, 'EIS-ONE', 'SITE-A', ['quantity' => '1']);
    throw new RuntimeException('expected enclosing transaction guard');
} catch (LogicException $e) { verify(true, 'initializer requires transaction'); }
verify(directInit($db) === 'initialized', 'direct helper initialized');
verify(directInit($db, ['quantity' => '99']) === 'already_initialized', 'direct helper idempotent');
$db->table('purchase_lines')->delete();
$before = graph($db);
verify(directInit($db) === 'opening_marker_ledger_missing_or_changed' && graph($db) === $before, 'deleted ledger diagnosed, never recreated from marker');

foreach (['0.0000', '1.5000', '10.1234'] as $quantity) {
    [$db, $service] = openingFixture();
    $db->table('tax_rates')->update(['rate' => '25']);
    importItem($service, ['quantity' => $quantity, 'cost' => '2.50']);
    $header = $db->table('transactions')->first();
    $line = $db->table('purchase_lines')->first();
    verify($line->purchase_price === '2.0000' && $line->purchase_price_inc_tax === '2.5000'
        && $line->item_tax === '0.5000', 'taxed opening matches normal purchase-line cost semantics');
    verify($header->total_before_tax === bcmul($quantity, '2', 4)
        && $header->final_total === bcmul($quantity, '2.5', 4)
        && $header->tax_amount === bcsub($header->final_total, $header->total_before_tax, 4), 'taxed importer header exact ex/tax/inc totals');
    $before = graph($db);
    importItem($service, ['quantity' => '100', 'cost' => '9']);
    verify(graph($db) === $before, 'historical taxed header and line never repriced by repull');
}
[$db] = openingFixture();
seedMapped($db);
$db->table('variations')->update(['default_purchase_price' => '0.0000', 'dpp_inc_tax' => '999999999999999999.9999']);
$before = graph($db);
verify(directInit($db, ['quantity' => '2']) === 'opening_total_overflow' && graph($db) === $before, 'inclusive/tax overflow blocked before all graph writes');
verify(directInit($db, ['quantity' => '1']) === 'initialized'
    && $db->table('transactions')->value('tax_amount') === '999999999999999999.9999', 'DECIMAL header/tax upper boundary preserved');

foreach (['wrong_location_tenant', 'wrong_variation_product', 'deleted_variation', 'duplicate_map', 'bad_marker', 'invalid_cost', 'total_overflow'] as $case) {
    [$db] = openingFixture();
    seedMapped($db);
    if ($case === 'wrong_location_tenant') { $db->table('business_locations')->where('id', 2)->update(['business_id' => 2]); }
    if ($case === 'wrong_variation_product') { $db->table('variations')->update(['product_id' => 999]); }
    if ($case === 'deleted_variation') { $db->table('variations')->update(['deleted_at' => '2026-01-01']); }
    if ($case === 'duplicate_map') { $db->table('eis_product_maps')->insert(['business_id' => 2, 'product_id' => 10, 'eis_product_id' => 'OTHER']); }
    if ($case === 'bad_marker') { $db->table('eis_product_maps')->update(['meta' => '{"eis_opening_stock_v1":{"productId":999}}']); }
    if ($case === 'invalid_cost') { $db->table('variations')->update(['default_purchase_price' => '-1']); }
    if ($case === 'total_overflow') { $db->table('variations')->update(['default_purchase_price' => '999999999999999999.9999', 'dpp_inc_tax' => '999999999999999999.9999']); }
    $before = graph($db);
    verify(directInit($db) !== 'initialized' && graph($db) === $before, 'helper fail closed: '.$case);
}

foreach (['sku', 'name', 'eis_product_id'] as $column) {
    [$db, $service] = openingFixture();
    foreach ([10, 20] as $id) {
        $db->table('products')->insert(['id' => $id, 'business_id' => 1, 'type' => 'single', 'enable_stock' => 1,
            'name' => $column === 'name' ? 'Synthetic item' : 'Other '.$id,
            'sku' => $column === 'sku' ? 'SKU-ONE' : 'Other '.$id,
            'eis_product_id' => $column === 'eis_product_id' ? 'EIS-ONE' : null]);
    }
    try { importItem($service); throw new RuntimeException('expected ambiguous identity'); }
    catch (RuntimeException $e) { verify($e->getMessage() !== 'expected ambiguous identity', 'ambiguous '.$column.' rejected'); }
    verify($db->table('eis_product_maps')->count() === 0 && $db->table('purchase_lines')->count() === 0, 'ambiguous fallback never claims product');
}

foreach (['eis_product_maps', 'transactions', 'purchase_lines', 'variation_location_details'] as $table) {
    [$db] = openingFixture();
    seedMapped($db);
    $connection = new OpeningEngineConnection($db->getPdo(), ':memory:', '', $db->getConfig());
    $connection->badTable = $table;
    $before = graph($db);
    $result = $connection->transaction(function () use ($connection) {
        return (new OpeningStockInitializer($connection))->initialize(1, 10, 12, 2, 'EIS-ONE', 'SITE-A', ['quantity' => '1']);
    });
    verify($result === 'nontransactional_storage:'.$table && graph($db) === $before, 'MyISAM '.$table.' cannot initialize supply');
}

[$db, $service] = openingFixture();
importItem($service, ['quantity' => '9007199254740993.1234', 'cost' => '0', 'price' => '0']);
verify($db->table('purchase_lines')->value('quantity') === '9007199254740993.1234'
    && $db->table('variation_location_details')->value('qty_available') === '9007199254740993.1234', 'beyond IEEE-754 exact integer boundary preserved');

[$db, $service] = openingFixture();
try { importItem($service, [], ' '); throw new RuntimeException('expected missing EIS identity'); }
catch (InvalidArgumentException $e) { verify($db->table('products')->count() === 0, 'missing EIS identifier cannot create catalogue/stock'); }

[$db, $service] = openingFixture();
seedMapped($db, '0.0000');
$db->table('variations')->update(['deleted_at' => '2026-01-01']);
importItem($service);
verify($db->table('variations')->count() === 1 && $db->table('purchase_lines')->count() === 0, 'deleted variation never replaced with a new ambiguous variant');

[$db, $service] = openingFixture();
importItem($service, ['isProduct' => false]);
importItem($service, ['isProduct' => true]);
verify($db->table('products')->value('enable_stock') === '0' && $db->table('purchase_lines')->count() === 0, 'import cannot silently enable tracking on existing nonstock product');

echo "PASS: $checks isolated EIS opening-stock checks; no application boot, persistent database or network.\n";