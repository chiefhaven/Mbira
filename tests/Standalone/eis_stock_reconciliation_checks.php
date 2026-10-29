<?php

require __DIR__.'\eis_opening_stock_checks.php';

use Illuminate\Database\Schema\Blueprint;
use Modules\EIS\Jobs\SyncProductsJob;
use Modules\EIS\Services\Stock\StockInventoryClient;
use Modules\EIS\Services\Stock\StockReconciliationService;

$catalogSync = new SyncProductsJob(1, null, false, null, true);
$automaticSync = new SyncProductsJob(1, null, true, 7, true);
verify($catalogSync->uniqueId() === '1:all:all-catalog'
    && $automaticSync->uniqueId() === '1:all:catalog-and-stock'
    && $catalogSync->uniqueId() !== $automaticSync->uniqueId(),
    'Product-listing syncs coalesce by business while catalog-only and stock-reconciling work stay separate');

[$db, $upserter] = openingFixture();
$schema = $db->getSchemaBuilder();
$container = Illuminate\Container\Container::getInstance();
$container['config']['eis.base_url'] = 'https://eis.invalid/api/v1';
$schema->table('transactions', function (Blueprint $t) {
    foreach (['source', 'ref_no', 'adjustment_type'] as $column) { $t->text($column)->nullable(); }
});
$schema->table('stock_adjustment_lines', function (Blueprint $t) { $t->text('unit_price')->nullable(); });
$schema->table('transaction_sell_lines_purchase_lines', function (Blueprint $t) { $t->integer('stock_adjustment_line_id')->nullable(); });
$schema->table('eis_settings', function (Blueprint $t) { $t->boolean('status')->default(true); $t->string('tpin')->default('TIN'); });
$schema->create('eis_terminal_configurations', function (Blueprint $t) {
    $t->increments('id'); $t->integer('configuration_id'); $t->boolean('is_active');
});
$db->table('eis_terminal_configurations')->insert(['configuration_id' => 1, 'is_active' => true]);
$schema->create('eis_sales', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->integer('transaction_id'); $t->string('status');
});
$container->instance('db.schema', $schema);
$migration = require dirname(__DIR__, 2).'\Modules\EIS\Database\Migrations\2026_10_23_231000_create_eis_stock_reconciliations.php';
$migration->up();
$migration = require dirname(__DIR__, 2).'\Modules\EIS\Database\Migrations\2026_10_23_234000_create_eis_stock_operations.php';
$migration->up();
$migration = require dirname(__DIR__, 2).'\Modules\EIS\Database\Migrations\2026_10_24_020000_create_eis_utility_requests.php';
$migration->up();
$migration = require dirname(__DIR__, 2).'\Modules\EIS\Database\Migrations\2026_10_24_030000_create_eis_sale_adjustments.php';
$migration->up();
$migration = require dirname(__DIR__, 2).'\Modules\EIS\Database\Migrations\2026_10_24_040000_create_eis_stock_tool_requests.php';
$migration->up();
$client = new class extends StockInventoryClient {
    public array $inventory = ['EIS-ONE' => '8.0000'];
    public function fetch(\Modules\EIS\Models\EisSetting $setting): array { return $this->inventory; }
};
$service = new StockReconciliationService($db, $client);
$db->transaction(fn () => importItem($upserter));
$stockRow = (array) $db->table('variation_location_details')->first();
unset($stockRow['id']);
$stockRow['location_id'] = 4;
$stockRow['qty_available'] = '77.0000';
$db->table('variation_location_details')->insert($stockRow);
function stockReject(callable $work, string $message): void
{
    try { $work(); } catch (InvalidArgumentException | UnexpectedValueException $e) { verify(true, $message); return; }
    throw new RuntimeException('Expected rejection: '.$message);
}
$before = graph($db);
$preview = $service->preview(1, 1, 7);
verify(graph($db) === $before, 'Comparison never changes stock or movement graph');
$lines = json_decode($preview->lines, true);
verify($lines[0]['local'] === '10.1234' && $lines[0]['remote'] === '8.0000', 'Comparison uses exact four-place quantities');
$applied = $service->apply(1, $preview->id, 7);
verify($applied->status === 'applied' && $db->table('variation_location_details')->value('qty_available') === '8.0000',
    'Authoritative decrease updates local stock');
