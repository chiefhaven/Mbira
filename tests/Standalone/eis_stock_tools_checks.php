<?php

// Isolated SQLite and HTTP fakes; no application boot, .env or live stock writes.
require __DIR__.'\eis_terminal_management_checks.php';

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\EIS\Http\Controllers\StockToolsController;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Stock\StockOperationsClient;
use Modules\EIS\Services\Stock\StockToolsOperations;
use Modules\EIS\Services\Stock\StockToolsService;

$toolChecks = 0;
function toolCheck(bool $condition, string $message): void {
    global $toolChecks;
    if (!$condition) { throw new RuntimeException('FAIL: '.$message); }
    $toolChecks++;
}
function toolReject(callable $work, string $message, ?string $expected = null): void {
    try { $work(); } catch (InvalidArgumentException | UnexpectedValueException | Illuminate\Validation\ValidationException $e) {
        if ($expected !== null && !str_contains($e->getMessage(), $expected)) {
            throw new RuntimeException('Unexpected rejection for '.$message.': '.$e->getMessage());
        }
        toolCheck(true, $message); return;
    }
    throw new RuntimeException('Expected rejection: '.$message);
}
$app->instance('validator', $validator);
$app->instance('db.schema', $schema);
$app->instance('log', new Psr\Log\NullLogger());
$app['config']->set('eis.base_url', 'https://eis.invalid/api/v1');
$schema->create('business', function (Blueprint $t) { $t->increments('id'); });
DB::table('business')->insert(['id' => 1]);
$schema->table('eis_settings', function (Blueprint $t) { $t->string('branch_id')->nullable(); $t->string('tpin')->nullable(); });
$schema->table('business_locations', function (Blueprint $t) { $t->string('eis_site_id')->nullable(); });
DB::table('business_locations')->where('id', 10)->update(['eis_site_id' => 'SITE-A']);
DB::table('eis_settings')->where('id', 101)->update(['branch_id' => 'SITE-A', 'tpin' => '12345678']);
$migration = require dirname(__DIR__, 2).'\Modules\EIS\Database\Migrations\2026_10_24_040000_create_eis_stock_tool_requests.php';
$migration->up();
// Sentinel local ledgers must remain byte-for-byte unchanged throughout.
foreach (['variation_location_details', 'products', 'transactions'] as $table) {
    $schema->create($table, function (Blueprint $t) { $t->increments('id'); $t->string('sentinel'); });
    DB::table($table)->insert(['sentinel' => 'UNCHANGED']);
}
function toolGraph(): array {
    $graph = [];
    foreach (['variation_location_details', 'products', 'transactions'] as $table) { $graph[$table] = DB::table($table)->get()->toJson(); }
    return $graph;
}
$local = toolGraph();
$setting = EisSetting::findOrFail(101);
$factory = new Factory(); Http::swap($factory);
$requests = []; $status = 200; $disconnect = false;
$body = ['statusCode' => 1, 'data' => []];
$factory->fake(function ($request) use ($factory, &$requests, &$status, &$body, &$disconnect) {
    $requests[] = $request;
    if ($disconnect) { throw new Illuminate\Http\Client\ConnectionException('Synthetic lost response'); }
    return $factory->response($body, $status);
});
$client = new StockOperationsClient();
$service = new StockToolsService($client);
$examples = StockToolsOperations::definitions();
$examples['warehouse-transfer'] = ['items' => [['barcode' => 'BAR-1', 'quantity' => 2, 'price' => 10]]];
$examples['submit-conversion'] = ['productionBatchId' => 'BATCH-1', 'productionDate' => '2026-10-24T02:00:00+02:00',
    'rawMaterials' => [['productId' => 'RAW-1', 'productName' => 'Raw material', 'availableQuantity' => 5, 'usedQuantity' => 2]],
    'finishedProducts' => [['quantity' => 1, 'unitOfMeasure' => 'Each', 'productDescription' => 'Finished', 'barcode' => 'BAR-1']]];
