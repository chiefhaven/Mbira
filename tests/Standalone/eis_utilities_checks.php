<?php

require __DIR__.'\eis_stock_outbound_checks.php';

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\EIS\Services\Utilities\EisUtilitiesClient;
use Modules\EIS\Services\Utilities\UtilityOperations;
use Modules\EIS\Services\Utilities\UtilityRequestService;

$translator = new Illuminate\Translation\Translator(new Illuminate\Translation\ArrayLoader(), 'en');
$app->instance('translator', $translator);
$app->instance('validator', new Illuminate\Validation\Factory($translator, $app));
$schema->table('eis_settings', function ($table) { $table->string('tpin')->nullable(); });
DB::table('eis_settings')->where('id', 1)->update(['tpin' => 'TIN-7']);
$setting = Modules\EIS\Models\EisSetting::findOrFail(1);
$migration = require dirname(__DIR__, 2).'\Modules\EIS\Database\Migrations\2026_10_24_020000_create_eis_utility_requests.php';
$migration->up();
$schema->create('eis_sales', function ($table) {
    $table->increments('id');
    foreach (['business_id', 'location_id', 'eis_setting_id', 'transaction_id'] as $field) { $table->integer($field); }
    foreach (['receipt_number', 'fiscal_invoice_number', 'eis_invoice_number', 'status'] as $field) { $table->string($field)->nullable(); }
});
DB::table('transactions')->insert(['id' => 800, 'business_id' => 7, 'location_id' => 1, 'type' => 'sell']);
DB::table('transactions')->insert(['id' => 801, 'business_id' => 7, 'location_id' => 1, 'type' => 'sell']);
DB::table('eis_sales')->insert([
    'business_id' => 7, 'location_id' => 1, 'eis_setting_id' => 1, 'transaction_id' => 800,
    'receipt_number' => 'RECEIPT-800', 'fiscal_invoice_number' => 'RECEIPT-800',
    'eis_invoice_number' => 'INVOICE-800', 'status' => 'submitted',
]);
DB::table('eis_sales')->insert([
    'business_id' => 7, 'location_id' => 1, 'eis_setting_id' => 1, 'transaction_id' => 801,
    'receipt_number' => 'RECEIPT-801', 'fiscal_invoice_number' => 'RECEIPT-801',
    'eis_invoice_number' => 'INVOICE-801', 'status' => 'submitted',
]);
$voidMigration = require dirname(__DIR__, 2).'\Modules\EIS\Database\Migrations\2026_10_25_140000_add_eis_void_receipt_sync.php';
$voidMigration->up();
$utilityChecks = 0;
function utilityCheck(bool $condition, string $message): void {
    global $utilityChecks;
    if (!$condition) { throw new RuntimeException($message); }
    ++$utilityChecks;
}
function utilityReject(callable $work, string $message): void {
    try { $work(); } catch (ValidationException | InvalidArgumentException | Illuminate\Database\Eloquent\ModelNotFoundException | Symfony\Component\HttpKernel\Exception\HttpException $e) {
        utilityCheck(true, $message); return;
    }
    throw new RuntimeException('Expected rejection: '.$message);
}
$requests = [];
$responseBody = ['statusCode' => 1, 'data' => ['ok' => true]];
$responseStatus = 200;
$disconnect = false;
Http::swap(new Factory());
Http::preventStrayRequests();
Http::fake(function ($request) use (&$requests, &$responseBody, &$responseStatus, &$disconnect) {
    $requests[] = $request;
    if ($disconnect) { throw new Illuminate\Http\Client\ConnectionException('Simulated lost response'); }
    return Factory::response($responseBody, $responseStatus);
});
$client = new EisUtilitiesClient();
utilityCheck(count(UtilityOperations::definitions()) === 11, 'All eleven documented Utilities operations are implemented');
$examples = UtilityOperations::definitions();
$examples['validate-vat5-certificate'] = ['projectNumber' => 'PROJECT', 'certificateNumber' => 'VAT5', 'quantity' => 2];
$examples['check-tin-authorization-requirement'] = ['tin' => 'BUYER-TIN'];
$examples['validate-authorization-code'] = ['authorizationCode' => 'AUTH-CODE'];
$examples['product-status'] = ['productId' => 'BARCODE'];
$examples['cancel-receipt'] = ['receiptNumber' => 'RECEIPT-800', 'reason' => 'Mistake', 'supportingDocuments' => base64_encode('evidence')];
$examples['taxpayer-initial-inventory-upload'] = ['isLastBatch' => false, 'products' => [[
    'barCode' => 'ONE', 'productName' => 'Product', 'productDescription' => 'Description',
    'unitPrice' => 10, 'quantityInStock' => 100, 'costPrice' => 5, 'sellingPrice' => 10,
]]];
foreach ($examples as $operation => $example) {
    $payload = UtilityOperations::payload($setting, $operation, $example);
    $result = $client->send($setting, $operation, $payload);
    $request = end($requests);
    $path = in_array($operation, ['cancel-receipt', 'get-void-receipts'], true) ? 'sales/' : 'utilities/';
    utilityCheck($request->url() === 'https://eis.invalid/api/v1/'.$path.$operation, 'Exact Swagger endpoint: '.$operation);
    utilityCheck($request->method() === 'POST' && $request->hasHeader('Authorization'), 'Authenticated POST: '.$operation);
    utilityCheck($result['outcome'] === 'accepted', 'Structured response accepted: '.$operation);
    if ($operation === 'ping') { utilityCheck($request->body() === '', 'Ping sends no undocumented body'); }
    else { utilityCheck($request->data() === $payload, 'Payload matches validated contract: '.$operation); }
    utilityReject(fn () => UtilityOperations::payload($setting, $operation, $example + ['arbitrary' => 1]), 'Unknown utility fields rejected');
}
utilityCheck(UtilityOperations::payload($setting, 'get-terminal-site-products', []) === ['tin' => 'TIN-7', 'siteId' => 'SITE-A'],
    'Site inventory identity is exact and has no pagination');
