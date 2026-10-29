<?php

// Isolated SQLite fixtures: no application kernel, .env, external EIS or live writes.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Transaction;
use App\TransactionSellLine;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Modules\EIS\Exceptions\EisSaleException;
use Modules\EIS\Models\EisSale;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Models\EisTaxRate;
use Modules\EIS\Services\Http\EisSaleClient;
use Modules\EIS\Services\Sales\InvoiceNumberGenerator;
use Modules\EIS\Services\Sales\OfflineSignatureService;
use Modules\EIS\Services\Sales\SaleResponseService;
use Modules\EIS\Services\Sales\SaleOutcomeRecoveryService;
use Modules\EIS\Services\Sales\SaleSubmissionService;
use Modules\EIS\Services\Sales\SalesApiClient;
use Modules\EIS\Services\Sales\SaleTransformer;

$container = new Container();
$capsule = new Capsule($container);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setEventDispatcher(new Illuminate\Events\Dispatcher($container));
$capsule->setAsGlobal();
$capsule->bootEloquent();
Container::setInstance($container);
$container->instance('db', $capsule->getDatabaseManager());
$container->instance('log', new Psr\Log\NullLogger());
$container->instance('cache', new class {
    private $store;
    public function __construct() { $this->store = new Illuminate\Cache\ArrayStore(); }
    public function lock($name, $seconds) { return new Illuminate\Cache\ArrayLock($this->store, $name, $seconds); }
});
Facade::setFacadeApplication($container);
$schema = $capsule->getConnection()->getSchemaBuilder();
$schema->create('transactions', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->string('invoice_no');
    $t->string('type'); $t->string('status'); $t->string('payment_status');
    $t->string('transaction_date')->nullable();
    $t->integer('location_id')->default(3);
    $t->boolean('is_fiscalized')->default(false);
    $t->string('fiscal_invoice_number')->nullable(); $t->string('receipt_number')->nullable();
    $t->timestamps();
});
$schema->create('eis_settings', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->string('tpin'); $t->string('branch_id'); $t->timestamps();
    $t->integer('location_id')->default(3); $t->boolean('status')->default(true);
});
$container->instance('db.schema', $schema);
(require dirname(__DIR__, 2).'/Modules/EIS/Database/Migrations/2026_07_05_000001_create_eis_sales_table.php')->up();
$schema->table('eis_sales', function (Blueprint $t) {
    $t->integer('location_id')->default(3); $t->integer('eis_setting_id')->default(1);
    $t->string('eis_invoice_number')->nullable();
    $t->boolean('is_fiscalized')->default(false); $t->dateTime('fiscalized_at')->nullable();
});
$schema->create('business_locations', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->string('eis_site_id');
    $t->integer('default_eis_setting_id')->nullable();
});
DB::table('business_locations')->insert(['id' => 3, 'business_id' => 7, 'eis_site_id' => 'SITE-7', 'default_eis_setting_id' => 1]);
$schema->create('eis_configurations', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->integer('eis_setting_id')->unique(); $t->integer('location_id');
});
$schema->create('eis_terminal_configurations', function (Blueprint $t) {
    $t->increments('id'); $t->integer('configuration_id'); $t->boolean('is_active')->default(true);
    $t->integer('terminal_position'); $t->integer('taxpayer_id');
});
DB::table('eis_configurations')->insert(['id' => 1, 'business_id' => 7, 'eis_setting_id' => 1, 'location_id' => 3]);
DB::table('eis_terminal_configurations')->insert(['configuration_id' => 1, 'terminal_position' => 3, 'taxpayer_id' => 100]);
$schema->create('eis_failed_transactions', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->integer('transaction_id');
    $t->text('payload')->nullable(); $t->text('error_message')->nullable();
    $t->integer('attempts'); $t->dateTime('next_retry_at')->nullable(); $t->timestamps();
    $t->integer('location_id')->nullable(); $t->integer('eis_setting_id')->nullable();
});
$schema->create('eis_migration_issues', function (Blueprint $t) {
    $t->increments('id'); $t->string('source_table'); $t->integer('source_id');
});
DB::table('eis_settings')->insert(['business_id' => 7, 'tpin' => 'FIXTURE', 'branch_id' => 'SITE-7']);
$settings = EisSetting::first();
$transformer = new class(new EisTaxRate(), new OfflineSignatureService(), new InvoiceNumberGenerator()) extends SaleTransformer {
    public int $signatures = 0;
    public int $offlineQrGenerations = 0;
    public array $signatureDates = [];
    protected function generateOfflineSignature(EisSetting $settings, Transaction $transaction, string $eisInvoiceNumber, array $invoiceSummary, int $totalItems): array {
        ++$this->signatures;
        $this->signatureDates[] = $transaction->transaction_date;
        return ['offlineDataSignature' => 'fixture-signature', 'validationURL' => 'https://eis.invalid/offline'];
    }
    public function generateOfflineValidationData(EisSetting $settings, Transaction $transaction, array $payload): array {
        ++$this->offlineQrGenerations;
        $this->signatureDates[] = $payload['invoiceHeader']['invoiceDateTime'] ?? null;
        return ['offlineDataSignature' => 'fixture-signature', 'validationURL' => 'https://eis.invalid/offline'];
    }
};
$client = new class extends EisSaleClient {
    public array $payloads = [];
    public $beforeResponse = null;
    public array $response = ['success' => false, 'error' => 'Fixture offline'];
    public function __construct() {}
    public function submit(array $payload, object $settings): array {
        $this->payloads[] = $payload;
        if ($this->beforeResponse !== null) {
            ($this->beforeResponse)($payload);
        }
        return $this->response;
    }
};
$recoveryClient = new class extends SalesApiClient {
    public array $responses = [];
    public array $requests = [];
    public $responder = null;
    public int $calls = 0;
    public function __construct() {}
    public function send(EisSetting $setting, string $operation, array $payload = []): array {
        ++$this->calls;
        $this->requests[] = ['operation' => $operation, 'payload' => $payload];
        return $this->responder ? ($this->responder)($setting, $operation, $payload)
            : (array_shift($this->responses) ?? ['outcome' => 'unknown', 'response' => []]);
    }
};
$recovery = new SaleOutcomeRecoveryService($recoveryClient);
$service = new class($transformer, $client, new SaleResponseService(), new InvoiceNumberGenerator(), $recovery) extends SaleSubmissionService {
    public int $generated = 0;
    protected function generateEISInvoiceNumber(Transaction $transaction, EisSetting $setting): string {
        return 'GENERATED-'.++$this->generated;
    }
};
$checks = 0;
function checkRetry(bool $condition, string $message): void {
    global $checks;
    if (! $condition) { throw new RuntimeException($message); }
    ++$checks;
}
function fixtureSale(): Transaction {
    $id = DB::table('transactions')->insertGetId(['business_id' => 7, 'invoice_no' => 'POS-ORIGINAL',
        'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid']);
    return fixtureRelations(Transaction::findOrFail($id));
}
function fixtureRelations(Transaction $transaction): Transaction {
    $transaction->setRelation('business', null)->setRelation('location', null)->setRelation('contact', null);
    $lines = [];
    foreach ([
        ['id' => 1, 'unit_price' => 10, 'unit_price_inc_tax' => 11.25, 'item_tax' => 1.25, 'quantity' => 2],
        ['id' => 2, 'unit_price' => 20, 'unit_price_inc_tax' => 20.75, 'item_tax' => 0.75, 'quantity' => 3],
    ] as $attributes) {
        $line = new TransactionSellLine();
        $line->setRawAttributes($attributes);
        $line->exists = true;
        $line->setRelation('product', null)->setRelation('variations', null)->setRelation('modifiers', collect());
        $lines[] = $line;
    }
    $transaction->setRelation('sell_lines', new Illuminate\Database\Eloquent\Collection($lines));
    return $transaction;
}
// refresh() reloads relations; use a transaction subtype that keeps only fixture relations.
class RetryFixtureTransaction extends Transaction {
    public function refresh() {
        $this->setRelations([]);
        parent::refresh();
        return fixtureRelations($this);
    }
}
function submissionFixture(): Transaction {
    $transaction = fixtureSale();
    $fixture = new RetryFixtureTransaction();
    $fixture->setRawAttributes($transaction->getAttributes(), true);
    $fixture->exists = true;
    return fixtureRelations($fixture);
}
function failSubmission(SaleSubmissionService $service, Transaction $transaction, EisSetting $settings): EisSaleException {
    try { $service->submit($transaction, $settings); }
    catch (EisSaleException $e) { checkRetry(true, 'Failure propagated to queue'); return $e; }
    throw new RuntimeException('Expected failed submission');
}
$transaction = submissionFixture();
$client->beforeResponse = function (array $payload) use ($transaction) {
    $pending = EisSale::where('transaction_id', $transaction->id)->firstOrFail();
    checkRetry($pending->status === 'pending'
        && $pending->receipt_number === $pending->fiscal_invoice_number
        && $pending->qr_code === 'https://eis.invalid/offline'
        && ($pending->request_payload['invoiceSummary']['offlineSignature'] ?? null) === 'fixture-signature'
        && ($pending->request_payload['offlineSignatureData']['validationURL'] ?? null) === $pending->qr_code,
        'Pending submission persists signature and validation URL before receiving any HTTP response');
    checkRetry(!isset($payload['offlineSignatureData']) && !isset($payload['invoiceSummary']['validationURL']),
        'Locally prepared pending retry data is not sent on the first online attempt');
};
failSubmission($service, $transaction, $settings);
$client->beforeResponse = null;
$first = $client->payloads[0];
checkRetry(array_column($first['invoiceLineItems'], 'totalVAT') === [2.5, 2.25], 'Inline item VAT includes quantity');
checkRetry($first['invoiceSummary']['totalVAT'] === array_sum(array_column($first['invoiceLineItems'], 'totalVAT')),
    'Invoice VAT equals the sum of inline item VAT');
