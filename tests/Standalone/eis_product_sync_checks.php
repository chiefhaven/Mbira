<?php

// Bare SQLite memory fixture; no kernel, persistent database, or network.
require dirname(__DIR__, 2).'/vendor/autoload.php';
foreach (['DB' => Illuminate\Support\Facades\DB::class, 'Log' => Illuminate\Support\Facades\Log::class] as $alias => $class) {
    if (!class_exists($alias)) {
        class_alias($class, $alias);
    }
}

use App\Product;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Modules\EIS\Models\EisProductMap;
use Modules\EIS\Services\Products\EisProductClient;
use Modules\EIS\Services\Products\ProductTransformer;
use Modules\EIS\Services\Products\ProductUpsertService;
use Modules\EIS\Services\Sync\ProductSyncService;

class CatalogFixtureClient extends EisProductClient
{
    public array $pages = [];
    public array $requests = [];
    public function fetch($settings, int $page = 1)
    {
        $this->requests[] = $page;
        $response = $this->pages[$page - 1] ?? null;
        if ($response instanceof Throwable) {
            throw $response;
        }
        return $response;
    }
}

$checks = 0;
function catalogCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
}
function catalogFixture(): array
{
    $app = new class extends Container {
        public function abort($code, $message = '', array $headers = [])
        {
            throw new Symfony\Component\HttpKernel\Exception\HttpException($code, $message, null, $headers);
        }
    };
    Container::setInstance($app);
    $capsule = new Capsule($app);
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
    $capsule->setEventDispatcher(new Illuminate\Events\Dispatcher($app));
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    Illuminate\Database\Eloquent\Model::clearBootedModels();
    $app->instance('db', $capsule->getDatabaseManager());
    $app->instance('log', new Psr\Log\NullLogger());
    $app->instance('translator', new Illuminate\Translation\Translator(
        new Illuminate\Translation\FileLoader(new Illuminate\Filesystem\Filesystem(), dirname(__DIR__, 2).'\lang'), 'en'
    ));
    $app['config']['eis.system_user_id'] = 1;
    $app['config']['eis.base_url'] = 'https://fixture.invalid';
    Facade::setFacadeApplication($app);
    Facade::clearResolvedInstances();
    $db = $capsule->getConnection();
    foreach ([
        'business' => [],
        'products' => ['business_id', 'type', 'created_by', 'enable_stock', 'name', 'product_description', 'sku', 'tax',
            'eis_product_id', 'eis_last_synced_at', 'unit_id', 'expiry_period', 'deleted_at',
            'brand_id', 'category_id', 'sub_category_id', 'alert_quantity', 'image', 'product_custom_field1',
            'woocommerce_media_id', 'woocommerce_product_id'],
        'eis_settings' => ['business_id', 'location_id', 'status', 'site_id', 'branch_id', 'jwt_token'],
        'product_variations' => ['product_id', 'variation_template_id', 'name', 'is_dummy'],
        'variations' => ['product_id', 'product_variation_id', 'name', 'default_sell_price', 'default_purchase_price',
            'dpp_inc_tax', 'sell_price_inc_tax', 'sub_sku', 'profit_percent', 'deleted_at'],
        'business_locations' => ['business_id', 'eis_site_id', 'name', 'is_active'],
        'product_locations' => ['product_id', 'location_id'],
        'units' => ['business_id', 'short_name', 'actual_name'],
        'tax_rates' => ['business_id', 'configuration_id', 'tax_rate_id', 'name', 'is_activated', 'rate', 'deleted_at'],
        'eis_configurations' => ['business_id', 'eis_setting_id', 'location_id'],
        'eis_product_maps' => ['business_id', 'product_id', 'eis_product_id', 'sku', 'last_synced_at', 'meta', 'deleted_at'],
        'variation_location_details' => ['product_id', 'product_variation_id', 'variation_id', 'location_id', 'qty_available'],
        'transactions' => ['business_id', 'location_id', 'type', 'status', 'payment_status', 'opening_stock_product_id',
            'created_by', 'transaction_date', 'total_before_tax', 'final_total', 'tax_amount', 'additional_notes'],
        'purchase_lines' => ['transaction_id', 'product_id', 'variation_id', 'quantity', 'pp_without_discount',
            'purchase_price', 'purchase_price_inc_tax', 'item_tax', 'tax_id', 'quantity_sold', 'quantity_adjusted',
            'quantity_returned', 'mfg_quantity_used'],
        'transaction_sell_lines' => ['transaction_id', 'product_id', 'variation_id', 'quantity'],
        'stock_adjustment_lines' => ['transaction_id', 'product_id', 'variation_id', 'quantity'],
        'transaction_sell_lines_purchase_lines' => ['sell_line_id', 'purchase_line_id', 'quantity', 'qty_returned'],
    ] as $name => $columns) {
        $db->getSchemaBuilder()->create($name, function (Blueprint $table) use ($name, $columns) {
            $table->increments('id');
            foreach (array_merge($columns, ['created_at', 'updated_at']) as $column) {
                $table->text($column)->nullable();
            }
            if ($name === 'eis_product_maps') {
                $table->unique(['business_id', 'eis_product_id']);
                $table->unique(['business_id', 'product_id']);
            }
        });
    }
    $db->table('business')->insert([['id' => 1], ['id' => 2]]);
    $db->table('eis_settings')->insert(['business_id' => 1, 'location_id' => 1, 'status' => 0, 'branch_id' => 'SITE-A']);
    $db->table('business_locations')->insert([
        ['id' => 1, 'business_id' => 1, 'eis_site_id' => 'SITE-A', 'is_active' => 1],
        ['id' => 2, 'business_id' => 2, 'eis_site_id' => 'SITE-A', 'is_active' => 1],
    ]);
    $db->table('eis_configurations')->insert(['id' => 1, 'business_id' => 1, 'eis_setting_id' => 1, 'location_id' => 1]);
    $db->table('units')->insert(['business_id' => 1, 'short_name' => 'pcs', 'actual_name' => 'Pieces']);
    $db->table('tax_rates')->insert(['business_id' => 1, 'configuration_id' => 1,
        'tax_rate_id' => 'A', 'name' => 'Zero', 'is_activated' => 1, 'rate' => '0']);
    $client = new CatalogFixtureClient();
    $sync = new ProductSyncService($client, new ProductTransformer(), new ProductUpsertService());
    return [$db, $client, $sync];
}
function catalogItem(string $id, $quantity = '12.3456'): array
{
    return ['productCode' => $id, 'productName' => 'Product '.$id, 'quantity' => $quantity,
        'isProduct' => true, 'taxRateId' => 'A', 'price' => '10', 'cost' => '3'];
}
function pullCatalog($sync): void
{
    $sync->sync((object) ['id' => 1, 'business_id' => 1, 'location_id' => 1, 'site_id' => 'SITE-A'], 1);
}
function catalogSnapshot($db): array
{
    $result = [];
    foreach (['products', 'eis_product_maps', 'product_variations', 'variations', 'product_locations',
        'variation_location_details'] as $table) {
        $result[$table] = $db->table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    }
    return $result;
}
function rejectCatalog($db, $sync, string $message): void
{
    $before = catalogSnapshot($db);
    try {
        pullCatalog($sync);
    } catch (Throwable $e) {
        catalogCheck(catalogSnapshot($db) === $before, $message.' preserves all committed data');
        return;
    }
    throw new RuntimeException($message.' should fail');
}