$examples['add-product'] = ['barcode' => 'BAR-2', 'hsCode' => '1001', 'name' => 'Product', 'description' => 'Product description', 'uom' => 'Each'];
foreach ([
    'warehouse-inventory' => ['GET', 'stock/warehouse-inventory'],
    'get-raw-material' => ['GET', 'raw-material/get-raw-material'],
    'get-suppliers' => ['POST', 'stock/get-suppliers'],
    'get-hs-codes' => ['GET', 'stock/get-hs-codes'],
    'get-units-of-measure' => ['GET', 'stock/get-units-of-measure'],
] as $operation => [$method, $path]) {
    $payload = StockToolsOperations::payload($setting, $operation, $examples[$operation]);
    $body['data'] = isset($payload['page'])
        ? ['stocks' => [['barcode' => 'BAR-1', 'currentQuantity' => 5]], 'total' => 1, 'page' => 1, 'pageSize' => 50] : [];
    $result = $client->lookup($setting, $operation, $payload);
    $sent = end($requests);
    $expected = 'https://eis.invalid/api/v1/'.$path.(isset($payload['page']) ? '?page=1&pageSize=50' : '');
    toolCheck($sent->method() === $method && $sent->url() === $expected, 'Exact documented method/path/query: '.$operation);
    toolCheck($sent->body() === '' && $sent->hasHeader('Authorization', 'Bearer '.$setting->jwt_token),
        'Lookup has no invented request body and uses selected terminal token: '.$operation);
    toolCheck($result['statusCode'] === 1, 'Documented lookup response retained: '.$operation);
}
$warehouse = StockToolsOperations::payload($setting, 'warehouse-transfer', $examples['warehouse-transfer']);
toolCheck($warehouse['fromWarehouseToSite'] === true && $warehouse['siteToWarehouse'] === false
    && $warehouse['fromSiteId'] === null && $warehouse['toSiteId'] === 'SITE-A', 'Warehouse direction and exact selected destination site are server-pinned');
foreach ([
    ['warehouse-inventory', ['page' => 0, 'pageSize' => 50]],
    ['warehouse-inventory', ['page' => 1, 'pageSize' => 201]],
    ['get-raw-material', ['page' => 1, 'pageSize' => 0]],
    ['get-suppliers', ['tin' => 'OTHER']],
    ['warehouse-transfer', array_merge($examples['warehouse-transfer'], ['toSiteId' => 'FOREIGN'])],
    ['warehouse-transfer', ['items' => ['object-not-list' => $examples['warehouse-transfer']['items'][0]]]],
    ['warehouse-transfer', ['items' => [['barcode' => 'BAR', 'quantity' => -1]]]],
    ['warehouse-transfer', ['items' => [['barcode' => 'BAR', 'quantity' => INF]]]],
    ['warehouse-transfer', ['items' => [['barcode' => 'BAR', 'quantity' => 1000000001]]]],
    ['warehouse-transfer', ['items' => [['barcode' => 'BAR', 'quantity' => 1, 'extra' => true]]]],
    ['warehouse-transfer', ['items' => array_fill(0, 2, $examples['warehouse-transfer']['items'][0])]],
    ['add-product', array_merge($examples['add-product'], ['barcode' => '123'])],
    ['add-product', array_merge($examples['add-product'], ['name' => '   '])],
] as [$operation, $payload]) {
    toolReject(fn () => StockToolsOperations::payload($setting, $operation, $payload), 'Invalid contract, unknown fields, duplicate item or numeric limit rejected');
}
foreach (['warehouse-inventory', 'get-raw-material'] as $operation) {
    toolCheck(StockToolsOperations::payload($setting, $operation, ['page' => 1, 'pageSize' => 200])['pageSize'] === 200,
        'Exact maximum page size 200 accepted: '.$operation);
}
$many = ['items' => []];
for ($i = 0; $i < 100; $i++) { $many['items'][] = ['barcode' => 'BAR-'.$i, 'quantity' => 1]; }
toolCheck(count(StockToolsOperations::payload($setting, 'warehouse-transfer', $many)['items']) === 100,
    'Exactly 100 unique transfer items accepted');
$many['items'][] = ['barcode' => 'BAR-101', 'quantity' => 1];
toolReject(fn () => StockToolsOperations::payload($setting, 'warehouse-transfer', $many), '101 transfer items rejected');
$bad = $examples['submit-conversion']; $bad['rawMaterials'][0]['usedQuantity'] = 6;
toolReject(fn () => StockToolsOperations::payload($setting, 'submit-conversion', $bad), 'Cannot consume more raw material than declared available');
$bad = $examples['submit-conversion']; $bad['rawMaterials'][0]['usedQuantity'] = 0.009;
toolReject(fn () => StockToolsOperations::payload($setting, 'submit-conversion', $bad), 'Conversion minimum 0.01 enforced');
$bad['rawMaterials'][0]['usedQuantity'] = 0.01;
toolCheck(StockToolsOperations::payload($setting, 'submit-conversion', $bad)['rawMaterials'][0]['usedQuantity'] === 0.01,
    'Exact minimum conversion quantity 0.01 accepted');
