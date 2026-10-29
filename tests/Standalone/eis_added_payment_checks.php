<?php

// Real payment controller/utilities, SQLite memory, local event observer; no kernel or EIS calls.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Transaction;
use App\TransactionPayment;
use App\Http\Controllers\TransactionPaymentController;
use App\Utils\TransactionUtil;
use App\Utils\ModuleUtil;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Modules\EIS\Events\SaleCompleted;

class Module
{
    public static function toCollection() { return collect([]); }
}
$container = new Container();
$capsule = new Capsule($container);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$events = new Illuminate\Events\Dispatcher($container);
$capsule->setEventDispatcher($events);
$capsule->setAsGlobal(); $capsule->bootEloquent();
Container::setInstance($container);
$container->instance('db', $capsule->getDatabaseManager());
$container->instance('events', $events);
$container->instance('translator', new Illuminate\Translation\Translator(new Illuminate\Translation\ArrayLoader(), 'en'));
$container->instance('log', new Psr\Log\NullLogger());
$config = new Illuminate\Config\Repository([
    'database' => ['default' => 'default', 'connections' => ['default' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]],
    'auth' => ['defaults' => ['guard' => 'fixture'], 'guards' => ['fixture' => ['driver' => 'fixture']]],
    'activitylog' => ['enabled' => true, 'default_log_name' => 'default', 'activity_model' => \Spatie\Activitylog\Models\Activity::class, 'table_name' => 'activity_log'],
]);
$container->instance('config', $config);
$container->instance(Illuminate\Contracts\Config\Repository::class, $config);
Facade::setFacadeApplication($container); Facade::clearResolvedInstances();
if (! class_exists('DB')) { class_alias(DB::class, 'DB'); }
if (! class_exists('Log')) { class_alias(Illuminate\Support\Facades\Log::class, 'Log'); }
if (! class_exists('Carbon')) { class_alias(Carbon\Carbon::class, 'Carbon'); }
$db = $capsule->getConnection();
$db->setTransactionManager(new Illuminate\Database\DatabaseTransactionsManager());
$db->getPdo()->sqliteCreateFunction('IF', fn ($condition, $yes, $no) => $condition ? $yes : $no, 3);
$schema = $db->getSchemaBuilder();
$schema->create('transactions', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->integer('contact_id'); $t->integer('location_id')->nullable();
    $t->string('type'); $t->string('status'); $t->string('payment_status');
    $t->decimal('final_total', 22, 4); $t->string('transaction_date'); $t->timestamps();
});
$schema->create('contacts', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->decimal('balance', 22, 4)->default(0); $t->softDeletes();
});
$schema->create('transaction_payments', function (Blueprint $t) {
    $t->increments('id');
    foreach (['transaction_id', 'business_id', 'payment_for', 'created_by', 'parent_id'] as $column) { $t->integer($column)->nullable(); }
    foreach (['method', 'note', 'card_number', 'card_holder_name', 'card_transaction_number', 'card_type', 'card_month', 'card_year', 'card_security',
        'cheque_number', 'bank_account_number', 'paid_on', 'transaction_no', 'payment_ref_no', 'document'] as $column) { $t->string($column)->nullable(); }
    $t->decimal('amount', 22, 4); $t->boolean('is_return')->default(false); $t->timestamps();
});
$schema->create('saved_sale_split_operations', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->integer('user_id');
    $t->string('operation_id')->unique(); $t->integer('source_id'); $t->integer('result_id')->nullable();
    $t->string('payload_hash'); $t->string('expected_version'); $t->text('allocation_json');
    $t->text('response_json')->nullable(); $t->timestamps();
});
$schema->create('business', function (Blueprint $t) {
    $t->increments('id'); $t->text('custom_labels')->nullable(); $t->string('time_zone')->default('UTC');
});
$schema->create('business_locations', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->string('name');
    $t->text('default_payment_accounts')->nullable();
});
$schema->create('activity_log', function (Blueprint $t) {
    $t->increments('id'); $t->string('log_name')->nullable(); $t->text('description');
    $t->string('subject_type')->nullable(); $t->integer('subject_id')->nullable();
    $t->string('causer_type')->nullable(); $t->integer('causer_id')->nullable();
    $t->string('batch_uuid')->nullable(); $t->text('properties')->nullable();
    $t->integer('business_id')->nullable(); $t->timestamps();
});
DB::table('contacts')->insert(['id' => 1, 'business_id' => 7]);
DB::table('business')->insert(['id' => 7, 'time_zone' => 'UTC']);
DB::table('business_locations')->insert([
    'id' => 2, 'business_id' => 7, 'name' => 'Fixture Location',
    'default_payment_accounts' => json_encode([
        'cash' => ['is_enabled' => 1], 'card' => ['is_enabled' => 1],
        'bank_transfer' => ['is_enabled' => 1],
    ]),
]);
$session = new Illuminate\Session\Store('fixture', new Illuminate\Session\ArraySessionHandler(120));
$session->put('user.business_id', 7); $session->put('business.id', 7);
$request = Request::create('https://fixture.invalid/payments'); $request->setLaravelSession($session);
$container->instance('request', $request); $container->instance('session', $session);
$redirector = new Illuminate\Routing\Redirector(new Illuminate\Routing\UrlGenerator(new Illuminate\Routing\RouteCollection(), $request));
$redirector->setSession($session); $container->instance('redirect', $redirector);
$user = Mockery::mock(App\User::class)->makePartial();
$paymentPermission = true; $permittedLocation = true;
$user->id = 1; $user->business_id = 7;
$user->shouldReceive('hasRole')->andReturn(false);
$user->shouldReceive('can')->andReturnUsing(function ($permission) use (&$paymentPermission, &$permittedLocation) {
    if ($permission === 'superadmin') { return false; }
    if ($permission === 'sell.payments') { return $paymentPermission; }
    if (in_array($permission, ['access_all_locations', 'location.2'], true)) { return $permittedLocation; }
    return true;
});
$authGuard = Mockery::mock();
$authGuard->shouldReceive('user')->andReturn($user);
$auth = Mockery::mock(Illuminate\Auth\AuthManager::class);
$auth->shouldReceive('guard')->andReturn($authGuard);
$auth->shouldReceive('user')->andReturn($user);
$container->instance(Illuminate\Auth\AuthManager::class, $auth);
$container->instance(Illuminate\Contracts\Auth\Factory::class, $auth);
$container->instance('auth', $auth);
/** @var TransactionUtil&\Mockery\MockInterface $util */
$util = Mockery::mock(TransactionUtil::class)->makePartial();
$util->shouldReceive('num_uf')->andReturnUsing(fn ($amount) => (float) $amount);
$util->shouldReceive('uf_date')->andReturn('2026-10-14 10:00:00');
$util->shouldReceive('setAndGetReferenceCount')->andReturn(1);
$util->shouldReceive('generateReferenceNumber')->andReturn('FIXTURE');
$util->shouldReceive('uploadFile')->andReturn(null);
$util->shouldReceive('activityLog')->andReturn(null);
$controller = new TransactionPaymentController($util, new ModuleUtil());
$checks = 0; $submitted = [];
function paymentCheck($condition, string $message): void {
    global $checks;
    if (! $condition) { throw new RuntimeException($message); } ++$checks;
}
$events->listen(SaleCompleted::class, function (SaleCompleted $event) use (&$submitted) {
    paymentCheck(DB::transactionLevel() === 0, 'Fiscal event only after outer commit');
    $sale = $event->transaction->fresh();
    paymentCheck($sale->payment_status === 'paid' && $sale->type === 'sell' && $sale->status === 'final', 'Committed final paid sale');
    paymentCheck((new TransactionUtil())->getTotalPaid($sale->id) >= $sale->final_total, 'Payments exist when event fires');
    $submitted[] = $sale->id;
});
function saleFixture(string $type = 'sell', string $status = 'final'): Transaction {
    return Transaction::create(['business_id' => 7, 'contact_id' => 1, 'type' => $type, 'status' => $status,
        'location_id' => 2, 'payment_status' => 'due', 'final_total' => 100, 'transaction_date' => '2026-10-14 10:00:00']);
}
function postPayment(Transaction $sale, $amount, string $method = 'cash', ?string $operation = null): array {
    global $controller, $session, $container;
    $data = ['transaction_id' => $sale->id, 'amount' => $amount, 'method' => $method, 'paid_on' => '2026-10-14'];
    if ($operation !== null) { $data['saved_split_operation_id'] = $operation; }
    $request = Request::create('/payments', 'POST', $data);
    $request->setLaravelSession($session); $container->instance('request', $request);
    $controller->store($request);
    return $session->get('status');
}
function addPayment(Transaction $sale, $amount, string $method = 'cash', ?string $operation = null): void {
    paymentCheck(postPayment($sale, $amount, $method, $operation)['success'] === true, 'Payment saved successfully');
}
function deletePayment(TransactionPayment $payment): array {
    global $controller, $session, $container;
    $request = Request::create('/payments/'.$payment->id, 'DELETE', [], [], [], [
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    ]);
    $request->setLaravelSession($session); $container->instance('request', $request);
    return $controller->destroy($payment->id);
}
function trackSplit(Transaction $sale): void {
    DB::table('saved_sale_split_operations')->insert([
        'business_id' => $sale->business_id, 'user_id' => 1,
        'operation_id' => (string) Illuminate\Support\Str::uuid(),
        'source_id' => $sale->id, 'result_id' => $sale->id,
        'payload_hash' => str_repeat('a', 64), 'expected_version' => str_repeat('a', 64),
        'allocation_json' => json_encode(['kind' => 'split']), 'response_json' => '{}',
    ]);
}
foreach (['cash', 'bank_transfer', 'card'] as $method) {
    $sale = saleFixture(); $before = count($submitted);
    addPayment($sale, 60, $method);
    paymentCheck($sale->fresh()->payment_status === 'partial' && count($submitted) === $before, 'Partial payment does not initiate EIS');
    addPayment($sale, 40, $method);
    paymentCheck($sale->fresh()->payment_status === 'paid' && array_slice($submitted, $before) === [$sale->id], 'Zero balance initiates EIS once for '.$method);
    $paymentCount = TransactionPayment::where('transaction_id', $sale->id)->count();
    addPayment($sale, 40, $method);
    paymentCheck(count($submitted) === $before + 1 && TransactionPayment::where('transaction_id', $sale->id)->count() === $paymentCount, 'Already paid retry adds no payment or submission');
}
foreach ([['purchase', 'final'], ['sell', 'draft'], ['sell_return', 'final']] as [$type, $status]) {
    $sale = saleFixture($type, $status); $before = count($submitted);
    addPayment($sale, 100);
    paymentCheck($sale->fresh()->payment_status === 'paid' && count($submitted) === $before, 'No EIS for '.$type.'/'.$status);
}
$sale = saleFixture(); $before = count($submitted);
DB::beginTransaction(); addPayment($sale, 100);
paymentCheck(count($submitted) === $before, 'Nested payment commit defers submission');
DB::rollBack();
paymentCheck(count($submitted) === $before && $sale->fresh()->payment_status === 'due'
    && TransactionPayment::where('transaction_id', $sale->id)->count() === 0, 'Rollback discards payment and fiscal callback');