[$db, $client, $sync] = catalogFixture();
$db->table('products')->insert([
    ['id' => 100, 'business_id' => 1, 'name' => 'Manual', 'sku' => 'LOCAL'],
    ['id' => 101, 'business_id' => 2, 'name' => 'Other business', 'sku' => 'OTHER'],
]);
$client->pages = [['data' => [catalogItem('ONE')], 'has_more' => true],
    ['data' => [catalogItem('TWO', '0')], 'has_more' => false]];
pullCatalog($sync);
catalogCheck($client->requests === [1, 2], 'All catalog pages are fetched');
catalogCheck(Product::where('business_id', 1)->count() === 3
    && !Product::withTrashed()->find(100)->trashed(), 'Unmapped manual business products are retained');
catalogCheck(!Product::findOrFail(101)->trashed(), 'Other businesses remain unchanged');
$one = EisProductMap::where('eis_product_id', 'ONE')->firstOrFail();
$two = EisProductMap::where('eis_product_id', 'TWO')->firstOrFail();
catalogCheck($db->table('variation_location_details')->where('product_id', $one->product_id)->value('qty_available') === '12.3456',
    'Stock exactly matches four-decimal EIS quantity');
catalogCheck($db->table('variation_location_details')->where('product_id', $two->product_id)->value('qty_available') === '0.0000',
    'Explicit zero stock is persisted');
$db->table('business_locations')->insert(['id' => 3, 'business_id' => 1, 'eis_site_id' => 'SITE-A']);
$db->table('eis_settings')->insert(['id' => 2, 'business_id' => 1, 'location_id' => 3,
    'status' => 0, 'branch_id' => 'SITE-A']);
$db->table('eis_configurations')->insert(['id' => 2, 'business_id' => 1, 'eis_setting_id' => 2, 'location_id' => 3]);
$db->table('tax_rates')->insert(['id' => 2, 'business_id' => 1, 'configuration_id' => 2,
    'tax_rate_id' => 'A', 'name' => 'Zero', 'is_activated' => 1, 'rate' => '0']);
$client->pages = [['data' => [catalogItem('ONE'), catalogItem('TWO', '0')]]];
$sync->sync((object) ['id' => 2, 'business_id' => 1, 'location_id' => 3, 'site_id' => 'SITE-A'], 1);
catalogCheck($db->table('variation_location_details')->where('product_id', $one->product_id)
    ->where('location_id', 1)->value('qty_available') === '12.3456'
    && $db->table('variation_location_details')->where('product_id', $one->product_id)
        ->where('location_id', 3)->value('qty_available') === '12.3456',
    'Same-site terminals initialize separate selected locations without changing the first');