$bad = $examples['submit-conversion']; $bad['finishedProducts'][0]['expiryDate'] = '2026-10-23T02:00:00+02:00';
toolReject(fn () => StockToolsOperations::payload($setting, 'submit-conversion', $bad), 'Expiry before production rejected');
$bad = $examples['submit-conversion']; $bad['productionDate'] = '2026-10-24';
toolReject(fn () => StockToolsOperations::payload($setting, 'submit-conversion', $bad), 'Production date requires timezone');
$withoutBarcode = $examples['add-product']; unset($withoutBarcode['barcode']);
toolCheck(!isset(StockToolsOperations::payload($setting, 'add-product', $withoutBarcode)['barcode']), 'Optional product barcode can be omitted for EIS assignment');

foreach (['warehouse-transfer', 'submit-conversion', 'add-product'] as $operation) {
    DB::table('eis_stock_tool_requests')->delete();
    $key = (string) Str::uuid(); $before = count($requests);
    $row = $service->prepare($setting, $operation, $examples[$operation], $key, 7);
    toolCheck($row->status === 'prepared' && count($requests) === $before && toolGraph() === $local,
        'Prepare persists exact review without HTTP or local mutations: '.$operation);
    toolCheck($service->prepare($setting, $operation, $examples[$operation], $key, 7)->id === $row->id,
        'Duplicate prepare recovers original record: '.$operation);
    $changed = $examples[$operation]; $changed['unknown'] = true;
    toolReject(fn () => $service->prepare($setting, $operation, $changed, $key, 7), 'Request-key replay cannot change payload');
    if ($operation !== 'warehouse-transfer') {
        toolReject(fn () => $service->prepare($setting, $operation, $examples[$operation], (string) Str::uuid(), 7),
            'Production batch or product cannot be prepared twice with a new browser request key');
    }
    $body = ['statusCode' => 1, 'data' => $operation === 'add-product' ? array_merge($examples['add-product'], ['productId' => 55]) : (object) []];
    $sent = $service->submit($setting, $row->id, 8);
    $path = ['warehouse-transfer' => 'stock/transfer-inventory', 'submit-conversion' => 'raw-material/submit-conversion',
        'add-product' => 'stock/add-product'][$operation];
    toolCheck($sent->status === 'submitted' && count($requests) === $before + 1
        && end($requests)->url() === 'https://eis.invalid/api/v1/'.$path, 'Reviewed mutation sends once to exact endpoint: '.$operation);
    toolCheck((int) $sent->created_by === 7 && (int) $sent->submitted_by === 8 && $sent->submitted_at !== null,
        'Audit distinguishes preparation and submission actors');
    $service->submit($setting, $row->id, 7);
    toolCheck(count($requests) === $before + 1 && toolGraph() === $local, 'Replay never sends or modifies local ledgers: '.$operation);
}
DB::table('eis_stock_tool_requests')->delete();
$row = $service->prepare($setting, 'warehouse-transfer', $examples['warehouse-transfer'], (string) Str::uuid(), 7);
$before = count($requests);
DB::beginTransaction();
toolReject(fn () => $service->submit($setting, $row->id, 7), 'Send claim cannot be enclosed in an outer transaction');
DB::rollBack();
$app['config']->set('eis.base_url', 'https://other.invalid');
toolReject(fn () => $service->submit($setting, $row->id, 7), 'Changed endpoint invalidates saved review');
$app['config']->set('eis.base_url', 'https://eis.invalid/api/v1');
DB::table('eis_settings')->where('id', 101)->update(['branch_id' => 'MOVED']);
toolReject(fn () => $service->submit($setting, $row->id, 7), 'Changed site invalidates saved review');
DB::table('eis_settings')->where('id', 101)->update(['branch_id' => 'SITE-A']);
DB::table('eis_settings')->where('id', 101)->update(['status' => false]);
toolReject(fn () => $service->submit($setting, $row->id, 7), 'Manual terminal deactivation prevents mutation');
DB::table('eis_settings')->where('id', 101)->update(['status' => true]);
DB::table('eis_stock_tool_requests')->where('id', $row->id)->update(['status' => 'sending']);
toolCheck($service->submit($setting, $row->id, 7)->status === 'sending' && count($requests) === $before,
    'A second caller does not own an existing sending claim');