foreach (['cash', 'card', 'bank_transfer'] as $method) {
    $sale = saleFixture(); trackSplit($sale); $before = count($submitted);
    $firstOperation = (string) Illuminate\Support\Str::uuid();
    addPayment($sale, 60, $method, $firstOperation);
    paymentCheck($sale->fresh()->payment_status === 'partial', 'Split sale accepts partial '.$method.' payment');

    $paymentCount = TransactionPayment::where('transaction_id', $sale->id)->count();
    addPayment($sale, 60, $method, $firstOperation);
    paymentCheck(TransactionPayment::where('transaction_id', $sale->id)->count() === $paymentCount,
        'Split payment retry with the same operation does not duplicate payment');

    addPayment($sale, 40, $method, (string) Illuminate\Support\Str::uuid());
    paymentCheck($sale->fresh()->payment_status === 'paid' && array_slice($submitted, $before) === [$sale->id],
        'Split sale full settlement submits once for '.$method);
    paymentCheck(\App\Services\Sales\SavedSaleWriteGuard::fiscal(DB::connection(), $sale->id)->payment_status === 'paid',
        'Paid split sale passes the guarded fiscal check');
}

$sale = saleFixture(); trackSplit($sale);
$operation = (string) Illuminate\Support\Str::uuid();
addPayment($sale, 25, 'cash', $operation);
$paymentCount = TransactionPayment::where('transaction_id', $sale->id)->count();
$collision = postPayment($sale, 26, 'cash', $operation);
paymentCheck($collision['success'] === false && $sale->fresh()->payment_status === 'partial'
    && TransactionPayment::where('transaction_id', $sale->id)->count() === $paymentCount,
    'Split payment operation cannot be replayed with a changed payload');