$db->table('tax_rates')->where('id', 2)->update(['tax_rate_id' => 'OTHER']);
$client->pages = [['data' => [catalogItem('ONE')]]];
$beforeUnmappedTerminalTax = catalogSnapshot($db);
try {
    $sync->sync((object) ['id' => 2, 'business_id' => 1, 'location_id' => 3, 'site_id' => 'SITE-A'], 1);
    throw new RuntimeException('expected terminal-scoped tax mapping rejection');
} catch (UnexpectedValueException $e) {
    catalogCheck(catalogSnapshot($db) === $beforeUnmappedTerminalTax,
        'Missing selected-terminal tax mapping cannot fall back to another configuration');
}
$db->table('tax_rates')->where('id', 2)->update(['tax_rate_id' => 'A']);
$db->table('tax_rates')->where('id', 2)->update(['rate' => '15']);
$client->pages = [['data' => [catalogItem('ONE')]]];
$beforeConflict = catalogSnapshot($db);
try {
    $sync->sync((object) ['id' => 2, 'business_id' => 1, 'location_id' => 3, 'site_id' => 'SITE-A'], 1);
    throw new RuntimeException('expected incompatible terminal tax rejection');
} catch (UnexpectedValueException $e) {
    catalogCheck(catalogSnapshot($db) === $beforeConflict,
        'A different terminal rate cannot overwrite the shared product tax or its catalog state');
}
$db->table('tax_rates')->where('id', 2)->update(['rate' => '0']);
$db->table('business_locations')->insert(['id' => 4, 'business_id' => 1, 'eis_site_id' => 'SITE-B']);
$stockRow = (array) $db->table('variation_location_details')->where('product_id', $one->product_id)->first();
unset($stockRow['id']);
$db->table('variation_location_details')->insert(array_replace($stockRow, ['location_id' => 4, 'qty_available' => '42.0000']));
$db->table('products')->where('id', $one->product_id)->update(['enable_stock' => 0]);
$client->pages = [['data' => [catalogItem('ONE', '1.0001')]]];
pullCatalog($sync);
catalogCheck($db->table('variation_location_details')->where('product_id', $one->product_id)
    ->where('location_id', 1)->value('qty_available') === '12.3456',
    'Repull retains the immutable opening quantity rather than replacing local stock');
catalogCheck(!(bool) Product::findOrFail($one->product_id)->enable_stock,
    'Repull does not silently enable stock tracking on an existing nonstock product');
catalogCheck($db->table('variation_location_details')->where('location_id', 4)->value('qty_available') === '42.0000',
    'Sync overwrites only the exact EIS site location quantity');
catalogCheck(!Product::withTrashed()->find($two->product_id)->trashed()
    && !EisProductMap::withTrashed()->find($two->id)->trashed(), 'An absent location catalog entry does not retire shared products or maps');
$client->pages = [['data' => [catalogItem('ONE', '0'), catalogItem('TWO', '999999999999999999.9999')]]];
pullCatalog($sync);
catalogCheck(Product::find($two->product_id) !== null && EisProductMap::find($two->id) !== null,
    'Returning product and map restore the same identities');
catalogCheck($db->table('variation_location_details')->where('product_id', $two->product_id)->where('location_id', 1)->value('qty_available')
    === '0.0000', 'Opening ledger quantity remains immutable after a changed remote snapshot');
catalogCheck($db->table('variation_location_details')->where('product_id', $one->product_id)->where('location_id', 1)->value('qty_available') === '12.3456',
    'Repull cannot replace the initialized opening quantity with zero');
$snapshot = catalogSnapshot($db);
pullCatalog($sync);
catalogCheck(Product::where('business_id', 1)->count() === 3 && EisProductMap::where('business_id', 1)->count() === 2
    && $db->table('variation_location_details')->count() === 5, 'Repeated sync does not duplicate identities or stock rows');

foreach ([
    null, [], ['error' => 'expired token'], ['data' => null], ['data' => 'invalid'],
    ['data' => [], 'success' => false], ['data' => [], 'has_more' => 'invalid'],
    ['data' => [], 'has_more' => null], ['data' => [], 'error' => 'Unauthorized'],
    ['data' => [], 'has_more' => true], ['data' => [['productName' => 'No identifier']]],
    ['data' => [catalogItem('ONE', null)]], ['data' => [catalogItem('ONE', '-1')]],
    ['data' => [catalogItem('ONE', '1.12345')]], ['data' => [catalogItem('ONE', 'invalid')]],
    ['data' => [array_merge(catalogItem('ONE'), ['stock' => '99'])]],
    ['data' => [array_merge(catalogItem('ONE'), ['siteId' => 'ANOTHER-SITE'])]],
    ['data' => [catalogItem('ONE'), catalogItem('ONE')]],
] as $response) {
    $client->pages = [$response];
    rejectCatalog($db, $sync, 'Malformed or incomplete catalog');
}
$client->pages = [['data' => [catalogItem('ONE')], 'has_more' => true], new RuntimeException('Network failed')];
rejectCatalog($db, $sync, 'Later-page network failure');
$client->pages = [['data' => [catalogItem('ONE')], 'has_more' => true],
    ['data' => [catalogItem('ONE')], 'has_more' => true]];
rejectCatalog($db, $sync, 'Repeating page');

// A later upsert failure must roll back earlier restores, quantities, and new products.
$db->table('products')->where('id', $two->product_id)->update(['type' => 'variable', 'deleted_at' => now()]);
$db->table('eis_product_maps')->where('id', $two->id)->update(['deleted_at' => now()]);
$client->pages = [['data' => [catalogItem('NEW'), catalogItem('ONE', '77'), catalogItem('TWO')]]];
rejectCatalog($db, $sync, 'Later product import failure');
$db->table('products')->where('id', $two->product_id)->update(['type' => 'single']);
$client->pages = [['data' => []]];
pullCatalog($sync);
catalogCheck(Product::withTrashed()->where('business_id', 1)->count() === 3
    && EisProductMap::withTrashed()->where('business_id', 1)->count() === 2
    && !Product::withTrashed()->find(100)->trashed(),
    'A successful explicit empty catalog preserves existing maps, products and manual business products');
catalogCheck(Product::where('business_id', 2)->count() === 1, 'Empty catalog remains tenant scoped');
$client->pages = [['data' => [catalogItem('TWO')]]];
pullCatalog($sync);
catalogCheck(Product::find($two->product_id) !== null && EisProductMap::find($two->id) !== null,
    'Restoration works after an empty catalog');
