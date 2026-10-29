<?php

// Real return/stock/allocation methods, SQLite and fake HTTP. No .env or live EIS.
require __DIR__.'\eis_terminal_management_checks.php';
class_alias(Carbon\Carbon::class, 'Carbon');

use App\Transaction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\EIS\Http\Controllers\SalesController;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Sales\ReturnAdjustmentPayload;
use Modules\EIS\Services\Sales\SaleAdjustmentService;
use Modules\EIS\Services\Sales\SalesApiClient;

$salesChecks = 0;
function salesCheck(bool $condition, string $message): void
{
    global $salesChecks;
    if (!$condition) { throw new RuntimeException('FAIL: '.$message); }
    $salesChecks++;
}
function salesReject(callable $work, string $message): void
{
    try { $work(); } catch (InvalidArgumentException | UnexpectedValueException | Illuminate\Validation\ValidationException $e) {
        salesCheck(true, $message); return;
    }
    throw new RuntimeException('Expected rejection: '.$message);
}
$capsule->setEventDispatcher(new Illuminate\Events\Dispatcher($app));
$capsule->bootEloquent();
Illuminate\Database\Eloquent\Model::clearBootedModels();
$app->instance('db.schema', $schema);
$app->instance('log', new Psr\Log\NullLogger());
$app->instance('validator', $validator);
$app['config']->set('eis.base_url', 'https://eis.invalid/api/v1');
$app['config']->set('eis.environment', 'production');
$schema->create('business', function (Blueprint $t) {
    $t->increments('id'); $t->integer('currency_id'); $t->boolean('enable_rp')->default(false);
});
$schema->create('currencies', function (Blueprint $t) { $t->increments('id'); });
DB::table('currencies')->insert(['id' => 1]);
DB::table('business')->insert(['id' => 1, 'currency_id' => 1]);
$schema->create('users', function (Blueprint $t) { $t->increments('id'); $t->integer('business_id'); $t->softDeletes(); });
DB::table('users')->insert(['id' => 7, 'business_id' => 1]);
$gate = Mockery::mock(Illuminate\Contracts\Auth\Access\Gate::class);
$gate->shouldReceive('forUser')->andReturnSelf();
$actorAllowed = true;
$gate->shouldReceive('check')->andReturnUsing(function ($ability) use (&$actorAllowed) {
    return $ability === 'access_all_locations' || $actorAllowed;
});
$app->instance(Illuminate\Contracts\Auth\Access\Gate::class, $gate);
$schema->table('eis_settings', function (Blueprint $t) { $t->string('tpin')->nullable(); $t->string('branch_id')->nullable(); });
$schema->create('transactions', function (Blueprint $t) {
    $t->increments('id');
    foreach (['business_id', 'location_id', 'contact_id', 'customer_group_id', 'created_by', 'return_parent_id', 'tax_id'] as $c) { $t->integer($c)->nullable(); }
    foreach (['type', 'status', 'payment_status', 'invoice_no', 'discount_type', 'transaction_date'] as $c) { $t->text($c)->nullable(); }
    foreach (['final_total', 'total_before_tax', 'tax_amount', 'discount_amount', 'rp_earned'] as $c) { $t->decimal($c, 18, 4)->default(0); }
    $t->timestamps();
});
$schema->create('products', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->string('sku'); $t->boolean('enable_stock'); $t->softDeletes();
});
$schema->create('variations', function (Blueprint $t) {
    $t->increments('id'); $t->integer('product_id'); $t->integer('product_variation_id'); $t->softDeletes();
});
$schema->create('units', function (Blueprint $t) { $t->increments('id'); $t->softDeletes(); });
$schema->create('transaction_sell_lines', function (Blueprint $t) {
    $t->increments('id');
    foreach (['transaction_id', 'product_id', 'variation_id', 'parent_sell_line_id', 'sub_unit_id'] as $c) { $t->integer($c)->nullable(); }
    foreach (['quantity', 'quantity_returned', 'unit_price_inc_tax', 'item_tax'] as $c) { $t->decimal($c, 18, 4)->default(0); }
    $t->timestamps();
});
$schema->create('purchase_lines', function (Blueprint $t) {
    $t->increments('id'); $t->decimal('quantity', 18, 4); $t->decimal('quantity_sold', 18, 4); $t->timestamps();
});
$schema->create('transaction_sell_lines_purchase_lines', function (Blueprint $t) {
    $t->increments('id'); $t->integer('sell_line_id'); $t->integer('purchase_line_id');
    $t->decimal('quantity', 18, 4); $t->decimal('qty_returned', 18, 4)->default(0); $t->timestamps();
});
$schema->create('variation_location_details', function (Blueprint $t) {
    $t->increments('id');
    foreach (['location_id', 'product_id', 'variation_id', 'product_variation_id'] as $c) { $t->integer($c); }
    $t->decimal('qty_available', 18, 4); $t->timestamps();
});
$schema->create('transaction_payments', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->integer('transaction_id'); $t->decimal('amount', 18, 4);
});
$schema->create('eis_sales', function (Blueprint $t) {
    $t->increments('id');
    foreach (['business_id', 'transaction_id', 'eis_setting_id', 'location_id'] as $c) { $t->integer($c); }
    foreach (['status', 'request_payload', 'receipt_number', 'eis_invoice_number', 'fiscal_invoice_number',
        'invoice_number', 'error_message', 'submitted_at'] as $c) { $t->text($c)->nullable(); }
    $t->timestamps();
});
$m = require dirname(__DIR__, 2).'\Modules\EIS\Database\Migrations\2026_10_24_020000_create_eis_utility_requests.php'; $m->up();
$m = require dirname(__DIR__, 2).'\Modules\EIS\Database\Migrations\2026_10_24_030000_create_eis_sale_adjustments.php'; $m->up();
DB::table('eis_settings')->where('id', 101)->update(['branch_id' => 'SITE-A', 'tpin' => '12345678']);
DB::table('eis_settings')->where('id', 102)->update(['branch_id' => 'SITE-A', 'tpin' => '12345678']);
DB::table('transactions')->insert(['id' => 800, 'business_id' => 1, 'location_id' => 10, 'type' => 'sell',
    'status' => 'final', 'payment_status' => 'paid', 'created_by' => 7, 'final_total' => 345, 'invoice_no' => 'POS-800']);