utilityCheck(UtilityOperations::payload($setting, 'get-terminal-blocking-message', []) === ['terminalId' => 'TERM-A'],
    'Terminal diagnostics bind the remote terminal ID, not the local primary key');
utilityReject(fn () => UtilityOperations::payload($setting, 'get-terminal-site-products', ['siteId' => 'FOREIGN']), 'Cannot override site');
utilityReject(fn () => UtilityOperations::payload($setting, 'product-status', ['productId' => 'ONE', 'tin' => 'OTHER']), 'Cannot override taxpayer');
utilityReject(fn () => UtilityOperations::payload($setting, 'check-terminal-unblock-status', ['terminalId' => 'OTHER']), 'Cannot override terminal');
utilityReject(fn () => UtilityOperations::payload($setting, 'unknown', []), 'Arbitrary remote endpoints blocked');
utilityReject(fn () => UtilityOperations::payload($setting, 'cancel-receipt', ['receiptNumber' => 'R', 'reason' => 'Reason', 'supportingDocuments' => '*bad']), 'Invalid base64 blocked');
$document = $examples['cancel-receipt'];
$document['supportingDocuments'] = base64_encode(str_repeat('a', 5 * 1024 * 1024));
utilityCheck(UtilityOperations::payload($setting, 'cancel-receipt', $document)['supportingDocuments'] === $document['supportingDocuments'],
    'Exactly five MiB decoded supporting documents are accepted');