$sale = saleFixture(); trackSplit($sale);
$missingOperation = postPayment($sale, 25);
paymentCheck($missingOperation['success'] === false && $sale->fresh()->payment_status === 'due'
    && TransactionPayment::where('transaction_id', $sale->id)->count() === 0,
    'Split payment requires the idempotency token supplied by the payment form');

$sale = saleFixture(); trackSplit($sale);
DB::beginTransaction();
try {
    \App\Services\Sales\SavedSaleWriteGuard::splitPayment(
        DB::connection(), $sale->id, $sale->business_id, 1,
        (string) Illuminate\Support\Str::uuid(), str_repeat('b', 64),
        function () use ($sale) {
            $sale->final_total = 1;
            $sale->save();
            return ['success' => true, 'payment_id' => 1];
        }
    );
    DB::commit();
    throw new RuntimeException('Expected split header mutation to be rejected');
} catch (\App\Services\Sales\SavedSplitException $e) {
    DB::rollBack();
    paymentCheck($e->errorCode === 'split_write_fenced', 'Split payment scope rejects sale repricing');
}
paymentCheck((float) $sale->fresh()->final_total === 100.0
    && TransactionPayment::where('transaction_id', $sale->id)->count() === 0,
    'Rejected split header mutation rolls back completely');

$sale = saleFixture(); trackSplit($sale);
addPayment($sale, 60, 'cash', (string) Illuminate\Support\Str::uuid());
$splitPayment = TransactionPayment::where('transaction_id', $sale->id)->firstOrFail();
DB::beginTransaction();
try {
    $splitPayment->delete();
    DB::commit();
    throw new RuntimeException('Expected direct split payment deletion to be rejected');
} catch (\App\Services\Sales\SavedSplitException $e) {
    DB::rollBack();
    paymentCheck($e->errorCode === 'split_write_fenced', 'Direct split payment deletion remains fenced');
}
paymentCheck(TransactionPayment::whereKey($splitPayment->id)->exists(), 'Rejected direct deletion preserves the split payment');
$deleteResponse = deletePayment($splitPayment);
paymentCheck($deleteResponse['success'] === true
    && !TransactionPayment::whereKey($splitPayment->id)->exists()
    && $sale->fresh()->payment_status === 'due',
    'Split payment deletion uses the scoped guard and recalculates status');