checkRetry(! array_key_exists('offlineSignature', $first['invoiceSummary']), 'First summary omits signature key entirely');
checkRetry(! isset($first['offlineSignatureData']) && ! isset($first['invoiceSummary']['validationURL']), 'First payload omits offline metadata');
checkRetry($transformer->signatures === 0, 'First attempt does not generate offline signature');
$tracking = EisSale::where('transaction_id', $transaction->id)->firstOrFail();
checkRetry($tracking->status === 'failed' && $tracking->fiscal_invoice_number === 'GENERATED-1', 'Failed invoice persists generated fiscal number');
checkRetry($tracking->receipt_number === $tracking->fiscal_invoice_number,
    'Failed invoice uses its fiscal invoice number as the provisional receipt number');
checkRetry(($tracking->request_payload['invoiceHeader']['invoiceNumber'] ?? null) === 'GENERATED-1',
    'First request payload stores the EIS invoice identity used for submission');
checkRetry($tracking->qr_code === 'https://eis.invalid/offline'
    && $transformer->offlineQrGenerations === 1,
    'Failed initial submission generates and saves the EIS offline validation QR URL');
$storedFirstFailure = $tracking->request_payload;
checkRetry(($storedFirstFailure['offlineSignatureData']['offlineDataSignature'] ?? null) === 'fixture-signature'
    && ($storedFirstFailure['invoiceSummary']['offlineSignature'] ?? null) === 'fixture-signature'
    && ($storedFirstFailure['invoiceSummary']['validationURL'] ?? null) === 'https://eis.invalid/offline',
    'First failure persists its exact offline signature in the retry payload');
$settings->secret_key = 'fixture-secret';
$realOfflineTransformer = new SaleTransformer(new EisTaxRate(), new OfflineSignatureService(), new InvoiceNumberGenerator());
$offlineValidation = $realOfflineTransformer->generateOfflineValidationData($settings, $transaction, $first);
checkRetry(str_starts_with($offlineValidation['validationURL'], 'https://dev-eis-portal.mra.mw/ReceiptValidation/Validate/?')
    && str_contains($offlineValidation['validationURL'], '&S='),
    'Offline QR validation URL follows the EIS receipt signature format');
checkRetry($tracking->invoice_number === 'POS-ORIGINAL' && ! $transaction->fresh()->is_fiscalized, 'Failure preserves POS identity without fiscalizing');
failSubmission($service, $transaction, $settings);
$retry = $client->payloads[1];
checkRetry($retry['invoiceSummary']['offlineSignature'] === 'fixture-signature', 'Retry includes offline signature');
checkRetry($retry['offlineSignatureData'] === $storedFirstFailure['offlineSignatureData']
    && $transformer->offlineQrGenerations === 1 && $transformer->signatures === 0,
    'Retry reuses the first-failure offline signature without generating another');