DB::table('products')->insert(['id' => 1, 'business_id' => 1, 'sku' => 'SKU-1', 'enable_stock' => true]);
DB::table('variations')->insert(['id' => 1, 'product_id' => 1, 'product_variation_id' => 1]);
DB::table('transaction_sell_lines')->insert(['id' => 1, 'transaction_id' => 800, 'product_id' => 1, 'variation_id' => 1,
    'quantity' => 3, 'unit_price_inc_tax' => 115, 'item_tax' => 15]);
DB::table('variation_location_details')->insert(['location_id' => 10, 'product_id' => 1, 'variation_id' => 1,
    'product_variation_id' => 1, 'qty_available' => 7]);
DB::table('purchase_lines')->insert([['id' => 1, 'quantity' => 10, 'quantity_sold' => 1], ['id' => 2, 'quantity' => 10, 'quantity_sold' => 2]]);
DB::table('transaction_sell_lines_purchase_lines')->insert([
    ['sell_line_id' => 1, 'purchase_line_id' => 1, 'quantity' => 1], ['sell_line_id' => 1, 'purchase_line_id' => 2, 'quantity' => 2],
]);
$original = ['invoiceHeader' => ['invoiceNumber' => 'FISCAL-800', 'invoiceDateTime' => '2026-10-24T01:00:00+02:00',
    'sellerTIN' => '12345678', 'siteId' => 'SITE-A', 'globalConfigVersion' => 1, 'terminalConfigVersion' => 1, 'taxpayerConfigVersion' => 1],
    'invoiceLineItems' => [['id' => 1, 'productCode' => 'SKU-1', 'quantity' => 3, 'unitPrice' => 115,
        'total' => 345, 'totalVAT' => 45, 'taxRateId' => 'A', 'isProduct' => true, 'discount' => 0]],
    'invoiceSummary' => ['invoiceTotal' => 345, 'totalVAT' => 45, 'taxBreakDown' => [['rateId' => 'A', 'taxableAmount' => 300, 'taxAmount' => 45]], 'levyBreakDown' => []]];