$deleteOperation = DB::table('saved_sale_split_operations')->where('source_id', $sale->id)
    ->where('result_id', $sale->id)->orderByDesc('id')->first();
paymentCheck(json_decode($deleteOperation->allocation_json, true)['kind'] === 'payment_delete'
    && !empty($deleteOperation->response_json), 'Split payment deletion is recorded in the durable ledger');

$sale = saleFixture(); trackSplit($sale);
$paymentForEdit = null;
DB::transaction(function () use ($sale, &$paymentForEdit) {
    \App\Services\Sales\SavedSaleWriteGuard::paymentLines(DB::connection(), $sale->id, 1, function () use ($sale, &$paymentForEdit) {
        \App\Services\Sales\SavedSaleWriteGuard::lock(DB::connection(), $sale->id);
        $paymentForEdit = TransactionPayment::create([
            'transaction_id' => $sale->id, 'business_id' => $sale->business_id, 'created_by' => 1,
            'payment_for' => $sale->contact_id, 'amount' => 60, 'method' => 'cash', 'paid_on' => '2026-10-14',
        ]);
        $sale->payment_status = 'partial';
        $sale->save();
    });
});
paymentCheck($paymentForEdit && $sale->fresh()->payment_status === 'partial',
    'POS payment-only workflow accepts a split-aware tender');

DB::transaction(function () use ($sale, $paymentForEdit) {
    \App\Services\Sales\SavedSaleWriteGuard::paymentLines(DB::connection(), $sale->id, 1, function () use ($paymentForEdit) {
        $paymentForEdit->amount = 75;
        $paymentForEdit->save();
    });
});
paymentCheck((float) $paymentForEdit->fresh()->amount === 75.0,
    'POS payment-only workflow can update an existing split tender');