$db->table('variations')->where('product_id', $two->product_id)->update(['deleted_at' => now()]);
pullCatalog($sync);
catalogCheck($db->table('variations')->where('product_id', $two->product_id)->count() === 1
    && $db->table('variations')->where('product_id', $two->product_id)->value('deleted_at') === null,
    'Returning single variations restore instead of creating duplicate stock identities');
$productId = $db->table('products')->insertGetId([
    'business_id' => 1, 'type' => 'single', 'name' => 'Manual match', 'sku' => 'MANUAL', 'deleted_at' => now(),
]);
$client->pages = [['data' => [catalogItem('MANUAL')]]];
pullCatalog($sync);
catalogCheck(Product::find($productId) !== null
    && (int) EisProductMap::where('eis_product_id', 'MANUAL')->value('product_id') === $productId,
    'A returning catalog entry can restore a manual product by SKU and create its map');

// Reject nontransactional map storage before restoring or deleting anything.
$manager = Container::getInstance()['db'];
$engineDb = Mockery::mock(Illuminate\Database\Connection::class);
$engineDb->shouldReceive('getDriverName')->andReturn('mysql');
$engineDb->shouldReceive('getTablePrefix')->andReturn('');
$engineDb->shouldReceive('selectOne')->andReturnUsing(fn ($sql, $bindings) => (object) [
    'engine' => $bindings[0] === 'eis_product_maps' ? 'MyISAM' : 'InnoDB',
]);
$engineManager = Mockery::mock();
$engineManager->shouldReceive('connection')->andReturn($engineDb);
Illuminate\Support\Facades\DB::swap($engineManager);
try {
    rejectCatalog($db, $sync, 'Nontransactional product map storage');
} finally {
    Illuminate\Support\Facades\DB::swap($manager);
}

[$db, $client, $sync] = catalogFixture();
$db->table('business_locations')->where('business_id', 1)->update(['eis_site_id' => 'WRONG']);
$client->pages = [['data' => [catalogItem('ONE')]]];
rejectCatalog($db, $sync, 'Unknown site mapping');
$db->table('business_locations')->where('business_id', 1)->update(['eis_site_id' => 'SITE-A']);
$db->table('business_locations')->insert(['business_id' => 1, 'eis_site_id' => 'SITE-A']);
$client->pages = [['data' => [catalogItem('ONE')]]];
pullCatalog($sync);
catalogCheck($db->table('variation_location_details')->where('product_id',
    EisProductMap::where('eis_product_id', 'ONE')->value('product_id'))->where('location_id', 1)->exists(),
    'Selected terminal location is honored when multiple locations share its EIS site');
$db->table('business_locations')->where('business_id', 1)->where('id', '!=', 1)->delete();
$foreignId = $db->table('products')->insertGetId(['business_id' => 2, 'type' => 'single', 'name' => 'Foreign', 'sku' => 'ONE']);
$db->table('eis_product_maps')->where('business_id', 1)->where('eis_product_id', 'ONE')->update(['product_id' => $foreignId]);
rejectCatalog($db, $sync, 'Cross-business map identity');

// Real client must reject HTTP failures before decoding an error response as a catalog.
$http = new Illuminate\Http\Client\Factory();
$app = Container::getInstance();
$app->instance(Illuminate\Http\Client\Factory::class, $http);
Illuminate\Support\Facades\Http::swap($http);
$http->fake(['*' => $http->response(['data' => []], 500)]);
try {
    (new EisProductClient())->fetch((object) [
        'business_id' => 1,
        'jwt_token' => 'fixture',
        'tpin' => 'fixture',
        'site_id' => 'SITE-A',
    ]);
    throw new RuntimeException('Expected HTTP failure');
} catch (Illuminate\Http\Client\RequestException $e) {
    catalogCheck($e->response->status() === 500, 'HTTP errors cannot masquerade as empty successful catalogs');
}

[$db, $client, $sync] = catalogFixture();
$client->pages = [['data' => [catalogItem('OWNED')]]];
pullCatalog($sync);
$owned = Product::where('business_id', 1)->firstOrFail();
catalogCheck(\App\Services\EisProductPolicy::configured(1), 'Inactive EIS settings still enforce ownership');
catalogCheck(!\App\Services\EisProductPolicy::configured(2), 'Ordinary businesses retain manual products');
function rejectOwnership(callable $write, string $message): void
{
    try {
        $write();
    } catch (DomainException $e) {
        catalogCheck(str_contains($e->getMessage(), 'managed by EIS'), $message);
        return;
    }
    throw new RuntimeException($message.' should fail');
}
rejectOwnership(fn () => (new Product())->forceFill(['business_id' => 1, 'name' => 'Manual'])->save(), 'Manual model creation fails');
foreach (['name' => 'Forged', 'sku' => 'LOCAL', 'enable_stock' => 0, 'unit_id' => 999,
    'tax' => 999, 'product_description' => 'Local description', 'expiry_period' => '99', 'business_id' => 2] as $field => $value) {
    rejectOwnership(function () use ($owned, $field, $value) {
        $product = $owned->fresh();
        $product->$field = $value;
        $product->save();
    }, 'Protected product field '.$field.' fails');
}
rejectOwnership(fn () => $owned->fresh()->delete(), 'Manual product deletion fails');
$ordinary = (new Product())->forceFill(['business_id' => 2, 'name' => 'Manual allowed', 'sku' => 'LOCAL']);
$ordinary->save();
$ordinary->name = 'Local edited';
$ordinary->save();
catalogCheck($ordinary->fresh()->name === 'Local edited', 'Non-EIS creation and edits still work');
rejectOwnership(function () use ($ordinary) {
    $product = $ordinary->fresh();
    $product->business_id = 1;
    $product->save();
}, 'Moving an ordinary product into EIS cannot bypass ownership');
$variation = \App\Variation::where('product_id', $owned->id)->firstOrFail();
rejectOwnership(function () use ($variation) {
    $variation->default_sell_price = '999';
    $variation->save();
}, 'Manual variation price edits fail');
rejectOwnership(fn () => $variation->fresh()->delete(), 'Manual variation deletion fails');
rejectOwnership(fn () => \App\Variation::create(['product_id' => $owned->id, 'name' => 'Extra']),
    'Extra variations cannot be created manually');