checkRetry($retry['invoiceHeader']['invoiceNumber'] === $first['invoiceHeader']['invoiceNumber'] && $service->generated === 1, 'Retry reuses generated identity');
$failed = DB::table('eis_failed_transactions')->where('transaction_id', $transaction->id)->first();
checkRetry($failed->attempts === 2 && json_decode($failed->payload, true) === json_decode(json_encode($retry), true), 'Failed retry stores current signed payload');
$duplicateSale = submissionFixture();
DB::table('eis_failed_transactions')->insert([
    'business_id' => 7, 'transaction_id' => $duplicateSale->id, 'attempts' => 2,
    'next_retry_at' => now(), 'created_at' => now(), 'updated_at' => now(),
]);
$client->response = [
    'success' => false, 'error_code' => 'DUPLICATE_INVOICE',
    'error' => 'EIS reports this invoice number already exists.',
    'data' => ['statusCode' => -2, 'remark' => 'Invoice Number already exists'],
];
$duplicateError = failSubmission($service, $duplicateSale, $settings);
checkRetry(EisSale::where('transaction_id', $duplicateSale->id)->value('status') === 'failed'
    && ! DB::table('eis_failed_transactions')->where('transaction_id', $duplicateSale->id)->exists(),
    'Duplicate invoice outcome remains failed for verification and is removed from blind automatic retries');
checkRetry($duplicateError->getCode() === EisSaleException::DUPLICATE_INVOICE,
    'Duplicate invoice has a distinct non-retryable exception code');
$duplicateTracking = EisSale::where('transaction_id', $duplicateSale->id)->first();
checkRetry(($duplicateTracking->response_payload['recovery']['state'] ?? null) === 'lookup_required',
    'Unverified duplicate is held for read-only lookup before another attempt');

$client->response = ['success' => false, 'error_code' => 'CONNECTION_ERROR',
    'error' => 'Connection timed out after sending request', 'retryable' => true];
$recoveryClient->responder = function (EisSetting $setting, string $operation, array $payload) {
    $tracking = EisSale::where('eis_invoice_number', $payload['invoiceNumber'])->firstOrFail();
    $saved = $tracking->request_payload;
    return ['outcome' => 'accepted', 'response' => ['statusCode' => 1, 'data' => [
        'invoiceHeader' => $saved['invoiceHeader'],
        'invoiceLineItems' => $saved['invoiceLineItems'],
        'invoiceSummary' => $saved['invoiceSummary'],
        'validationURL' => null,
        'dateSubmitted' => '2026-10-25T21:52:32.97',
    ]]];
};
$uncertainSale = submissionFixture();
$uncertainSubmitted = $service->submit($uncertainSale, $settings);
checkRetry($uncertainSubmitted->status === 'submitted'
    && ($uncertainSubmitted->response_payload['recovery']['state'] ?? null) === 'verified',
    'Ambiguous post is reconciled after exact read-only invoice match');
checkRetry($uncertainSubmitted->fiscal_invoice_number === $uncertainSubmitted->eis_invoice_number
    && $uncertainSubmitted->receipt_number === $uncertainSubmitted->fiscal_invoice_number && !$uncertainSale->fresh()->is_fiscalized
    && str_starts_with($uncertainSubmitted->qr_code, 'https://eis.invalid/offline'),
    'Verified invoice without official receipt retains offline QR and does not claim fiscal receipt');
$submittedCount = count($client->payloads);
$service->submit($uncertainSale, $settings);
checkRetry(count($client->payloads) === $submittedCount,
    'Verified invoice without a receipt is never resubmitted on a later call');
$recoveryClient->responder = function (EisSetting $setting, string $operation, array $payload) {
    $tracking = EisSale::where('eis_invoice_number', $payload['invoiceNumber'])->firstOrFail();
    $saved = $tracking->request_payload;
    $header = $saved['invoiceHeader'];
    $header['invoiceDateTime'] = Carbon\Carbon::parse($header['invoiceDateTime'])->format('Y-m-d\TH:i:s');
    $lines = $saved['invoiceLineItems'];
    foreach ($lines as &$line) { unset($line['totalVAT']); }
    unset($line);
    return ['outcome' => 'accepted', 'response' => ['statusCode' => 1, 'data' => [
        'invoiceHeader' => $header,
        'invoiceLineItems' => $lines,
        'invoiceSummary' => $saved['invoiceSummary'],
        'validationURL' => 'https://eservices.mra.mw/doc/v/?vc=SYNC-RECEIPT&c=SYNC-CODE',
        'dateSubmitted' => '2026-10-25T21:52:32.97',
    ]]];
};
$uncertainSubmitted->update([
    'error_message' => 'Earlier submission failure',
    'receipt_signature' => 'OLD-SIGNATURE',
]);
$beforeManualSync = $uncertainSubmitted->fresh();
$syncResult = $recovery->sync($uncertainSubmitted, $settings);
checkRetry(end($recoveryClient->requests)['payload']['invoiceNumber']
    === $uncertainSubmitted->getRawOriginal('fiscal_invoice_number')
    && $uncertainSubmitted->getRawOriginal('fiscal_invoice_number') === $uncertainSubmitted->request_payload['invoiceHeader']['invoiceNumber']
    && end($recoveryClient->requests)['payload']['invoiceNumber'] !== $uncertainSale->invoice_no,
    'Individual sync looks up eis_sales.fiscal_invoice_number, not the POS transaction number');
checkRetry($syncResult['receipt_available'] && $syncResult['receipt_number'] === 'SYNC-RECEIPT'
    && $uncertainSubmitted->fresh()->fiscal_invoice_number === $uncertainSubmitted->eis_invoice_number
    && $uncertainSubmitted->fresh()->receipt_number === 'SYNC-RECEIPT',
    'Individual sync saves fiscal invoice identity and the official receipt number');
$afterManualSync = $uncertainSubmitted->fresh();
checkRetry($afterManualSync->status === 'submitted'
    && $afterManualSync->submitted_at->format('Y-m-d H:i:s') === '2026-10-25 21:52:32',
    'Individual sync updates submitted status and EIS submission time');
checkRetry($afterManualSync->response_payload === $syncResult['response'],
    'Individual sync stores the exact EIS lookup response');
checkRetry($afterManualSync->qr_code === 'https://eservices.mra.mw/doc/v/?vc=SYNC-RECEIPT&c=SYNC-CODE'
    && $afterManualSync->receipt_signature === 'SYNC-CODE'
    && $afterManualSync->error_message === null,
    'Individual sync refreshes QR, receipt signature and clears stale error');