DB::beginTransaction();
try {
    \App\Services\Sales\SavedSaleWriteGuard::paymentLines(DB::connection(), $sale->id, 1, function () use ($sale) {
        \App\Services\Sales\SavedSaleWriteGuard::model(
            new \App\TransactionSellLine(['transaction_id' => $sale->id, 'quantity' => 1])
        );
    });
    DB::commit();
    throw new RuntimeException('Expected split payment-only scope to reject a sale-line write');
} catch (\App\Services\Sales\SavedSplitException $e) {
    DB::rollBack();
    paymentCheck($e->errorCode === 'split_write_fenced', 'POS payment-only scope cannot change sale lines');
}

DB::beginTransaction();
try {
    \App\Services\Sales\SavedSaleWriteGuard::paymentLines(DB::connection(), $sale->id, 1, function () use ($sale) {
        $sale->final_total = 1;
        $sale->save();
    });
    DB::commit();
    throw new RuntimeException('Expected split payment-only scope to reject sale repricing');
} catch (\App\Services\Sales\SavedSplitException $e) {
    DB::rollBack();
    paymentCheck($e->errorCode === 'split_write_fenced', 'POS payment-only scope cannot reprice a split sale');
}
paymentCheck((float) $sale->fresh()->final_total === 100.0,
    'Rejected POS sale repricing leaves the split sale unchanged');

$ordinarySale = saleFixture();
addPayment($ordinarySale, 50);
$ordinaryPayment = TransactionPayment::where('transaction_id', $ordinarySale->id)->firstOrFail();
paymentCheck(deletePayment($ordinaryPayment)['success'] === true
    && !TransactionPayment::whereKey($ordinaryPayment->id)->exists()
    && $ordinarySale->fresh()->payment_status === 'due',
    'Ordinary sale payment deletion remains unchanged');

// Edited partial payment becoming paid.
$sale = saleFixture(); addPayment($sale, 50); $before = count($submitted);
$payment = TransactionPayment::where('transaction_id', $sale->id)->firstOrFail();
$request = Request::create('/payments/'.$payment->id, 'POST', ['amount' => 100, 'method' => 'cash', 'paid_on' => '2026-10-14']);
$request->setLaravelSession($session); $container->instance('request', $request);
$controller->update($request, $payment->id);
paymentCheck($session->get('status')['success'] === true && array_slice($submitted, $before) === [$sale->id], 'Edited payment clearing balance initiates EIS');
$controller->update($request, $payment->id);
paymentCheck(count($submitted) === $before + 1, 'Editing already paid sale does not repeat submission');

// Desktop creation and web conversion may retain the model loaded before payment calculation.
foreach (['cash', 'card', 'bank_transfer'] as $method) {
    $sale = saleFixture();
    DB::beginTransaction();
    TransactionPayment::create(['transaction_id' => $sale->id, 'business_id' => 7,
        'amount' => 100, 'method' => $method, 'paid_on' => '2026-10-14']);
    $before = count($submitted);
    $util->updatePaymentStatus($sale->id, $sale->final_total);
    paymentCheck($sale->payment_status === 'due', 'Caller retains pre-payment snapshot for '.$method);
    \Modules\EIS\Services\Sales\SaleSubmissionRule::initiate($sale);
    paymentCheck(count($submitted) === $before, 'Stale-model submission waits for commit');
    DB::commit();
    paymentCheck(array_slice($submitted, $before) === [$sale->id],
        'Committed fully paid sale initiates EIS despite stale caller for '.$method);
}
$sale = saleFixture();
$sale->payment_status = 'paid';
$before = count($submitted);
\Modules\EIS\Services\Sales\SaleSubmissionRule::initiate($sale);
paymentCheck(count($submitted) === $before, 'Unsaved paid snapshot cannot submit a committed unpaid sale');
$sale = saleFixture();
DB::beginTransaction();
TransactionPayment::create(['transaction_id' => $sale->id, 'business_id' => 7,
    'amount' => 100, 'method' => 'cash', 'paid_on' => '2026-10-14']);
$before = count($submitted);
$util->updatePaymentStatus($sale->id, $sale->final_total);
\Modules\EIS\Services\Sales\SaleSubmissionRule::initiate($sale);
DB::rollBack();
paymentCheck(count($submitted) === $before && $sale->fresh()->payment_status === 'due',
    'Rolled-back full payment never initiates EIS from a stale snapshot');