$local = ['image' => 'local.jpg', 'category_id' => 5, 'sub_category_id' => 6, 'brand_id' => 7,
    'alert_quantity' => '2.5000', 'product_custom_field1' => 'Local custom'];
foreach ($local as $field => $value) {
    $owned->$field = $value;
}
$owned->save();
$db->table('products')->where('id', $owned->id)->update([
    'name' => 'Stale name', 'sku' => 'STALE', 'product_description' => 'Stale description', 'expiry_period' => '99',
]);
$client->pages = [['data' => [array_merge(catalogItem('OWNED'), ['productName' => 'New EIS name', 'description' => 'New EIS description'])]]];
pullCatalog($sync);
$owned = $owned->fresh();
catalogCheck($owned->name === 'New EIS name' && $owned->sku === 'OWNED'
    && $owned->product_description === 'New EIS description' && $owned->expiry_period === null,
    'Repull refreshes EIS identity, description and expiry');
foreach ($local as $field => $value) {
    catalogCheck((string) $owned->$field === (string) $value, 'Repull preserves local '.$field);
}
try {
    \App\Services\EisProductPolicy::duringSync(1, function () {
        catalogCheck(!\App\Services\EisProductPolicy::syncing(2), 'Trusted sync scope cannot authorize another business');
        throw new RuntimeException('Fixture failure');
    });
} catch (RuntimeException $e) {
    catalogCheck(!\App\Services\EisProductPolicy::syncing(1), 'Trusted scope is cleared after failure');
}
rejectOwnership(fn () => (new Product())->forceFill(['business_id' => 1, 'name' => 'After failure'])->save(),
    'Failed sync does not leave manual writes enabled');

$app = Container::getInstance();
$session = new Illuminate\Session\Store('ownership', new Illuminate\Session\ArraySessionHandler(120));
$session->put('user', ['business_id' => 1]);
$app->instance('session', $session);
$middleware = new \App\Http\Middleware\EnforceEisProductCatalog();
foreach ([
    'ProductController' => ['create', 'store', 'quickAdd', 'saveQuickProduct', 'destroy', 'massDestroy',
        'bulkEdit', 'bulkUpdate', 'addSellingPrices', 'saveSellingPrices', 'massDeactivate', 'activate', 'toggleWooCommerceSync'],
    'ImportProductsController' => ['index', 'store'],
    'ImportOpeningStockController' => ['index', 'store'],
    'OpeningStockController' => ['add', 'save'],
    'SellingPriceGroupController' => ['updateProductPrice', 'import'],
    'ModifierSetsController' => ['store', 'update', 'destroy'],
    'StockAdjustmentController' => ['store', 'destroy'],
] as $controller => $methods) {
    foreach ($methods as $method) {
        $request = Illuminate\Http\Request::create('http://localhost/fixture', 'POST');
        $request->setLaravelSession($session);
        $route = new Illuminate\Routing\Route('POST', 'fixture', [
            'uses' => 'App\Http\Controllers\\'.$controller.'@'.$method,
            'controller' => 'App\Http\Controllers\\'.$controller.'@'.$method,
        ]);
        $request->setRouteResolver(fn () => $route);
        try {
            $middleware->handle($request, fn () => true);
            throw new RuntimeException('Expected forbidden '.$controller.'@'.$method);
        } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
            catalogCheck($e->getStatusCode() === 403, $controller.'@'.$method.' is forbidden');
        }
        $session->put('user.business_id', 2);
        catalogCheck($middleware->handle($request, fn () => true) === true, 'Non-EIS endpoint stays available');
        $session->put('user.business_id', 1);
    }
}

