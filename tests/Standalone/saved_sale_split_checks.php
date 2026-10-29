<?php

/** Run directly with PHP. NEVER artisan/phpunit application bootstrap/.env.
 * Every database is a brand-new SQLite :memory: connection. No network/events.
 */
require dirname(__DIR__, 2).'/vendor/autoload.php';
if (!class_exists('Carbon', false)) { class_alias(Carbon\Carbon::class, 'Carbon'); }

use App\Services\Sales\SavedSaleSplitService;
use App\Services\Sales\SavedSaleWriteGuard;
use App\Services\Sales\SavedSplitException;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

final class SplitActor
{
    public $id = 7;
    public $business_id = 1;
    public $admin = false;
    public $permissions = ['sell.create', 'sell.update', 'location.2'];
    public function hasRole($name) { return $this->admin && $name === 'Admin#'.$this->business_id; }
    public function can($name) { return in_array($name, $this->permissions, true); }
    public function permitted_locations($businessId) { return $businessId === $this->business_id ? [2] : []; }
}

$checks = 0;
function check($condition, $message)
{
    global $checks;
    ++$checks;
    if (!$condition) { throw new RuntimeException('FAIL: '.$message); }
}
function rejects(callable $fn, $code)
{
    try { $fn(); } catch (SavedSplitException $e) {
        check($e->errorCode === $code, 'expected '.$code.', received '.$e->errorCode);
        return;
    }
    throw new RuntimeException('FAIL: expected rejection '.$code);
}
function fixture()
{
    $capsule = new Capsule();
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $capsule->setEventDispatcher(new Dispatcher(new Container()));
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    Illuminate\Database\Eloquent\Model::clearBootedModels(); // New dispatcher needs fresh model event hooks.
    $container = $capsule->getContainer();
    Container::setInstance($container);
    $container->instance('db', $capsule->getDatabaseManager());
    $container->instance('log', new Psr\Log\NullLogger());
    $container->instance('session', new Illuminate\Session\Store('synthetic', new Illuminate\Session\ArraySessionHandler(120)));
    $container['config']['constants.invoice_scheme_separator'] = '-';
    if (!class_exists('Log', false)) { class_alias(Illuminate\Support\Facades\Log::class, 'Log'); }
    Illuminate\Support\Facades\Facade::setFacadeApplication($container);
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    $db = $capsule->getConnection();
    check($db->getDriverName() === 'sqlite' && $db->getDatabaseName() === ':memory:', 'isolated database');
    $schema = $db->getSchemaBuilder();
    $schema->create('transactions', function (Blueprint $t) {
        $t->increments('id');
        foreach (['business_id', 'location_id', 'type', 'status', 'payment_status', 'contact_id',
            'created_by', 'transaction_date', 'invoice_no', 'invoice_token', 'total_before_tax', 'final_total',
            'tax_amount', 'exchange_rate', 'is_suspend', 'is_direct_sale', 'is_created_from_api',
            'return_parent_id', 'transfer_parent_id', 'recur_parent_id', 'mfg_parent_production_purchase_id',
            'additional_notes', 'staff_note', 'custom_field_1', 'essentials_duration', 'essentials_amount_per_unit_duration',
            'created_at', 'updated_at', 'is_quotation', 'sub_status', 'discount_amount', 'shipping_charges',
            'packing_charge', 'round_off_amount', 'rp_earned', 'rp_redeemed', 'rp_redeemed_amount',
            'journal_entry_id', 'woocommerce_order_id', 'sales_order_ids', 'is_recurring', 'tax_id',
            'additional_expense_value_1', 'prefer_payment_account', 'source', 'unknown_module_state', 'connector_till_id'] as $c) {
            $t->text($c)->nullable(); // TEXT prevents SQLite converting decimal strings to floats.
        }
    });
    $schema->create('transaction_sell_lines', function (Blueprint $t) {
        $t->increments('id');
        foreach (['transaction_id', 'product_id', 'variation_id', 'quantity', 'unit_price_before_discount',
            'unit_price', 'unit_price_inc_tax', 'item_tax', 'tax_id', 'sell_line_note', 'parent_sell_line_id',
            'quantity_returned', 'secondary_unit_quantity', 'sub_unit_id', 'so_line_id', 'discount_id',
            'line_discount_type', 'line_discount_amount', 'lot_no_line_id', 'woocommerce_line_items_id',
            'created_at', 'updated_at'] as $c) { $t->text($c)->nullable(); }
        $t->text('children_type')->default('');
    });
        foreach (['transaction_payments' => ['transaction_id', 'amount', 'parent_id', 'is_return', 'business_id', 'method',
            'card_transaction_number', 'card_number', 'card_type', 'card_holder_name', 'card_month', 'card_security',
            'cheque_number', 'bank_account_number', 'note', 'paid_on', 'created_by', 'payment_for', 'payment_ref_no', 'account_id', 'created_at', 'updated_at'],
        'transaction_sell_lines_purchase_lines' => ['sell_line_id', 'purchase_line_id', 'quantity', 'qty_returned'],
        'purchase_lines' => ['quantity_sold', 'transaction_id', 'product_id', 'variation_id', 'quantity', 'quantity_adjusted', 'quantity_returned', 'mfg_quantity_used'], 'account_transactions' => ['transaction_id', 'transfer_transaction_id'],
        'cash_register_transactions' => ['transaction_id'], 'eis_sales' => ['business_id', 'transaction_id', 'status', 'fiscal_invoice_number', 'request_payload'],
        'eis_failed_transactions' => ['transaction_id'], 'sell_line_warranties' => ['sell_line_id', 'warranty_id'],
        'business' => ['transaction_edit_days', 'pos_settings', 'enable_rp', 'enable_product_expiry', 'enable_inline_tax', 'accounting_method', 'ref_no_prefixes'],
        'business_locations' => ['business_id', 'default_payment_accounts', 'invoice_scheme_id', 'default_eis_setting_id'], 'users' => ['business_id'],
        'contacts' => ['business_id', 'type', 'balance', 'deleted_at'],
        'products' => ['business_id', 'type', 'unit_id', 'enable_stock', 'deleted_at', 'tax', 'not_for_selling', 'is_inactive'],
        'product_locations' => ['product_id', 'location_id'],
        'variations' => ['product_id', 'product_variation_id', 'deleted_at', 'default_sell_price'],
        'units' => ['business_id', 'allow_decimal'], 'tax_rates' => ['business_id', 'is_tax_group'],
        'reference_counts' => ['business_id', 'ref_type', 'ref_count'],
        'invoice_schemes' => ['business_id', 'is_default', 'scheme_type', 'prefix', 'number_type', 'start_number', 'invoice_count', 'total_digits'],
        'variation_location_details' => ['qty_available', 'product_id', 'product_variation_id', 'variation_id', 'location_id', 'created_at', 'updated_at'],
        'eis_settings' => ['business_id', 'tpin', 'location_id', 'status'],
        'eis_configurations' => ['business_id', 'location_id', 'eis_setting_id'], 'eis_terminal_configurations' => ['configuration_id', 'taxpayer_id', 'terminal_position', 'max_cummulative_amount', 'max_transaction_age', 'is_active'],
        'eis_offline_limits' => ['terminal_configuration_id', 'max_cumulative_amount', 'max_transaction_age_hours']] as $table => $columns) {
        $schema->create($table, function (Blueprint $t) use ($columns) {
            $t->increments('id'); foreach (array_unique(array_merge($columns, ['created_at', 'updated_at'])) as $c) { $t->text($c)->nullable(); }
            if ($t->getTable() === 'tax_rates') { $t->float('amount')->nullable(); }
        });
    }
    $schema->create('saved_sale_split_operations', function (Blueprint $t) {
        $t->increments('id');
        foreach (['business_id', 'user_id', 'operation_id', 'source_id', 'result_id', 'payload_hash',
            'expected_version', 'allocation_json', 'response_json', 'created_at', 'updated_at'] as $c) { $t->text($c)->nullable(); }
        $t->unique(['business_id', 'operation_id']);
    });
    $db->table('business')->insert(['id' => 1, 'transaction_edit_days' => '30', 'enable_inline_tax' => 1, 'accounting_method' => 'fifo']);
    $db->table('invoice_schemes')->insert(['business_id' => 1, 'is_default' => 1, 'scheme_type' => 'blank',
        'prefix' => 'INV-', 'number_type' => 'sequential', 'start_number' => 1, 'invoice_count' => 0, 'total_digits' => 5]);
    $db->table('eis_settings')->insert(['business_id' => 1, 'tpin' => 'TEST', 'location_id' => 2, 'status' => 1]);
    $db->table('eis_configurations')->insert(['id' => 1, 'business_id' => 1, 'location_id' => 2, 'eis_setting_id' => 1]);
    $db->table('eis_terminal_configurations')->insert(['configuration_id' => 1, 'taxpayer_id' => 98765, 'terminal_position' => 4, 'is_active' => 1]);
    $db->table('business_locations')->insert(['id' => 2, 'business_id' => 1, 'default_payment_accounts' => '{"cash":{"is_enabled":1,"account":null}}', 'default_eis_setting_id' => 1]);
    $db->table('users')->insert([['id' => 7, 'business_id' => 1], ['id' => 8, 'business_id' => 1]]);
    $db->table('contacts')->insert(['id' => 3, 'business_id' => 1, 'type' => 'customer']);
    $db->table('units')->insert(['id' => 1, 'business_id' => 1, 'allow_decimal' => 1]);
    $db->table('products')->insert(['id' => 4, 'business_id' => 1, 'type' => 'single', 'unit_id' => 1, 'enable_stock' => '0']);
    $db->table('variations')->insert(['id' => 5, 'product_id' => 4, 'default_sell_price' => '10.0000']);
    $db->table('product_locations')->insert(['product_id' => 4, 'location_id' => 2]);
    $db->table('tax_rates')->insert(['id' => 6, 'business_id' => 1, 'amount' => '20.0000']);
    $db->table('purchase_lines')->insert(['id' => 1, 'quantity_sold' => '123.4567']);
    $db->table('variation_location_details')->insert(['id' => 1, 'qty_available' => '987.6543']);
    $db->table('transactions')->insert(['id' => 1, 'business_id' => 1, 'location_id' => 2, 'type' => 'sell',
        'status' => 'draft', 'payment_status' => 'due', 'created_by' => 7, 'contact_id' => 3,
        'transaction_date' => date('Y-m-d H:i:s'), 'invoice_no' => 'DRAFT-001', 'invoice_token' => 'original-token',
        'total_before_tax' => '70.0000', 'final_total' => '70.0000', 'tax_amount' => '0.0000', 'exchange_rate' => '1.000',
        'is_suspend' => '1', 'is_direct_sale' => '0', 'is_created_from_api' => '1', 'additional_notes' => 'historic note',
        'custom_field_1' => 'safe metadata', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
    foreach ([1 => ['4.0000', '10.0000', '2.0000', '12.0000'], 2 => ['2.0000', '11.0000', '0.0000', '11.0000']] as $id => $v) {
        $db->table('transaction_sell_lines')->insert(['id' => $id, 'transaction_id' => 1, 'product_id' => 4,
            'variation_id' => 5, 'quantity' => $v[0], 'unit_price_before_discount' => $v[1], 'unit_price' => $v[1],
            'item_tax' => $v[2], 'unit_price_inc_tax' => $v[3], 'tax_id' => $id === 1 ? 6 : null,
            'sell_line_note' => 'saved line '.$id, 'children_type' => '', 'created_at' => '2026-01-01 00:00:00']);
    }
    return [$db, new SavedSaleSplitService($db), new SplitActor()];
}
function requestFor($service, $actor, $lines = [['id' => 1, 'quantity' => '1.5000']], $op = null)
{
    return ['operation_id' => $op ?? (string) Illuminate\Support\Str::uuid(),
        'expected_version' => $service->preview(1, $actor)['expected_version'], 'lines' => $lines];
}
function snapshot($db)
{
    $out = [];
    foreach ($db->getSchemaBuilder()->getAllTables() as $row) {
        $name = $row->name;
        if ($name !== 'sqlite_sequence') { $out[$name] = $db->table($name)->orderBy('id')->get()->toJson(); }
    }
    return $out;
}

foreach (['draft', 'final'] as $status) {
    [$db, $service, $actor] = fixture();
    $db->table('transactions')->where('id', 1)->update(['status' => $status, 'source' => 'desktop_pos']);
    $before = snapshot($db);
    $preview = $service->preview(1, $actor);
    check($preview['eligible'], 'desktop origin eligible for '.$status);
    check(snapshot($db) === $before, 'desktop origin preview read-only');
    $request = requestFor($service, $actor);
    $response = $service->split(1, $actor, $request);
    check($response['bill_b']['invoice_no'] === ($status === 'final' ? 'INV-00001' : date('Y').'/0001'),
        'normal system number for '.$status.' split');
    $allocation = json_decode($db->table('saved_sale_split_operations')->where('result_id', $response['result_id'])
        ->value('allocation_json'), true);
    check(!array_key_exists('eis_invoice_number', $allocation) && !array_key_exists('invoice_number_rule', $allocation),
        'split does not generate or reserve an EIS number for '.$status);
    foreach ([1, $response['result_id']] as $id) {
        check($db->table('transactions')->where('id', $id)->value('source') === 'desktop_pos', 'desktop provenance preserved on both bills');
    }
    $after = snapshot($db);
    check($service->split(1, $actor, $request) === $response && snapshot($db) === $after, 'desktop origin durable retry');
}
foreach (['woocommerce', 'external_integration', 'Desktop_Pos'] as $source) {
    [$db, $service, $actor] = fixture();
    $request = requestFor($service, $actor);
    $db->table('transactions')->where('id', 1)->update(['source' => $source]);
    $before = snapshot($db);
    $preview = $service->preview(1, $actor);
    check(!$preview['eligible'] && $preview['reason']['code'] === 'unsupported_header', 'unknown/external origin remains blocked');
    rejects(function () use ($service, $actor, $request) { $service->split(1, $actor, $request); }, 'stale_split_version');
    $request['expected_version'] = $preview['expected_version'];
    rejects(function () use ($service, $actor, $request) { $service->split(1, $actor, $request); }, 'unsupported_header');
    check(snapshot($db) === $before, 'rejected external source leaves graph unchanged');
}

[$db, $service, $actor] = fixture();
$before = snapshot($db);
$preview = $service->preview(1, $actor);
check($preview['eligible'] && $preview['supported_statuses'] === ['draft', 'final'], 'ordinary unpaid draft eligible');
check(snapshot($db) === $before, 'preview read-only');
check($preview['source']['lines'][0]['quantity'] === '4.0000', 'saved quantities are decimal strings');
$request = requestFor($service, $actor, [['id' => 1, 'quantity' => '1.5000'], ['id' => 2, 'quantity' => '2.0000']]);
$response = $service->split(1, $actor, $request);
check($response['bill_a']['final_total'] === '30.0000' && $response['bill_b']['final_total'] === '40.0000', 'historic totals partition');
check($response['result_id'] !== 1 && $response['bill_a']['invoice_no'] !== $response['bill_b']['invoice_no']
    && $db->table('transactions')->count() === 2, 'split portion is a separate invoice transaction');
check($response['bill_a']['invoice_no'] === 'DRAFT-001'
    && $response['bill_b']['invoice_no'] === date('Y').'/0001', 'draft uses normal system transaction number');
check($db->table('transactions')->where('id', $response['result_id'])->value('transaction_date')
    === $db->table('transactions')->where('id', 1)->value('transaction_date'), 'source sale date preserved');
check($db->table('transactions')->where('id', $response['result_id'])->value('invoice_token') !== 'original-token', 'fresh token');
check($db->table('transactions')->where('id', $response['result_id'])->value('created_by') === '7', 'creator preserved');
check($db->table('transaction_sell_lines')->where('id', 2)->value('transaction_id') == $response['result_id'], 'whole line keeps id');
check($db->table('transaction_sell_lines')->where('id', 1)->value('quantity') === '2.5000', 'partial remainder');
check(count($response['line_allocations']) === 2 && $response['line_allocations'][0]['bill_b_line_id'] !== 1, 'partial line fresh id');
foreach (['purchase_lines', 'variation_location_details', 'transaction_payments', 'account_transactions',
    'cash_register_transactions', 'eis_sales', 'eis_failed_transactions', 'transaction_sell_lines_purchase_lines'] as $table) {
    check(snapshot($db)[$table] === $before[$table], 'no mutation '.$table);
}
$after = snapshot($db);
check($service->split(1, $actor, $request) === $response && snapshot($db) === $after, 'durable exact retry no writes');
$reordered = $request; $reordered['lines'] = array_reverse($request['lines']);
check($service->split(1, $actor, $reordered) === $response, 'canonical payload ignores input ordering');
$collision = $request; $collision['lines'][0]['quantity'] = '1.0000';
rejects(function () use ($service, $actor, $collision) { $service->split(1, $actor, $collision); }, 'operation_collision');
$actor->admin = true; $actor->id = 8;
rejects(function () use ($service, $actor, $request) { $service->split(1, $actor, $request); }, 'operation_collision');
$actor->id = 7; $actor->admin = false;
check(SavedSaleWriteGuard::hasSplitHistory($db, 1), 'source retains split history');
check(!SavedSaleWriteGuard::hasSplitHistory($db, $response['result_id']), 'new invoice has normal sale history semantics');
rejects(function () use ($db) {
    $db->transaction(function () use ($db) { SavedSaleWriteGuard::lock($db, 1); });
}, 'split_write_fenced');
$db->beginTransaction();
try {
    $db->transaction(function () use ($db, $response) {
        SavedSaleWriteGuard::lock($db, $response['result_id']);
        $sale = App\Transaction::find($response['result_id']);
        $sale->status = 'final';
        $sale->payment_status = 'paid';
        $sale->save();
        App\TransactionPayment::create(['transaction_id' => $response['result_id'], 'amount' => '1.0000']);
        SavedSaleWriteGuard::fiscal($db, $response['result_id']);
    });
} finally {
    $db->rollBack();
}
rejects(function () use ($db) {
    $db->transaction(function () { $m = App\TransactionSellLine::find(1); $m->quantity = '99'; $m->save(); });
}, 'split_write_fenced');
check(snapshot($db) === $after, 'independent bill writes rollback cleanly in test');
$childPreview = $service->preview($response['result_id'], $actor);
$childLineId = $response['line_allocations'][0]['bill_b_line_id'];
$resplit = $service->split($response['result_id'], $actor, [
    'operation_id' => (string) Illuminate\Support\Str::uuid(),
    'expected_version' => $childPreview['expected_version'],
    'lines' => [['id' => $childLineId, 'quantity' => '0.5000']],
]);
check(SavedSaleWriteGuard::hasSplitHistory($db, $response['result_id']), 're-split source is protected again');
rejects(function () use ($db, $response) {
    $db->transaction(function () use ($db, $response) { SavedSaleWriteGuard::lock($db, $response['result_id']); });
}, 'split_write_fenced');
check(!SavedSaleWriteGuard::hasSplitHistory($db, $resplit['result_id']), 're-split result is another independent invoice');

[$db, $service, $actor] = fixture();
$staleHeader = App\Transaction::find(1); $staleLine = App\TransactionSellLine::find(1);
$r = $service->split(1, $actor, requestFor($service, $actor));
rejects(function () use ($db, $staleHeader) {
    $db->transaction(function () use ($staleHeader) { $staleHeader->status = 'final'; $staleHeader->save(); });
}, 'split_write_fenced');
rejects(function () use ($db, $staleLine) {
    $db->transaction(function () use ($staleLine) { $staleLine->quantity = '4'; $staleLine->save(); });
}, 'split_write_fenced');
rejects(function () use ($db, $staleHeader) {
    $db->transaction(function () use ($staleHeader) { $staleHeader->delete(); });
}, 'split_write_fenced');
rejects(function () use ($db) {
    $db->transaction(function () { Modules\EIS\Models\EisSale::create(['transaction_id' => 1, 'business_id' => 1, 'status' => 'pending']); });
}, 'split_write_fenced');
check($db->table('eis_sales')->count() === 0, 'split fiscal fence before tracking write');

// Execute the REAL common helpers: every call must stop at its shared fence,
// before repricing, stock, reference counters, payments, accounting or events.
$util = new App\Utils\TransactionUtil();
foreach ([
    function () use ($util) { $util->updateSellTransaction(1, 1, [], [], 7); },
    function () use ($util) { $util->createOrUpdateSellLines(1, [], 2); },
    function () use ($util) { $util->editSellLine(['transaction_sell_lines_id' => 1], 2, 'draft'); },
    function () use ($util) { $util->deleteSellLines([1], 2); },
    function () use ($util) { $util->createOrUpdatePaymentLines(1, []); },
    function () use ($util) { $util->updatePaymentStatus(1); },
    function () use ($util) { $util->updateSellTransactionTotals(1); },
    function () use ($util) { $util->deleteSale(1, 1); },
    function () use ($util) { $util->addSellReturn(['transaction_id' => 1], 1, 7); },
    function () use ($util) { $util->adjustMappingPurchaseSell('draft', (object) ['id' => 1], []); },
] as $call) {
    rejects(function () use ($db, $call) { $db->transaction($call); }, 'split_write_fenced');
}
check($db->table('transaction_payments')->count() === 0 && $db->table('eis_sales')->count() === 0, 'helpers never reach postings/events');

// Thin controller envelope/status tests, without an HTTP server or kernel.
$factory = Mockery::mock(Illuminate\Contracts\Routing\ResponseFactory::class);
$factory->shouldReceive('json')->andReturnUsing(function ($data, $status = 200) {
    return new Illuminate\Http\JsonResponse($data, $status);
});
Container::getInstance()->instance(Illuminate\Contracts\Routing\ResponseFactory::class, $factory);
$controller = new Modules\Connector\Http\Controllers\Api\SavedSaleSplitController();
$http = Illuminate\Http\Request::create('/connector/api/sell/1/split-preview', 'GET');
$http->setUserResolver(function () use ($actor) { return $actor; });
$json = $controller->preview($http, 1);
check($json->getStatusCode() === 200 && $json->getData(true)['data']['eligible'], 'controller preview data envelope');
$http = Illuminate\Http\Request::create('/connector/api/sell/1/split', 'POST', ['operation_id' => 'bad']);
$http->setUserResolver(function () use ($actor) { return $actor; });
$json = $controller->split($http, 1);
check($json->getStatusCode() === 422 && $json->getData(true)['error']['code'] === 'invalid_split_request', 'controller error envelope');
$json = $controller->preview($http, 999);
check($json->getStatusCode() === 404 && $json->getData(true)['error']['code'] === 'split_not_found', 'controller not-found status');
Mockery::close();

[$db, $service, $actor] = fixture();
rejects(function () use ($db) {
    $db->transaction(function () { Modules\EIS\Models\EisSale::create(['transaction_id' => 1, 'business_id' => 1, 'status' => 'pending']); });
}, 'draft_fiscal_state_blocked');
check($db->table('eis_sales')->count() === 0, 'ordinary draft fiscal fence before MyISAM-equivalent write');

// Engine verifier branch exercised over a synthetic metadata database attached
// IN MEMORY. This does NOT certify real MySQL locks, isolation or SQL dialect.
[$db, $service, $actor] = fixture();
$tableNames = array_map(function ($r) { return $r->name; }, $db->getSchemaBuilder()->getAllTables());
$db->statement("ATTACH DATABASE ':memory:' AS information_schema");
$db->statement('CREATE TABLE information_schema.TABLES (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, ENGINE TEXT)');
foreach ($tableNames as $name) {
    $db->table('information_schema.TABLES')->insert(['TABLE_SCHEMA' => 'synthetic', 'TABLE_NAME' => $name, 'ENGINE' => 'InnoDB']);
}
$metadataDb = new class($db->getPdo(), 'synthetic', '', []) extends Illuminate\Database\SQLiteConnection {
    public function getDriverName() { return 'mysql'; }
};
$engineService = new SavedSaleSplitService($metadataDb);
check($engineService->preview(1, $actor)['eligible'], 'all InnoDB metadata accepted');
foreach (['transactions', 'transaction_sell_lines', 'transaction_sell_lines_purchase_lines',
    'purchase_lines', 'transaction_payments', 'saved_sale_split_operations'] as $table) {
    $db->table('information_schema.TABLES')->where('TABLE_NAME', $table)->update(['ENGINE' => 'MyISAM']);
    rejects(function () use ($engineService, $actor) { $engineService->preview(1, $actor); }, 'split_storage_unavailable');
    $db->table('information_schema.TABLES')->where('TABLE_NAME', $table)->update(['ENGINE' => 'InnoDB']);
}
$db->table('information_schema.TABLES')->where('TABLE_NAME', 'eis_sales')->update(['ENGINE' => 'MyISAM']);
check($engineService->preview(1, $actor)['eligible'], 'empty MyISAM fiscal table only read, never part of core writes');

// Fresh synthetic fixtures for each authorization/eligibility blocker.
foreach (['sell.create', 'sell.update', 'location.2'] as $permission) {
    [$db, $service, $actor] = fixture(); $actor->permissions = array_values(array_diff($actor->permissions, [$permission]));
    rejects(function () use ($service, $actor) { $service->preview(1, $actor); }, 'split_forbidden');
}
[$db, $service, $actor] = fixture(); $actor->id = 8;
rejects(function () use ($service, $actor) { $service->preview(1, $actor); }, 'split_forbidden');
$actor->admin = true; $r = $service->split(1, $actor, requestFor($service, $actor));
check($db->table('transactions')->where('id', $r['result_id'])->value('created_by') === '7', 'admin preserves original creator');
[$db, $service, $actor] = fixture(); $actor->business_id = 9;
rejects(function () use ($service, $actor) { $service->preview(1, $actor); }, 'split_not_found');

$blockers = [
    ['transactions', 'status', 'ordered', 'unsupported_sale_status'],
    ['transactions', 'payment_status', 'paid', 'sale_not_completely_unpaid'],
    ['transactions', 'payment_status', 'partial', 'sale_not_completely_unpaid'],
    ['transactions', 'final_total', '71.0000', 'unexplained_totals'],
    ['transactions', 'exchange_rate', '2.000', 'unsupported_header'],
    ['transactions', 'transaction_date', '2000-01-01', 'sale_edit_expired'],
    ['contacts', 'business_id', '2', 'unsupported_sale_graph'],
    ['products', 'business_id', '2', 'unsupported_sale_graph'],
    ['products', 'type', 'combo', 'unsupported_sale_graph'],
    ['variations', 'product_id', '99', 'unsupported_sale_graph'],
    ['tax_rates', 'business_id', '99', 'unsupported_sale_graph'],
];
foreach (['discount_amount', 'shipping_charges', 'packing_charge', 'round_off_amount', 'rp_earned',
    'rp_redeemed', 'rp_redeemed_amount', 'journal_entry_id', 'woocommerce_order_id', 'sales_order_ids',
    'is_recurring', 'tax_id', 'tax_amount', 'additional_expense_value_1', 'prefer_payment_account',
    'source', 'unknown_module_state', 'is_quotation', 'sub_status'] as $field) {
    $blockers[] = ['transactions', $field, '1', 'unsupported_header'];
}
foreach (['parent_sell_line_id', 'quantity_returned', 'secondary_unit_quantity', 'sub_unit_id', 'so_line_id',
    'line_discount_amount', 'discount_id', 'lot_no_line_id', 'woocommerce_line_items_id', 'children_type'] as $field) {
    $blockers[] = ['transaction_sell_lines', $field, '1', 'unsupported_line'];
}
$blockers[] = ['transaction_sell_lines', 'unit_price_before_discount', '9.0000', 'unexplained_line_pricing'];
foreach ($blockers as [$table, $field, $value, $code]) {
    [$db, $service, $actor] = fixture();
    $db->table($table)->where('id', $table === 'contacts' ? 3 : ($table === 'products' ? 4 : ($table === 'variations' ? 5 : ($table === 'tax_rates' ? 6 : 1))))->update([$field => $value]);
    if ($field === 'parent_sell_line_id') {
        $db->table($table)->where('id', 1)->update([$field => '99']); // Avoid also making this line its own child.
    }
    $p = $service->preview(1, $actor);
    check(!$p['eligible'] && $p['reason']['code'] === $code, 'preview blocker '.$table.'.'.$field);
    $before = snapshot($db);
    rejects(function () use ($service, $actor) { $service->split(1, $actor, requestFor($service, $actor)); }, $code);
    check(snapshot($db) === $before, 'rejection no writes '.$field);
}
foreach (['transaction_payments', 'account_transactions', 'cash_register_transactions', 'eis_sales',
    'eis_failed_transactions', 'sell_line_warranties', 'transaction_sell_lines_purchase_lines'] as $table) {
    [$db, $service, $actor] = fixture();
    $row = in_array($table, ['sell_line_warranties', 'transaction_sell_lines_purchase_lines'], true)
        ? ['sell_line_id' => 1] : ['transaction_id' => 1];
    if ($table === 'transaction_payments') { $row += ['amount' => '0.0000', 'is_return' => '1']; }
    if ($table === 'transaction_sell_lines_purchase_lines') { $row += ['purchase_line_id' => 1, 'quantity' => '4.0000', 'qty_returned' => '0.0000']; }
    $db->table($table)->insert($row);
    $p = $service->preview(1, $actor); check(!$p['eligible'], 'related blocker '.$table);
    $before = snapshot($db);
    rejects(function () use ($service, $actor) { $service->split(1, $actor, requestFor($service, $actor)); }, $p['reason']['code']);
    check(snapshot($db) === $before, 'related rejection no writes '.$table);
}
[$db, $service, $actor] = fixture();
$db->table('transactions')->insert(['id' => 2, 'return_parent_id' => 1]);
check(!$service->preview(1, $actor)['eligible'], 'linked return rejected');
[$db, $service, $actor] = fixture();
$db->table('transaction_sell_lines')->insert(['id' => 3, 'transaction_id' => 99, 'parent_sell_line_id' => 1]);
check(!$service->preview(1, $actor)['eligible'], 'cross-transaction child rejected');

foreach (['1e0', 'NaN', '-1', '0', '1.00001', 1.0, 1, ' 1', '9999999999999999999'] as $quantity) {
    [$db, $service, $actor] = fixture(); $req = requestFor($service, $actor);
    $req['lines'][0]['quantity'] = $quantity; $before = snapshot($db);
    try { $service->split(1, $actor, $req); throw new RuntimeException('accepted invalid quantity'); }
    catch (SavedSplitException $e) { check($e->httpStatus === 422, 'invalid decimal rejected'); }
    check(snapshot($db) === $before, 'invalid quantity no writes');
}
foreach ([
    [[['id' => 1, 'quantity' => '5']], 'invalid_split_quantity'],
    [[['id' => 99, 'quantity' => '1']], 'invalid_split_partition'],
    [[['id' => 1, 'quantity' => '4'], ['id' => 2, 'quantity' => '2']], 'invalid_split_partition'],
    [[['id' => 1, 'quantity' => '1'], ['id' => 1, 'quantity' => '1']], 'invalid_split_request'],
] as [$lines, $code]) {
    [$db, $service, $actor] = fixture(); $req = requestFor($service, $actor, $lines);
    rejects(function () use ($service, $actor, $req) { $service->split(1, $actor, $req); }, $code);
}
[$db, $service, $actor] = fixture(); $req = requestFor($service, $actor); $req['prices'] = 'forbidden';
rejects(function () use ($service, $actor, $req) { $service->split(1, $actor, $req); }, 'invalid_split_request');
[$db, $service, $actor] = fixture(); $req = requestFor($service, $actor); $req['operation_id'] = 'not-uuid';
rejects(function () use ($service, $actor, $req) { $service->split(1, $actor, $req); }, 'invalid_split_request');
[$db, $service, $actor] = fixture(); $req = requestFor($service, $actor);
$db->table('transaction_sell_lines')->where('id', 1)->update(['sell_line_note' => 'concurrent edit']);
rejects(function () use ($service, $actor, $req) { $service->split(1, $actor, $req); }, 'stale_split_version');
[$db, $service, $actor] = fixture(); $req = requestFor($service, $actor);
$db->table('transaction_payments')->insert(['transaction_id' => 1, 'amount' => '0']);
rejects(function () use ($service, $actor, $req) { $service->split(1, $actor, $req); }, 'stale_split_version');
[$db, $service, $actor] = fixture(); $db->table('units')->update(['allow_decimal' => '0']); $req = requestFor($service, $actor);
rejects(function () use ($service, $actor, $req) { $service->split(1, $actor, $req); }, 'invalid_unit_quantity');
[$db, $service, $actor] = fixture(); $db->getSchemaBuilder()->drop('saved_sale_split_operations');
rejects(function () use ($service, $actor) { $service->preview(1, $actor); }, 'split_storage_unavailable');
[$db, $service, $actor] = fixture(); $db->setDatabaseName('not-memory');
rejects(function () use ($service, $actor) { $service->preview(1, $actor); }, 'split_storage_unavailable');

// Inject failures AFTER operation/header/line insertion using only SQLite triggers.
foreach (['transactions', 'transaction_sell_lines', 'saved_sale_split_operations'] as $table) {
    [$db, $service, $actor] = fixture(); $req = requestFor($service, $actor); $before = snapshot($db);
    $db->statement("CREATE TRIGGER fail_write BEFORE UPDATE ON $table BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
    try { $service->split(1, $actor, $req); throw new RuntimeException('missing injected failure'); }
    catch (Illuminate\Database\QueryException $e) { check(str_contains($e->getMessage(), 'synthetic failure'), 'injected late failure'); }
    check(snapshot($db) === $before, 'atomic rollback '.$table);
}
[$db, $service, $actor] = fixture();
rejects(function () use ($db) { SavedSaleWriteGuard::lock($db, 1); }, 'draft_write_requires_transaction');
$db->transaction(function () use ($db) { check(SavedSaleWriteGuard::lock($db, 1)->status === 'draft', 'ordinary draft lock within transaction'); });

// Fractional partitions with exact historical pricing; deterministic pseudo-random
// quantities, not catalog repricing. Fresh fixture for every persisted split.
mt_srand(7102026);
for ($i = 0; $i < 100; ++$i) {
    [$db, $service, $actor] = fixture();
    $q = bcdiv((string) mt_rand(1, 39999), '10000', 4);
    $r = $service->split(1, $actor, requestFor($service, $actor, [['id' => 1, 'quantity' => $q]]));
    check(bcadd($r['bill_a']['final_total'], $r['bill_b']['final_total'], 4) === '70.0000', 'random amount conservation');
    $a = $db->table('transaction_sell_lines')->where('id', 1)->value('quantity');
    check(bcadd($a, $q, 4) === '4.0000', 'random quantity conservation');
    check($r['bill_b']['lines'][0]['unit_price_inc_tax'] === '12.0000', 'historic inline tax price unchanged');
    check($db->table('purchase_lines')->value('quantity_sold') === '123.4567', 'purchase consumption unchanged');
}

// A rounding-only penny cannot be silently invented as a header adjustment.
[$db, $service, $actor] = fixture();
$db->table('transaction_sell_lines')->where('id', 2)->delete();
$db->table('transaction_sell_lines')->where('id', 1)->update(['quantity' => '1.0000',
    'unit_price' => '0.0001', 'unit_price_before_discount' => '0.0001', 'item_tax' => '0.0000', 'unit_price_inc_tax' => '0.0001']);
$db->table('transactions')->where('id', 1)->update(['total_before_tax' => '0.0001', 'final_total' => '0.0001']);
$req = requestFor($service, $actor, [['id' => 1, 'quantity' => '0.5000']]);
rejects(function () use ($service, $actor, $req) { $service->split(1, $actor, $req); }, 'split_rounding_not_conserved');

// Exercise the supplied migration itself, ONLY on the synthetic :memory: schema.
[$db, $service, $actor] = fixture();
check($db->getDatabaseName() === ':memory:' && $db->getDriverName() === 'sqlite', 'migration isolated-memory guard');
$db->getSchemaBuilder()->drop('saved_sale_split_operations');
Container::getInstance()->instance('db.schema', $db->getSchemaBuilder());
Illuminate\Support\Facades\Schema::clearResolvedInstance('db.schema');
$migration = require dirname(__DIR__, 2).'/database/migrations/2026_10_06_000001_create_saved_sale_split_operations_table.php';
$migration->up();
$req = requestFor($service, $actor);
$r = $service->split(1, $actor, $req);
check($service->split(1, $actor, $req) === $r, 'actual migration schema supports durable replay');
check($db->table('saved_sale_split_operations')->count() === 1, 'actual migration ledger persisted once');
try {
    $db->table('saved_sale_split_operations')->insert([
        'business_id' => 1, 'user_id' => 8, 'operation_id' => $req['operation_id'], 'source_id' => 1,
        'payload_hash' => str_repeat('a', 64), 'expected_version' => str_repeat('b', 64), 'allocation_json' => '{}']);
    throw new RuntimeException('missing migration uniqueness');
} catch (Illuminate\Database\QueryException $e) {
    check($e->getCode() === '23000', 'migration tenant UUID unique constraint');
}
$migration->down();
check(!$db->getSchemaBuilder()->hasTable('saved_sale_split_operations'), 'migration down isolated schema only');

// Verify route registration and auth metadata without dispatching/booting a kernel.
$container = Container::getInstance();
$router = new Illuminate\Routing\Router(new Dispatcher($container), $container);
$container->instance('router', $router);
Illuminate\Support\Facades\Route::clearResolvedInstance('router');
require dirname(__DIR__, 2).'/Modules/Connector/Routes/api.php';
$splitRoutes = [];
foreach ($router->getRoutes() as $route) {
    if (in_array($route->uri(), ['connector/api/sell/{id}/split-preview', 'connector/api/sell/{id}/split'], true)) {
        $splitRoutes[] = $route;
        check(in_array('auth:api', $route->middleware(), true) && in_array('timezone', $route->middleware(), true), 'split route authentication/timezone');
    }
}
check(count($splitRoutes) === 2 && in_array('GET', $splitRoutes[0]->methods(), true)
    && $splitRoutes[1]->methods() === ['POST'], 'exact preview/split endpoint methods');
$additionRoutes = [];
foreach ($router->getRoutes() as $route) {
    if (in_array($route->uri(), ['connector/api/sell/{id}/add-items-preview', 'connector/api/sell/{id}/add-items'], true)) {
        $additionRoutes[] = $route;
        check(in_array('auth:api', $route->middleware(), true) && in_array('timezone', $route->middleware(), true),
            'add-items routes retain authentication and business timezone');
    }
}
check(count($additionRoutes) === 2 && in_array('GET', $additionRoutes[0]->methods(), true)
    && $additionRoutes[1]->methods() === ['POST'], 'exact preview/add-items endpoint methods');
$editRoutes = array_values(array_filter($router->getRoutes()->getRoutes(),
    fn ($route) => $route->uri() === 'connector/api/sell/{id}/edit-items'));
check(count($editRoutes) === 1 && $editRoutes[0]->methods() === ['POST'] &&
    str_ends_with($editRoutes[0]->getActionName(), '@editItems'), 'explicit editor endpoint registered to correct writer');
check(in_array('auth:api', $editRoutes[0]->middleware(), true) &&
    in_array('timezone', $editRoutes[0]->middleware(), true), 'editor endpoint retains authentication and timezone');

// FINAL splitting: preserve purchase consumption/stock while distributing each
// historical mapping, including one selection spanning multiple purchase rows.
function allocatedFixture($final = true)
{
    [$db, $service, $actor] = fixture();
    $db->table('products')->update(['enable_stock' => '1']);
    $db->table('variation_location_details')->update(['product_id' => 4, 'variation_id' => 5, 'location_id' => 2, 'qty_available' => '20.0000']);
    $db->table('transactions')->insert(['id' => 100, 'business_id' => 1, 'location_id' => 2, 'type' => 'purchase',
        'status' => 'received', 'transaction_date' => '2026-01-01 00:00:00', 'invoice_no' => 'P100']);
    $db->table('purchase_lines')->where('id', 1)->update(['transaction_id' => 100, 'product_id' => 4, 'variation_id' => 5,
        'quantity' => '30.0000', 'quantity_sold' => $final ? '10.0000' : '0.0000', 'quantity_adjusted' => '0.0000', 'quantity_returned' => '0.0000', 'mfg_quantity_used' => '0.0000']);
    $db->table('purchase_lines')->insert(['id' => 2, 'transaction_id' => 100, 'product_id' => 4, 'variation_id' => 5,
        'quantity' => '30.0000', 'quantity_sold' => $final ? '20.0000' : '0.0000', 'quantity_adjusted' => '0.0000', 'quantity_returned' => '0.0000', 'mfg_quantity_used' => '0.0000']);
    if ($final) {
        $db->table('transactions')->where('id', 1)->update(['status' => 'final', 'is_suspend' => '0']);
        $db->table('transaction_sell_lines_purchase_lines')->insert([
            ['sell_line_id' => 1, 'purchase_line_id' => 1, 'quantity' => '1.0000', 'qty_returned' => '0.0000'],
            ['sell_line_id' => 1, 'purchase_line_id' => 2, 'quantity' => '3.0000', 'qty_returned' => '0.0000'],
            ['sell_line_id' => 2, 'purchase_line_id' => 2, 'quantity' => '2.0000', 'qty_returned' => '0.0000']]);
    }
    return [$db, $service, $actor];
}
[$db, $service, $actor] = allocatedFixture();
$before = snapshot($db);
check($service->preview(1, $actor)['eligible'], 'ordinary allocated unpaid final eligible');
$req = requestFor($service, $actor, [['id' => 1, 'quantity' => '1.5000'], ['id' => 2, 'quantity' => '2.0000']]);
$r = $service->split(1, $actor, $req);
check($r['bill_a']['status'] === 'final' && $r['bill_b']['status'] === 'final', 'final status preserved in both bills');
check($r['bill_a']['final_total'] === '30.0000' && $r['bill_b']['final_total'] === '40.0000', 'final historical total conservation');
$newLine = $r['line_allocations'][0]['bill_b_line_id'];
check($db->table('transaction_sell_lines_purchase_lines')->where('sell_line_id', $newLine)->count() === 2, 'partial allocation crosses purchase rows');
check($db->table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 1)->value('quantity') === '2.5000', 'source retains mapping remainder');
foreach (['purchase_lines', 'variation_location_details', 'transaction_payments', 'eis_sales'] as $table) {
    check(snapshot($db)[$table] === $before[$table], 'final no consumption/stock/payment/fiscal mutation '.$table);
}
$after = snapshot($db);
check($service->split(1, $actor, $req) === $r && snapshot($db) === $after, 'final durable replay unchanged');
rejects(function () use ($db) { $db->transaction(function () { $m = App\TransactionSellLinesPurchaseLines::where('sell_line_id', 1)->first(); $m->quantity = '99'; $m->save(); }); }, 'split_write_fenced');
$db->beginTransaction();
try {
    $db->transaction(function () use ($db, $newLine) {
        $mapping = App\TransactionSellLinesPurchaseLines::where('sell_line_id', $newLine)->first();
        $mapping->quantity = '99';
        $mapping->save();
    });
} finally {
    $db->rollBack();
}
foreach (['0.0000', '5.0000'] as $quantity) {
    [$db, $service, $actor] = allocatedFixture();
    $db->table('transaction_sell_lines_purchase_lines')->where('id', 1)->update(['quantity' => $quantity]);
    check(!$service->preview(1, $actor)['eligible'], 'nonpositive/incomplete final mapping rejected');
}
foreach (['qty_returned' => '1', 'purchase_line_id' => '0'] as $field => $value) {
    [$db, $service, $actor] = allocatedFixture();
    $db->table('transaction_sell_lines_purchase_lines')->where('id', 1)->update([$field => $value]);
    check(!$service->preview(1, $actor)['eligible'], 'complex/oversold final mapping rejected');
}
[$db, $service, $actor] = allocatedFixture();
$db->table('transactions')->where('id', 100)->update(['business_id' => 2]);
check(!$service->preview(1, $actor)['eligible'], 'cross-tenant purchase rejected');
[$db, $service, $actor] = allocatedFixture();
$db->table('transaction_payments')->insert(['transaction_id' => 1, 'amount' => '0']);
check(!$service->preview(1, $actor)['eligible'], 'any zero final payment rejected');

// REAL stock/mapping/payment utils, but standalone Capsule, in-memory stock,
// local session/dispatcher, and a no-network after-commit fiscal observer.
class IsolatedCheckoutService extends SavedSaleSplitService
{
    public $scheduled = 0;
    protected function scheduleFiscal($id)
    {
        Capsule::connection()->afterCommit(function () { ++$this->scheduled; });
    }
}
function checkoutFixture($final = false)
{
    [$db, $unused, $actor] = allocatedFixture($final);
    $container = Container::getInstance();
    $container->instance('session', new Illuminate\Session\Store('synthetic', new Illuminate\Session\ArraySessionHandler(120)));
    $container->instance('events', $db->getEventDispatcher());
    $db->setTransactionManager(new Illuminate\Database\DatabaseTransactionsManager());
    if (!class_exists('Carbon', false)) { class_alias(Carbon\Carbon::class, 'Carbon'); }
    $db->table('eis_terminal_configurations')->where('configuration_id', 1)
        ->update(['max_cummulative_amount' => '0', 'max_transaction_age' => '0']);
    return [$db, new IsolatedCheckoutService($db), $actor];
}
function cashRequest($service, $actor, $id = 1)
{
    $p = $service->checkoutPreview($id, $actor);
    return ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'expected_version' => $p['expected_version'],
        'method' => 'cash', 'amount' => $p['source']['final_total']];
}
foreach ([false, true] as $final) {
    [$db, $service, $actor] = checkoutFixture($final);
    $split = $service->split(1, $actor, requestFor($service, $actor));
    $lines = $db->table('transaction_sell_lines')->orderBy('id')->get()->toJson();
    $stock = $db->table('variation_location_details')->value('qty_available');
    $purchases = snapshot($db)['purchase_lines']; $mappings = snapshot($db)['transaction_sell_lines_purchase_lines'];
    $request = cashRequest($service, $actor);
    $response = $service->checkout(1, $actor, $request);
    check($response['bill']['payment_status'] === 'paid' && $response['bill']['status'] === 'final', 'real utils checkout paid final');
    check($db->table('transaction_sell_lines')->orderBy('id')->get()->toJson() === $lines, 'checkout saved lines/ids/prices unchanged');
    check($db->table('transaction_payments')->count() === 1 && $db->table('transaction_payments')->value('amount') === '52.0000', 'one exact persisted cash payment');
    check($service->scheduled === 1 && $db->table('eis_sales')->count() === 0, 'only local after-commit fiscal observer, no external events');
    $after = snapshot($db);
    check($service->checkout(1, $actor, $request) === $response && snapshot($db) === $after && $service->scheduled === 1, 'paid durable retry no duplicate payment/stock/events');
    if ($final) {
        check(snapshot($db)['purchase_lines'] === $purchases && snapshot($db)['transaction_sell_lines_purchase_lines'] === $mappings, 'final checkout no allocation consumption change');
        check($db->table('variation_location_details')->value('qty_available') === $stock, 'final checkout no stock decrease');
    } else {
        check(bccomp(SavedSaleSplitService::decimal($db->table('variation_location_details')->value('qty_available')), bcsub($stock, '4.5000', 4), 4) === 0, 'draft stock decreases only retained saved quantities');
        check(bccomp(SavedSaleSplitService::decimal($db->table('purchase_lines')->where('id', 1)->value('quantity_sold')), '4.5000', 4) === 0, 'draft existing utils allocate purchase consumption once');
    }
    rejects(function () use ($db) { $db->transaction(function () { SavedSaleWriteGuard::lock($db = Capsule::connection(), 1); }); }, 'split_write_fenced');
    rejects(function () use ($service, $actor) { $service->checkout(1, $actor, cashRequest($service, $actor)); }, 'sale_not_completely_unpaid');
    check(SavedSaleWriteGuard::fiscal($db, 1)->payment_status === 'paid', 'committed paid checkout permits fiscal tracking');
    $collision = $request; $collision['amount'] = '1.0000';
    rejects(function () use ($service, $actor, $collision) { $service->checkout(1, $actor, $collision); }, 'operation_collision');
}
foreach (['transactions', 'transaction_sell_lines_purchase_lines', 'transaction_payments', 'saved_sale_split_operations'] as $table) {
    [$db, $service, $actor] = checkoutFixture(); $request = cashRequest($service, $actor); $before = snapshot($db);
    $db->statement("CREATE TRIGGER checkout_fail BEFORE ".($table === 'transactions' || $table === 'saved_sale_split_operations' ? 'UPDATE' : 'INSERT')." ON $table BEGIN SELECT RAISE(ABORT, 'checkout failure'); END");
    try { $service->checkout(1, $actor, $request); throw new RuntimeException('missing checkout failure'); }
    catch (Illuminate\Database\QueryException $e) { check(str_contains($e->getMessage(), 'checkout failure'), 'late checkout failure'); }
    check(snapshot($db) === $before && $service->scheduled === 0, 'checkout complete rollback without deferred events '.$table);
}
foreach ([['business', 'pos_settings', '{"disable_express_checkout":1}'], ['business_locations', 'default_payment_accounts', '{}'],
    ['business_locations', 'default_payment_accounts', '{"cash":{"is_enabled":1,"account":4}}'], ['business', 'enable_rp', '1'],
    ['business', 'enable_product_expiry', '1']] as [$table, $field, $value]) {
    [$db, $service, $actor] = checkoutFixture();
    $db->table($table)->update([$field => $value]);
    check(!$service->checkoutPreview(1, $actor)['eligible'], 'configuration checkout gate '.$field);
}
[$db, $service, $actor] = checkoutFixture(); $request = cashRequest($service, $actor);
$db->table('transaction_sell_lines')->where('id', 1)->update(['sell_line_note' => 'new version']);
rejects(function () use ($service, $actor, $request) { $service->checkout(1, $actor, $request); }, 'stale_split_version');
[$db, $service, $actor] = checkoutFixture(); $request = cashRequest($service, $actor); $request['amount'] = '71.0000';
rejects(function () use ($service, $actor, $request) { $service->checkout(1, $actor, $request); }, 'invalid_checkout_amount');
[$db, $service, $actor] = checkoutFixture(); $actor->permissions[] = 'disable_express_checkout';
check(!$service->checkoutPreview(1, $actor)['eligible'], 'cash disable permission respected');
$actor->admin = true;
check($service->checkoutPreview(1, $actor)['eligible'], 'admin overrides disable permission, not settings');

// Ordinary overselling debt: explicit 0 references, never missing purchases.
function sentinelFixture($mixed = false)
{
    [$db, $service, $actor] = checkoutFixture(true);
    $db->table('business')->update(['pos_settings' => '{"allow_overselling":1}']);
    $db->table('transaction_sell_lines_purchase_lines')->whereIn('id', $mixed ? [2, 3] : [1, 2, 3])
        ->update(['purchase_line_id' => '0']);
    $db->table('variation_location_details')->update(['qty_available' => '-6.0000']);
    return [$db, $service, $actor];
}
function allocationLedger($db)
{
    $totals = [];
    foreach ($db->table('transaction_sell_lines_purchase_lines')->orderBy('id')->get() as $m) {
        $totals[$m->purchase_line_id] = bcadd($totals[$m->purchase_line_id] ?? '0', $m->quantity, 4);
    }
    ksort($totals, SORT_NUMERIC);
    return $totals;
}
function receiveSynthetic($db, $quantity, $type = 'purchase', $business = 1, $location = 2)
{
    // Only synthetic receipt graph rows. This intentionally models stock having
    // ALREADY been added by the receipt controller before reconciliation.
    $id = $db->table('transactions')->insertGetId(['business_id' => $business, 'location_id' => $location,
        'type' => $type, 'status' => 'received', 'transaction_date' => date('Y-m-d H:i:s')]);
    $db->table('purchase_lines')->insert(['transaction_id' => $id, 'product_id' => 4, 'variation_id' => 5,
        'quantity' => $quantity, 'quantity_sold' => '0.0000', 'quantity_adjusted' => '0.0000',
        'quantity_returned' => '0.0000', 'mfg_quantity_used' => '0.0000']);
    $before = SavedSaleSplitService::decimal($db->table('variation_location_details')->where('id', 1)->value('qty_available'));
    $db->table('variation_location_details')->where('id', 1)->update(['qty_available' => bcadd($before, $quantity, 4)]);
    return App\Transaction::findOrFail($id);
}
function reconcileReceipt($db, $receipt)
{
    $db->transaction(function () use ($receipt) { (new App\Utils\ProductUtil())->adjustStockOverSelling($receipt); });
}
foreach ([false, true] as $mixed) {
    foreach ([['1.5000', false], ['4.0000', false], ['1.5000', true]] as [$quantity, $wholeSecond]) {
        [$db, $service, $actor] = sentinelFixture($mixed);
        $before = snapshot($db); $ledger = allocationLedger($db);
        $p = $service->preview(1, $actor);
        check($p['eligible'] && snapshot($db) === $before, 'sentinel/mixed preview read-only');
        $lines = [['id' => 1, 'quantity' => $quantity]];
        if ($wholeSecond) { $lines[] = ['id' => 2, 'quantity' => '2.0000']; }
        $req = requestFor($service, $actor, $lines);
        $response = $service->split(1, $actor, $req);
        check(allocationLedger($db) === $ledger, 'split conserves every real/sentinel allocation quantity');
        foreach (['purchase_lines', 'variation_location_details', 'transaction_payments', 'eis_sales'] as $table) {
            check(snapshot($db)[$table] === $before[$table], 'oversold final split no mutation '.$table);
        }
        $after = snapshot($db);
        check($service->split(1, $actor, $req) === $response && snapshot($db) === $after, 'sentinel split durable replay');
        foreach ([1, $response['result_id']] as $id) {
            $cash = cashRequest($service, $actor, $id);
            $paid = $service->checkout($id, $actor, $cash);
            check($paid['bill']['payment_status'] === 'paid', 'each oversold final bill checked out');
            check(snapshot($db)['variation_location_details'] === $before['variation_location_details'] &&
                snapshot($db)['purchase_lines'] === $before['purchase_lines'], 'final cash never decrements stock/consumption');
            $committed = snapshot($db);
            check($service->checkout($id, $actor, $cash) === $paid && snapshot($db) === $committed, 'sentinel cash UUID retry exact');
        }
        check(allocationLedger($db) === $ledger && $service->scheduled === 2, 'two payments but unchanged stock allocations');
    }
}
foreach ([null, '', '00', '-0', '0.0', ' 0', '0e0', 'abc', '-1', '99999'] as $reference) {
    [$db, $service, $actor] = sentinelFixture();
    $db->table('transaction_sell_lines_purchase_lines')->where('id', 1)->update(['purchase_line_id' => $reference]);
    $p = $service->preview(1, $actor); $before = snapshot($db);
    check(!$p['eligible'] && $p['reason']['code'] === 'unsupported_purchase_mapping', 'invalid reference rejected '.var_export($reference, true));
    rejects(function () use ($service, $actor) { $service->split(1, $actor, requestFor($service, $actor)); }, 'unsupported_purchase_mapping');
    rejects(function () use ($service, $actor) { $service->checkout(1, $actor, cashRequest($service, $actor)); }, 'unsupported_purchase_mapping');
    check(snapshot($db) === $before, 'invalid sentinel reference leaves all rows unchanged');
}
foreach (['0', '-1', '0.00001', 'oops'] as $quantity) {
    [$db, $service, $actor] = sentinelFixture();
    $db->table('transaction_sell_lines_purchase_lines')->where('id', 1)->update(['quantity' => $quantity]);
    $p = $service->preview(1, $actor);
    check(!$p['eligible'], 'nonpositive/malformed/excess precision sentinel rejected');
}
foreach (['absent', 'partial', 'returned', 'unknown'] as $state) {
    [$db, $service, $actor] = sentinelFixture();
    if ($state === 'absent') { $db->table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 1)->delete(); }
    if ($state === 'partial') { $db->table('transaction_sell_lines_purchase_lines')->where('id', 1)->update(['quantity' => '0.5000']); }
    if ($state === 'returned') { $db->table('transaction_sell_lines_purchase_lines')->where('id', 1)->update(['qty_returned' => '0.0001']); }
    if ($state === 'unknown') {
        $db->getSchemaBuilder()->table('transaction_sell_lines_purchase_lines', function (Blueprint $t) { $t->text('module_state')->nullable(); });
        $db->table('transaction_sell_lines_purchase_lines')->where('id', 1)->update(['module_state' => 'x']);
    }
    check(!$service->preview(1, $actor)['eligible'], 'sentinel mapping coverage/metadata blocked '.$state);
}
foreach ([null, '{}', '{"allow_overselling":0}', '{"allow_overselling":false}'] as $settings) {
    [$db, $service, $actor] = sentinelFixture();
    $db->table('business')->update(['pos_settings' => $settings]);
    check(!$service->preview(1, $actor)['eligible'] && !$service->checkoutPreview(1, $actor)['eligible'], 'existing sentinel needs enabled tenant flag for split/cash');
    [$db, $service, $actor] = checkoutFixture(true);
    $db->table('business')->update(['pos_settings' => $settings]);
    check($service->checkoutPreview(1, $actor)['eligible'], 'real allocated final checkout independent of flag');
}