$sale = saleFixture();
$before = count($submitted);
DB::beginTransaction();
DB::table('transactions')->where('id', $sale->id)->update(['payment_status' => 'paid']);
\Modules\EIS\Services\Sales\SaleSubmissionRule::initiate($sale);
DB::table('transactions')->where('id', $sale->id)->update(['payment_status' => 'partial']);
DB::commit();
paymentCheck(count($submitted) === $before, 'Final committed balance wins over an earlier full-payment state');
$sale = saleFixture('sell', 'draft');
$sale->status = 'final';
$sale->payment_status = 'paid';
\Modules\EIS\Services\Sales\SaleSubmissionRule::initiate($sale);
paymentCheck(count($submitted) === $before, 'Unsaved final/paid snapshot cannot submit a committed draft');

// Contact-due distribution pays two invoices and only partly pays the third.
DB::table('transactions')->update(['contact_id' => 99]);
$first = saleFixture(); $second = saleFixture(); $third = saleFixture();
$parent = TransactionPayment::create(['business_id' => 7, 'payment_for' => 1, 'amount' => 250, 'method' => 'cash', 'paid_on' => '2026-10-14']);
$before = count($submitted); DB::beginTransaction();
$remaining = $util->payAtOnce($parent, 'sell');
paymentCheck(count($submitted) === $before, 'Contact due submissions deferred until allocations commit');
DB::commit();
paymentCheck($remaining == 0 && array_slice($submitted, $before) === [$first->id, $second->id], 'Each fully settled invoice initiates EIS');
paymentCheck($third->fresh()->payment_status === 'partial', 'Partial allocated invoice not submitted');