toolReject(fn () => $service->verify(1, $row->id, 7, 'Verified with MRA reference TEST-123'), 'Running send cannot be reset through verification');
DB::table('eis_stock_tool_requests')->where('id', $row->id)->update(['status' => 'prepared']);
$disconnect = true;
toolCheck($service->submit($setting, $row->id, 7)->status === 'unknown' && count($requests) === $before + 1,
    'Lost stock mutation response persists unknown after one attempt');
$service->submit($setting, $row->id, 7);
toolCheck(count($requests) === $before + 1, 'Unknown request cannot be retried');
$next = $service->prepare($setting, 'submit-conversion', $examples['submit-conversion'], (string) Str::uuid(), 7);
toolReject(fn () => $service->submit($setting, $next->id, 7), 'Unresolved taxpayer request blocks new stock-tool sends');
toolReject(fn () => $service->verify(1, $row->id, 7, 'Too short'), 'Verification requires meaningful notes');
$verified = $service->verify(1, $row->id, 9, 'MRA reference TEST-123: confirmed transfer completed.');
toolCheck($verified->status === 'verified' && (int) $verified->verified_by === 9 && $verified->verified_at !== null,
    'External verification is attributed and timestamped without resending');
$service->submit($setting, $row->id, 7);
toolCheck(count($requests) === $before + 1, 'Verified request remains non-retryable');
$disconnect = false; $body = ['statusCode' => 1, 'data' => (object) []];
toolCheck($service->submit($setting, $next->id, 7)->status === 'submitted', 'Verified outcome permits the next genuine operation');
DB::table('eis_stock_tool_requests')->delete();
$row = $service->prepare($setting, 'add-product', $examples['add-product'], (string) Str::uuid(), 7);
$body = ['statusCode' => 1, 'data' => ['productId' => 12, 'barcode' => 'DIFFERENT']];
toolCheck($service->submit($setting, $row->id, 7)->status === 'needs_review', 'Mismatching product result is not accepted as catalog creation');
toolCheck(toolGraph() === $local, 'All remote outcomes leave local quantities, manufacturing, purchases and catalog untouched');

foreach ([
    [200, ['statusCode' => 0, 'data' => []]],
    [200, ['statusCode' => 1, 'errors' => ['bad'], 'data' => []]],
    [302, ['statusCode' => 1, 'data' => []]],
    [503, ['statusCode' => 1, 'data' => []]],
] as [$status, $body]) {
    toolReject(fn () => $client->lookup($setting, 'get-suppliers'), 'Rejected/redirected/mixed/server lookup fails explicitly');
}
$status = 200; $body = ['statusCode' => 1, 'data' => ['stocks' => [], 'total' => 0, 'page' => 2, 'pageSize' => 50]];
toolReject(fn () => $client->lookup($setting, 'warehouse-inventory', ['page' => 1, 'pageSize' => 50]), 'Mismatched upstream pagination cannot masquerade as requested inventory page');
$controller = new StockToolsController();
$user = new class {
    public int $id = 7;
    public int $business_id = 1;
    public bool $allowed = true;
    public $locations = 'all';
    public array $denied = [];
    public function can($permission): bool { return $this->allowed && !in_array($permission, $this->denied, true); }
    public function permitted_locations($businessId = null) { return $this->locations; }
};
function toolRequest(array $input = []): Illuminate\Http\Request { return managementRequest($input); }
$key = (string) Str::uuid();
$body = ['statusCode' => 1, 'data' => []];
$response = $controller->execute(toolRequest(['terminal_id' => 101, 'operation' => 'get-suppliers', 'payload' => '{}', 'request_key' => $key]), $client, $service);
toolCheck($response->getStatusCode() === 200 && $response->getData(true)['success'], 'Actual controller executes an authenticated lookup');
$invalid = $controller->execute(toolRequest(['terminal_id' => 101, 'operation' => 'get-suppliers', 'payload' => '{invalid', 'request_key' => $key]), $client, $service);
toolCheck($invalid->getStatusCode() === 422, 'Invalid UI JSON gets explicit failure');
$user->locations = [10];
managementReject(fn () => $controller->execute(toolRequest(), $client, $service), 403, 'Restricted location user cannot access taxpayer warehouse data');
$user->locations = 'all'; $user->allowed = false;
managementReject(fn () => $controller->execute(toolRequest(), $client, $service), 403, 'Warehouse console requires business settings permission');
$user->allowed = true;
managementReject(fn () => $controller->execute(toolRequest(['terminal_id' => 201, 'operation' => 'get-suppliers', 'payload' => '{}', 'request_key' => $key]), $client, $service),
    404, 'Other business terminal cannot be used');