DB::table('eis_sales')->insert(['id' => 1, 'business_id' => 1, 'transaction_id' => 800, 'eis_setting_id' => 101,
    'location_id' => 10, 'status' => 'submitted', 'receipt_number' => 'RECEIPT-800',
    'eis_invoice_number' => 'FISCAL-800', 'request_payload' => json_encode($original)]);
$salesFixtures = [
    [801, 2, 'paid', 'pending'],
    [802, 3, 'paid', 'failed'],
    [803, 4, 'due', 'pending'],
    [804, 5, 'paid', 'cancelled'],
];
foreach ($salesFixtures as [$transactionId, $eisSaleId, $paymentStatus, $eisStatus]) {
    DB::table('transactions')->insert([
        'id' => $transactionId, 'business_id' => 1, 'location_id' => 10, 'type' => 'sell',
        'status' => 'final', 'payment_status' => $paymentStatus, 'created_by' => 7,
        'final_total' => 100, 'invoice_no' => 'POS-'.$transactionId,
    ]);
    DB::table('eis_sales')->insert([
        'id' => $eisSaleId, 'business_id' => 1, 'transaction_id' => $transactionId,
        'eis_setting_id' => 101, 'location_id' => 10, 'status' => $eisStatus,
        'invoice_number' => 'POS-'.$transactionId,
    ]);
}
$factory = new Factory(); Http::swap($factory);
$requests = []; $disconnect = false; $responseStatus = 200;
$responseBody = ['statusCode' => 1, 'data' => []];
$factory->fake(function ($request) use ($factory, &$requests, &$disconnect, &$responseStatus, &$responseBody) {
    $requests[] = $request;
    if ($disconnect) { throw new Illuminate\Http\Client\ConnectionException('Synthetic lost response'); }
    return $factory->response($responseBody, $responseStatus);
});
$client = new SalesApiClient();
$transformer = new ReturnAdjustmentPayload();
$service = new SaleAdjustmentService($client, $transformer);
$app->instance(SaleAdjustmentService::class, $service);
// Only ancillary logging and payment-status module hooks are stubbed; the actual
// return calculation, stock updates, purchase allocation and outbox code execute.
$util = new class extends App\Utils\TransactionUtil {
    public function activityLog($on, $action = null, $before = null, $properties = [], $log_changes = true, $business_id = null) {}
    public function updatePaymentStatus($transaction_id, $final_amount = null) {
        DB::table('transactions')->where('id', $transaction_id)->update(['payment_status' => $final_amount > 0 ? 'due' : 'paid']);
    }
};
function makeReturn(float $quantity): Transaction {
    global $util;
    return DB::transaction(fn () => $util->addSellReturn(['transaction_id' => 800, 'invoice_no' => 'RETURN-800',
        'products' => [['sell_line_id' => 1, 'quantity' => $quantity, 'unit_price_inc_tax' => 115]]], 1, 7, false));
}
function localSalesGraph(): array {
    $result = [];
    foreach (['transactions', 'transaction_sell_lines', 'purchase_lines', 'transaction_sell_lines_purchase_lines', 'variation_location_details', 'transaction_payments'] as $table) {
        $result[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    }
    return $result;
}
$before = localSalesGraph();
try {
    DB::transaction(function () { makeReturn(1); throw new RuntimeException('Rollback test'); });
} catch (RuntimeException $e) {}
salesCheck(localSalesGraph() === $before && DB::table('eis_sale_adjustments')->count() === 0 && count($requests) === 0,
    'Return, stock, allocations and EIS outbox roll back atomically; preparing never calls HTTP');
$return = makeReturn(2);
$row = DB::table('eis_sale_adjustments')->first();
$payload = json_decode($row->payload, true);
salesCheck($row->status === 'prepared' && $row->note_type === 'Credit' && (float) $payload['invoiceSummary']['invoiceTotal'] === 115.0
    && (float) $payload['invoiceSummary']['totalVAT'] === 15.0 && $payload['invoiceSummary']['taxBreakDown'][0]['rateId'] === 'A',
    'POS return generates updated remaining invoice with exact VAT and Swagger rateId breakdown');
salesCheck((float) $return->final_total === 230.0 && (float) DB::table('variation_location_details')->value('qty_available') === 9.0
    && (float) DB::table('purchase_lines')->sum('quantity_sold') === 1.0,
    'Real POS return creates customer credit, increases stock and releases purchase allocations once');
$afterLocal = localSalesGraph();
salesReject(fn () => makeReturn(1), 'Prepared note blocks return edits');
salesCheck(localSalesGraph() === $afterLocal, 'Blocked edit leaves accounting and stock unchanged');
$setting = EisSetting::findOrFail(101);
DB::beginTransaction();
salesReject(fn () => $service->submit($setting, $row->id, 7), 'Durable send claim cannot be enclosed by another transaction');
DB::rollBack();
$responseBody = ['statusCode' => 1, 'data' => ['invoiceNumber' => 'CREDIT-1', 'originalInvoiceNumber' => 'FISCAL-800',
    'noteType' => 'Credit Note', 'invoiceTotal' => 115, 'totalVat' => 15]];
$submitted = $service->submit($setting, $row->id, 7);
salesCheck($submitted->status === 'accepted' && $submitted->invoice_number === 'CREDIT-1' && (int) $submitted->submitted_by === 7,
    'Accepted credit note retains result, actor and invoice link');
salesCheck(count($requests) === 1 && end($requests)->url() === 'https://eis.invalid/api/v1/sales/process-credit-debit-note'
    && end($requests)->hasHeader('Authorization', 'Bearer '.$setting->jwt_token), 'Credit note uses exact authenticated Sales endpoint');
$service->submit($setting, $row->id, 7);
salesCheck(count($requests) === 1 && localSalesGraph() === $afterLocal, 'Replay never resends or reapplies stock/customer credit');

DB::table('transaction_payments')->insert(['business_id' => 1, 'transaction_id' => $return->id, 'amount' => 230]);
$paidGraph = localSalesGraph();
salesReject(fn () => makeReturn(0.5), 'Reducing an already refunded return requires reversing the excess refund first');
salesCheck(localSalesGraph() === $paidGraph && DB::table('eis_sale_adjustments')->count() === 1, 'Over-refund reduction rolls back all local writes');
DB::table('transaction_payments')->delete();
$reduced = makeReturn(0.5);
$debit = DB::table('eis_sale_adjustments')->orderByDesc('id')->first();
$payload = json_decode($debit->payload, true);
salesCheck($debit->note_type === 'Debit' && $payload['invoiceHeader']['invoiceNumber'] === 'CREDIT-1'
    && $payload['invoiceSummary']['invoiceTotal'] === 287.5 && $payload['invoiceSummary']['totalVAT'] === 37.5,
    'Reducing accepted return creates a linked debit note from its last accepted fiscal invoice');
salesCheck((float) $reduced->final_total === 57.5 && (float) DB::table('variation_location_details')->value('qty_available') === 7.5
    && (float) DB::table('purchase_lines')->sum('quantity_sold') === 2.5
    && (float) DB::table('transaction_sell_lines_purchase_lines')->sum('qty_returned') === 0.5,
    'Return reduction reduces credit and stock and correctly reverses allocations spanning multiple purchase supplies');
$before = localSalesGraph();
$responseBody['data'] = ['invoiceNumber' => 'DEBIT-1', 'originalInvoiceNumber' => 'CREDIT-1', 'noteType' => 'Debit',
    'invoiceTotal' => 287.5, 'totalVat' => 37.5];
salesCheck($service->submit($setting, $debit->id, 7)->status === 'accepted' && localSalesGraph() === $before,
    'Debit acceptance does not apply local ledger changes twice');
salesReject(fn () => DB::transaction(fn () => Transaction::findOrFail($return->id)->delete()), 'Fiscal return cannot be deleted outside debit workflow');
salesCheck(Transaction::findOrFail($return->id)->exists, 'Blocked delete preserves linked return');
salesReject(fn () => DB::transaction(fn () => Transaction::findOrFail(800)->delete()), 'Original sale with a fiscal note chain cannot be deleted');

$return = makeReturn(3);
$row = DB::table('eis_sale_adjustments')->orderByDesc('id')->first();
$payload = json_decode($row->payload, true);
salesCheck((float) $payload['invoiceSummary']['invoiceTotal'] === 0.0 && (float) $payload['invoiceSummary']['totalVAT'] === 0.0
    && (float) $payload['invoiceLineItems'][0]['quantity'] === 0.0, 'Full return produces zero remaining invoice, retaining original line identities');
$disconnect = true; $count = count($requests);
salesCheck($service->submit($setting, $row->id, 7)->status === 'unknown' && count($requests) === $count + 1,
    'Network loss persists unknown after one send');
$service->submit($setting, $row->id, 7);
salesCheck(count($requests) === $count + 1, 'Unknown adjustment is never resent');
salesReject(fn () => makeReturn(2), 'Unknown outcome blocks subsequent local return edits');
$disconnect = false;

// Exercise terminal-scoped retrieval and the actual controller validation layer.
$controller = new SalesController();
$user = new class {
    public int $id = 7;
    public int $business_id = 1;
    public bool $allowed = true;
    public $locations = [10];
    public function can($permission): bool { return $this->allowed; }
    public function permitted_locations($businessId = null) { return $this->locations; }
};
$user->locations = [10]; $user->allowed = true;
function salesRequest(array $data = []): Illuminate\Http\Request { return managementRequest($data); }
$viewFactory = Mockery::mock(Illuminate\Contracts\View\Factory::class);
$viewFactory->shouldReceive('make')->once()->andReturnUsing(function ($name, $data) {
    return new class($name, $data) {
        public function __construct(public string $name, private array $data) {}
        public function getData(): array { return $this->data; }
    };
});
$app->instance(Illuminate\Contracts\View\Factory::class, $viewFactory);
$salesListing = $controller->index(salesRequest())->getData()['sales'];
salesCheck($salesListing->pluck('transaction_id')->sort()->values()->all() === [800, 801, 802]
    && $salesListing->pluck('status')->sort()->values()->all() === ['failed', 'pending', 'submitted'],
    'EIS sales list includes only fully paid final POS sales with pending, submitted or failed EIS states');
$responseBody = ['statusCode' => 1, 'data' => $original];
foreach (['get-invoice-by-number', 'last-submitted-online-transaction', 'last-submitted-offline-transaction'] as $operation) {
    $result = $controller->lookup(salesRequest(['terminal_id' => 101, 'operation' => $operation, 'invoice_number' => 'RECEIPT-800']), $client);
    $sent = end($requests);
    salesCheck($result->getStatusCode() === 200 && $sent->url() === 'https://eis.invalid/api/v1/sales/'.$operation,
        'Scoped controller retrieves documented Sales operation: '.$operation);
    salesCheck($operation === 'get-invoice-by-number' ? $sent['invoiceNumber'] === 'FISCAL-800' : $sent->body() === '',
        'Lookup canonicalizes local receipt aliases; last-invoice calls send no body');
}
$count = count($requests);
DB::table('eis_sales')->where('id', 1)->update(['status' => 'failed']);
salesCheck($controller->lookup(salesRequest(['terminal_id' => 101, 'operation' => 'get-invoice-by-number', 'invoice_number' => 'FISCAL-800']), $client)->getStatusCode() === 200,
    'Invoice retrieval can investigate a locally failed/uncertain submission without resending or changing its fiscal status');
salesCheck(DB::table('eis_sales')->where('id', 1)->value('status') === 'failed', 'Retrieval never silently promotes a failed local invoice');
DB::table('eis_sales')->where('id', 1)->update(['status' => 'submitted']);
$count = count($requests);
managementReject(fn () => $controller->lookup(salesRequest(['terminal_id' => 201, 'operation' => 'last-submitted-online-transaction']), $client), 404,
    'Foreign tenant retrieval rejected before HTTP');
managementReject(fn () => $controller->lookup(salesRequest(['terminal_id' => 103, 'operation' => 'last-submitted-online-transaction']), $client), 403,
    'Restricted location retrieval rejected before HTTP');
salesCheck(count($requests) === $count, 'Tenant/location denials never contact EIS');
managementReject(fn () => $controller->lookup(salesRequest(['terminal_id' => 101, 'operation' => 'get-invoice-by-number', 'invoice_number' => 'FOREIGN']), $client), 422,
    'Arbitrary invoice lookup cannot disclose other taxpayer invoices');
$responseBody['data']['invoiceHeader']['siteId'] = 'FOREIGN-SITE';
$result = $controller->lookup(salesRequest(['terminal_id' => 101, 'operation' => 'last-submitted-online-transaction']), $client);
salesCheck($result->getStatusCode() === 502 && !str_contains($result->getContent(), 'FOREIGN-SITE'),
    'Upstream site mismatch is withheld, not displayed as a successful lookup');
$responseBody = ['statusCode' => 1, 'data' => $original];
$responseBody['data']['invoiceHeader']['invoiceNumber'] = 'SIBLING-INVOICE';
salesCheck($controller->lookup(salesRequest(['terminal_id' => 101, 'operation' => 'last-submitted-online-transaction']), $client)->getStatusCode() === 502,
    'An unlinked invoice cannot cross terminal boundaries even when site and TIN match');
$responseBody = ['statusCode' => 0, 'data' => []];
salesCheck($controller->lookup(salesRequest(['terminal_id' => 101, 'operation' => 'last-submitted-offline-transaction']), $client)->getStatusCode() === 502,
    'Rejected retrieval is explicit rather than success-shaped');
$shown = $controller->show(salesRequest(), $row->id)->getData(true)['data'];
salesCheck($shown['return_id'] == $return->id && !isset($shown['jwt_token']) && !isset($shown['secret_key']),
    'Review exposes immutable linked return payload, not credentials');
salesReject(fn () => $controller->submit(salesRequest(), $row->id, $service), 'Submit requires explicit review confirmation');
DB::table('eis_sale_adjustments')->where('id', $row->id)->update(['location_id' => 11]);
managementReject(fn () => $controller->show(salesRequest(), $row->id), 403, 'Historical review checks saved location independently of current terminal');
DB::table('eis_sale_adjustments')->where('id', $row->id)->update(['location_id' => 10]);
$user->allowed = false;
managementReject(fn () => $controller->show(salesRequest(), $row->id), 403, 'Sales console requires settings permission');
$user->allowed = true;

// Validation uses the exact original fiscal lines, never today's mutable catalog.
$line = ['quantity' => 3, 'quantity_returned' => 1, 'sku' => 'SKU-1', 'unit_price_inc_tax' => 115, 'item_tax' => 15];
foreach ([
    array_merge($line, ['quantity_returned' => -1]),
    array_merge($line, ['quantity_returned' => 4]),
    array_merge($line, ['sku' => 'CHANGED']),
    array_merge($line, ['unit_price_inc_tax' => 116]),
    array_merge($line, ['item_tax' => 14]),
] as $badLine) {
    salesReject(fn () => $transformer->build($original, [$badLine], 115, 'FISCAL-800'), 'Invalid or changed fiscal return line is rejected');
}
salesReject(fn () => $transformer->build($original, [$line], 1, 'FISCAL-800'), 'Changed local refund price cannot produce a mismatching fiscal adjustment');
$levied = $original; $levied['invoiceSummary']['levyBreakDown'] = [['rateId' => 'LEVY']];
salesReject(fn () => $transformer->build($levied, [$line], 115, 'FISCAL-800'), 'Unsupported levy allocation fails explicitly instead of inventing tax treatment');
salesCheck(!str_contains(file_get_contents(dirname(__DIR__, 2).'\Modules\EIS\Resources\views\sales\index.blade.php'), '.html('),
    'Sales results render as safe text rather than interpreted upstream HTML');

$count = count($requests);
DB::table('eis_sale_adjustments')->where('id', $row->id)->update(['status' => 'sending']);
salesCheck($service->submit($setting, $row->id, 7)->status === 'sending' && count($requests) === $count,
    'Another caller cannot send a request already claimed as sending');
DB::table('eis_sale_adjustments')->where('id', $row->id)->update(['status' => 'prepared']);
$app['config']->set('eis.base_url', 'https://other.invalid');
salesReject(fn () => $service->submit($setting, $row->id, 7), 'Changing endpoint invalidates prepared adjustment identity');
$app['config']->set('eis.base_url', 'https://eis.invalid/api/v1');
DB::table('eis_settings')->where('id', 101)->update(['branch_id' => 'MOVED']);
salesReject(fn () => $service->submit($setting, $row->id, 7), 'Moving the original terminal cannot reroute a saved adjustment');
DB::table('eis_settings')->where('id', 101)->update(['branch_id' => 'SITE-A']);
DB::table('transactions')->where('id', $return->id)->update(['final_total' => 1]);
salesReject(fn () => $service->submit($setting, $row->id, 7), 'Local accounting tampering invalidates saved review snapshot');
DB::table('transactions')->where('id', $return->id)->update(['final_total' => 345]);
salesCheck(count($requests) === $count, 'All stale endpoint/terminal/local ledger checks occur before HTTP');
foreach ([
    ['invoiceNumber' => 'WRONG', 'originalInvoiceNumber' => 'OTHER', 'noteType' => 'Credit', 'invoiceTotal' => 0, 'totalVat' => 0],
    ['invoiceNumber' => 'NOTE', 'originalInvoiceNumber' => 'DEBIT-1', 'noteType' => 'Debit', 'invoiceTotal' => 0, 'totalVat' => 0],
    ['invoiceNumber' => 'NOTE', 'originalInvoiceNumber' => 'DEBIT-1', 'noteType' => 'Credit', 'invoiceTotal' => 99, 'totalVat' => 0],
    ['invoiceNumber' => 'NOTE', 'originalInvoiceNumber' => 'DEBIT-1', 'noteType' => 'Credit', 'invoiceTotal' => 0.01, 'totalVat' => 0],
] as $data) {
    DB::table('eis_sale_adjustments')->where('id', $row->id)->update(['status' => 'prepared']);
    $responseBody = ['statusCode' => 1, 'data' => $data];
    salesCheck($service->submit($setting, $row->id, 7)->status === 'needs_review', 'Mismatching original invoice/type/totals cannot settle a note');
}
DB::table('eis_sale_adjustments')->where('id', $row->id)->update(['status' => 'prepared']);
$responseBody = ['statusCode' => 0, 'data' => []];
salesCheck($service->submit($setting, $row->id, 7)->status === 'rejected', 'Explicit rejection persists instead of claiming fiscal success');
salesReject(fn () => makeReturn(1), 'Rejected fiscal note also fences changes until reviewed');
$before = count($requests);
$service->submit($setting, $row->id, 7);
salesCheck(count($requests) === $before, 'Rejected note cannot be replayed');

// Synthetic administrative acceptance below is fixture-only; production has no reset.
DB::table('eis_sale_adjustments')->where('id', $row->id)->update(['status' => 'accepted', 'invoice_number' => 'FULL-CREDIT']);
DB::table('variation_location_details')->update(['qty_available' => 1]);
$graph = localSalesGraph();
salesReject(fn () => makeReturn(0), 'Cannot reduce a return after returned goods have already been resold');
salesCheck(localSalesGraph() === $graph, 'Insufficient-stock debit rollback preserves stock and purchase allocations');
DB::table('variation_location_details')->update(['qty_available' => 10]);
$actorAllowed = false;
salesReject(fn () => makeReturn(2), 'Actual POS hook checks the actor permissions before changing the return');
$actorAllowed = true;
DB::table('eis_sales')->where('id', 1)->update(['status' => 'failed']);
salesReject(fn () => makeReturn(2), 'Unsubmitted fiscal sale cannot be returned before fiscalization');
DB::table('eis_sales')->where('id', 1)->update(['status' => 'submitted']);
$void = new Modules\EIS\Services\Utilities\UtilityRequestService(new Modules\EIS\Services\Utilities\EisUtilitiesClient());
salesReject(fn () => $void->prepare($setting, 'cancel-receipt', ['receiptNumber' => 'RECEIPT-800', 'reason' => 'Void'], 7),
    'A fiscal return note chain cannot also void its original invoice');
salesCheck(DB::table('eis_utility_requests')->count() === 0, 'Conflicting void is rejected before preparing or sending');
$snapshot = localSalesGraph();
salesReject(fn () => makeReturn(4), 'Over-return cannot increase stock or refund above sold quantity');
salesCheck(localSalesGraph() === $snapshot, 'Over-return rollback preserves accounting and stock');

$duplicate = $original;
$duplicate['invoiceLineItems'][] = $duplicate['invoiceLineItems'][0];
$duplicate['invoiceLineItems'][1]['id'] = 2;
$duplicate['invoiceSummary']['invoiceTotal'] = 690; $duplicate['invoiceSummary']['totalVAT'] = 90;
$built = $transformer->build($duplicate, [$line, $line], 230, 'FISCAL-800');
salesCheck($built['invoiceSummary']['totalVAT'] === 60.0 && $built['invoiceSummary']['invoiceTotal'] === 460.0
    && $built['invoiceSummary']['taxBreakDown'] === [['rateId' => 'A', 'taxableAmount' => 400.0, 'taxAmount' => 60.0]],
    'Multiple returned items aggregate VAT and taxable amounts exactly once per tax rate');
$badVat = $original; $badVat['invoiceSummary']['totalVAT'] = 1;
salesReject(fn () => $transformer->build($badVat, [$line], 115, 'FISCAL-800'), 'Inconsistent original invoice VAT must be repaired, not silently recalculated');
salesReject(fn () => $transformer->build($original, [$line], 115.01, 'FISCAL-800'), 'A one-cent POS refund mismatch is rejected');
$discounted = $original; $discounted['invoiceLineItems'][0]['discount'] = 10;
salesCheck($transformer->build($discounted, [$line], 115, 'FISCAL-800')['invoiceLineItems'][0]['discount'] === 10,
    'Quantity adjustments preserve the original per-unit line discount, consistent with POS fiscal transformation');
$app['config']->set('eis.base_url', 'https://eis.invalid');
$responseBody = ['statusCode' => 1, 'data' => $original];
$client->send($setting, 'last-submitted-online-transaction');
salesCheck(end($requests)->url() === 'https://eis.invalid/api/v1/sales/last-submitted-online-transaction',
    'Host-only endpoint normalization preserves exact Sales API prefix');
$count = count($requests);
salesReject(fn () => $client->send($setting, 'arbitrary-endpoint'), 'Unknown Sales operations are rejected');
salesCheck(count($requests) === $count, 'Arbitrary operation never contacts EIS');
$health = Mockery::mock(Modules\EIS\Services\Http\EisHealthService::class);
$health->shouldReceive('isOnline')->andReturn(true);
$submissionClient = new Modules\EIS\Services\Http\EisSaleClient($health);
$responseBody = ['statusCode' => 1, 'data' => []]; $responseStatus = 200;
$count = count($requests);
$submittedSale = $submissionClient->submit($original, $setting);
salesCheck($submittedSale['success'] && count($requests) === $count + 1
    && end($requests)->url() === 'https://eis.invalid/api/v1/sales/submit-sales-transaction',
    'Existing submission client uses the documented Sales endpoint with a host-only configuration');
$responseStatus = 302;
salesCheck(!$submissionClient->submit($original, $setting)['success'], 'Redirected sale submission is never reported as successful');
$responseStatus = 200;
echo "PASS: {$salesChecks} Sales checks (plus management fixture), actual POS return/stock/allocation methods and fake HTTP.\n";