// Draft checkout uses canonical exact stock/mapping helpers, never synthetic supply.
foreach (['0.0000', '-2.3456', '1.1234', null] as $stock) {
    foreach ([false, true] as $enabled) {
        [$db, $service, $actor] = checkoutFixture(false);
        $db->table('business')->update(['pos_settings' => json_encode(['allow_overselling' => $enabled])]);
        $db->table('purchase_lines')->update(['quantity' => '0.0000']);
        if ($stock === null) { $db->table('variation_location_details')->delete(); }
        else { $db->table('variation_location_details')->update(['qty_available' => $stock]); }
        $req = cashRequest($service, $actor); $before = snapshot($db);
        if (!$enabled) {
            rejects(function () use ($service, $actor, $req) { $service->checkout(1, $actor, $req); }, 'checkout_stock_unavailable');
            check(snapshot($db) === $before && $service->scheduled === 0, 'disabled overselling refuses low/missing stock atomically');
            continue;
        }
        $paid = $service->checkout(1, $actor, $req);
        check($paid['bill']['payment_status'] === 'paid', 'draft shortage allowed only by tenant flag');
        check($db->table('variation_location_details')->value('qty_available') === bcsub($stock ?? '0', '6', 4), 'draft canonical negative stock exact');
        check(allocationLedger($db) === [0 => '6.0000'], 'no fabricated positive purchase supply');
        check(snapshot($db)['purchase_lines'] === $before['purchase_lines'], 'zero receipt supply never consumed');
        $after = snapshot($db);
        check($service->checkout(1, $actor, $req) === $paid && snapshot($db) === $after, 'negative stock retry consumes once');
    }
}
foreach ([false, true] as $enabled) {
    [$db, $service, $actor] = checkoutFixture(false);
    $db->table('business')->update(['pos_settings' => json_encode(['allow_overselling' => $enabled])]);
    $db->table('purchase_lines')->where('id', 1)->update(['quantity' => '1.2345']);
    $db->table('purchase_lines')->where('id', 2)->update(['quantity' => '0']);
    $req = cashRequest($service, $actor); $before = snapshot($db);
    if (!$enabled) {
        rejects(function () use ($service, $actor, $req) { $service->checkout(1, $actor, $req); }, 'checkout_stock_unavailable');
        check(snapshot($db) === $before, 'canonical purchase supply shortage rollback despite positive location stock');
    } else {
        $service->checkout(1, $actor, $req);
        check(allocationLedger($db) === [0 => '4.7655', 1 => '1.2345'], 'canonical mixed real supply plus exact sentinel shortage');
        check($db->table('purchase_lines')->where('id', 1)->value('quantity_sold') === '1.2345', 'only real available supply consumed');
    }
}

