<?php

// Isolated SQLite fixture; no application boot, live data or external calls.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Transaction;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Modules\Connector\Transformers\SellResource;

$container = new Container();
Container::setInstance($container);
$capsule = new Capsule($container);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$container->instance('db', $capsule->getDatabaseManager());
$urls = Mockery::mock();
$urls->shouldReceive('route')->andReturn('https://example.invalid/invoice');
$container->instance('url', $urls);
$schema = $capsule->getConnection()->getSchemaBuilder();
$schema->create('business_locations', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('business_id');
    $table->integer('invoice_layout_id')->nullable();
});
$schema->create('invoice_layouts', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('business_id');
    $table->boolean('is_default')->default(false);
    $table->string('invoice_heading');
    $table->string('invoice_heading_not_paid')->nullable();
});
$db = $capsule->getConnection();
$db->table('invoice_layouts')->insert([
    ['id' => 1, 'business_id' => 7, 'is_default' => 1, 'invoice_heading' => 'Invoice', 'invoice_heading_not_paid' => 'Payment Due'],
    ['id' => 2, 'business_id' => 7, 'is_default' => 0, 'invoice_heading' => 'Table Order', 'invoice_heading_not_paid' => 'Unpaid'],
    ['id' => 3, 'business_id' => 8, 'is_default' => 1, 'invoice_heading' => 'Other Business', 'invoice_heading_not_paid' => null],
]);
$db->table('business_locations')->insert([
    ['id' => 1, 'business_id' => 7, 'invoice_layout_id' => 2],
    ['id' => 2, 'business_id' => 7, 'invoice_layout_id' => 999],
    ['id' => 3, 'business_id' => 8, 'invoice_layout_id' => 3],
]);
$checks = 0;
function headingCheck(bool $condition, string $message): void
{
    global $checks;
    if (! $condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
}
function headingResponse(Request $request, int $business, int $location, string $payment, string $status = 'final'): array
{
    $transaction = new Transaction();
    $transaction->setRawAttributes([
        'id' => 10, 'type' => 'sell', 'business_id' => $business, 'location_id' => $location,
        'status' => $status, 'payment_status' => $payment, 'is_quotation' => 0, 'invoice_token' => 'fixture',
    ]);
    $transaction->setRelation('sell_lines', collect());

    return (new SellResource($transaction))->toArray($request);
}
$request = Request::create('/connector/api/sell');
foreach (['due', 'partial'] as $payment) {
    headingCheck(headingResponse($request, 7, 1, $payment)['invoice_heading'] === 'Table Order Unpaid', 'Location heading and unpaid suffix for '.$payment);
    headingCheck(headingResponse($request, 7, 2, $payment)['invoice_heading'] === 'Invoice Payment Due', 'Missing assigned layout uses business default');
}
headingCheck(headingResponse($request, 8, 3, 'due')['invoice_heading'] === 'Other Business', 'Business isolation and absent suffix');
headingCheck(! array_key_exists('invoice_heading', headingResponse($request, 7, 1, 'paid')), 'Paid heading unchanged');
headingCheck(! array_key_exists('invoice_heading', headingResponse($request, 7, 1, 'due', 'draft')), 'Draft heading unchanged');
$db->enableQueryLog();
$db->flushQueryLog();
for ($i = 0; $i < 100; $i++) {
    headingResponse($request, 7, 1, 'due');
}
headingCheck(count($db->getQueryLog()) === 0, 'No per-sale heading queries after request-scoped resolution');
$db->table('invoice_layouts')->where('id', 2)->update(['invoice_heading_not_paid' => 'Awaiting Payment']);
$freshRequest = Request::create('/connector/api/sell');
headingCheck(headingResponse($freshRequest, 7, 1, 'due')['invoice_heading'] === 'Table Order Awaiting Payment', 'Next request reads changed backend settings');
$db->table('invoice_layouts')->where('business_id', 7)->delete();
headingCheck(headingResponse(Request::create('/connector/api/sell'), 7, 2, 'due')['invoice_heading'] === null, 'Missing layouts do not borrow another business heading');
Mockery::close();
echo "PASS: {$checks} Connector invoice-heading checks (SQLite memory only).\n";