$document['supportingDocuments'] = base64_encode(str_repeat('a', 5 * 1024 * 1024 + 1));
utilityReject(fn () => UtilityOperations::payload($setting, 'cancel-receipt', $document), 'Five MiB plus one decoded byte is rejected even at the same encoded length');
unset($document);
utilityReject(fn () => UtilityOperations::payload($setting, 'get-void-receipts', ['page' => 0]), 'Invalid page blocked');
utilityReject(fn () => UtilityOperations::payload($setting, 'get-void-receipts', ['pageSize' => 201]), 'Page size capped');
utilityReject(fn () => UtilityOperations::payload($setting, 'get-void-receipts', ['startDate' => '2026-10-24T00:00:00+02:00', 'endDate' => '2026-10-23T00:00:00+02:00']), 'Reversed dates blocked');
utilityCheck(isset(UtilityOperations::payload($setting, 'get-void-receipts', ['endDate' => '2026-10-24T00:00:00+02:00'])['endDate']),
    'End-date-only void filter is valid');
$initial = $examples['taxpayer-initial-inventory-upload'];
$tooMany = $initial; $tooMany['products'] = array_fill(0, 51, $initial['products'][0]);
utilityReject(fn () => UtilityOperations::payload($setting, 'taxpayer-initial-inventory-upload', $tooMany), 'Batch maximum fifty enforced');
$duplicate = $initial; $duplicate['products'][] = $initial['products'][0];
utilityReject(fn () => UtilityOperations::payload($setting, 'taxpayer-initial-inventory-upload', $duplicate), 'Duplicate barcode blocked');
$extra = $initial; $extra['products'][0]['extra'] = 'no';
utilityReject(fn () => UtilityOperations::payload($setting, 'taxpayer-initial-inventory-upload', $extra), 'Nested unknown fields blocked');
$negative = $initial; $negative['products'][0]['quantityInStock'] = -1;
utilityReject(fn () => UtilityOperations::payload($setting, 'taxpayer-initial-inventory-upload', $negative), 'Negative stock blocked');
$fifty = $initial;
$fifty['products'] = [];
for ($i = 0; $i < 50; $i++) {
    $item = $initial['products'][0]; $item['barCode'] = 'BAR-'.$i; $fifty['products'][] = $item;
}
utilityCheck(count(UtilityOperations::payload($setting, 'taxpayer-initial-inventory-upload', $fifty)['products']) === 50,
    'Exactly fifty unique inventory items are accepted');
$objectItems = $initial; $objectItems['products'] = ['not-a-list' => $initial['products'][0]];
utilityReject(fn () => UtilityOperations::payload($setting, 'taxpayer-initial-inventory-upload', $objectItems), 'Products must be a list, not an object');
$service = new UtilityRequestService($client);
$setting->status = false;
utilityCheck($client->send($setting, 'get-terminal-blocking-message', ['terminalId' => $setting->device_id])['outcome'] === 'accepted',
    'Disabled terminals can run blocking diagnostics without being enabled');
utilityReject(fn () => $service->prepare($setting, 'cancel-receipt', $examples['cancel-receipt'], 5), 'Disabled terminals cannot prepare mutations');
$setting->status = true;
$receipt = $service->prepare($setting, 'cancel-receipt', $examples['cancel-receipt'], 5);
DB::beginTransaction();
utilityReject(fn () => $service->submit($setting, $receipt->id, 5), 'Mutation claim cannot be rolled back by an outer transaction after HTTP');
DB::rollBack();
$before = count($requests);
$same = $examples['cancel-receipt']; $same['reason'] = 'Changed reason must not duplicate';
utilityCheck($service->prepare($setting, 'cancel-receipt', $same, 5)->id === $receipt->id && count($requests) === $before,
    'Preparing/reviewing a void request sends nothing and reuses receipt identity');
$same['receiptNumber'] = 'INVOICE-800';
utilityCheck($service->prepare($setting, 'cancel-receipt', $same, 5)->id === $receipt->id,
    'Fiscal invoice and receipt aliases cannot create duplicate void requests for one sale');