// Later receipts consume historical debt across both split participants. Paid
// fiscal graphs remain protected; flag changes do not erase historical debt.
foreach (['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'] as $type) {
    foreach ([false, true] as $mixed) {
        [$db, $service, $actor] = sentinelFixture($mixed);
        $split = $service->split(1, $actor, requestFor($service, $actor, [['id' => 1, 'quantity' => '1.5000'], ['id' => 2, 'quantity' => '2']]));
        $service->checkout(1, $actor, cashRequest($service, $actor));
        $db->table('business')->update(['pos_settings' => '{}']);
        $db->table('eis_sales')->insert(['transaction_id' => 1, 'status' => 'submitted']);
        $initial = allocationLedger($db); $debt = $initial[0];
        $stale = requestFor($service, $actor);
        $receipt = receiveSynthetic($db, '0.7500', $type);
        $stockBefore = snapshot($db)['variation_location_details'];
        $protected = snapshot($db);
        reconcileReceipt($db, $receipt);
        $purchaseId = $db->table('purchase_lines')->where('transaction_id', $receipt->id)->value('id');
        check($db->table('purchase_lines')->where('id', $purchaseId)->value('quantity_sold') === '0.7500', 'receipt partial exact consumption '.$type);
        check(allocationLedger($db)[0] === bcsub($debt, '0.7500', 4), 'partial receipt debt reduction across split');
        foreach (['transactions', 'transaction_sell_lines', 'transaction_payments', 'eis_sales', 'saved_sale_split_operations', 'variation_location_details'] as $table) {
            check(snapshot($db)[$table] === $protected[$table], 'receipt preserves protected graph '.$table);
        }
        check(snapshot($db)['variation_location_details'] === $stockBefore, 'receipt reconciliation never decrements stock twice');
        $after = snapshot($db); reconcileReceipt($db, $receipt);
        check(snapshot($db) === $after, 'partial receipt repeated reconciliation inert');
        rejects(function () use ($service, $actor, $stale) { $service->split(1, $actor, $stale); }, 'stale_split_version');
        $receipt = receiveSynthetic($db, '10.0000', $type);
        $beforeStock = snapshot($db)['variation_location_details'];
        reconcileReceipt($db, $receipt);
        $purchaseId = $db->table('purchase_lines')->where('transaction_id', $receipt->id)->value('id');
        check(!isset(allocationLedger($db)[0]), 'later receipt completely reconciles sentinel debt');
        check($db->table('purchase_lines')->where('id', $purchaseId)->value('quantity_sold') === bcsub($debt, '0.7500', 4), 'complete receipt consumes only outstanding debt');
        check(snapshot($db)['variation_location_details'] === $beforeStock, 'complete debt reconciliation no stock decrement');
        if ($mixed) { check(allocationLedger($db)[1] === $initial[1], 'old real purchase allocation remains invariant'); }
        $after = snapshot($db); reconcileReceipt($db, $receipt);
        check(snapshot($db) === $after, 'complete receipt replay inert');
        rejects(function () use ($db) { $db->transaction(function () use ($db) { SavedSaleWriteGuard::lock($db, 1); }); }, 'split_write_fenced');
        $db->transaction(function () use ($db, $split) { SavedSaleWriteGuard::lock($db, $split['result_id']); });
        rejects(function () use ($db) { $db->transaction(function () { $m = App\TransactionSellLinesPurchaseLines::where('sell_line_id', 1)->first(); $m->quantity = '99'; $m->save(); }); }, 'split_write_fenced');
    }
}
// Multiple receipt lines for the same variant must refresh residual debt ids.
[$db, $service, $actor] = sentinelFixture();
$service->split(1, $actor, requestFor($service, $actor));
$receipt = receiveSynthetic($db, '0.2500');
$db->table('purchase_lines')->insert(['transaction_id' => $receipt->id, 'product_id' => 4, 'variation_id' => 5,
    'quantity' => '0.8750', 'quantity_sold' => '0', 'quantity_adjusted' => '0', 'quantity_returned' => '0', 'mfg_quantity_used' => '0']);