verify($db->table('variation_location_details')->where('location_id', 4)->value('qty_available') === '77.0000',
    'Reconciliation never changes another site quantity for the same shared product');
verify($db->table('stock_adjustment_lines')->value('quantity') === '2.1234'
    && $db->table('purchase_lines')->value('quantity_adjusted') === '2.1234',
    'Decrease creates stock-adjustment line and exact purchase consumption');
verify($db->table('transaction_sell_lines_purchase_lines')->whereNotNull('stock_adjustment_line_id')->value('quantity') === '2.1234',
    'Decrease has an auditable allocation ledger');
$graph = graph($db);
$service->apply(1, $preview->id, 7);
verify(graph($db) === $graph, 'Apply replay is idempotent and creates no duplicate movements');
$client->inventory = ['EIS-ONE' => '12.0000'];
$preview = $service->preview(1, 1, 7);
$service->apply(1, $preview->id, 7);
verify($db->table('variation_location_details')->value('qty_available') === '12.0000'
    && $db->table('purchase_lines')->orderByDesc('id')->value('quantity') === '4.0000',
    'Increase creates a fresh supply ledger instead of rewriting historical opening stock');
verify($db->table('transactions')->where('source', 'eis_reconciliation')->count() === 2,
    'Every nonzero difference creates an explicitly sourced audit transaction');
$preview = $service->preview(1, 1, 7);
$before = graph($db);
$service->apply(1, $preview->id, 7);
verify(graph($db) === $before, 'Already-equal stock produces no movement');
$preview = $service->preview(1, 1, 7);
$client->inventory['EIS-ONE'] = '13.0000';
stockReject(fn () => $service->apply(1, $preview->id, 7), 'Changed remote snapshot requires fresh comparison');
verify(graph($db) === $before, 'Remote change rolls back all local writes');
$preview = $service->preview(1, 1, 7);
$db->table('variation_location_details')->where('location_id', 2)->update(['qty_available' => '11.0000']);
stockReject(fn () => $service->apply(1, $preview->id, 7), 'Changed local quantity or allocation blocks stale snapshot');
$db->table('variation_location_details')->where('location_id', 2)->update(['qty_available' => '12.0000']);
$client->inventory = [];
stockReject(fn () => $service->preview(1, 1, 7), 'Missing remote product is never interpreted as zero');
$client->inventory = ['EIS-ONE' => '12.0000'];
$db->table('transactions')->insert(['id' => 99, 'business_id' => 1, 'location_id' => 2, 'type' => 'sell', 'status' => 'final']);
stockReject(fn () => $service->preview(1, 1, 7), 'Outstanding fiscal sale blocks authoritative overwrite');
$db->table('eis_sales')->insert(['transaction_id' => 99, 'business_id' => 1, 'status' => 'submitted']);
$preview = $service->preview(1, 1, 7);
verify($preview->status === 'preview', 'Submitted final sale clears fiscal-stock blocker');
stockReject(fn () => $service->apply(2, $preview->id, 7), 'Tenant cannot apply another tenant snapshot');
$db->table('eis_settings')->where('id', 1)->update(['location_id' => 4]);
stockReject(fn () => $service->apply(1, $preview->id, 7), 'Moved terminal cannot reconcile old site snapshot');
$db->table('eis_settings')->where('id', 1)->update(['location_id' => 2]);
$db->table('eis_stock_reconciliations')->where('id', $preview->id)->update(['created_at' => now()->subMinutes(6)]);
stockReject(fn () => $service->apply(1, $preview->id, 7), 'Expired comparison cannot be applied');
$preview = $service->preview(1, 1, 7);
$client->inventory = ['EIS-ONE' => '15.0000'];
$preview = $service->preview(1, 1, 7);
$before = graph($db);
$db->statement("CREATE TRIGGER fail_eis_supply BEFORE INSERT ON purchase_lines BEGIN SELECT RAISE(ABORT, 'Synthetic supply failure'); END");
try {
    $service->apply(1, $preview->id, 7);
    throw new RuntimeException('Expected supply failure');
} catch (Illuminate\Database\QueryException $e) {
    verify(str_contains($e->getMessage(), 'Synthetic supply failure'), 'Injected ledger write failure is explicit');
}
verify(graph($db) === $before && $db->table('eis_stock_reconciliations')->where('id', $preview->id)->value('status') === 'preview',
    'Ledger write failure rolls back header, stock and applied audit status atomically');