utilityReject(fn () => $service->prepare($setting, 'cancel-receipt', ['receiptNumber' => 'FOREIGN', 'reason' => 'Wrong'], 5), 'Void must belong to a submitted local terminal receipt');
$responseBody = ['statusCode' => 1, 'data' => ['invoiceNumber' => 'INVOICE-800', 'approvalStatus' => 'Pending']];
$submitted = $service->submit($setting, $receipt->id, 6);
utilityCheck($submitted->status === 'accepted' && count($requests) === $before + 1, 'Confirmed void sends exactly once and saves acceptance, not local cancellation');
utilityCheck($submitted->created_by === 5 && $submitted->submitted_by === 6 && $submitted->submitted_at !== null,
    'Audit distinguishes the preparing actor from the confirming/sending actor');
$service->submit($setting, $receipt->id, 5);
utilityCheck(count($requests) === $before + 1 && DB::table('transactions')->where('id', 800)->value('type') === 'sell',
    'Replaying void never resends or alters the sale');
$initialRow = $service->prepare($setting, 'taxpayer-initial-inventory-upload', $initial, 5);
$config->set('eis.base_url', 'https://other.invalid/api/v1');
utilityReject(fn () => $service->submit($setting, $initialRow->id, 5), 'Endpoint change invalidates the reviewed request before HTTP');
$config->set('eis.base_url', 'https://eis.invalid/api/v1');
$setting->branch_id = 'MOVED';
DB::table('eis_settings')->where('id', 1)->update(['branch_id' => 'MOVED']);
utilityReject(fn () => $service->submit($setting, $initialRow->id, 5), 'Terminal movement invalidates prepared requests');
$setting->branch_id = 'SITE-A';
DB::table('eis_settings')->where('id', 1)->update(['branch_id' => 'SITE-A']);
$foreign = clone $setting; $foreign->business_id = 8;
utilityReject(fn () => $service->submit($foreign, $initialRow->id, 5), 'Foreign tenant cannot submit saved request');
$responseBody = ['statusCode' => 1, 'data' => [
    'totalItems' => 1, 'mappedItems' => 1, 'unmappedItems' => 0, 'isPartialUpload' => true, 'skippedItems' => [],
]];
$first = $service->submit($setting, $initialRow->id, 5);
utilityCheck($first->status === 'accepted', 'Nonfinal phased batch accepted as staged');
$repeated = $initial; $repeated['products'][0]['quantityInStock'] = 101;
$repeatedRow = $service->prepare($setting, 'taxpayer-initial-inventory-upload', $repeated, 5);
utilityReject(fn () => $service->submit($setting, $repeatedRow->id, 5), 'Changed quantity cannot reupload a previously accepted barcode');
$last = $initial; $last['products'][0]['barCode'] = 'TWO'; $last['isLastBatch'] = true;
$lastRow = $service->prepare($setting, 'taxpayer-initial-inventory-upload', $last, 5);
$disconnect = true;
$unknown = $service->submit($setting, $lastRow->id, 5);
utilityCheck($unknown->status === 'unknown', 'Lost mutation response is persisted as unknown');
$before = count($requests);
$service->submit($setting, $lastRow->id, 5);
utilityCheck(count($requests) === $before, 'Unknown request is never retried');
$blocked = $initial; $blocked['products'][0]['barCode'] = 'THREE';
$blockedRow = $service->prepare($setting, 'taxpayer-initial-inventory-upload', $blocked, 5);
utilityReject(fn () => $service->submit($setting, $blockedRow->id, 5), 'Unknown phased batch blocks further taxpayer uploads');
$disconnect = false;
DB::table('eis_utility_requests')->where('id', $lastRow->id)->update(['status' => 'accepted']);
$later = $initial; $later['products'][0]['barCode'] = 'FOUR';
$laterRow = $service->prepare($setting, 'taxpayer-initial-inventory-upload', $later, 5);
utilityReject(fn () => $service->submit($setting, $laterRow->id, 5), 'Accepted last batch closes taxpayer initial upload');
foreach ([
    [503, ['statusCode' => 1, 'data' => []], 'unknown'],
    [200, ['statusCode' => 0, 'data' => null], 'rejected'],
    [200, ['statusCode' => 1, 'errors' => ['mixed'], 'data' => []], 'unknown'],
    [200, ['statusCode' => 'unexpected', 'data' => []], 'unknown'],
] as [$responseStatus, $responseBody, $expected]) {
    utilityCheck($client->send($setting, 'ping', [])['outcome'] === $expected, 'Conservative HTTP/envelope classification');
}
$responseStatus = 200;
$config->set('eis.base_url', 'https://eis.invalid');
$client->send($setting, 'ping', []);
utilityCheck(end($requests)->url() === 'https://eis.invalid/api/v1/utilities/ping', 'Host-only base normalized exactly once');
$controller = new Modules\EIS\Http\Controllers\UtilitiesController();
$user = new class extends App\User {
    public $locations = [1];
    public bool $allowed = true;
    public bool $canDeleteSell = false;
    public function permitted_locations($business_id = null) { return $this->locations; }
    public function can($abilities, $arguments = []) {
        return $this->allowed || ($this->canDeleteSell && $abilities === 'sell.delete');
    }
};
$user->business_id = 7;
$request = Illuminate\Http\Request::create('/eis/sales/operations');
$request->setUserResolver(fn () => $user);
$operationGuard = new ReflectionMethod($controller, 'authorizeOperation'); $operationGuard->setAccessible(true);
utilityReject(fn () => $operationGuard->invoke($controller, $request, $setting, 'taxpayer-initial-inventory-upload', $initial), 'Restricted admin cannot upload taxpayer-wide inventory');
utilityReject(fn () => $operationGuard->invoke($controller, $request, $setting, 'product-status', ['productId' => 'ONE']), 'Restricted admin cannot see taxpayer-wide product quantities');
utilityReject(fn () => $operationGuard->invoke($controller, $request, $setting, 'get-void-receipts', []), 'Restricted user cannot list every taxpayer receipt');
$operationGuard->invoke($controller, $request, $setting, 'get-void-receipts', ['invoiceNumber' => 'INVOICE-800']);
utilityCheck(true, 'Restricted admin may filter void lookup by owned submitted invoice');
$terminalGuard = new ReflectionMethod($controller, 'terminal'); $terminalGuard->setAccessible(true);
$user->locations = [];
utilityReject(fn () => $terminalGuard->invoke($controller, $request, 1), 'Location permissions apply even to administrators');
$accountGuard = new ReflectionMethod($controller, 'authorizeAccount'); $accountGuard->setAccessible(true);
$user->allowed = false;
utilityReject(fn () => $accountGuard->invoke($controller, $request), 'Utilities require business-settings permission');
$user->canDeleteSell = true;
utilityCheck($accountGuard->invoke($controller, $request, 'cancel-receipt') === null,
    'Receipt cancellation may use the sales-delete permission without general utilities access');