reconcileReceipt($db, $receipt);
check(allocationLedger($db)[0] === '4.8750', 'same-variant receipt rows consume residual ids exactly once');

// Scope denies unrelated legacy model writes even DURING an allocation save.
foreach (['header', 'line', 'payment', 'fiscal', 'allocation', 'allocation_delete'] as $kind) {
    [$db, $service, $actor] = sentinelFixture();
    $service->split(1, $actor, requestFor($service, $actor));
    $receipt = receiveSynthetic($db, '0.2500');
    new App\TransactionSellLinesPurchaseLines(); // Register real booted guards before the synthetic observer.
    App\TransactionSellLinesPurchaseLines::saving(function () use ($kind) {
        if ($kind === 'header') { $m = App\Transaction::find(1); $m->final_total = '999'; $m->save(); }
        if ($kind === 'line') { $m = App\TransactionSellLine::find(1); $m->quantity = '99'; $m->save(); }
        if ($kind === 'payment') { App\TransactionPayment::create(['transaction_id' => 1, 'amount' => '1']); }
        if ($kind === 'fiscal') { Modules\EIS\Models\EisSale::create(['transaction_id' => 1, 'business_id' => 1, 'status' => 'pending']); }
        if ($kind === 'allocation') { $m = App\TransactionSellLinesPurchaseLines::find(1); $m->quantity = '99'; $m->save(); }
        if ($kind === 'allocation_delete') { App\TransactionSellLinesPurchaseLines::find(1)->delete(); }
    });
    $before = snapshot($db);
    rejects(function () use ($db, $receipt) { reconcileReceipt($db, $receipt); }, 'split_write_fenced');
    check(snapshot($db) === $before, 'allocation scope never authorizes '.$kind.' legacy write');
}
foreach (['allocation', 'consumption', 'protected_header', 'stock'] as $failure) {
    [$db, $service, $actor] = sentinelFixture(true);
    $service->split(1, $actor, requestFor($service, $actor));
    $receipt = receiveSynthetic($db, '0.7500'); $before = snapshot($db);
    if ($failure === 'allocation') {
        $db->statement("CREATE TRIGGER reconciliation_fail BEFORE INSERT ON transaction_sell_lines_purchase_lines BEGIN SELECT RAISE(ABORT, 'reconciliation failure'); END");
    } elseif ($failure === 'consumption') {
        $db->statement("CREATE TRIGGER reconciliation_fail BEFORE UPDATE ON purchase_lines BEGIN SELECT RAISE(ABORT, 'reconciliation failure'); END");
    } elseif ($failure === 'protected_header') {
        $db->statement("CREATE TRIGGER reconciliation_corruption AFTER UPDATE ON purchase_lines BEGIN UPDATE transactions SET final_total = '999' WHERE id=1; END");
    } else {
        $db->statement("CREATE TRIGGER reconciliation_corruption AFTER UPDATE ON purchase_lines BEGIN UPDATE variation_location_details SET qty_available = '999' WHERE id=1; END");
    }
    try { reconcileReceipt($db, $receipt); throw new RuntimeException('missing reconciliation failure'); }
    catch (SavedSplitException $e) { check($e->errorCode === 'stock_reconciliation_invalid', 'protected invariant detects corruption'); }
    catch (Illuminate\Database\QueryException $e) { check(str_contains($e->getMessage(), 'reconciliation failure'), 'late allocation/consumption injected failure'); }
    check(snapshot($db) === $before, 'complete reconciliation rollback '.$failure);
    rejects(function () use ($db) { $db->transaction(function () { $m = App\TransactionSellLinesPurchaseLines::where('sell_line_id', 1)->first(); $m->quantity = '99'; $m->save(); }); }, 'split_write_fenced');
}
foreach (['business', 'location', 'product', 'status', 'overconsumed'] as $invalid) {
    [$db, $service, $actor] = sentinelFixture();
    $service->split(1, $actor, requestFor($service, $actor));
    $receipt = receiveSynthetic($db, '1.0000');
    if ($invalid === 'business') { $db->table('transactions')->where('id', $receipt->id)->update(['business_id' => 2]); }
    if ($invalid === 'location') { $db->table('transactions')->where('id', $receipt->id)->update(['location_id' => 999]); }
    if ($invalid === 'product') { $db->table('products')->where('id', 4)->update(['business_id' => 2]); }
    if ($invalid === 'status') { $db->table('transactions')->where('id', $receipt->id)->update(['status' => 'draft']); }
    if ($invalid === 'overconsumed') { $db->table('purchase_lines')->where('transaction_id', $receipt->id)->update(['quantity_sold' => '2']); }
    $before = snapshot($db);
    rejects(function () use ($db, $receipt) { reconcileReceipt($db, $receipt); }, $invalid === 'product' ? 'unsupported_purchase_mapping' : 'stock_reconciliation_invalid');
    check(snapshot($db) === $before, 'bad receipt graph rollback '.$invalid);
}
[$db, $service, $actor] = sentinelFixture();
$receipt = receiveSynthetic($db, '1');
rejects(function () use ($receipt) { (new App\Utils\ProductUtil())->adjustStockOverSelling($receipt); }, 'stock_reconciliation_invalid');
rejects(function () { (new App\Utils\ProductUtil())->decreaseProductQuantity(4, 5, 2, '1', 0, 1); }, 'checkout_invariant_failed');