checkRetry($afterManualSync->receipt_signature !== $beforeManualSync->receipt_signature
    && $afterManualSync->is_fiscalized === $beforeManualSync->is_fiscalized
    && $afterManualSync->fiscalized_at === $beforeManualSync->fiscalized_at
    && !$uncertainSale->fresh()->is_fiscalized,
    'Individual sync leaves fiscalization flags and transactions unchanged');

$uncertainSubmitted->update([
    'receipt_number' => 'STALE-RECEIPT',
    'receipt_signature' => 'STALE-SIGNATURE',
    'qr_code' => 'https://eis.invalid/stale',
]);
$recoveryClient->responder = function (EisSetting $setting, string $operation, array $payload) {
    $tracking = EisSale::where('eis_invoice_number', $payload['invoiceNumber'])->firstOrFail();
    $saved = $tracking->request_payload;
    return ['outcome' => 'accepted', 'response' => ['statusCode' => 1, 'data' => [
        'invoiceHeader' => $saved['invoiceHeader'],
        'invoiceLineItems' => $saved['invoiceLineItems'],
        'invoiceSummary' => $saved['invoiceSummary'],
        'validationURL' => null,
        'dateSubmitted' => '2026-10-25T21:52:32.97',
    ]]];
};
$noReceiptSync = $recovery->sync($uncertainSubmitted->fresh(), $settings);
$afterNoReceiptSync = $uncertainSubmitted->fresh();
checkRetry(!$noReceiptSync['receipt_available'] && $noReceiptSync['offline_receipt_preserved']
    && $afterNoReceiptSync->receipt_number === 'STALE-RECEIPT'
    && $afterNoReceiptSync->receipt_signature === 'STALE-SIGNATURE'
    && $afterNoReceiptSync->qr_code === 'https://eis.invalid/stale'
    && $afterNoReceiptSync->response_payload === $noReceiptSync['response'],
    'Offline-signed invoice keeps its original QR and receipt when EIS returns no validation URL');
$afterNoReceiptSync->update(['qr_code' => null, 'receipt_signature' => null]);
$offlineFixtureUrl = 'https://eis.invalid/offline?TI=X&N=1&S=OFFLINE-SIG';
$restorePayload = $afterNoReceiptSync->request_payload;
$restorePayload['invoiceSummary']['validationURL'] = $offlineFixtureUrl;
$afterNoReceiptSync->update(['request_payload' => $restorePayload]);
$recovery->sync($afterNoReceiptSync->fresh(), $settings);
checkRetry($afterNoReceiptSync->fresh()->qr_code === $offlineFixtureUrl
    && $afterNoReceiptSync->fresh()->receipt_signature === 'OFFLINE-SIG',
    'Offline-signed invoice with a cleared QR is restored from its saved offline URL and fills the S signature');
$onlinePayload = $afterNoReceiptSync->request_payload;
$offlinePayload = $onlinePayload;
unset($onlinePayload['invoiceSummary']['offlineSignature'], $onlinePayload['invoiceSummary']['validationURL'], $onlinePayload['offlineSignatureData']);
$afterNoReceiptSync->update(['request_payload' => $onlinePayload, 'qr_code' => 'https://eis.invalid/stale']);
$onlineSync = $recovery->sync($afterNoReceiptSync->fresh(), $settings);
$afterOnlineSync = $afterNoReceiptSync->fresh();
checkRetry(!$onlineSync['offline_receipt_preserved'] && $afterOnlineSync->receipt_number === 'STALE-RECEIPT'
    && $afterOnlineSync->receipt_signature === 'OFFLINE-SIG' && $afterOnlineSync->qr_code === 'https://eis.invalid/stale',
    'Online invoice preserves saved receipt fields when EIS returns no validation URL');
$afterOnlineSync->update(['request_payload' => $offlinePayload]);
$afterNoReceiptSync = $afterOnlineSync->fresh();

$validSyncResponder = $recoveryClient->responder;
$beforeMismatchSync = $afterNoReceiptSync->getRawOriginal();
$recoveryClient->responder = function (EisSetting $setting, string $operation, array $payload) use ($validSyncResponder) {
    $lookup = $validSyncResponder($setting, $operation, $payload);
    $lookup['response']['data']['invoiceSummary']['invoiceTotal']++;
    return $lookup;
};
try {
    $recovery->sync($afterNoReceiptSync, $settings);
    throw new RuntimeException('Mismatched invoice was synchronized');
} catch (\UnexpectedValueException $exception) {
    checkRetry($afterNoReceiptSync->fresh()->getRawOriginal() === $beforeMismatchSync,
        'Individual sync rejects mismatched totals without changing any saved field');
}
$recoveryClient->responder = function (EisSetting $setting, string $operation, array $payload) use ($validSyncResponder, $afterNoReceiptSync) {
    $lookup = $validSyncResponder($setting, $operation, $payload);
    DB::table('eis_sales')->where('id', $afterNoReceiptSync->id)
        ->update(['fiscal_invoice_number' => 'CHANGED-DURING-LOOKUP']);
    return $lookup;
};
try {
    $recovery->sync($afterNoReceiptSync, $settings);
    throw new RuntimeException('Concurrent invoice identity change was overwritten');
} catch (\UnexpectedValueException $exception) {
    checkRetry($afterNoReceiptSync->fresh()->getRawOriginal('fiscal_invoice_number') === 'CHANGED-DURING-LOOKUP'
        && $afterNoReceiptSync->fresh()->response_payload === $afterNoReceiptSync->response_payload,
        'Individual sync refuses to overwrite an invoice identity changed during lookup');
}
DB::table('eis_sales')->where('id', $afterNoReceiptSync->id)
    ->update(['fiscal_invoice_number' => $beforeMismatchSync['fiscal_invoice_number']]);

$recoveryClient->responder = function (EisSetting $setting, string $operation, array $payload) {
    return ['outcome' => 'accepted', 'response' => ['statusCode' => 1, 'data' => [
        'invoiceHeader' => ['invoiceNumber' => $payload['invoiceNumber'], 'siteId' => 'SITE-7', 'sellerTIN' => 'FIXTURE',
            'invoiceDateTime' => '2026-10-25T21:52:32.97'],
        'invoiceLineItems' => [], 'invoiceSummary' => ['invoiceTotal' => 0, 'totalVAT' => 0],
    ]]];
};
$mismatchSale = submissionFixture();
$mismatchError = failSubmission($service, $mismatchSale, $settings);
checkRetry($mismatchError->getCode() !== EisSaleException::DUPLICATE_INVOICE
    && (EisSale::where('transaction_id', $mismatchSale->id)->value('response_payload') !== null),
    'Ambiguous invoice with nonmatching lookup stays unresolved');