$user->canDeleteSell = false;
$config->set('eis.base_url', 'https://eis.invalid/api/v1');
DB::table('eis_utility_requests')->delete();
$responseStatus = 200;
$responseBody = ['statusCode' => 1, 'data' => [
    'totalItems' => 1, 'mappedItems' => 1, 'unmappedItems' => 0, 'isPartialUpload' => false,
]];
$final = $initial; $final['isLastBatch'] = true;
$row = $service->prepare($setting, 'taxpayer-initial-inventory-upload', $final, 5);
$finalized = $service->submit($setting, $row->id, 5);
utilityCheck($finalized->status === 'accepted', 'Complete final inventory response persists acceptance');
$before = count($requests);
$service->submit($setting, $row->id, 5);
utilityCheck(count($requests) === $before, 'Accepted final inventory batch is not resent');
DB::table('eis_utility_requests')->delete();
$responseBody['data']['unmappedItems'] = 1;
$responseBody['data']['skippedItems'] = ['ONE'];
$row = $service->prepare($setting, 'taxpayer-initial-inventory-upload', $initial, 5);
$review = $service->submit($setting, $row->id, 5);
utilityCheck($review->status === 'needs_review', 'Skipped/unmapped initial inventory never pretends to be fully accepted');
$next = $initial; $next['products'][0]['barCode'] = 'NEXT';
$row = $service->prepare($setting, 'taxpayer-initial-inventory-upload', $next, 5);
utilityReject(fn () => $service->submit($setting, $row->id, 5), 'Partial initial-inventory response blocks subsequent uploads');
DB::table('eis_utility_requests')->delete();
$row = $service->prepare($setting, 'taxpayer-initial-inventory-upload', $initial, 5);
DB::table('eis_utility_requests')->where('id', $row->id)->update(['status' => 'sending']);
$before = count($requests);
utilityCheck($service->submit($setting, $row->id, 5)->status === 'sending' && count($requests) === $before,
    'A simultaneous caller cannot infer ownership from an existing sending status');