// Fiscal adapter remains fail-closed when the REAL changed API reports CHECK_ERROR.
[$db, $service, $actor] = checkoutFixture();
$db->getSchemaBuilder()->drop('eis_offline_limits');
$req = cashRequest($service, $actor); $before = snapshot($db);
rejects(function () use ($service, $actor, $req) { $service->checkout(1, $actor, $req); }, 'checkout_fiscal_limits');
check(snapshot($db) === $before && $service->scheduled === 0, 'real fiscal CHECK_ERROR never fail-open');

foreach (['same_model_delete', 'cancel', 'redirect'] as $action) {
    [$db, $service, $actor] = sentinelFixture();
    $service->split(1, $actor, requestFor($service, $actor));
    $receipt = receiveSynthetic($db, '0.2500');
    new App\TransactionSellLinesPurchaseLines();
    App\TransactionSellLinesPurchaseLines::saving(function ($m) use ($action) {
        if ($action === 'same_model_delete') { $m->delete(); }
        if ($action === 'cancel') { return false; }
        if ($action === 'redirect') { $m->purchase_line_id = 1; }
    });
    $before = snapshot($db);
    rejects(function () use ($db, $receipt) { reconcileReceipt($db, $receipt); },
        $action === 'same_model_delete' ? 'split_write_fenced' : 'stock_reconciliation_invalid');
    check(snapshot($db) === $before, 'exact saving capability rejects '.$action);
}
[$db, $service, $actor] = sentinelFixture();
$db->transaction(function () use ($db, $service) {
    $g = $service->lockedStockGraph(1, 1);
    foreach ([0.0, false, true, [], null] as $reference) {
        $invalid = $g; $invalid['mappings'][0]['purchase_line_id'] = $reference;
        rejects(function () use ($service, $invalid) { $service->validateMappings($invalid, true); }, 'unsupported_purchase_mapping');
    }
    foreach ([0, '0'] as $reference) {
        $valid = $g; $valid['mappings'][0]['purchase_line_id'] = $reference;
        $service->validateMappings($valid, true); check(true, 'explicit numeric zero sentinel accepted');
    }
});
foreach (['quantity', 'quantity_sold', 'quantity_adjusted', 'quantity_returned', 'mfg_quantity_used'] as $field) {
    [$db, $service, $actor] = allocatedFixture();
    $db->table('purchase_lines')->where('id', 1)->update([$field => $field === 'quantity' ? '0' : '99']);
    check(!$service->preview(1, $actor)['eligible'], 'invalid real supply/consumption blocked '.$field);
}
// Canonical receipt-edit bulk repair must never rewrite real split allocations.
foreach (['shrink', 'unreceive', 'delete'] as $action) {
    [$db, $service, $actor] = allocatedFixture();
    $service->split(1, $actor, requestFor($service, $actor));
    $receipt = App\Transaction::find(100);
    if ($action === 'shrink') {
        $db->table('purchase_lines')->where('id', 1)->update(['quantity' => '0.5000']);
        // Only this legacy aggregate SQL check needs native numeric affinity.
        // Keep the exact underlying ledger untouched; no real DB/schema is used.
        $db->statement('ALTER TABLE purchase_lines RENAME TO exact_purchase_lines');
        $db->statement('CREATE VIEW purchase_lines AS SELECT id, transaction_id, product_id, variation_id,
            CAST(quantity AS NUMERIC) AS quantity, quantity_sold, quantity_adjusted, quantity_returned,
            mfg_quantity_used, created_at, updated_at FROM exact_purchase_lines');
    }
    if ($action === 'unreceive') { $receipt->status = 'pending'; }
    $deleted = $action === 'delete' ? App\PurchaseLine::where('id', 1)->get() : null;
    $before = snapshot($db);
    rejects(function () use ($db, $receipt, $deleted) {
        $db->transaction(function () use ($receipt, $deleted) {
            (new App\Utils\TransactionUtil())->adjustMappingPurchaseSellAfterEditingPurchase('received', $receipt, $deleted);
        });
    }, 'split_write_fenced');
    check(snapshot($db) === $before, 'legacy receipt repair cannot change existing split consumption '.$action);
}
// A normal unsplit sale remains reconcilable through the same canonical entry.
[$db, $service, $actor] = sentinelFixture(true);
$receipt = receiveSynthetic($db, '0.4321');
reconcileReceipt($db, $receipt);
check(allocationLedger($db)[0] === '4.5679', 'ordinary non-split sale receipt reconciliation retained');
$before = snapshot($db); reconcileReceipt($db, $receipt);
check(snapshot($db) === $before, 'ordinary non-split receipt repeated exactly once');

// Non-sale debt keeps the legacy transfer path, without bypassing saved fences.
[$db, $service, $actor] = sentinelFixture();
$service->split(1, $actor, requestFor($service, $actor));
$db->table('transactions')->insert(['id' => 200, 'business_id' => 1, 'location_id' => 2, 'type' => 'sell_transfer', 'status' => 'final']);
$db->table('transaction_sell_lines')->insert(['id' => 200, 'transaction_id' => 200, 'product_id' => 4, 'variation_id' => 5, 'quantity' => '1.0000']);
$db->table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 200, 'purchase_line_id' => 0, 'quantity' => '1.0000']);
$receipt = receiveSynthetic($db, '10.0000');
reconcileReceipt($db, $receipt);
check(!isset(allocationLedger($db)[0]), 'receipt handles both split debt and ordinary outgoing transfer debt');
check(bccomp($db->table('purchase_lines')->where('transaction_id', $receipt->id)->value('quantity_sold'), '7', 4) === 0,
    'canonical non-sale reconciliation uses refreshed receipt balance');