$submittedCount = count($client->payloads);
try { $service->submit($mismatchSale, $settings); throw new RuntimeException('Unverified invoice was resubmitted'); }
catch (EisSaleException $e) { checkRetry(true, 'Unverified invoice is blocked before retry'); }
checkRetry(count($client->payloads) === $submittedCount,
    'Lookup mismatch prevents a subsequent EIS submission');
$recoveryClient->responder = null;
$client->response = ['success' => false, 'error' => 'Fixture offline'];
// Interrupted pending attempt, legacy failed tracking and retry-table-only history.
foreach (['pending', 'failed', 'retry-table'] as $history) {
    $sale = submissionFixture();
    if ($history === 'retry-table') {
        DB::table('eis_failed_transactions')->insert(['business_id' => 7, 'transaction_id' => $sale->id, 'attempts' => 1]);
    } else {
        EisSale::create(['business_id' => 7, 'transaction_id' => $sale->id, 'status' => $history,
            'request_payload' => $history === 'pending' ? $first : null]);
    }
    failSubmission($service, $sale, $settings);
    checkRetry(end($client->payloads)['invoiceSummary']['offlineSignature'] === 'fixture-signature', 'Signature for '.$history.' history');
}
// A pending record without an earlier request is still a first submission.
$fresh = submissionFixture();
EisSale::create(['business_id' => 7, 'transaction_id' => $fresh->id, 'status' => 'pending', 'fiscal_invoice_number' => 'RESERVED-1']);
$client->response = ['success' => true, 'data' => ['statusCode' => 0, 'remark' => 'Fixture validation rejection']];
failSubmission($service, $fresh, $settings);
checkRetry(! isset(end($client->payloads)['invoiceSummary']['offlineSignature']), 'Unattempted pending record omits signature');
checkRetry(EisSale::where('transaction_id', $fresh->id)->first()->fiscal_invoice_number === 'RESERVED-1', 'Reserved generated fiscal identity retained');
$client->response = ['success' => true, 'data' => ['statusCode' => 1, 'data' => ['validationURL' => 'https://eis.invalid/validate?vc=FISCAL-RECEIPT']]];
$submitted = $service->submit($transaction, $settings);
checkRetry($submitted->status === 'submitted' && $submitted->fiscal_invoice_number === 'GENERATED-1'
    && $submitted->receipt_number === 'FISCAL-RECEIPT',
    'Success preserves the EIS invoice identity and stores the official receipt separately');
checkRetry($submitted->eis_invoice_number === 'GENERATED-1' && $transaction->fresh()->is_fiscalized
    && $transaction->fresh()->fiscal_invoice_number === 'GENERATED-1',
    'Generated invoice identity is projected to POS separately from the receipt');
$count = count($client->payloads);
DB::table('transactions')->where('id', $transaction->id)->update([
    'is_fiscalized' => false, 'fiscal_invoice_number' => null, 'receipt_number' => null,
]);
$service->submit($transaction, $settings);
checkRetry(count($client->payloads) === $count, 'Submitted sale never sent again');
checkRetry($transaction->fresh()->is_fiscalized
    && $transaction->fresh()->receipt_number === 'FISCAL-RECEIPT',
    'Already submitted sale repairs interrupted local receipt projection without another HTTP request');

$schema->create('saved_sale_split_operations', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->integer('source_id');
    $t->integer('result_id')->nullable(); $t->text('allocation_json'); $t->text('response_json');
});
function splitSubmissionFixture(string $billNumber, bool $oldReservation = false): Transaction {
    $sale = submissionFixture();
    DB::table('transactions')->where('id', $sale->id)->update(['invoice_no' => $billNumber]);
    $sale->refresh();
    DB::table('saved_sale_split_operations')->insert([
        ['business_id' => 7, 'source_id' => 9999, 'result_id' => $sale->id,
            'allocation_json' => json_encode($oldReservation ? ['invoice_number_rule' => 'eis',
                'eis_invoice_number' => 'OBSOLETE-RESERVATION'] : []),
            'response_json' => json_encode(['bill_b' => ['invoice_no' => $billNumber]])],
        ['business_id' => 7, 'source_id' => $sale->id, 'result_id' => null,
            'allocation_json' => '{"kind":"checkout"}', 'response_json' => '{"paid":true}'],
    ]);
    return $sale;
}
$client->response = ['success' => false, 'error' => 'Fixture offline'];
$split = splitSubmissionFixture('INV-00042');
$generatedBefore = $service->generated;
checkRetry(!EisSale::where('transaction_id', $split->id)->exists(), 'Split has no EIS record before submission');
failSubmission($service, $split, $settings);
$splitNumber = 'GENERATED-'.($generatedBefore + 1);
checkRetry(end($client->payloads)['invoiceHeader']['invoiceNumber'] === $splitNumber
    && $service->generated === $generatedBefore + 1, 'First paid split submission generates EIS identity on tracking creation');
failSubmission($service, $split, $settings);
checkRetry(end($client->payloads)['invoiceHeader']['invoiceNumber'] === $splitNumber
    && $service->generated === $generatedBefore + 1, 'Split retry retains the created EIS identity');
checkRetry(end($client->payloads)['invoiceSummary']['offlineSignature'] === 'fixture-signature',
    'Split retry follows normal signed retry rules');
$client->response = ['success' => true, 'data' => ['statusCode' => 1,
    'data' => ['validationURL' => 'https://eis.invalid/validate?vc=SPLIT-RECEIPT']]];
$submittedSplit = $service->submit($split, $settings);
checkRetry($submittedSplit->eis_invoice_number === $splitNumber
    && $submittedSplit->receipt_number === 'SPLIT-RECEIPT', 'Submitted split keeps invoice identity separate from fiscal receipt');
checkRetry($submittedSplit->invoice_number === 'INV-00042' && $split->fresh()->invoice_no === 'INV-00042',
    'EIS invoice_number always matches system transaction number');

$client->response = ['success' => false, 'error' => 'Fixture offline'];
$legacy = splitSubmissionFixture('INV-00043', true);
failSubmission($service, $legacy, $settings);
checkRetry($service->generated === $generatedBefore + 2
    && end($client->payloads)['invoiceHeader']['invoiceNumber'] === 'GENERATED-'.($generatedBefore + 2),
    'Obsolete audit reservations are ignored and never influence new EIS creation');