// Failed child-payment insert rolls back statuses, with no fiscal event.
DB::table('transactions')->update(['contact_id' => 99]); $sale = saleFixture();
$parent = TransactionPayment::create(['business_id' => 7, 'payment_for' => 1, 'amount' => 100, 'method' => 'cash']);
$before = count($submitted);
DB::statement("CREATE TRIGGER reject_allocation BEFORE INSERT ON transaction_payments BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
DB::beginTransaction();
try { $util->payAtOnce($parent, 'sell'); throw new RuntimeException('Expected allocation failure'); }
catch (Illuminate\Database\QueryException $e) { DB::rollBack(); }
paymentCheck(count($submitted) === $before && $sale->fresh()->payment_status === 'due', 'Failed allocation cannot dispatch fiscal event');
DB::statement('DROP TRIGGER reject_allocation');

$schema->create('restaurant_bills', function (Blueprint $t) {
    $t->increments('id'); $t->integer('transaction_id'); $t->integer('business_id');
    $t->integer('location_id'); $t->integer('waiter_id'); $t->string('state');
    $t->integer('version')->default(1); $t->timestamps();
});
$schema->create('transaction_sell_lines', function (Blueprint $t) {
    $t->increments('id'); $t->integer('transaction_id'); $t->string('quantity');
});
$schema->create('restaurant_tickets', function (Blueprint $t) {
    $t->increments('id'); $t->integer('transaction_id'); $t->string('status');
});
foreach (['eis_sales', 'eis_failed_transactions'] as $table) {
    $schema->create($table, function (Blueprint $t) { $t->increments('id'); $t->integer('transaction_id'); });
}
function restaurantPaymentFixture(string $state = 'open'): Transaction {
    $sale = saleFixture();
    trackSplit($sale);
    DB::table('restaurant_bills')->insert(['transaction_id' => $sale->id, 'business_id' => 7,
        'location_id' => 2, 'waiter_id' => 99, 'state' => $state, 'version' => 1]);
    DB::table('transaction_sell_lines')->insert(['transaction_id' => $sale->id, 'quantity' => '2.0000']);
    DB::table('restaurant_tickets')->insert(['transaction_id' => $sale->id, 'status' => 'received']);
    return $sale;
}
$restaurantSale = restaurantPaymentFixture();
try {
    DB::transaction(function () use ($restaurantSale) {
        \App\Services\Sales\SavedSaleWriteGuard::paymentLines(
            DB::connection(),
            $restaurantSale->id,
            1,
            function () {
                throw new RuntimeException('Restaurant payment-only scope must not be entered.');
            }
        );
    });
    throw new RuntimeException('Expected restaurant bill payment-only workflow to remain fenced');
} catch (\App\Services\Sales\SavedSplitException $e) {
    paymentCheck($e->errorCode === 'restaurant_write_fenced',
        'POS payment-only workflow preserves audited restaurant clearing');
}
foreach (['open', 'ready'] as $state) {
    $sale = restaurantPaymentFixture($state);
    $lines = DB::table('transaction_sell_lines')->where('transaction_id', $sale->id)->get()->toJson();
    $tickets = DB::table('restaurant_tickets')->where('transaction_id', $sale->id)->get()->toJson();
    $operation = (string) Illuminate\Support\Str::uuid();
    $before = count($submitted);
    addPayment($sale, 60, 'cash', $operation);
    paymentCheck($sale->fresh()->payment_status === 'partial' &&
        DB::table('restaurant_bills')->where('transaction_id', $sale->id)->value('state') === 'partial' &&
        count($submitted) === $before, 'Normal backend payment accepts '.$state.' restaurant bill and syncs partial state');
    $version = DB::table('restaurant_bills')->where('transaction_id', $sale->id)->value('version');
    addPayment($sale, 60, 'cash', $operation);
    paymentCheck(TransactionPayment::where('transaction_id', $sale->id)->count() === 1 &&
        DB::table('restaurant_bills')->where('transaction_id', $sale->id)->value('version') === $version,
        'Restaurant backend payment retry does not duplicate tender or workflow version');
    paymentCheck(postPayment($sale, 50, 'cash', (string) Illuminate\Support\Str::uuid())['success'] === false &&
        TransactionPayment::where('transaction_id', $sale->id)->count() === 1 &&
        $sale->fresh()->payment_status === 'partial' && count($submitted) === $before,
        'Restaurant backend overpayment rolls back payment, state and fiscal callback');
    addPayment($sale, 40, 'card', (string) Illuminate\Support\Str::uuid());
    paymentCheck($sale->fresh()->payment_status === 'paid' &&
        DB::table('restaurant_bills')->where('transaction_id', $sale->id)->value('state') === 'closed' &&
        array_slice($submitted, $before) === [$sale->id],
        'Normal backend final payment closes restaurant bill and submits EIS once after commit');
    paymentCheck(DB::table('transaction_sell_lines')->where('transaction_id', $sale->id)->get()->toJson() === $lines &&
        DB::table('restaurant_tickets')->where('transaction_id', $sale->id)->get()->toJson() === $tickets,
        'Backend clearing preserves restaurant quantities and preparation status');
    addPayment($sale, 40, 'card', (string) Illuminate\Support\Str::uuid());
    paymentCheck(TransactionPayment::where('transaction_id', $sale->id)->count() === 2 &&
        count($submitted) === $before + 1, 'Paid restaurant bill retry cannot add payments or resubmit EIS');
    $payment = TransactionPayment::where('transaction_id', $sale->id)->firstOrFail();
    paymentCheck(deletePayment($payment)['success'] === false && TransactionPayment::whereKey($payment->id)->exists(),
        'Backend clearing does not relax direct restaurant tender deletion');
}
$sale = restaurantPaymentFixture();
$paymentPermission = false;
paymentCheck(postPayment($sale, 100, 'cash', (string) Illuminate\Support\Str::uuid())['success'] === false &&
    TransactionPayment::where('transaction_id', $sale->id)->count() === 0,
    'Other payment permissions cannot substitute for sell.payments on a restaurant bill');
$paymentPermission = true; $permittedLocation = false;
paymentCheck(postPayment($sale, 100, 'cash', (string) Illuminate\Support\Str::uuid())['success'] === false &&
    TransactionPayment::where('transaction_id', $sale->id)->count() === 0,
    'Backend restaurant clearing checks actor location access');
$permittedLocation = true;
paymentCheck(postPayment($sale, 100)['success'] === false &&
    TransactionPayment::where('transaction_id', $sale->id)->count() === 0,
    'Restaurant backend payments require the operation UUID from the normal payment form');
paymentCheck(postPayment($sale, 0, 'cash', (string) Illuminate\Support\Str::uuid())['success'] === false &&
    TransactionPayment::where('transaction_id', $sale->id)->count() === 0,
    'Restaurant backend payment rejects zero amounts');
paymentCheck(postPayment($sale, 100, 'disabled_method', (string) Illuminate\Support\Str::uuid())['success'] === false &&
    TransactionPayment::where('transaction_id', $sale->id)->count() === 0,
    'Restaurant backend payment validates location-enabled tender methods');
DB::table('eis_failed_transactions')->insert(['transaction_id' => $sale->id]);
paymentCheck(postPayment($sale, 100, 'cash', (string) Illuminate\Support\Str::uuid())['success'] === false &&
    TransactionPayment::where('transaction_id', $sale->id)->count() === 0,
    'Existing fiscal tracking blocks additional restaurant payments');
DB::table('eis_failed_transactions')->where('transaction_id', $sale->id)->delete();
$before = count($submitted);
DB::statement("CREATE TRIGGER reject_restaurant_close BEFORE UPDATE ON restaurant_bills WHEN NEW.state = 'closed' BEGIN SELECT RAISE(ABORT, 'fixture close failure'); END");
paymentCheck(postPayment($sale, 100, 'cash', (string) Illuminate\Support\Str::uuid())['success'] === false &&
    TransactionPayment::where('transaction_id', $sale->id)->count() === 0 &&
    $sale->fresh()->payment_status === 'due' && count($submitted) === $before,
    'Restaurant workflow close failure rolls back backend payment and pending EIS event');
DB::statement('DROP TRIGGER reject_restaurant_close');
// POS list bulk clearing must initiate EIS for every settled sale, not just the last model.
DB::table('transactions')->update(['status' => 'draft']);
$bulkController = new App\Http\Controllers\SellController(
    new App\Utils\ContactUtil(), new App\Utils\BusinessUtil(), $util, new ModuleUtil(), new App\Utils\ProductUtil()
);
$util->shouldReceive('num_f')->andReturnUsing(fn ($amount) => number_format((float) $amount, 2, '.', ''));
$responseFactory = Mockery::mock(Illuminate\Contracts\Routing\ResponseFactory::class);
$responseFactory->shouldReceive('json')->andReturnUsing(fn ($data) => new Illuminate\Http\JsonResponse($data));
$container->instance(Illuminate\Contracts\Routing\ResponseFactory::class, $responseFactory);
$bulkRequest = Request::create('/sells/clear-all-bill', 'POST', [], [], [], [
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
]);
$bulkRequest->setLaravelSession($session);
$container->instance('request', $bulkRequest);
$firstBulk = saleFixture();
$secondBulk = saleFixture();
DB::beginTransaction();
TransactionPayment::create(['transaction_id' => $secondBulk->id, 'business_id' => 7, 'amount' => 60, 'method' => 'cash']);
TransactionPayment::create(['transaction_id' => $secondBulk->id, 'business_id' => 7, 'amount' => 10, 'method' => 'cash', 'is_return' => 1]);
DB::table('transactions')->where('id', $secondBulk->id)->update(['payment_status' => 'partial']);
DB::commit();
$before = count($submitted);
$bulkResult = $bulkController->clearAllBills()->getData(true);
paymentCheck($bulkResult['success'] && $bulkResult['total_cleared'] === 2,
    'POS list clears both due and partial bills');
paymentCheck(array_slice($submitted, $before) === [$firstBulk->id, $secondBulk->id],
    'Every bulk-cleared bill initiates EIS after commit using its committed paid status');
paymentCheck($firstBulk->fresh()->payment_status === 'paid' && $secondBulk->fresh()->payment_status === 'paid'
    && (float) $util->getTotalPaid($secondBulk->id) === 100.0,
    'Bulk clearing nets returned change so each bill is fully paid');
$before = count($submitted);
$paymentCount = TransactionPayment::count();
$bulkController->clearAllBills();
paymentCheck(count($submitted) === $before && TransactionPayment::count() === $paymentCount,
    'Repeated bulk clear does not pay or submit already settled bills again');
$rollbackFirst = saleFixture();
$rollbackSecond = saleFixture();
DB::statement("CREATE TRIGGER reject_bulk_payment BEFORE INSERT ON transaction_payments WHEN NEW.transaction_id = {$rollbackSecond->id} BEGIN SELECT RAISE(ABORT, 'fixture bulk failure'); END");
$failedBulk = $bulkController->clearAllBills()->getData(true);
paymentCheck(!$failedBulk['success'] && count($submitted) === $before
    && TransactionPayment::count() === $paymentCount
    && $rollbackFirst->fresh()->payment_status === 'due',
    'A later bulk payment failure rolls back all earlier payments and EIS callbacks');
DB::statement('DROP TRIGGER reject_bulk_payment');
Mockery::close();
echo "PASS: {$checks} EIS added-payment checks (SQLite memory and local events only).\n";