[$db, $service, $actor] = checkoutFixture(false);
$db->table('business')->update(['pos_settings' => '{"allow_overselling":1}']);
$beforeBusiness = snapshot($db)['business'];
$service->checkout(1, $actor, cashRequest($service, $actor));
check(!isset(allocationLedger($db)[0]), 'enabled overselling never bypasses available real purchase supply');
check(snapshot($db)['business'] === $beforeBusiness, 'checkout never mutates tenant settings');
foreach (['payment', 'allocation', 'ledger'] as $failure) {
    [$db, $service, $actor] = checkoutFixture(false);
    $db->table('business')->update(['pos_settings' => '{"allow_overselling":1}']);
    $db->table('purchase_lines')->update(['quantity' => '0']);
    $db->table('variation_location_details')->delete();
    $req = cashRequest($service, $actor); $before = snapshot($db);
    $table = ['payment' => 'transaction_payments', 'allocation' => 'transaction_sell_lines_purchase_lines', 'ledger' => 'saved_sale_split_operations'][$failure];
    $event = $failure === 'ledger' ? 'UPDATE' : 'INSERT';
    $db->statement("CREATE TRIGGER oversell_fail BEFORE $event ON $table BEGIN SELECT RAISE(ABORT, 'oversell failure'); END");
    try { $service->checkout(1, $actor, $req); throw new RuntimeException('missing overselling rollback'); }
    catch (Illuminate\Database\QueryException $e) { check(str_contains($e->getMessage(), 'oversell failure'), 'overselling injected late failure'); }
    check(snapshot($db) === $before && $service->scheduled === 0, 'new negative stock row and sentinel rollback '.$failure);
}

foreach (['eis_settings', 'eis_configurations'] as $missing) {
    [$db, $service, $actor] = fixture();
    $request = requestFor($service, $actor);
    $db->table($missing)->delete();
    $response = $service->split(1, $actor, $request);
    check($response['bill_b']['invoice_no'] === date('Y').'/0001', 'split needs no EIS configuration '.$missing);
    check($db->table('eis_sales')->count() === 0, 'split creates no EIS tracking '.$missing);
}

[$db, $service, $actor] = fixture();
$historicDate = now()->subDay()->format('Y-m-d H:i:s');
$db->table('transactions')->where('id', 1)->update(['transaction_date' => $historicDate]);
$beforeFiscal = snapshot($db)['eis_sales'];
$first = $service->split(1, $actor, requestFor($service, $actor, [['id' => 1, 'quantity' => '1.0000']]));
$second = $service->split(1, $actor, requestFor($service, $actor, [['id' => 1, 'quantity' => '1.0000']]));
check($first['bill_b']['invoice_no'] === date('Y').'/0001'
    && $second['bill_b']['invoice_no'] === date('Y').'/0002', 'multiple splits consume only normal transaction numbers');
foreach ([$first, $second] as $result) {
    check($db->table('transactions')->where('id', $result['result_id'])->value('transaction_date') === $historicDate,
        'split preserves historic sale date');
    $audit = $db->table('saved_sale_split_operations')->where('result_id', $result['result_id'])->first();
    check(!isset(json_decode($audit->allocation_json, true)['eis_invoice_number']), 'audit contains no EIS number reservation');
}
check(snapshot($db)['eis_sales'] === $beforeFiscal, 'splitting never mutates fiscal tracking');

[$db, $service, $actor] = fixture();
$request = requestFor($service, $actor);
$before = snapshot($db);
$db->statement("CREATE TRIGGER number_rollback BEFORE UPDATE ON transaction_sell_lines BEGIN SELECT RAISE(ABORT, 'number rollback'); END");
try { $service->split(1, $actor, $request); throw new RuntimeException('missing late numbering rollback'); }
catch (Illuminate\Database\QueryException $e) { check(str_contains($e->getMessage(), 'number rollback'), 'late split failure propagated'); }
check(snapshot($db) === $before, 'numbered header, items and audit all roll back on late failure');
$db->statement('DROP TRIGGER number_rollback');
$response = $service->split(1, $actor, $request);
check($response['bill_b']['invoice_no'] === date('Y').'/0001', 'failed split also rolls back system number counter');

[$db, $service, $actor] = fixture();
$db->table('invoice_schemes')->insert(['id' => 2, 'business_id' => 1, 'is_default' => 0, 'scheme_type' => 'year',
    'prefix' => 'BR-', 'number_type' => 'sequential', 'start_number' => 50, 'invoice_count' => 7, 'total_digits' => 4]);
$db->table('business_locations')->where('id', 2)->update(['invoice_scheme_id' => 2]);
$db->table('transactions')->where('id', 1)->update(['status' => 'final']);
$request = requestFor($service, $actor);
$response = $service->split(1, $actor, $request);
check($response['bill_b']['invoice_no'] === 'BR-'.date('Y').'-0057', 'branch invoice scheme and current counter are respected');
check($db->table('invoice_schemes')->where('id', 2)->value('invoice_count') === '8',
    'branch system invoice counter advances once');
check($db->table('invoice_schemes')->where('id', 1)->value('invoice_count') === '0',
    'default scheme counter not consumed for branch scheme');
$before = snapshot($db);
check($service->split(1, $actor, $request) === $response && snapshot($db) === $before,
    'idempotent split retry consumes neither system nor EIS number');

[$db, $service, $actor] = fixture();
$db->table('transactions')->where('id', 1)->update(['status' => 'final']);
$db->table('invoice_schemes')->delete();
$request = requestFor($service, $actor); $before = snapshot($db);
rejects(fn () => $service->split(1, $actor, $request), 'split_invoice_number_unavailable');
check(snapshot($db) === $before, 'missing system invoice scheme leaves sale and counters unchanged');

[$db, $service, $actor] = fixture();
$db->table('transactions')->where('id', 1)->update(['status' => 'final']);
$request = requestFor($service, $actor); $before = snapshot($db);
$db->statement("CREATE TRIGGER scheme_rollback BEFORE UPDATE ON transaction_sell_lines BEGIN SELECT RAISE(ABORT, 'scheme rollback'); END");
try { $service->split(1, $actor, $request); throw new RuntimeException('missing scheme rollback'); }
catch (Illuminate\Database\QueryException $e) { check(str_contains($e->getMessage(), 'scheme rollback'), 'final split failure propagated'); }
check(snapshot($db) === $before, 'late final split failure rolls back invoice scheme counter');