$unpaid = splitSubmissionFixture('INV-00044');
DB::table('transactions')->where('id', $unpaid->id)->update(['payment_status' => 'due']);
$count = count($client->payloads);
try { $service->submit($unpaid, $settings); throw new RuntimeException('Unpaid split was not blocked'); }
catch (App\Services\Sales\SavedSplitException $e) {
    checkRetry($e->errorCode === 'split_write_fenced', 'Unpaid split is fenced from fiscal creation');
}
checkRetry(!EisSale::where('transaction_id', $unpaid->id)->exists() && count($client->payloads) === $count
    && $service->generated === $generatedBefore + 2, 'Unpaid split neither generates EIS number nor creates tracking or sends request');
// A transport failure must return to persisted tracking, not silently replay
// the unsigned first payload in an internal HTTP retry.
$container['config']['eis.base_url'] = 'https://eis.invalid';
$container['config']['eis.http_retries'] = 3;
$http = new Illuminate\Http\Client\Factory();
$http->preventStrayRequests();
$container->instance(Illuminate\Http\Client\Factory::class, $http);
Illuminate\Support\Facades\Http::clearResolvedInstance(Illuminate\Http\Client\Factory::class);
$requests = 0;
Illuminate\Support\Facades\Http::fake(function () use (&$requests) {
    ++$requests;
    throw new Illuminate\Http\Client\ConnectionException('Fixture connection failure');
});
$health = new class extends Modules\EIS\Services\Http\EisHealthService {
    public function isOnline(int $businessId, string $token): bool { return true; }
};
$httpSettings = (object) ['business_id' => 7, 'jwt_token' => 'fixture', 'secret_key' => 'fixture', 'branch_id' => 'SITE-7', 'tpin' => 'FIXTURE'];
$failure = (new EisSaleClient($health))->submit($first, $httpSettings);
checkRetry($requests === 1, 'Client sends only one request per tracked attempt');
checkRetry(! $failure['success'] && $failure['error_code'] === 'CONNECTION_ERROR', 'Transport failure returns for persisted retry');
$requests = 0;
$duplicateHttp = new Illuminate\Http\Client\Factory();
$duplicateHttp->preventStrayRequests();
$container->instance(Illuminate\Http\Client\Factory::class, $duplicateHttp);
Illuminate\Support\Facades\Http::clearResolvedInstance(Illuminate\Http\Client\Factory::class);
Illuminate\Support\Facades\Http::fake(function () use (&$requests) {
    ++$requests;
    return Illuminate\Support\Facades\Http::response(
        ['statusCode' => -2, 'remark' => 'Invoice Number already exists', 'data' => null, 'errors' => []],
        400
    );
});
$duplicateResponse = (new EisSaleClient($health))->submit($first, $httpSettings);
checkRetry($requests === 1 && ! $duplicateResponse['success']
    && $duplicateResponse['error_code'] === 'DUPLICATE_INVOICE'
    && ! $duplicateResponse['retryable'],
    'Duplicate EIS invoice response is marked for verification, not automatic retry');
$foreignSettings = new EisSetting();
$foreignSettings->business_id = 8;
$beforeGenerated = $service->generated;
$beforeRequests = count($client->payloads);
try {
    $service->submit(submissionFixture(), $foreignSettings);
    throw new RuntimeException('Expected foreign business settings rejection');
} catch (InvalidArgumentException $e) {
    checkRetry($service->generated === $beforeGenerated && count($client->payloads) === $beforeRequests,
        'Foreign settings fail before invoice allocation or HTTP');
}
$wrongPayload = $first;
$wrongPayload['invoiceHeader']['siteId'] = 'OTHER';
$failure = (new EisSaleClient($health))->submit($wrongPayload, $httpSettings);
checkRetry($failure['error_code'] === 'TERMINAL_MISMATCH' && $requests === 1,
    'HTTP client rejects wrong terminal site without sending');
$wrongPayload = $first;
$wrongPayload['invoiceHeader']['sellerTIN'] = 'OTHER';
$failure = (new EisSaleClient($health))->submit($wrongPayload, $httpSettings);
checkRetry($failure['error_code'] === 'TERMINAL_MISMATCH' && $requests === 1,
    'HTTP client rejects wrong terminal TIN without sending');
$callerSettings = clone $settings;
$callerSettings->tpin = 'STALE-OR-FORGED-TIN';
$client->response = ['success' => false, 'error' => 'Fixture offline'];
failSubmission($service, submissionFixture(), $callerSettings);
checkRetry(end($client->payloads)['invoiceHeader']['sellerTIN'] === 'FIXTURE',
    'Submission uses persisted terminal credentials, not caller overrides');
$foreignTransaction = submissionFixture();
$beforeRequests = count($client->payloads);
$beforeTracking = EisSale::count();
$beforeFailures = DB::table('eis_failed_transactions')->count();
$job = new Modules\EIS\Jobs\SubmitOfflineSalesJob($foreignTransaction->id, 8);
$job->handle($service);
checkRetry(count($client->payloads) === $beforeRequests && EisSale::count() === $beforeTracking
    && DB::table('eis_failed_transactions')->count() === $beforeFailures,
    'Queued business/transaction mismatch neither submits nor creates foreign retry tracking');
$job->failed(new RuntimeException('Fixture mismatch'));
checkRetry(DB::table('eis_failed_transactions')->count() === $beforeFailures,
    'Failed callback cannot create retry tracking for a foreign business transaction');
$deferredTransaction = submissionFixture();
$beforeRequests = count($client->payloads);
$deferredJob = new class($deferredTransaction->id, 7) extends Modules\EIS\Jobs\SubmitOfflineSalesJob {
    public bool $deferred = false;
    protected function deferUntilCommit(): void { $this->deferred = true; }
};
DB::beginTransaction();
$deferredJob->handle($service);
checkRetry(count($client->payloads) === $beforeRequests,
    'Submission job does not reach EIS inside the caller transaction');