// Exercise the actual local-only update endpoint with real validation and persistence.
$db->getSchemaBuilder()->table('business_locations', function (Blueprint $table) {
    $table->string('location_id')->nullable();
    foreach (['receipt_printer_type', 'selling_price_group_id', 'default_payment_accounts', 'invoice_scheme_id',
        'invoice_layout_id', 'sale_invoice_scheme_id'] as $column) {
        $table->text($column)->nullable();
    }
});
$db->getPdo()->sqliteCreateFunction('IF', fn ($condition, $yes, $no) => $condition ? $yes : $no, 3);
foreach (['brands', 'categories', 'selling_price_groups', 'product_racks'] as $tableName) {
    $db->getSchemaBuilder()->create($tableName, function (Blueprint $table) {
        $table->increments('id');
        foreach (['business_id', 'parent_id', 'is_active', 'name', 'product_id', 'location_id', 'rack', 'row', 'position'] as $column) {
            $table->text($column)->nullable();
        }
        $table->timestamps();
        $table->softDeletes();
    });
}
$db->table('brands')->insert([['id' => 7, 'business_id' => 1], ['id' => 8, 'business_id' => 2]]);
$db->table('categories')->insert([
    ['id' => 5, 'business_id' => 1, 'parent_id' => 0], ['id' => 6, 'business_id' => 1, 'parent_id' => 5],
    ['id' => 8, 'business_id' => 2, 'parent_id' => 0],
]);
$db->table('business_locations')->insert(['id' => 3, 'business_id' => 1, 'eis_site_id' => 'SITE-B']);
$db->table('product_locations')->insert(['product_id' => $owned->id, 'location_id' => 3]);
$user = Mockery::mock();
$user->shouldReceive('can')->andReturn(true);
$user->shouldReceive('permitted_locations')->andReturn([1]);
$auth = Mockery::mock(Illuminate\Contracts\Auth\Factory::class);
$auth->shouldReceive('user')->andReturn($user);
$app->instance(Illuminate\Contracts\Auth\Factory::class, $auth);
$validator = new Illuminate\Validation\Factory($app['translator'], $app);
$validator->setPresenceVerifier(new Illuminate\Validation\DatabasePresenceVerifier($app['db']));
$app->instance('validator', $validator);
Illuminate\Http\Request::macro('validate', function ($rules) {
    return app('validator')->make($this->all(), $rules)->validate();
});
$app->instance('events', new Illuminate\Events\Dispatcher($app));
$app->instance('redirect', new Illuminate\Routing\Redirector(new Illuminate\Routing\UrlGenerator(
    new Illuminate\Routing\RouteCollection(), Illuminate\Http\Request::create('http://localhost')
)));
$app['redirect']->setSession($session);
$app['config']['constants.document_size_limit'] = 5000000;
$util = Mockery::mock(\App\Utils\ProductUtil::class)->makePartial();
$uploadedImage = null;
$util->shouldReceive('uploadFile')->andReturnUsing(function () use (&$uploadedImage) {
    return $uploadedImage;
});
$controller = new \App\Http\Controllers\ProductController($util, Mockery::mock(\App\Utils\ModuleUtil::class));
function localEditRequest(array $data): Illuminate\Http\Request
{
    global $app, $session;
    $request = Illuminate\Http\Request::create('http://localhost/products/1', 'PUT', $data);
    $request->setLaravelSession($session);
    $app->instance('request', $request);
    return $request;
}
$session->put('currency', ['thousand_separator' => ',', 'decimal_separator' => '.']);
foreach ([
    ['name' => 'Forged'], ['single_dsp' => '999'], ['enable_stock' => 0],
    ['alert_quantity' => 'abc'], ['alert_quantity' => '-1'], ['alert_quantity' => '1.23456'],
    ['brand_id' => 8], ['category_id' => 8], ['category_id' => 5, 'sub_category_id' => 8],
    ['product_locations' => [2]], ['product_locations' => [3]], ['product_racks' => [2 => ['rack' => 'X']]],
    ['product_racks' => [1 => ['business_id' => 2]]],
] as $invalid) {
    try {
        $controller->update(localEditRequest($invalid), $owned->id);
        throw new RuntimeException('Expected local edit validation failure: '.json_encode($invalid));
    } catch (Illuminate\Validation\ValidationException $e) {
        catalogCheck(count($e->errors()) > 0, 'Forged fields, malformed values and foreign references fail validation');
    }
}
$response = $controller->update(localEditRequest([
    'brand_id' => 7, 'category_id' => 5, 'sub_category_id' => 6, 'alert_quantity' => '1,234.5000',
    'product_custom_field1' => 'Updated local', 'product_locations' => [1],
    'product_racks' => [1 => ['rack' => 'A', 'row' => 'B', 'position' => 'C']],
]), $owned->id);
catalogCheck($response->getSession()->get('status.success') === 1, 'Local-only update reports success');
catalogCheck($owned->fresh()->alert_quantity === '1234.5000'
    && $owned->fresh()->product_custom_field1 === 'Updated local' && $owned->fresh()->name === 'New EIS name',
    'Allowed fields persist without changing EIS identity');
catalogCheck($db->table('product_racks')->where('product_id', $owned->id)->value('rack') === 'A',
    'Rack details persist for an authorized location');
catalogCheck($owned->product_locations()->count() === 2, 'Local edits preserve assignments outside user permissions');
$controller->update(localEditRequest(['product_locations' => null]), $owned->id);
catalogCheck($owned->product_locations()->pluck('business_locations.id')->all() === [3],
    'Clearing selectable locations preserves inaccessible assignments');
$uploadedImage = 'replacement.jpg';
$controller->update(localEditRequest([]), $owned->id);
catalogCheck($owned->fresh()->image === 'replacement.jpg' && $owned->fresh()->woocommerce_media_id === null,
    'Local image updates persist and reset external image metadata');
$beforePrice = \App\Variation::where('product_id', $owned->id)->value('default_purchase_price');
$util->updateProductFromPurchase(['variation_id' => $variation->id, 'pp_without_discount' => 999, 'sell_price_inc_tax' => 999]);
catalogCheck(\App\Variation::where('product_id', $owned->id)->value('default_purchase_price') === $beforePrice,
    'Purchases do not overwrite EIS catalog prices');
try {
    $controller->updateProductLocation(localEditRequest([
        'products' => (string) $owned->id, 'product_location' => [2], 'update_type' => 'add',
    ]));
    throw new RuntimeException('Expected foreign bulk location validation failure');
} catch (Illuminate\Validation\ValidationException $e) {
    catalogCheck(isset($e->errors()['product_location.0']), 'Bulk location edits reject foreign locations');
}