function additionsRequest($service, $actor, $id = 1, $quantity = '2.0000', $variationId = 5)
{
    $preview = $service->additionsPreview($id, $actor);
    return ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'expected_version' => $preview['expected_version'],
        'items' => [['variation_id' => $variationId, 'quantity' => $quantity]]];
}
function addNewProduct($db)
{
    $db->table('products')->insert(['id' => 8, 'business_id' => 1, 'type' => 'single', 'unit_id' => 1,
        'enable_stock' => '0', 'tax' => 6]);
    $db->table('variations')->insert(['id' => 9, 'product_id' => 8, 'default_sell_price' => '10.0000']);
    $db->table('product_locations')->insert(['product_id' => 8, 'location_id' => 2]);
}
foreach (['draft', 'final'] as $status) {
    foreach ([false, true] as $split) {
        [$db, $service, $actor] = checkoutFixture($status === 'final');
        $db->table('products')->where('id', 4)->update(['tax' => 6]);
        $id = 1;
        if ($split) {
            $result = $service->split(1, $actor, requestFor($service, $actor));
            $id = $result['result_id'];
        }
        $previewBefore = snapshot($db);
        check($service->additionsPreview($id, $actor)['eligible'], 'unpaid '.$status.' additions eligible, split='.($split ? 'yes' : 'no'));
        check(snapshot($db) === $previewBefore, 'additions preview is read-only');
        $request = additionsRequest($service, $actor, $id);
        $linesBefore = $db->table('transaction_sell_lines')->where('transaction_id', $id)->orderBy('id')
            ->get()->map(fn ($line) => (array) $line)->all();
        $originalQuantity = array_reduce($linesBefore, fn ($sum, $line) => bcadd($sum, $line['quantity'], 4), '0.0000');
        $headerBefore = (array) $db->table('transactions')->where('id', $id)->first();
        $mapsBefore = $db->table('transaction_sell_lines_purchase_lines')->orderBy('id')->get();
        $stockBefore = $db->table('variation_location_details')->value('qty_available');
        $response = $service->addItems($id, $actor, $request);
        check($response['added_line_ids'] === [] && count($response['updated_line_ids']) === 1,
            'same variation increases existing line without creating a duplicate');
        $newId = $response['updated_line_ids'][0];
        $newLine = $db->table('transaction_sell_lines')->where('id', $newId)->first();
        check($newLine->unit_price === '10.0000' && $newLine->item_tax === '2.0000'
            && $newLine->unit_price_inc_tax === '12.0000', 'new line uses server price and product tax');
        check($newId === (int) $linesBefore[0]['id'], 'earliest historical line selected deterministically');
        check($newLine->quantity === bcadd($originalQuantity, '2', 4), 'duplicate quantities combined and increment persisted');
        $linesBefore[0]['quantity'] = $newLine->quantity;
        $linesBefore[0]['updated_at'] = $newLine->updated_at;
        check($db->table('transaction_sell_lines')->where('transaction_id', $id)->orderBy('id')
            ->get()->map(fn ($line) => (array) $line)->all() === [$linesBefore[0]],
            'only earliest saved line retained with combined quantity');
        check(bccomp($response['bill']['final_total'], bcmul(bcadd($originalQuantity, '2', 4), '12', 4), 4) === 0,
            'combined total uses earliest saved tax-inclusive price');
        $headerAfter = (array) $db->table('transactions')->where('id', $id)->first();
        check(array_diff_key($headerAfter, array_flip(['total_before_tax', 'final_total', 'updated_at']))
            === array_diff_key($headerBefore, array_flip(['total_before_tax', 'final_total', 'updated_at'])),
            'original number, date, customer, statuses and all other metadata preserved');
        $expectedStock = $status === 'final' ? bcsub($stockBefore, '2', 4) : $stockBefore;
        check(bccomp($db->table('variation_location_details')->value('qty_available'), $expectedStock, 4) === 0,
            'stock decreases only for new quantities on final orders');
        $oldLineIds = array_column($linesBefore, 'id');
        foreach ($mapsBefore as $map) {
            if (in_array($map->sell_line_id, $oldLineIds)) { $map->sell_line_id = (string) $newLine->id; }
        }
        check($db->table('transaction_sell_lines_purchase_lines')->whereIn('id', $mapsBefore->pluck('id'))->orderBy('id')->get()->toJson() === $mapsBefore->toJson(),
            'existing allocation quantities preserved and reassigned to canonical line');
        check($db->table('transaction_payments')->count() === 0 && $db->table('eis_sales')->count() === 0
            && $db->table('eis_failed_transactions')->count() === 0 && $service->scheduled === 0,
            'additions do not create payments, fiscal identities or submissions');
        $after = snapshot($db);
        check($service->addItems($id, $actor, $request) === $response && snapshot($db) === $after,
            'idempotent additions retry cannot duplicate stock or lines');
        $concurrent = $request; $concurrent['operation_id'] = (string) Illuminate\Support\Str::uuid();
        rejects(fn () => $service->addItems($id, $actor, $concurrent), 'stale_split_version');
        check(snapshot($db) === $after, 'another writer with the old preview cannot append twice');
        $changed = $request; $changed['items'][0]['quantity'] = '3';
        rejects(fn () => $service->addItems($id, $actor, $changed), 'operation_collision');
    }
}

foreach (['paid', 'partial', 'payment', 'fiscal', 'foreign-product', 'location', 'fractional', 'price', 'stale', 'permission', 'stock'] as $case) {
    [$db, $service, $actor] = checkoutFixture(true);
    $request = additionsRequest($service, $actor);
    $code = 'stale_split_version';
    switch ($case) {
        case 'paid': case 'partial':
            $db->table('transactions')->where('id', 1)->update(['payment_status' => $case]); break;
        case 'payment': $db->table('transaction_payments')->insert(['transaction_id' => 1, 'amount' => '0']); break;
        case 'fiscal': $db->table('eis_sales')->insert(['transaction_id' => 1, 'status' => 'pending']); break;
        case 'foreign-product': $db->table('products')->where('id', 4)->update(['business_id' => 2]);
            $code = 'unsupported_sale_graph'; break;
        case 'location': $db->table('product_locations')->delete(); $code = 'invalid_add_product'; break;
        case 'fractional': $db->table('units')->update(['allow_decimal' => 0]);
            $request['items'][0]['quantity'] = '0.5'; $code = 'invalid_unit_quantity'; break;
        case 'price': $request['items'][0]['unit_price'] = '0'; $code = 'invalid_add_items_request'; break;
        case 'stale': $db->table('transaction_sell_lines')->where('id', 1)->update(['sell_line_note' => 'changed']); break;
        case 'permission': $actor->permissions = ['sell.create', 'location.2']; $code = 'split_forbidden'; break;
        case 'stock': $db->table('variation_location_details')->update(['qty_available' => '0']); $code = 'checkout_stock_unavailable'; break;
    }
    $before = snapshot($db);
    rejects(fn () => $service->addItems(1, $actor, $request), $code);
    check(snapshot($db) === $before, 'rejected additions leave entire database unchanged '.$case);
    if (in_array($case, ['paid', 'partial', 'payment', 'fiscal'], true)) {
        $preview = $service->additionsPreview(1, $actor);
        check(!$preview['eligible'], 'fresh additions preview explicitly denies '.$case);
        $request['expected_version'] = $preview['expected_version'];
        rejects(fn () => $service->addItems(1, $actor, $request),
            $case === 'fiscal' ? 'unsupported_sale_graph' : 'sale_not_completely_unpaid');
        check(snapshot($db) === $before, 'fresh version cannot bypass unpaid/fiscal eligibility '.$case);
    }
}
foreach (['draft', 'final'] as $status) {
    [$db, $service, $actor] = checkoutFixture($status === 'final');
    $request = additionsRequest($service, $actor); $before = snapshot($db);
    $db->statement("CREATE TRIGGER additions_fail BEFORE UPDATE ON saved_sale_split_operations BEGIN SELECT RAISE(ABORT, 'additions rollback'); END");
    try { $service->addItems(1, $actor, $request); throw new RuntimeException('Expected additions rollback'); }
    catch (Illuminate\Database\QueryException $e) { check(str_contains($e->getMessage(), 'additions rollback'), 'late additions failure surfaced'); }
    check(snapshot($db) === $before, 'late additions failure rolls back lines, totals, stock, allocations and audit '.$status);
}

foreach ([false, true] as $final) {
    [$db, $service, $actor] = checkoutFixture($final);
    addNewProduct($db);
    $db->table('variations')->where('id', 5)->update(['default_sell_price' => '99.0000']);
    $db->table('tax_rates')->where('id', 6)->update(['amount' => '16.5']);
    $stockBefore = $db->table('variation_location_details')->value('qty_available');
    $soldBefore = $db->table('purchase_lines')->where('id', 1)->value('quantity_sold');
    $request = additionsRequest($service, $actor, 1, '0.1250');
    $request['items'][] = ['variation_id' => 9, 'quantity' => '2.0000'];
    $response = $service->addItems(1, $actor, $request);
    check($response['updated_line_ids'] === [1] && count($response['added_line_ids']) === 1,
        'mixed request merges existing variation and appends genuinely new variation');
    $saved = $db->table('transaction_sell_lines')->where('id', 1)->first();
    check($saved->quantity === '6.1250' && $saved->unit_price === '10.0000' &&
        $saved->item_tax === '2.0000' && $saved->unit_price_inc_tax === '12.0000',
        'changed current price/tax cannot reprice existing bill line');
    $new = $db->table('transaction_sell_lines')->where('id', $response['added_line_ids'][0])->first();
    check($new->quantity === '2.0000' && $new->unit_price === '10.0000' && $new->item_tax === '1.6500',
        'genuinely new item still uses current product price/tax');
    check($response['bill']['final_total'] === '96.8000', 'mixed additions total reconciles at earliest saved and current prices');
    check(bccomp($db->table('variation_location_details')->value('qty_available'),
        $final ? bcsub($stockBefore, '0.1250', 4) : $stockBefore, 4) === 0,
        'mixed additions stock only consumes merged delta on final bills');
    check(bccomp($db->table('purchase_lines')->where('id', 1)->value('quantity_sold'),
        $final ? bcadd($soldBefore, '0.1250', 4) : $soldBefore, 4) === 0,
        'mixed additions purchase consumption only increases by merged delta');
    $after = snapshot($db);
    check($service->addItems(1, $actor, $request) === $response && snapshot($db) === $after,
        'mixed additions retry cannot duplicate new line or merged quantity');
    if (!$final) {
        $service->checkout(1, $actor, cashRequest($service, $actor));
        check(bccomp($db->table('variation_location_details')->value('qty_available'),
            bcsub($stockBefore, '6.1250', 4), 4) === 0, 'draft checkout consumes full merged quantity exactly once');
    }
}
foreach ([false, true] as $final) {
    [$db, $service, $actor] = checkoutFixture($final);
    addNewProduct($db);
    $stockBefore = $db->table('variation_location_details')->value('qty_available');
    $soldBefore = $db->table('purchase_lines')->where('id', 1)->value('quantity_sold');
    $response = $service->addItems(1, $actor, additionsRequest($service, $actor, 1, '1', 9));
    check($response['merged_line_ids'] === [2], 'unselected historical duplicates consolidated when saving a new product');
    check($db->table('transaction_sell_lines')->where('transaction_id', 1)->count() === 2 &&
        $db->table('transaction_sell_lines')->where('id', 1)->value('quantity') === '6.0000',
        'exactly one line for each variation including untargeted item');
    check($response['bill']['final_total'] === '84.0000', 'earliest price/tax applied to all historical duplicate quantities');
    check($db->table('variation_location_details')->value('qty_available') === $stockBefore &&
        $db->table('purchase_lines')->where('id', 1)->value('quantity_sold') === $soldBefore,
        'consolidation alone never deducts stock or consumes purchases');
    $service->addItems(1, $actor, additionsRequest($service, $actor, 1, '0.2500'));
    $response = $service->addItems(1, $actor, additionsRequest($service, $actor, 1, '0.7500'));
    check($db->table('transaction_sell_lines')->where('transaction_id', 1)->where('variation_id', 5)->count() === 1 &&
        $db->table('transaction_sell_lines')->where('id', 1)->value('quantity') === '7.0000',
        'successive additions keep one line and increment its exact quantity');
    check($response['bill']['final_total'] === '96.0000', 'successive additions total matches combined quantity');
}
foreach (['delete-line', 'move-mapping'] as $failure) {
    [$db, $service, $actor] = checkoutFixture(true);
    $request = additionsRequest($service, $actor);
    $before = snapshot($db);
    $target = $failure === 'delete-line' ? 'DELETE ON transaction_sell_lines' : 'UPDATE ON transaction_sell_lines_purchase_lines';
    $db->statement("CREATE TRIGGER merge_fail BEFORE $target BEGIN SELECT RAISE(ABORT, 'merge rollback'); END");
    try { $service->addItems(1, $actor, $request); throw new RuntimeException('Expected merge rollback'); }
    catch (Illuminate\Database\QueryException $e) { check(str_contains($e->getMessage(), 'merge rollback'), 'merge storage error surfaced'); }
    check(snapshot($db) === $before, 'merge failure restores duplicate lines, allocations, stock and audit '.$failure);
}
[$db, $service, $actor] = checkoutFixture();
addNewProduct($db);
$db->table('variations')->where('id', 9)->update(['product_id' => 4]);
$service->addItems(1, $actor, additionsRequest($service, $actor, 1, '1', 9));
check($db->table('transaction_sell_lines')->where('transaction_id', 1)->where('product_id', 4)->count() === 2,
    'different variations of the same product remain distinct items');

foreach (['16.5' => '1.6500', '0' => '0.0000', '2.125' => '0.2125'] as $rate => $expectedTax) {
    [$db, $service, $actor] = checkoutFixture(true);
    addNewProduct($db);
    $db->table('tax_rates')->where('id', 6)->update(['amount' => $rate]);
    if ((string) $rate === '16.5') {
        check(is_float($db->table('tax_rates')->where('id', 6)->value('amount')),
            'fixture reproduces native SQL floating-point tax rate');
    }
    $response = $service->addItems(1, $actor, additionsRequest($service, $actor, 1, '1.0000', 9));
    $line = $db->table('transaction_sell_lines')->where('id', $response['added_line_ids'][0])->first();
    check($line->item_tax === $expectedTax, 'SQL float tax converted to decimal string, rate='.$rate);
    check($line->unit_price_inc_tax === bcadd('10', $expectedTax, 4), 'converted tax keeps exact gross price');
}
[$db, $service, $actor] = checkoutFixture();
addNewProduct($db);
$db->table('business')->update(['enable_inline_tax' => 0]);
$response = $service->addItems(1, $actor, additionsRequest($service, $actor, 1, '1.0000', 9));
$line = $db->table('transaction_sell_lines')->where('id', $response['added_line_ids'][0])->first();
check($line->tax_id === null && $line->item_tax === '0.0000' && $line->unit_price_inc_tax === '10.0000' &&
    $response['bill']['final_total'] === '82.0000',
    'restaurant item additions omit product tax and preserve the untaxed line total when inline tax is disabled');