checkRetry($deferredJob->deferred, 'Submission job schedules its EIS work for after commit');
DB::rollBack();
$container->instance('db.schema', $schema);
Illuminate\Support\Facades\Schema::clearResolvedInstance('db.schema');
$projectionMigration = require dirname(__DIR__, 2).'\Modules\EIS\Database\Migrations\2026_10_23_211000_add_transaction_fiscal_receipt_projection.php';
$schema->table('transactions', function (Blueprint $table) {
    $table->dropColumn(['is_fiscalized', 'fiscal_invoice_number', 'receipt_number']);
});
$projectionMigration->up();
checkRetry($schema->hasColumn('transactions', 'is_fiscalized')
    && $schema->hasColumn('transactions', 'fiscal_invoice_number')
    && $schema->hasColumn('transactions', 'receipt_number'), 'Migration restores all missing transaction fiscal columns');
$service->submit($transaction, $settings);
checkRetry($transaction->fresh()->is_fiscalized && $transaction->fresh()->receipt_number === 'FISCAL-RECEIPT',
    'Migration plus submitted retry restores receipt after missing-column failure');
$projectionMigration->up();
$projectionMigration->down();
checkRetry($transaction->fresh()->receipt_number === 'FISCAL-RECEIPT' && $schema->hasColumn('transactions', 'is_fiscalized'),
    'Repeated migration and compatibility rollback preserve existing receipt data');