$user->denied = ['product.create'];
managementReject(fn () => $controller->execute(toolRequest(['terminal_id' => 101, 'operation' => 'add-product', 'payload' => json_encode($examples['add-product']), 'request_key' => $key]), $client, $service),
    403, 'Catalog mutation requires product-create permission as well');
$user->denied = [];
DB::table('eis_stock_tool_requests')->delete();
$before = count($requests);
$prepared = $controller->execute(toolRequest(['terminal_id' => 101, 'operation' => 'warehouse-transfer',
    'payload' => json_encode($examples['warehouse-transfer']), 'request_key' => $key]), $client, $service)->getData(true);
toolCheck($prepared['prepared'] && count($requests) === $before, 'Controller prepares stock mutation without sending');
$id = $prepared['data']['id'];
toolReject(fn () => $controller->submit(toolRequest(), $id, $service), 'Controller requires explicit confirmation');
$shown = $controller->show(toolRequest(), $id)->getData(true)['data'];
toolCheck($shown['payload']['toSiteId'] === 'SITE-A' && !isset($shown['jwt_token']) && !isset($shown['secret_key']),
    'Review exposes exact pinned destination without credentials');
$body = ['statusCode' => 1, 'data' => (object) []];
toolCheck($controller->submit(toolRequest(['confirmed' => 1]), $id, $service)->getData(true)['success'],
    'Confirmed controller submission persists accepted stock request');
$controller->submit(toolRequest(['confirmed' => 1]), $id, $service);
toolCheck(count($requests) === $before + 1 && toolGraph() === $local, 'Repeated browser submit never resends or updates local stock');
$app['config']->set('eis.base_url', 'https://eis.invalid');
$client->lookup($setting, 'get-units-of-measure');
toolCheck(end($requests)->url() === 'https://eis.invalid/api/v1/stock/get-units-of-measure', 'Host-only stock tools endpoint normalizes API prefix exactly once');
$body = ['statusCode' => 1, 'data' => ['stocks' => null, 'total' => 0, 'page' => 1, 'pageSize' => 50]];
toolCheck($client->lookup($setting, 'warehouse-inventory', ['page' => 1, 'pageSize' => 50])['data']['stocks'] === [],
    'Documented nullable empty inventory is normalized only with an explicit zero total');
$body['data']['total'] = 1;
toolReject(fn () => $client->lookup($setting, 'warehouse-inventory', ['page' => 1, 'pageSize' => 50]),
    'Null inventory with nonzero total is not silently treated as empty stock');
$body['data']['stocks'] = [['barcode' => null, 'currentQuantity' => 1]];
toolCheck(count($client->lookup($setting, 'get-raw-material', ['page' => 1, 'pageSize' => 50])['data']['stocks']) === 1,
    'Documented nullable barcode remains visible for raw materials');
$before = count($requests);
toolReject(fn () => $client->lookup($setting, 'warehouse-inventory'), 'Direct client callers must provide valid paging');
toolCheck(count($requests) === $before, 'Invalid direct lookup cannot send an HTTP request');
foreach (['get-suppliers' => ['supplierId' => 1], 'get-hs-codes' => ['code' => '1001'],
    'get-units-of-measure' => ['unitOfMeasure' => 'Each']] as $operation => $item) {
    $body = ['statusCode' => 1, 'data' => [$item]];
    toolCheck($client->lookup($setting, $operation)['data'][0] === $item, 'Documented lookup item identity retained: '.$operation);
    $body['data'] = ['malformed item'];
    toolReject(fn () => $client->lookup($setting, $operation), 'Malformed list entry rejected: '.$operation);
}
DB::table('eis_stock_tool_requests')->delete();
$row = $service->prepare($setting, 'warehouse-transfer', $examples['warehouse-transfer'], (string) Str::uuid(), 7);
DB::table('business_locations')->where('id', 10)->update(['eis_site_id' => 'OTHER-SITE']);
toolReject(fn () => $service->submit($setting, $row->id, 7), 'Changed location site blocks a warehouse transfer even if terminal site is unchanged');
toolReject(fn () => $service->prepare($setting, 'warehouse-transfer', $examples['warehouse-transfer'], (string) Str::uuid(), 7),
    'Inconsistent location ownership cannot prepare a warehouse transfer');