[$db, $service, $actor] = checkoutFixture();
addNewProduct($db);
$db->getSchemaBuilder()->table('variations', function (Illuminate\Database\Schema\Blueprint $t) { $t->text('sell_price_inc_tax')->nullable(); });
$db->table('variations')->where('id', 9)->update(['default_sell_price' => '17021.2800', 'sell_price_inc_tax' => '20000.0000']);
$db->table('tax_rates')->where('id', 6)->update(['amount' => '17.5']);
$response = $service->addItems(1, $actor, additionsRequest($service, $actor, 1, '2.0000', 9));
$line = $db->table('transaction_sell_lines')->where('id', $response['added_line_ids'][0])->first();
check($line->unit_price === '17021.2800' && $line->item_tax === '2978.7200' && $line->unit_price_inc_tax === '20000.0000' &&
    $response['bill']['final_total'] === '40072.0000',
    'restaurant lines use the catalog tax-inclusive price like web POS, without recomputed fractional-tax drift');
$db->table('business')->update(['enable_inline_tax' => 0]);
$response = $service->addItems(1, $actor, additionsRequest($service, $actor, 1, '1.0000', 9));
check($db->table('transaction_sell_lines')->where('variation_id', 9)->value('unit_price_inc_tax') === '20000.0000',
    'existing restaurant catalog-priced line keeps its saved price on later additions');
[$db, $service, $actor] = checkoutFixture(true);
$request = additionsRequest($service, $actor, 1, '1.00001');
$before = snapshot($db);
rejects(fn () => $service->addItems(1, $actor, $request), 'invalid_decimal');
check(snapshot($db) === $before, 'quantity precision remains strictly limited to four places');

foreach ([false, true] as $final) {
    foreach ([false, true] as $split) {
        foreach (['reduce', 'increase', 'replace'] as $action) {
            [$db, $service, $actor] = checkoutFixture($final);
            addNewProduct($db);
            $id = $split ? $service->split(1, $actor, requestFor($service, $actor))['result_id'] : 1;
            $oldQuantity = $split ? '1.5000' : '6.0000';
            $desired = $action === 'increase' ? bcadd($oldQuantity, '0.1250', 4) : '0.5000';
            $request = additionsRequest($service, $actor, $id, $desired);
            if ($action === 'replace') { $request['items'] = [['variation_id' => 9, 'quantity' => '2.0000']]; }
            $stockBefore = $db->table('variation_location_details')->value('qty_available');
            $soldBefore = bcadd($db->table('purchase_lines')->where('id', 1)->value('quantity_sold'),
                $db->table('purchase_lines')->where('id', 2)->value('quantity_sold'), 4);
            $headerBefore = (array) $db->table('transactions')->where('id', $id)->first();
            $response = $service->editItems($id, $actor, $request);
            $lines = $db->table('transaction_sell_lines')->where('transaction_id', $id)->get();
            check($lines->count() === 1, 'edited bill has exactly one remaining item '.$action);
            $line = $lines->first();
            check((int) $line->variation_id === ($action === 'replace' ? 9 : 5) &&
                $line->quantity === ($action === 'replace' ? '2.0000' : $desired), 'edit uses absolute final quantities '.$action);
            check(array_intersect($response['updated_line_ids'], $response['removed_line_ids']) === [],
                'edit response never reports a removed line as updated');
            check($response['bill']['final_total'] === ($action === 'replace' ? '24.0000' : bcmul($desired, '12', 4)),
                'edit totals use saved price/tax or server price for replacement');
            $delta = bcsub($action === 'replace' ? '0' : $desired, $oldQuantity, 4);
            check(bccomp($db->table('variation_location_details')->value('qty_available'),
                $final ? bcsub($stockBefore, $delta, 4) : $stockBefore, 4) === 0, 'edit stock exact delta '.$action);
            $soldAfter = bcadd($db->table('purchase_lines')->where('id', 1)->value('quantity_sold'),
                $db->table('purchase_lines')->where('id', 2)->value('quantity_sold'), 4);
            check($soldAfter === ($final ? bcadd($soldBefore, $delta, 4) : $soldBefore), 'edit purchase consumption exact delta '.$action);
            $headerAfter = (array) $db->table('transactions')->where('id', $id)->first();
            check(array_diff_key($headerAfter, array_flip(['total_before_tax', 'final_total', 'updated_at'])) ===
                array_diff_key($headerBefore, array_flip(['total_before_tax', 'final_total', 'updated_at'])),
                'edit retains invoice date customer status and metadata');
            check($db->table('transaction_payments')->count() === 0 && $db->table('eis_sales')->count() === 0 &&
                $service->scheduled === 0, 'edit creates no payments or fiscal submissions');
            $after = snapshot($db);
            check($service->editItems($id, $actor, $request) === $response && snapshot($db) === $after,
                'edit replay never repeats stock returns or quantity changes');
            $collision = $request; $collision['items'][0]['quantity'] = '3';
            rejects(fn () => $service->editItems($id, $actor, $collision), 'operation_collision');
            rejects(fn () => $service->addItems($id, $actor, $request), 'operation_collision');
            $stale = $request; $stale['operation_id'] = (string) Illuminate\Support\Str::uuid();
            rejects(fn () => $service->editItems($id, $actor, $stale), 'stale_split_version');
            if (!$final) {
                $service->checkout($id, $actor, cashRequest($service, $actor, $id));
                check(bccomp($db->table('variation_location_details')->value('qty_available'),
                    $action === 'replace' ? $stockBefore : bcsub($stockBefore, $desired, 4), 4) === 0,
                    'edited draft checkout consumes remaining stock quantities only');
            }
        }
    }
}
foreach (['empty', 'negative', 'precision', 'float', 'duplicate', 'price', 'paid', 'partial', 'payment', 'fiscal', 'permission', 'unit', 'stock'] as $case) {
    [$db, $service, $actor] = checkoutFixture(true);
    $request = additionsRequest($service, $actor, 1, '1');
    $code = 'invalid_add_items_request';
    switch ($case) {
        case 'empty': $request['items'] = []; break;
        case 'negative': $request['items'][0]['quantity'] = '-1'; break;
        case 'precision': $request['items'][0]['quantity'] = '1.00001'; $code = 'invalid_decimal'; break;
        case 'float': $request['items'][0]['quantity'] = 1.5; break;
        case 'duplicate': $request['items'][] = $request['items'][0]; break;
        case 'price': $request['items'][0]['unit_price'] = '0'; break;
        case 'paid': case 'partial':
            $db->table('transactions')->where('id', 1)->update(['payment_status' => $case]); $code = 'sale_not_completely_unpaid'; break;
        case 'payment': $db->table('transaction_payments')->insert(['transaction_id' => 1, 'amount' => '0']); $code = 'sale_not_completely_unpaid'; break;
        case 'fiscal': $db->table('eis_sales')->insert(['transaction_id' => 1, 'status' => 'pending']); $code = 'unsupported_sale_graph'; break;
        case 'permission': $actor->permissions = ['sell.create', 'location.2']; $code = 'split_forbidden'; break;
        case 'unit': $db->table('units')->update(['allow_decimal' => 0]); $request['items'][0]['quantity'] = '0.5'; $code = 'invalid_unit_quantity'; break;
        case 'stock': $request['items'][0]['quantity'] = '99999'; $code = 'checkout_stock_unavailable'; break;
    }
    if (in_array($case, ['paid', 'partial', 'payment', 'fiscal', 'unit'], true)) {
        $request['expected_version'] = $service->additionsPreview(1, $actor)['expected_version'];
    }
    $before = snapshot($db);
    rejects(fn () => $service->editItems(1, $actor, $request), $code);
    check(snapshot($db) === $before, 'invalid edit rolls back every write '.$case);
}
foreach (['purchase_lines', 'transaction_sell_lines_purchase_lines', 'variation_location_details', 'transactions', 'saved_sale_split_operations'] as $table) {
    [$db, $service, $actor] = checkoutFixture(true);
    $request = additionsRequest($service, $actor, 1, '0.5000'); $before = snapshot($db);
    $db->statement("CREATE TRIGGER edit_fail BEFORE UPDATE ON $table BEGIN SELECT RAISE(ABORT, 'edit rollback'); END");
    try { $service->editItems(1, $actor, $request); throw new RuntimeException('Expected edit rollback'); }
    catch (Illuminate\Database\QueryException $e) { check(str_contains($e->getMessage(), 'edit rollback'), 'edit write failure surfaced'); }
    check(snapshot($db) === $before, 'failed edit restores lines, consumption, stock and audit '.$table);
}

foreach ([false, true] as $mixed) {
    [$db, $service, $actor] = sentinelFixture($mixed);
    $soldBefore = snapshot($db)['purchase_lines'];
    $response = $service->editItems(1, $actor, additionsRequest($service, $actor, 1, '1.0000'));
    check($db->table('variation_location_details')->value('qty_available') === '-1.0000',
        'edit reduces overselling stock debt by removed quantity');
    check(allocationLedger($db) === ($mixed ? [1 => '1.0000'] : [0 => '1.0000']),
        'edit releases newest sentinel debt while preserving oldest allocation');
    check(snapshot($db)['purchase_lines'] === $soldBefore, 'sentinel reductions do not alter real purchase counters');
    check($response['bill']['final_total'] === '12.0000', 'overselling edit totals reconcile');
}
[$db, $service, $actor] = checkoutFixture(true);
$service->editItems(1, $actor, additionsRequest($service, $actor, 1, '2.5000'));
check($db->table('purchase_lines')->where('id', 1)->value('quantity_sold') === '10.0000' &&
    $db->table('purchase_lines')->where('id', 2)->value('quantity_sold') === '16.5000',
    'reductions release newest purchase allocations before oldest purchase costs');
check(allocationLedger($db) === [1 => '1.0000', 2 => '1.5000'], 'partial retained mapping exact after reduction');

foreach (['update-only', 'create-only', 'payment-only', 'none', 'admin', 'superadmin'] as $role) {
    [$db, $service, $actor] = checkoutFixture(true);
    $actor->admin = $role === 'admin';
    $actor->permissions = array_merge(['location.2'], match ($role) {
        'update-only' => ['sell.update'], 'create-only' => ['sell.create'],
        'payment-only' => ['edit_pos_payment'], 'superadmin' => ['superadmin'], default => [],
    });
    $before = snapshot($db);
    $allowed = in_array($role, ['update-only', 'admin', 'superadmin'], true);
    if (!$allowed) {
        rejects(fn () => $service->additionsPreview(1, $actor), 'split_forbidden');
        $request = ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'expected_version' => str_repeat('a', 64),
            'items' => [['variation_id' => 5, 'quantity' => '1']]];
        rejects(fn () => $service->editItems(1, $actor, $request), 'split_forbidden');
        rejects(fn () => $service->addItems(1, $actor, $request), 'split_forbidden');
        check(snapshot($db) === $before, 'backend edit authorization denies all writes '.$role);
        continue;
    }
    $request = additionsRequest($service, $actor, 1, '1');
    check(snapshot($db) === $before, 'backend-authorized preview remains read-only '.$role);
    $service->editItems(1, $actor, $request);
    check($db->table('transaction_sell_lines')->where('id', 1)->value('quantity') === '1.0000',
        'backend edit role does not need sale creation permission '.$role);
    if ($role === 'update-only') {
        rejects(fn () => $service->preview(1, $actor), 'split_forbidden');
        $after = snapshot($db);
        $actor->permissions = ['location.2'];
        rejects(fn () => $service->editItems(1, $actor, $request), 'split_forbidden');
        check(snapshot($db) === $after, 'backend revocation blocks retry even with previously valid operation UUID');
    }
}
[$db, $service, $actor] = checkoutFixture();
$actor->permissions = ['sell.update', 'location.2'];
$request = additionsRequest($service, $actor, 1, '1');
$actor->permissions = ['sell.create', 'location.2'];
$before = snapshot($db);
rejects(fn () => $service->editItems(1, $actor, $request), 'split_forbidden');
check(snapshot($db) === $before, 'backend revocation between preview and save blocks edit');

function tillFixture($db): int
{
    $db->getSchemaBuilder()->create('connector_tills', function (Blueprint $t) {
        $t->increments('id'); $t->integer('business_id'); $t->integer('location_id');
        $t->integer('eis_setting_id')->nullable(); $t->string('name'); $t->boolean('is_active');
    });
    return $db->table('connector_tills')->insertGetId([
        'business_id' => 1, 'location_id' => 2, 'eis_setting_id' => 1, 'name' => 'Counter', 'is_active' => true,
    ]);
}
[$db, $unused, $actor] = checkoutFixture(true);
$tillId = tillFixture($db);
$service = new IsolatedCheckoutService($db, $tillId);
$request = cashRequest($service, $actor);
$paid = $service->checkout(1, $actor, $request);
check((int) $db->table('transactions')->where('id', 1)->value('connector_till_id') === $tillId
    && $paid['bill']['payment_status'] === 'paid', 'Connector checkout persists validated till inside payment transaction');
$after = snapshot($db);
check($service->checkout(1, $actor, $request) === $paid && snapshot($db) === $after,
    'Till-bound checkout replay does not rewrite history or duplicate payment');
[$db, $unused, $actor] = checkoutFixture(true);
$tillId = tillFixture($db);
$db->table('transactions')->where('id', 1)->update(['connector_till_id' => $tillId]);
$service = new IsolatedCheckoutService($db);
$split = $service->split(1, $actor, requestFor($service, $actor));
check((int) $db->table('transactions')->where('id', $split['bill_b']['id'])->value('connector_till_id') === $tillId,
    'Split child preserves named till provenance');
echo "PASS: $checks isolated saved-sale split/overselling/reconciliation/additions/editing checks. No application/kernel/live database boot.\n";