$db->statement('DROP TRIGGER fail_eis_supply');
$service->apply(1, $preview->id, 7);
$latestSupply = $db->table('purchase_lines')->orderByDesc('id')->value('id');
$schema->table('business', function ($table) { $table->string('accounting_method')->default('fifo'); });
$db->table('business')->where('id', 1)->update(['accounting_method' => 'lifo']);
$client->inventory = ['EIS-ONE' => '14.0000'];
$preview = $service->preview(1, 1, 7);
$service->apply(1, $preview->id, 7);
verify(bccomp($db->table('purchase_lines')->where('id', $latestSupply)->value('quantity_adjusted'), '1', 4) === 0,
    'LIFO reconciliation decreases allocate the most recent purchase supply first');
$db->table('variation_location_details')->where('location_id', 2)->update(['qty_available' => '13.0000']);
$before = graph($db);
stockReject(fn () => $service->preview(1, 1, 7), 'Inconsistent local purchase allocations block authoritative stock reconciliation');
verify(graph($db) === $before, 'Rejected inconsistent ledger is never silently overwritten');
$db->table('variation_location_details')->where('location_id', 2)->update(['qty_available' => '14.0000']);
$db->table('stock_adjustment_lines')->insert(['id' => 90, 'transaction_id' => 999,
    'product_id' => $stockRow['product_id'], 'variation_id' => $stockRow['variation_id'], 'quantity' => '1.0000']);
stockReject(fn () => $service->preview(1, 1, 7), 'Orphaned stock movements block synchronization');
$db->table('stock_adjustment_lines')->where('id', 90)->delete();
$db->table('transaction_sell_lines')->insert(['id' => 90, 'transaction_id' => 99,
    'product_id' => $stockRow['product_id'], 'variation_id' => $stockRow['variation_id'], 'quantity' => '1.0000']);
$db->table('transaction_sell_lines_purchase_lines')->insert(['id' => 90, 'sell_line_id' => 90, 'purchase_line_id' => 0, 'quantity' => '1.0000']);
stockReject(fn () => $service->preview(1, 1, 7), 'Overselling sentinel cannot be disguised by authoritative stock increases');
$db->table('transaction_sell_lines_purchase_lines')->where('id', 90)->delete();
$db->table('transaction_sell_lines')->where('id', 90)->delete();
foreach (Modules\EIS\Services\Stock\StockOutboundService::UNSETTLED_STATUSES as $status) {
    foreach (['location_id', 'destination_location_id'] as $scope) {
        $op = $db->table('eis_stock_operations')->insertGetId([
            'business_id' => 1, 'location_id' => $scope === 'location_id' ? 2 : 4,
            'destination_location_id' => $scope === 'destination_location_id' ? 2 : null,
            'eis_setting_id' => 1, 'operation_type' => 'transfer', 'status' => $status,
            'site_id' => 'SITE-A', 'request_payload' => '{}',
        ]);
        stockReject(fn () => $service->preview(1, 1, 7), 'Unsettled '.$status.' stock operation blocks '.$scope.' reconciliation');
        $db->table('eis_stock_operations')->where('id', $op)->delete();
    }
}