DB::table('business_locations')->where('id', 10)->update(['eis_site_id' => 'SITE-A']);
$outboxMigration = require dirname(__DIR__, 2).'\Modules\EIS\Database\Migrations\2026_10_23_234000_create_eis_stock_operations.php';
$outboxMigration->up();
$utilityMigration = require dirname(__DIR__, 2).'\Modules\EIS\Database\Migrations\2026_10_24_020000_create_eis_utility_requests.php';
$utilityMigration->up();
$outboxId = DB::table('eis_stock_operations')->insertGetId(['business_id' => 1, 'location_id' => 10,
    'eis_setting_id' => 101, 'operation_type' => 'informal_purchase', 'site_id' => 'SITE-A', 'request_payload' => '{}']);
foreach (Modules\EIS\Services\Stock\StockOutboundService::UNSETTLED_STATUSES as $state) {
    DB::table('eis_stock_operations')->where('id', $outboxId)->update(['status' => $state]);
    toolReject(fn () => $service->submit($setting, $row->id, 7), 'Existing '.$state.' stock outbox fences new taxpayer tool sends');
}
DB::table('eis_stock_operations')->where('id', $outboxId)->update(['status' => 'verified']);
$uploadId = DB::table('eis_utility_requests')->insertGetId(['business_id' => 1, 'location_id' => 10,
    'eis_setting_id' => 101, 'created_by' => 7, 'operation' => 'taxpayer-initial-inventory-upload',
    'endpoint' => 'https://eis.invalid/api/v1', 'tin' => '12345678', 'site_id' => 'SITE-A', 'device_id' => $setting->device_id,
    'identity' => str_repeat('a', 64), 'payload' => '{"isLastBatch":false,"products":[]}']);
foreach (['sending', 'unknown', 'needs_review', 'accepted'] as $state) {
    DB::table('eis_utility_requests')->where('id', $uploadId)->update(['status' => $state]);
    toolReject(fn () => $service->submit($setting, $row->id, 7), 'Staged/unresolved '.$state.' initial inventory fences taxpayer tools');
}
DB::table('eis_utility_requests')->where('id', $uploadId)->update(['payload' => '{"isLastBatch":true,"products":[]}']);
$body = ['statusCode' => 1, 'data' => (object) []];
toolCheck($service->submit($setting, $row->id, 7)->status === 'submitted',
    'Completed final initial-inventory upload and settled outbox allow taxpayer stock tools');
DB::table('eis_stock_tool_requests')->where('id', $row->id)->update(['status' => 'unknown']);
$outbox = new Modules\EIS\Services\Stock\StockOutboundService($client);
toolReject(fn () => $outbox->enqueue(1, 10, 101, 'adjustment', 'SITE-A',
    ['siteId' => 'SITE-A', 'lines' => [['barcode' => 'BAR-1', 'quantity' => 1, 'adjustmentType' => 'Increase', 'adjustmentReason' => 'Damaged']]],
    [['barcode' => 'BAR-1', 'quantity' => 1, 'payload' => ['siteId' => 'SITE-A', 'barcode' => 'BAR-1',
        'quantity' => 1, 'adjustmentType' => 'Increase', 'adjustmentReason' => 'Damaged']]], 7),
    'New outbound requests cannot bypass an unresolved taxpayer tool', 'unresolved taxpayer stock-tool');
DB::table('eis_stock_operations')->where('id', $outboxId)->update(['status' => 'queued']);
$before = count($requests);
$outbox->submit($outboxId);
$blocked = DB::table('eis_stock_operations')->find($outboxId);
toolCheck($blocked->status === 'queued' && str_contains($blocked->error_message, 'unresolved') && count($requests) === $before,
    'Already queued outbox remains unsent with explicit blocking reason, not falsely unknown or submitted');
DB::table('eis_utility_requests')->where('id', $uploadId)->update(['status' => 'prepared']);
$utility = new Modules\EIS\Services\Utilities\UtilityRequestService(new Modules\EIS\Services\Utilities\EisUtilitiesClient());
toolReject(fn () => $utility->submit($setting, $uploadId, 7), 'Initial-inventory upload cannot bypass an unresolved taxpayer tool', 'unresolved taxpayer stock-tool');
toolCheck(count($requests) === $before && DB::table('eis_utility_requests')->find($uploadId)->status === 'prepared',
    'Blocked initial upload never acquires a sending claim or sends HTTP');
toolCheck(toolGraph() === $local, 'Cross-workflow guards and failures preserve all local stock/catalog ledgers');
$migration->down();
echo "PASS: {$toolChecks} StockOperations tools checks (plus management fixture), SQLite and HTTP fakes only.\n";