$app->instance(Illuminate\Contracts\Routing\ResponseFactory::class, new Illuminate\Routing\ResponseFactory(
    Mockery::mock(Illuminate\Contracts\View\Factory::class), Mockery::mock(Illuminate\Routing\Redirector::class)
));
Illuminate\Http\Request::macro('validate', function (array $rules) {
    return Validator::make($this->all(), $rules)->validate();
});
$user->allowed = true; $user->locations = 'all'; $user->id = 5;
function utilityRequest(array $data): Illuminate\Http\Request {
    global $user;
    $request = Illuminate\Http\Request::create('/eis/sales/operations/execute', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    return $request;
}
$user->allowed = false; $user->canDeleteSell = true; $user->locations = 'all'; $user->id = 5;
$responseBody = ['statusCode' => 1, 'data' => ['invoiceNumber' => 'RECEIPT-800', 'approvalStatus' => 'Pending']];
$cancelPrepared = $controller->execute(utilityRequest([
    'terminal_id' => 1,
    'operation' => 'cancel-receipt',
    'payload' => json_encode(['receiptNumber' => 'RECEIPT-800', 'reason' => 'Recorded in POS sales list']),
]), $client, $service);
$preparedData = $cancelPrepared->getData(true)['data'] ?? [];
utilityCheck($cancelPrepared->getStatusCode() === 200 && ($preparedData['status'] ?? null) === 'prepared',
    'POS sales-delete permission can prepare a reviewed cancellation without general utilities access');
$cancelSubmitted = $controller->submit(utilityRequest(['confirmed' => 1]), (int) $preparedData['id'], $service);
utilityCheck($cancelSubmitted->getStatusCode() === 200 && $cancelSubmitted->getData(true)['data']['status'] === 'accepted',
    'POS sales-delete permission can submit the explicitly confirmed void request');
$user->allowed = true; $user->canDeleteSell = false;
$productStatusController = new Modules\EIS\Http\Controllers\ProductStatusController();
$user->locations = 'all';
$responseBody = ['statusCode' => 1, 'data' => ['productId' => 'LOCAL-BARCODE', 'status' => 'Active']];
$productStatusRequest = Illuminate\Http\Request::create('/eis/products/9/status', 'POST');
$productStatusRequest->setUserResolver(fn () => $user);
$productStatus = $productStatusController->show($productStatusRequest, 9, $client);
utilityCheck($productStatus->getStatusCode() === 200 && $productStatus->getData(true)['data']['status'] === 'Active'
    && end($requests)->url() === 'https://eis.invalid/api/v1/utilities/product-status'
    && end($requests)->data()['productId'] === 'LOCAL-BARCODE',
    'Mapped product action queries EIS status for the tenant-owned product identifier');
$user->locations = [1];
utilityReject(fn () => $productStatusController->show($productStatusRequest, 9, $client),
    'Taxpayer-wide product status is hidden from restricted-location users');
$user->locations = 'all';
$responseBody = ['statusCode' => 1, 'data' => ['serverDate' => '2026-10-24T02:00:00+02:00']];
$lookup = $controller->execute(utilityRequest(['terminal_id' => 1, 'operation' => 'ping', 'payload' => '{}']), $client, $service);
utilityCheck($lookup->getStatusCode() === 200 && $lookup->getData(true)['success'],
    'Controller executes a permission-scoped lookup with a real validated JSON request');
$badJson = $controller->execute(utilityRequest(['terminal_id' => 1, 'operation' => 'ping', 'payload' => '{invalid']), $client, $service);
utilityCheck($badJson->getStatusCode() === 422, 'Malformed UI JSON is surfaced as explicit validation failure');
$user->locations = [1];
$responseBody = ['statusCode' => 1, 'data' => ['items' => [['invoiceNumber' => 'FOREIGN-RECEIPT']], 'totalCount' => 1]];
$scopedVoid = $controller->execute(utilityRequest([
    'terminal_id' => 1, 'operation' => 'get-void-receipts', 'payload' => '{"invoiceNumber":"INVOICE-800"}',
]), $client, $service);
utilityCheck($scopedVoid->getStatusCode() === 502 && !str_contains($scopedVoid->getContent(), 'FOREIGN-RECEIPT'),
    'A remote void list ignoring the invoice filter cannot expose another receipt');
$user->locations = 'all';
$responseBody = ['statusCode' => 1, 'data' => [
    'items' => [['invoiceNumber' => 'INVOICE-800', 'approvalStatus' => 'Approved']],
    'totalCount' => 1,
]];
$transactionStatusBeforeVoidSync = DB::table('transactions')->where('id', 800)->value('status');
$voidSync = $controller->execute(utilityRequest([
    'terminal_id' => 1, 'operation' => 'get-void-receipts',
    'payload' => '{"invoiceNumber":"INVOICE-800","page":1,"pageSize":200}',
]), $client, $service)->getData(true);
utilityCheck($voidSync['success'] && $voidSync['data']['sync']['voided'] === 1
    && DB::table('eis_sales')->where('transaction_id', 800)->value('void_status') === 'void',
    'Approved EIS void receipt sync marks the matching local fiscal invoice void');
utilityCheck(DB::table('transactions')->where('id', 800)->value('status') === $transactionStatusBeforeVoidSync,
    'Void receipt sync preserves the transaction status and local sale history');
$responseBody['data']['items'][0]['approvalStatus'] = 'Pending';
$pendingSync = $controller->execute(utilityRequest([
    'terminal_id' => 1, 'operation' => 'get-void-receipts',
    'payload' => '{"invoiceNumber":"INVOICE-800","page":1,"pageSize":200}',
]), $client, $service)->getData(true);
utilityCheck($pendingSync['data']['sync']['voided'] === 1,
    'Previously approved void marker is not downgraded by a later pending result');
utilityCheck(DB::table('eis_sales')->where('transaction_id', 800)->value('void_approval_status') === 'Approved',
    'Previously approved status remains authoritative after a later pending response');
$responseBody['data']['items'] = [['invoiceNumber' => 'INVOICE-801', 'approvalStatus' => 'Pending']];
$pendingOnlySync = $controller->execute(utilityRequest([
    'terminal_id' => 1, 'operation' => 'get-void-receipts',
    'payload' => '{"invoiceNumber":"INVOICE-801","page":1,"pageSize":200}',
]), $client, $service)->getData(true);
utilityCheck($pendingOnlySync['data']['sync']['synced'] === 1
    && DB::table('eis_sales')->where('transaction_id', 801)->value('void_status') === null,
    'A pending receipt status is stored without marking the local invoice void');
$responseBody['data']['items'][0]['approvalStatus'] = 'approved';
$lowercaseSync = $controller->execute(utilityRequest([
    'terminal_id' => 1, 'operation' => 'get-void-receipts',
    'payload' => '{"invoiceNumber":"INVOICE-801","page":1,"pageSize":200}',
]), $client, $service)->getData(true);
utilityCheck($lowercaseSync['data']['sync']['voided'] === 0
    && DB::table('eis_sales')->where('transaction_id', 801)->value('void_status') === null,
    'Only the exact Approved status marks an invoice void');
$responseBody['data']['items'] = [
    ['invoiceNumber' => 'INVOICE-801', 'approvalStatus' => 'Approved'],
    ['invoiceNumber' => 'INCOMPLETE-RECEIPT'],
];
$malformedSync = $controller->execute(utilityRequest([
    'terminal_id' => 1, 'operation' => 'get-void-receipts',
    'payload' => '{"invoiceNumber":"INVOICE-801","page":1,"pageSize":200}',
]), $client, $service);
utilityCheck($malformedSync->getStatusCode() === 502
    && DB::table('eis_sales')->where('transaction_id', 801)->value('void_status') === null,
    'Malformed receipt lists are rejected before any local invoice is updated');
$responseBody['data']['items'] = [['invoiceNumber' => 'UNMATCHED-RECEIPT', 'approvalStatus' => 'Approved']];
$unmatchedSync = $controller->execute(utilityRequest([
    'terminal_id' => 1, 'operation' => 'get-void-receipts',
    'payload' => '{"invoiceNumber":"UNMATCHED-RECEIPT","page":1,"pageSize":200}',
]), $client, $service)->getData(true);
utilityCheck($unmatchedSync['data']['sync']['unmatched'] === 1
    && DB::table('eis_sales')->where('transaction_id', 801)->value('void_status') === null,
    'Unmatched remote receipts are reported without changing a local invoice');
DB::table('eis_utility_requests')->delete();
$before = count($requests);
$prepared = $controller->execute(utilityRequest([
    'terminal_id' => 1, 'operation' => 'cancel-receipt', 'payload' => json_encode($examples['cancel-receipt']),
]), $client, $service)->getData(true);
utilityCheck($prepared['prepared'] && count($requests) === $before && $prepared['data']['status'] === 'prepared',
    'Controller creates a reviewable void payload without sending HTTP');
$id = $prepared['data']['id'];
utilityReject(fn () => $controller->submit(utilityRequest([]), $id, $service), 'Controller requires explicit confirmation before mutation');
$shown = $controller->show(utilityRequest([]), $id)->getData(true)['data'];
utilityCheck($shown['payload']['receiptNumber'] === 'RECEIPT-800' && !isset($shown['jwt_token']),
    'Review response contains exact saved fields, not terminal credentials');
$user->locations = [1];
DB::table('eis_utility_requests')->where('id', $id)->update(['location_id' => 2]);
utilityReject(fn () => $controller->show(utilityRequest([]), $id), 'Historical audit review honors saved location even when terminal ownership changes');
DB::table('eis_utility_requests')->where('id', $id)->update(['location_id' => 1]);
$user->locations = 'all';
$responseBody = ['statusCode' => 1, 'data' => ['invoiceNumber' => 'INVOICE-800', 'approvalStatus' => 'Pending']];
$sent = $controller->submit(utilityRequest(['confirmed' => 1]), $id, $service)->getData(true);
utilityCheck($sent['success'] && $sent['data']['status'] === 'accepted' && count($requests) === $before + 1,
    'Confirmed controller request sends saved payload once and returns persisted result');
$controller->submit(utilityRequest(['confirmed' => 1]), $id, $service);
utilityCheck(count($requests) === $before + 1, 'Repeated UI submit is idempotent');
$preparedInventory = $controller->execute(utilityRequest([
    'terminal_id' => 1, 'operation' => 'taxpayer-initial-inventory-upload', 'payload' => json_encode($initial),
]), $client, $service)->getData(true);
utilityReject(fn () => $controller->submit(utilityRequest(['confirmed' => 1]), $preparedInventory['data']['id'], $service),
    'Initial upload requires a separate genuine-inventory declaration, excluding dummy repair use');
$migration->down();
echo 'PASS: '.$utilityChecks." Utilities checks (plus outbound fixture checks), fake HTTP and SQLite only.\n";