$routes = new Illuminate\Routing\RouteCollection();
$routes->add(new Illuminate\Routing\Route('PUT', 'products/{product}', [
    'uses' => \App\Http\Controllers\ProductController::class.'@update',
    'controller' => \App\Http\Controllers\ProductController::class.'@update',
]));
$url = new Illuminate\Routing\UrlGenerator($routes, $app['request']);
$app->instance('url', $url);
$kernel = Mockery::mock(Illuminate\Contracts\Http\Kernel::class);
$kernel->shouldReceive('hasMiddleware')->andReturn(false);
$app->instance(Illuminate\Contracts\Http\Kernel::class, $kernel);
$view = Mockery::mock(Illuminate\Contracts\View\Factory::class)->shouldIgnoreMissing();
$parentView = Mockery::mock(Illuminate\Contracts\View\View::class);
$parentView->shouldReceive('render')->andReturn('');
$view->shouldReceive('make')->andReturn($parentView);
$form = new Collective\Html\FormBuilder(new Collective\Html\HtmlBuilder($url, $view), $url, $view, $session->token());
$form->setSessionStore($session);
$app->instance('form', $form);
if (!class_exists('Form')) {
    class_alias(Collective\Html\FormFacade::class, 'Form');
}
$session->put('business.custom_labels', json_encode([
    'product' => ['custom_field_1' => 'Color', 'custom_field_2' => 'Local date'],
    'product_cf_details' => [1 => ['type' => 'dropdown', 'dropdown_options' => "Red\nBlue"], 2 => ['type' => 'date']],
]));
$product = $owned->fresh();
$categories = [5 => 'Food'];
$sub_categories = [6 => 'Prepared'];
$brands = [7 => 'Local brand'];
$business_locations = collect([1 => 'Main']);
$rack_details = [1 => ['rack' => 'A', 'row' => 'B', 'position' => 'C']];
$alert_quantity = '1,234.5000';
$errors = new Illuminate\Support\ViewErrorBag();
$asset_v = 'fixture';
$__env = $view;
$blade = new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem(), sys_get_temp_dir());
$compiled = $blade->compileString(file_get_contents(dirname(__DIR__, 2).'\resources\views\product\edit_eis.blade.php'));
ob_start();
try {
    eval('?>'.$compiled);
    $rendered = ob_get_contents();
} finally {
    ob_end_clean();
}
$dom = new DOMDocument();
@$dom->loadHTML($rendered);
$xpath = new DOMXPath($dom);
foreach (['brand_id', 'category_id', 'sub_category_id', 'alert_quantity', 'image',
    'product_locations[]', 'product_racks[1][rack]', 'product_custom_field1', 'product_custom_field2'] as $field) {
    catalogCheck($xpath->query('//*[@name="'.$field.'"]')->length > 0, 'Editor renders allowed field '.$field);
}
foreach (['name', 'sku', 'unit_id', 'tax', 'enable_stock', 'product_description', 'single_dsp', 'expiry_period'] as $field) {
    catalogCheck($xpath->query('//*[@name="'.$field.'"]')->length === 0, 'Editor omits EIS-owned field '.$field);
}
catalogCheck($xpath->query('//input[@type="date" and @name="product_custom_field2"]')->length === 1,
    'Editor preserves custom date field type');
catalogCheck($xpath->query('//select[@name="product_custom_field1"]/option[@value="0" or @value="1"]')->length === 2,
    'Editor preserves custom dropdown options');

[$db, $client, $sync] = catalogFixture();
$log = Mockery::mock(Psr\Log\LoggerInterface::class)->shouldIgnoreMissing();
Illuminate\Support\Facades\Log::swap($log);
foreach ([
    [array_merge(catalogItem('6001106100551'), ['quantity' => null]), 'invalid_or_conflicting_stock'],
    [array_diff_key(catalogItem('6001106100551'), ['quantity' => true]), 'missing_stock'],
    [array_merge(catalogItem('6001106100551'), ['stock' => '999']), 'invalid_or_conflicting_stock'],
    [array_merge(catalogItem('6001106100551'), ['siteId' => 'OTHER']), 'invalid_or_mismatched_site'],
] as [$item, $reason]) {
    $client->pages = [['data' => [$item]]];
    $before = catalogSnapshot($db);
    try {
        pullCatalog($sync);
        throw new RuntimeException('Expected precise catalog rejection');
    } catch (UnexpectedValueException $e) {
        catalogCheck(str_contains($e->getMessage(), '6001106100551: '.$reason),
            'Catalog failure identifies the exact item and reason');
        catalogCheck(catalogSnapshot($db) === $before, 'Diagnostic rejection does not change catalog or stock');
    }
    $log->shouldHaveReceived('error')->with('EIS product catalog validation failed',
        Mockery::on(fn ($context) => $context['eis_id'] === '6001106100551'
            && $context['reason'] === $reason && $context['request_site'] === 'SITE-A'
            && !array_key_exists('productName', $context['stock_and_site_fields'])));
}
$client->pages = [['data' => [catalogItem('6001106100551', 108977)]]];
pullCatalog($sync);
catalogCheck($db->table('variation_location_details')->value('qty_available') === '108977.0000',
    'Observed EIS integer quantity is accepted exactly');
