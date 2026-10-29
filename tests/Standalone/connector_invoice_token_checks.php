<?php

// SQLite memory only: no application kernel, live credentials, database or network.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Services\Sales\SavedSaleWriteGuard;
use App\Services\Sales\SavedSplitException;
use App\Transaction;
use App\Utils\Util;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Modules\Connector\Transformers\SellResource;

$container = new Container();
Container::setInstance($container);
$capsule = new Capsule($container);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setEventDispatcher(new Illuminate\Events\Dispatcher($container));
$capsule->setAsGlobal();
$capsule->bootEloquent();
$container->instance('db', $capsule->getDatabaseManager());
$urls = Mockery::mock();
$urls->shouldReceive('route')->andReturnUsing(fn ($name, $parameters) => 'https://example.invalid/'.$name.'/'.$parameters['token']);
$container->instance('url', $urls);
$db = $capsule->getConnection();
$schema = $db->getSchemaBuilder();
$schema->create('transactions', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->integer('location_id')->default(1);
    $t->string('type')->default('sell'); $t->string('status'); $t->string('payment_status')->default('due');
    $t->boolean('is_quotation')->default(false); $t->string('invoice_token')->nullable();
    $t->string('invoice_no'); $t->decimal('final_total', 20, 4)->default(100); $t->timestamps();
});
$schema->create('saved_sale_split_operations', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->integer('source_id'); $t->integer('result_id');
});
$schema->create('business_locations', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->integer('invoice_layout_id')->nullable();
});
$schema->create('invoice_layouts', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->boolean('is_default');
});
$db->table('business_locations')->insert(['id' => 1, 'business_id' => 7]);
$checks = 0;
function tokenCheck(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    ++$checks;
}
function tokenSnapshot($db): array {
    return array_map(fn ($table) => $db->table($table)->orderBy('id')->get()->toJson(),
        ['transactions', 'saved_sale_split_operations']);
}

foreach (['draft', 'final'] as $status) {
    foreach ([false, true] as $split) {
        foreach ([null, ''] as $missingToken) {
            $id = $db->table('transactions')->insertGetId(['business_id' => 7, 'status' => $status,
                'invoice_no' => 'POS-'.($checks + 1), 'invoice_token' => $missingToken, 'is_quotation' => $status === 'draft',
                'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
            if ($split) {
                $db->table('saved_sale_split_operations')->insert(['business_id' => 7, 'source_id' => $id, 'result_id' => 999]);
            }
            $sale = Transaction::findOrFail($id);
            $sale->invoice_token = 'unsafe-model-save';
            try { $sale->save(); throw new RuntimeException('Expected existing sale writer fence'); }
            catch (SavedSplitException $e) {
                tokenCheck($e->errorCode === ($split ? 'split_write_fenced' : 'draft_write_requires_transaction'),
                    'Original serialization model-save failure reproduced');
            }
            $sale = Transaction::findOrFail($id);
            $sale->setRelation('sell_lines', collect());
            $before = (array) $db->table('transactions')->where('id', $id)->first();
            $data = (new SellResource($sale))->toArray(Request::create('/connector/api/sell'));
            $after = (array) $db->table('transactions')->where('id', $id)->first();
            tokenCheck(is_string($data['invoice_token']) && strlen($data['invoice_token']) === 32,
                'History serialization initializes and exposes token');
            tokenCheck($data['invoice_token'] === $after['invoice_token'], 'Response token matches committed token');
            tokenCheck(array_diff_assoc($after, $before) === ['invoice_token' => $data['invoice_token']],
                'Only invoice token changes, not totals, statuses, invoice number or timestamps');
            tokenCheck($data['invoice_url'] === 'https://example.invalid/'.($status === 'draft' ? 'show_quote' : 'show_invoice').'/'.$data['invoice_token'],
                'Quote and invoice routes preserved');
            tokenCheck($data['payment_link'] === ($status === 'final' ? 'https://example.invalid/invoice_payment/'.$data['invoice_token'] : ''),
                'Payment link uses the same token only for final due sale');
            $snapshot = tokenSnapshot($db);
            tokenCheck((new Util())->getInvoiceUrl($id, 7) === $data['invoice_url'], 'Existing token reused');
            tokenCheck((new Util())->getInvoicePaymentLink($id, 7) === $data['payment_link'], 'Payment helper reuses same token');
            $stale = (new SellResource($sale))->toArray(Request::create('/connector/api/sell'));
            tokenCheck($stale['invoice_token'] === $data['invoice_token'], 'Stale missing-token model reuses the locked current token');
            tokenCheck(tokenSnapshot($db) === $snapshot, 'Repeated invoice reads do not mutate persisted state');
        }
    }
}

$id = $db->table('transactions')->insertGetId(['business_id' => 7, 'status' => 'final', 'invoice_no' => 'PAYMENT-FIRST']);
tokenCheck(str_starts_with((new Util())->getInvoicePaymentLink($id, 7), 'https://example.invalid/invoice_payment/'),
    'Payment-first token initialization also works outside a sale-edit transaction');
$snapshot = tokenSnapshot($db);
try { SavedSaleWriteGuard::invoiceToken($db, $id, 8); throw new RuntimeException('Expected tenant boundary'); }
catch (Illuminate\Database\Eloquent\ModelNotFoundException $e) { tokenCheck(true, 'Foreign tenant cannot initialize token'); }
tokenCheck(tokenSnapshot($db) === $snapshot, 'Rejected tenant leaves all state unchanged');
$id = $db->table('transactions')->insertGetId(['business_id' => 7, 'status' => 'final',
    'payment_status' => 'paid', 'invoice_no' => 'PAID-NO-TOKEN']);
tokenCheck((new Util())->getInvoicePaymentLink($id, 7) === '', 'Paid sales retain empty payment links');
$id = $db->table('transactions')->insertGetId(['business_id' => 7, 'status' => 'draft', 'invoice_no' => 'TOKEN-FAILURE']);
$snapshot = tokenSnapshot($db);
$db->statement("CREATE TRIGGER token_failure BEFORE UPDATE ON transactions BEGIN SELECT RAISE(ABORT, 'token failure'); END");
try { (new Util())->getInvoiceUrl($id, 7); throw new RuntimeException('Expected token storage failure'); }
catch (Illuminate\Database\QueryException $e) { tokenCheck(str_contains($e->getMessage(), 'token failure'), 'Token storage error is surfaced'); }
tokenCheck(tokenSnapshot($db) === $snapshot, 'Failed initialization rolls back without changing the sale');
tokenCheck($db->transactionLevel() === 0, 'Token initialization leaves no open transaction');
Mockery::close();
echo "PASS: {$checks} Connector invoice-token checks (SQLite memory only).\n";