$stockTool = $db->table('eis_stock_tool_requests')->insertGetId([
    'business_id' => 1, 'location_id' => 4, 'eis_setting_id' => 1, 'created_by' => 7,
    'operation' => 'submit-conversion', 'status' => 'unknown', 'request_key' => 'abc',
    'identity' => str_repeat('c', 64), 'endpoint' => 'https://eis.invalid/api/v1', 'tin' => 'TIN',
    'site_id' => 'SITE-A', 'device_id' => 'TEST', 'payload' => '{}',
]);
foreach (['sending', 'unknown', 'needs_review'] as $state) {
    $db->table('eis_stock_tool_requests')->where('id', $stockTool)->update(['status' => $state]);
    stockReject(fn () => $service->preview(1, 1, 7), 'Unresolved taxpayer stock tool '.$state.' blocks reconciliation even from another site');
}
foreach (['prepared', 'submitted', 'rejected', 'verified'] as $state) {
    $db->table('eis_stock_tool_requests')->where('id', $stockTool)->update(['status' => $state]);
    verify($service->preview(1, 1, 7)->status === 'preview', 'Unsent/settled taxpayer stock tool '.$state.' permits fresh stock reconciliation');
}
$db->table('eis_stock_tool_requests')->where('id', $stockTool)->update(['status' => 'unknown', 'tin' => 'OTHER-TAXPAYER']);
verify($service->preview(1, 1, 7)->status === 'preview', 'Other taxpayer stock tool does not block current reconciliation');
$db->table('eis_stock_tool_requests')->where('id', $stockTool)->delete();

$adjustment = $db->table('eis_sale_adjustments')->insertGetId([
    'business_id' => 1, 'location_id' => 2, 'eis_setting_id' => 1, 'sale_id' => 100, 'return_id' => 101,
    'created_by' => 7, 'endpoint' => 'https://eis.invalid/api/v1', 'tin' => 'TIN', 'site_id' => 'SITE-A',
    'device_id' => 'TEST', 'status' => 'prepared', 'note_type' => 'Credit', 'payload' => '{}', 'local_snapshot' => '{}',
]);
foreach (['prepared', 'sending', 'unknown', 'needs_review', 'rejected'] as $state) {
    $db->table('eis_sale_adjustments')->where('id', $adjustment)->update(['status' => $state]);
    stockReject(fn () => $service->preview(1, 1, 7), 'Unsettled '.$state.' return note blocks stock reconciliation');
}
$db->table('eis_sale_adjustments')->where('id', $adjustment)->update(['status' => 'accepted']);
verify($service->preview(1, 1, 7)->status === 'preview', 'Accepted fiscal return note permits reconciliation');
$db->table('eis_sale_adjustments')->where('id', $adjustment)->delete();