DB::table('business_locations')->insert([
    ['id' => 1, 'business_id' => 7, 'eis_site_id' => 'OTHER-SITE'],
    ['id' => 2, 'business_id' => 8, 'eis_site_id' => 'SITE-7'],
]);
foreach ([1, 2] as $locationId) {
    $sale = submissionFixture();
    DB::table('transactions')->where('id', $sale->id)->update(['location_id' => $locationId]);
    $beforeGenerated = $service->generated;
    $beforeRequests = count($client->payloads);
    try {
        $service->submit($sale, $settings);
        throw new RuntimeException('Expected terminal location rejection');
    } catch (\Exception $e) {
        checkRetry($service->generated === $beforeGenerated
            && count($client->payloads) === $beforeRequests && !EisSale::where('transaction_id', $sale->id)->exists(),
            'Wrong site or foreign business location fails before tracking, counters or HTTP');
    }
}
$generator = new InvoiceNumberGenerator();
$nextCount = new ReflectionMethod(SaleSubmissionService::class, 'getTransactionCount');
$nextCount->setAccessible(true);
Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-10-23 12:00:00'));
try {
    checkRetry($nextCount->invoke($service, 7) === 1, 'First invoice for the terminal today starts at one');
    $number = $generator->generateInvoiceNumber(7, 1, 3, 100);
    checkRetry($generator->parseInvoiceNumber($number)['count'] === 1,
        'Encoded invoice count is the sequence alone, without the business ID');
    $todayPrefix = implode('-', array_slice(explode('-', $number), 0, 3)).'-';
    foreach ([
        [7, $number],
        [7, $todayPrefix.$generator->base10ToBase64(2)],
        [7, $generator->base10ToBase64(100).'-'.$generator->base10ToBase64(4).'-'.$generator->base10ToBase64($generator->toJulianDate(now())).'-B'],
        [7, $generator->base10ToBase64(101).'-'.$generator->base10ToBase64(3).'-'.$generator->base10ToBase64($generator->toJulianDate(now())).'-B'],
        [8, $number],
    ] as [$businessId, $fiscalNumber]) {
        DB::table('eis_sales')->insert([
            'business_id' => $businessId, 'transaction_id' => fixtureSale()->id,
            'fiscal_invoice_number' => $fiscalNumber, 'status' => 'failed',
            'eis_invoice_number' => $fiscalNumber,
            'eis_setting_id' => 1, 'location_id' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    checkRetry($generator->getDailyCount(7) === 2 && $nextCount->invoke($service, 7) === 3,
        'Daily counter includes reserved/failed invoices but excludes other businesses, terminals and taxpayers');
    $client->response = ['success' => true, 'data' => ['statusCode' => 1,
        'data' => ['validationURL' => 'https://eis.invalid/validate?vc=DAILY-RECEIPT']]];
    $realService = new SaleSubmissionService($transformer, $client, new SaleResponseService(), $generator);
    $realSale = submissionFixture();
    $issued = $realService->submit($realSale, $settings);
    checkRetry($generator->parseInvoiceNumber($issued->eis_invoice_number)['count'] === 3
        && str_starts_with($issued->eis_invoice_number, $todayPrefix),
        'Real submission changes only the final component to this terminal daily count plus one');
    checkRetry($issued->fiscal_invoice_number === $issued->eis_invoice_number
        && $issued->receipt_number === 'DAILY-RECEIPT'
        && $generator->getDailyCount(7, 1) === 3 && $nextCount->invoke($service, 7) === 4,
        'Confirmed receipts remain included in daily invoice allocation');
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-10-24 00:00:00'));
    checkRetry($nextCount->invoke($service, 7) === 1, 'Terminal invoice count resets at the next day');
    DB::table('eis_terminal_configurations')->where('configuration_id', 1)->update(['terminal_position' => 4]);
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-10-23 12:00:00'));
    checkRetry($nextCount->invoke($service, 7) === 2, 'Replacing terminal uses the replacement terminal daily count');
} finally {
    Carbon\Carbon::setTestNow();
}
$listenerReflection = new ReflectionClass(Modules\EIS\Listeners\DispatchEisSaleJob::class);
checkRetry(!$listenerReflection->implementsInterface(Illuminate\Contracts\Queue\ShouldQueue::class),
    'Checkout listener dispatches the submission job without blocking checkout on EIS');
$dispatched = [];
$container->instance(Illuminate\Contracts\Bus\Dispatcher::class, new class($dispatched) implements Illuminate\Contracts\Bus\Dispatcher {
    public function __construct(public array &$dispatched) {}
    public function dispatch($command) { $this->dispatched[] = $command; return $command; }
    public function dispatchSync($command, $handler = null) { throw new RuntimeException('Unexpected synchronous job dispatch'); }
    public function dispatchNow($command, $handler = null) { throw new RuntimeException('Unexpected direct job execution'); }
    public function hasCommandHandler($command) { return false; }
    public function getCommandHandler($command) { return null; }
    public function getMiddleware($command) { return []; }
    public function pipeThrough(array $pipes) {}
    public function map(array $map) {}
});
$directTransaction = submissionFixture();
(new Modules\EIS\Listeners\DispatchEisSaleJob())->handle(new Modules\EIS\Events\SaleCompleted($directTransaction));
checkRetry(count($dispatched) === 1
    && $dispatched[0] instanceof Modules\EIS\Jobs\SubmitOfflineSalesJob
    && $dispatched[0]->onQueue('eis-submission') === $dispatched[0],
    'Checkout queues EIS submission in the background for a paid final sale');
$interrupted = submissionFixture();
$client->beforeResponse = function () {
    throw new RuntimeException('Fixture interrupted before EIS response');
};
try {
    $service->submit($interrupted, $settings);
    throw new RuntimeException('Expected interrupted submission');
} catch (RuntimeException $exception) {
    checkRetry($exception->getMessage() === 'Fixture interrupted before EIS response',
        'Unexpected interruption remains visible to the caller');
} finally {
    $client->beforeResponse = null;
}
$pending = EisSale::where('transaction_id', $interrupted->id)->firstOrFail();
$pendingPayload = $pending->request_payload;
$generationCount = $transformer->offlineQrGenerations;
checkRetry($pending->status === 'pending' && $pending->qr_code === 'https://eis.invalid/offline'
    && $pending->receipt_number === $pending->fiscal_invoice_number
    && !empty($pendingPayload['offlineSignatureData']['offlineDataSignature']),
    'Interrupted attempt leaves a pending invoice with complete offline retry metadata');
$client->response = ['success' => false, 'error' => 'Fixture offline'];
failSubmission($service, $interrupted, $settings);
$retriedPayload = end($client->payloads);
checkRetry($retriedPayload['offlineSignatureData'] === $pendingPayload['offlineSignatureData']
    && $retriedPayload['invoiceHeader']['invoiceNumber'] === $pendingPayload['invoiceHeader']['invoiceNumber']
    && $transformer->offlineQrGenerations === $generationCount,
    'Interrupted pending retry reuses the saved invoice, signature and URL without regeneration');
checkRetry(json_decode(DB::table('eis_failed_transactions')->where('transaction_id', $interrupted->id)->value('payload'), true)['offlineSignatureData']
    === $pendingPayload['offlineSignatureData'],
    'Failure history preserves the pending invoice offline metadata for future retries');
$nullReceiptSale = submissionFixture();
$client->response = ['success' => true, 'data' => ['statusCode' => 1,
    'data' => ['receiptNumber' => null, 'validationURL' => null]]];
$nullReceiptResult = $service->submit($nullReceiptSale, $settings);
checkRetry($nullReceiptResult->status === 'submitted'
    && $nullReceiptResult->receipt_number === $nullReceiptResult->fiscal_invoice_number
    && $nullReceiptResult->qr_code === 'https://eis.invalid/offline',
    'Successful submission with null receipt and URL preserves saved provisional receipt and offline URL');
$offlineMode = new ReflectionMethod(Modules\EIS\Jobs\SubmitOfflineSalesJob::class, 'requiresOfflineSubmission');
$offlineMode->setAccessible(true);
$syncJob = new Illuminate\Queue\Jobs\SyncJob($container, '{}', 'sync', 'eis-submission');
foreach ([[true, true, false], [true, false, true], [false, true, true], [false, false, true]] as [$original, $synchronous, $expectedOffline]) {
    $job = new Modules\EIS\Jobs\SubmitOfflineSalesJob(1, 7, $original);
    $job = unserialize(serialize($job));
    if ($synchronous) { $job->setJob($syncJob); }
    checkRetry($offlineMode->invoke($job) === $expectedOffline,
        'Submission mode distinguishes original synchronous checkout from later payments and workers');
}
$checkoutTransaction = submissionFixture();
(new Modules\EIS\Listeners\DispatchEisSaleJob())->handle(
    new Modules\EIS\Events\SaleCompleted($checkoutTransaction, true)
);
$checkoutJob = end($dispatched);
$checkoutJob->setJob($syncJob);
checkRetry(!$offlineMode->invoke($checkoutJob), 'Original checkout event propagates its online eligibility through the listener');
$deferredTransaction = submissionFixture();
$deferredResult = $service->submit($deferredTransaction, $settings, true);
$firstDeferredPayload = end($client->payloads);
checkRetry($firstDeferredPayload['invoiceSummary']['offlineSignature'] === 'fixture-signature'
    && $firstDeferredPayload['invoiceSummary']['validationURL'] === 'https://eis.invalid/offline'
    && $firstDeferredPayload['offlineSignatureData']['offlineDataSignature'] === 'fixture-signature',
    'Deferred first submission includes complete offline metadata without requiring an earlier failure');
checkRetry($deferredResult->status === 'submitted',
    'Offline first attempt still uses normal successful response handling');
Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-10-26 13:10:00'));
try {
    $datedSale = submissionFixture();
    DB::table('transactions')->where('id', $datedSale->id)->update(['transaction_date' => '2026-09-01 09:00:00']);
    $client->response = ['success' => false, 'error' => 'Fixture offline'];
    failSubmission($service, $datedSale, $settings);
    $datedTracking = EisSale::where('transaction_id', $datedSale->id)->firstOrFail();
    $invoiceDate = end($client->payloads)['invoiceHeader']['invoiceDateTime'];
    checkRetry($invoiceDate === $datedTracking->created_at->toIso8601String()
        && Carbon\Carbon::parse($invoiceDate)->eq(now()),
        'A new fiscal invoice uses current EIS creation time instead of the historical sale date');
    checkRetry($datedSale->fresh()->transaction_date === '2026-09-01 09:00:00',
        'Fiscal creation does not change the original POS transaction date');
    checkRetry(Carbon\Carbon::parse(end($transformer->signatureDates))->eq(Carbon\Carbon::parse($invoiceDate)),
        'Pending offline metadata is generated with the new fiscal invoice time');
    Carbon\Carbon::setTestNow(now()->addHour());
    failSubmission($service, $datedSale, $settings);
    checkRetry(end($client->payloads)['invoiceHeader']['invoiceDateTime'] === $invoiceDate,
        'Delayed retry retains the saved invoice timestamp rather than refreshing it');
    $offlineSale = submissionFixture();
    DB::table('transactions')->where('id', $offlineSale->id)->update(['transaction_date' => '2026-09-02 09:00:00']);
    try { $service->submit($offlineSale, $settings, true); } catch (EisSaleException $exception) {}
    checkRetry(Carbon\Carbon::parse(end($transformer->signatureDates))->eq(now())
        && Carbon\Carbon::parse(end($client->payloads)['invoiceHeader']['invoiceDateTime'])->eq(now()),
        'Offline first attempt uses the current fiscal creation time for both header and signature');
} finally {
    Carbon\Carbon::setTestNow();
}
echo "PASS: {$checks} EIS submission retry checks (isolated database, no network).\n";