$precision = ini_get('serialize_precision');
try {
    foreach (['100', '4', '-1'] as $runtimePrecision) {
        ini_set('serialize_precision', $runtimePrecision);
        $client->pages = [['data' => [catalogItem('6001106100551', 108977.0)]]];
        pullCatalog($sync);
        catalogCheck($db->table('variation_location_details')->value('qty_available') === '108977.0000',
            'Observed live float imports exactly with serialize_precision='.$runtimePrecision);
        catalogCheck(\Modules\EIS\Services\Products\OpeningStockInitializer::stockSource(['quantity' => 0.0])['quantity'] === '0.0000',
            'Zero float remains valid');
        catalogCheck(\Modules\EIS\Services\Products\OpeningStockInitializer::stockSource(['quantity' => 12.3456])['quantity'] === '12.3456',
            'Four-decimal floats remain exact under host precision settings');
        foreach ([12.34567, -1.0, INF, NAN, 1e10] as $invalid) {
            catalogCheck(\Modules\EIS\Services\Products\OpeningStockInitializer::stockSource(['quantity' => $invalid])['quantity'] === null,
                'Unsafe float is not rounded into accepted stock');
        }
        catalogCheck(\Modules\EIS\Services\Products\OpeningStockInitializer::stockSource(['quantity' => 108977.0, 'stock' => '1'])['quantity'] === null,
            'Conflicting aliases remain rejected');
        catalogCheck(ini_get('serialize_precision') === $runtimePrecision, 'Parser restores host precision after accepted and rejected quantities');
        $transformer = new ProductTransformer();
        foreach ([
            ['quantity', 108977.0, '108977.0000'],
            ['quantity', '999999999999999999.9999', '999999999999999999.9999'],
            ['quantity', 0, '0.0000'],
            ['stock', '12.3456', '12.3456'],
            ['stockQuantity', '7.5000', '7.5000'],
        ] as [$field, $quantity, $expected]) {
            $item = array_diff_key(catalogItem('6001106100551'), ['quantity' => true]);
            $item[$field] = $quantity;
            $data = $transformer->fromEis($item, '6001106100551', 'SITE-A');
            catalogCheck($data['stock'] === $expected && $data['opening_stock_source']['quantity'] === $expected,
                'Transformed stock exactly matches the writer source for '.$field);
            $log->shouldHaveReceived('debug')->with('Product transformation completed',
                Mockery::on(fn ($context) => $context['eis_id'] === '6001106100551'
                    && $context['transformed_stock'] === $expected
                    && $context['stock_validation_reason'] === null));
        }
        foreach ([null, 'bad', '-1', '1.12345'] as $invalid) {
            $data = $transformer->fromEis(catalogItem('6001106100551', $invalid), '6001106100551', 'SITE-A');
            catalogCheck($data['stock'] === null && !$transformer->validate($data),
                'Invalid transformed stock stays null and fails validation instead of becoming zero');
        }
    }
} finally {
    ini_set('serialize_precision', $precision);
}
[$db, $client, $sync] = catalogFixture();
$client->pages = [['data' => [catalogItem('TERMINAL', '108977')]]];
pullCatalog($sync);
$product = Product::where('business_id', 1)->firstOrFail();
$variation = \App\Variation::where('product_id', $product->id)->firstOrFail();
$db->table('business_locations')->insert([
    ['id' => 3, 'business_id' => 1, 'eis_site_id' => 'SITE-B', 'is_active' => 1],
    ['id' => 4, 'business_id' => 1, 'eis_site_id' => 'SITE-C', 'is_active' => 0],
]);
foreach ([3 => '42.0000', 4 => '500.0000', 2 => '999.0000'] as $location => $quantity) {
    $db->table('variation_location_details')->insert([
        'product_id' => $product->id, 'variation_id' => $variation->id,
        'location_id' => $location, 'qty_available' => $quantity,
    ]);
}
function listedStock(int $businessId, $locationId = null, $permitted = 'all')
{
    return Product::join('variations as v', 'v.product_id', '=', 'products.id')
        ->withStockLocationDetails($businessId, $locationId, $permitted)
        ->where('products.business_id', $businessId)->whereNull('v.deleted_at')
        ->selectRaw('SUM(vld.qty_available) as current_stock')->value('current_stock');
}
catalogCheck((float) listedStock(1) === 109019.0,
    'Business stock includes quantities from its active locations only');
catalogCheck((float) listedStock(1, 1) === 108977.0, 'Selected terminal displays exact EIS stock');
catalogCheck((float) listedStock(1, 3) === 42.0, 'Another location displays only its own quantity');
catalogCheck((float) listedStock(1, null, [3]) === 42.0, 'Permitted location contributes only its own quantity');
catalogCheck((float) listedStock(1, null, [1]) === 108977.0, 'Permitted terminal quantity is included');
catalogCheck(listedStock(1, 4) === null, 'Inactive location stock is excluded when specifically selected');
catalogCheck(listedStock(1, null, [4]) === null, 'Inactive permitted location stock is excluded');
$db->table('eis_settings')->where('business_id', 1)->update(['site_id' => 'SITE-B']);
catalogCheck((float) listedStock(1) === 109019.0, 'Changing terminal site does not change the active business locations in scope');
$db->table('eis_settings')->where('business_id', 1)->update(['site_id' => 'UNKNOWN']);
catalogCheck((float) listedStock(1) === 109019.0, 'Missing terminal site does not alter ordinary business stock totals');
$db->table('eis_settings')->where('business_id', 1)->delete();
catalogCheck((float) listedStock(1) === 109019.0, 'Ordinary business list sums only its own locations');
catalogCheck((float) listedStock(1, 3) === 42.0, 'Ordinary selected-location stock excludes other locations');
catalogCheck((float) listedStock(1, null, [3]) === 42.0, 'Ordinary total respects permitted locations');
catalogCheck(listedStock(1, 2) === null, 'Foreign location cannot contribute to a business stock total');
Mockery::close();
echo "PASS: {$checks} isolated EIS catalog sync, ownership and editor checks; no live sync or network.\n";