$upload = $db->table('eis_utility_requests')->insertGetId([
    'business_id' => 1, 'location_id' => 2, 'eis_setting_id' => 1, 'created_by' => 7,
    'operation' => 'taxpayer-initial-inventory-upload', 'status' => 'accepted',
    'endpoint' => 'https://eis.invalid/api/v1', 'tin' => 'TIN', 'site_id' => 'SITE-A',
    'device_id' => 'TEST', 'identity' => str_repeat('a', 64), 'payload' => '{"isLastBatch":false,"products":[]}',
]);
stockReject(fn () => $service->preview(1, 1, 7), 'Staged taxpayer inventory blocks site reconciliation until finalization');
foreach (['sending', 'unknown', 'needs_review'] as $state) {
    $db->table('eis_utility_requests')->where('id', $upload)->update(['status' => $state]);
    stockReject(fn () => $service->preview(1, 1, 7), 'Unresolved '.$state.' initial upload blocks reconciliation');
}
$db->table('eis_utility_requests')->where('id', $upload)->update(['status' => 'accepted', 'payload' => '{"isLastBatch":true,"products":[]}']);
verify($service->preview(1, 1, 7)->status === 'preview', 'Accepted final initial upload clears stock reconciliation staging blocker');
$otherUpload = (array) $db->table('eis_utility_requests')->find($upload);
unset($otherUpload['id']);
$otherUpload['tin'] = 'OTHER-TAXPAYER'; $otherUpload['identity'] = str_repeat('b', 64);
$db->table('eis_utility_requests')->insert($otherUpload);
$db->table('eis_utility_requests')->where('id', $upload)->update(['payload' => '{"isLastBatch":false,"products":[]}']);
stockReject(fn () => $service->preview(1, 1, 7), 'A final batch for a different taxpayer cannot clear current taxpayer staging');
$db->table('eis_utility_requests')->where('id', $upload)->update(['status' => 'unknown', 'endpoint' => 'https://other.invalid/api/v1']);
verify($service->preview(1, 1, 7)->status === 'preview', 'Another endpoint or taxpayer cannot block current taxpayer reconciliation');
$db->table('eis_utility_requests')->where('identity', str_repeat('b', 64))->delete();
$db->table('eis_utility_requests')->where('id', $upload)->delete();

$container['config']['eis.base_url'] = 'https://eis.invalid/api/v1';
$db->table('eis_settings')->where('id', 1)->update(['jwt_token' => 'fixture-token']);
$http = new Illuminate\Http\Client\Factory();
$http->preventStrayRequests();
Illuminate\Support\Facades\Http::swap($http);
Illuminate\Support\Facades\Http::fake(fn () => Illuminate\Support\Facades\Http::response([
    'statusCode' => 1, 'data' => [['productCode' => 'EIS-ONE', 'siteId' => 'SITE-A', 'quantity' => 12.5]],
]));
$actual = new StockInventoryClient();
$remote = $actual->fetch(\Modules\EIS\Models\EisSetting::findOrFail(1));
$request = Illuminate\Support\Facades\Http::recorded()->last()[0];
verify($request->url() === 'https://eis.invalid/api/v1/utilities/get-terminal-site-products'
    && $request->data() === ['tin' => 'TIN', 'siteId' => 'SITE-A'],
    'Inventory request matches official InventoryRequest exactly, without undocumented pagination');
verify($remote === ['EIS-ONE' => '12.5000'], 'Official numeric quantity parses as exact canonical local decimal');
Illuminate\Support\Facades\Http::swap(new Illuminate\Http\Client\Factory());
Illuminate\Support\Facades\Http::preventStrayRequests();
Illuminate\Support\Facades\Http::fake(fn () => Illuminate\Support\Facades\Http::response(['statusCode' => 0, 'data' => []]));
stockReject(fn () => $actual->fetch(\Modules\EIS\Models\EisSetting::findOrFail(1)), 'Unsuccessful inventory response is rejected');
foreach ([
    [['productCode' => 'EIS-ONE', 'siteId' => 'OTHER', 'quantity' => 1]],
    [['productCode' => 'EIS-ONE', 'quantity' => -1]],
    [['productCode' => 'EIS-ONE', 'quantity' => 1], ['productCode' => 'EIS-ONE', 'quantity' => 2]],
    [['productCode' => 'EIS-ONE', 'quantity' => '1.00001']],
] as $badItems) {
    Illuminate\Support\Facades\Http::swap(new Illuminate\Http\Client\Factory());
    Illuminate\Support\Facades\Http::preventStrayRequests();
    Illuminate\Support\Facades\Http::fake(fn () => Illuminate\Support\Facades\Http::response(['statusCode' => 1, 'data' => $badItems]));
    stockReject(fn () => $actual->fetch(\Modules\EIS\Models\EisSetting::findOrFail(1)), 'Invalid or foreign inventory blocks stock synchronization');
}
echo "PASS: {$checks} cumulative EIS stock reconciliation checks (SQLite and fake HTTP).\n";
