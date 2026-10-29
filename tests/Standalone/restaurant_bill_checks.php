<?php

require __DIR__.'/saved_sale_split_checks.php';

use App\Services\Sales\RestaurantBillService;
use Illuminate\Database\Schema\Blueprint;

class IsolatedRestaurantService extends RestaurantBillService
{
    public int $scheduled = 0;
    protected function checkFiscalLimits(array $bill): void {}
    protected function scheduleFiscal(int $id): void
    {
        Illuminate\Database\Capsule\Manager::connection()->afterCommit(function () { ++$this->scheduled; });
    }
}

function restaurantFixture(): array
{
    [$db, $unused, $actor] = checkoutFixture(true);
    $schema = $db->getSchemaBuilder();
    Illuminate\Container\Container::getInstance()->instance('translator',
        new Illuminate\Translation\Translator(new Illuminate\Translation\ArrayLoader(), 'en'));
    $schema->table('transactions', function (Blueprint $t) {
        foreach (['res_table_id', 'res_waiter_id', 'is_kitchen_order', 'res_order_status'] as $c) { $t->text($c)->nullable(); }
    });
    $schema->table('business', function (Blueprint $t) { $t->text('enabled_modules')->nullable(); $t->text('custom_labels')->nullable(); });
    $schema->table('contacts', function (Blueprint $t) { $t->boolean('is_default')->default(0); });
    $schema->table('products', function (Blueprint $t) { $t->text('name')->default('Test product'); });
    $schema->table('variations', function (Blueprint $t) { $t->text('name')->default('DUMMY'); });
    $schema->create('cash_registers', function (Blueprint $t) {
        $t->increments('id'); $t->integer('user_id'); $t->integer('business_id'); $t->integer('location_id'); $t->text('status');
    });
    $schema->table('cash_register_transactions', function (Blueprint $t) {
        foreach (['cash_register_id', 'amount', 'pay_method', 'type', 'transaction_type'] as $column) { $t->text($column)->nullable(); }
    });
    $schema->create('res_tables', function (Blueprint $t) {
        $t->boolean('allow_multiple_orders')->default(false);
        $t->increments('id'); $t->integer('business_id'); $t->integer('location_id'); $t->text('name'); $t->text('deleted_at')->nullable();
    });
    foreach ([
        'restaurant_bills' => ['transaction_id', 'business_id', 'location_id', 'table_id', 'waiter_id', 'state', 'version'],
        'restaurant_tickets' => ['transaction_id', 'variation_id', 'station', 'quantity', 'original_quantity', 'status', 'created_by', 'completed_by'],
        'restaurant_approvals' => ['transaction_id', 'requested_by', 'approved_by', 'expected_version', 'items_json', 'reason', 'disposition', 'status'],
        'restaurant_operations' => ['business_id', 'transaction_id', 'user_id', 'operation_id', 'payload_hash', 'response_json'],
        'restaurant_waste' => ['approval_id', 'transaction_id', 'variation_id', 'purchase_line_id', 'quantity'],
    ] as $name => $columns) {
        $schema->create($name, function (Blueprint $t) use ($name, $columns) {
            $t->increments('id');
            foreach (array_merge($columns, ['created_at', 'updated_at']) as $column) { $t->text($column)->nullable(); }
            if ($name === 'restaurant_operations') { $t->unique(['business_id', 'operation_id']); }
            if ($name === 'restaurant_bills') { $t->unique('transaction_id'); }
        });
    }
    $db->table('business')->update(['enabled_modules' => '["tables","kitchen","service_staff"]']);
    $db->table('contacts')->update(['is_default' => 1]);
    $db->table('res_tables')->insert(['id' => 1, 'business_id' => 1, 'location_id' => 2, 'name' => 'Table 1']);
    $db->table('restaurant_bills')->insert(['transaction_id' => 1, 'business_id' => 1, 'location_id' => 2,
        'table_id' => 1, 'waiter_id' => 7, 'state' => 'open', 'version' => 1]);
    $db->table('transactions')->where('id', 1)->update(['res_table_id' => 1, 'res_waiter_id' => 7]);
    $db->table('saved_sale_split_operations')->insert(['business_id' => 1, 'user_id' => 7,
        'operation_id' => (string) Illuminate\Support\Str::uuid(), 'source_id' => 1, 'result_id' => 1,
        'allocation_json' => '{"kind":"restaurant_open"}', 'response_json' => '{"id":1}']);
    return [$db, new IsolatedRestaurantService($db), $actor];
}

function restaurantInput($service, $actor, array $extra = [], int $id = 1): array
{
    $board = $service->board($actor, 2);
    $bill = collect($board['bills'])->firstWhere('id', $id);
    return ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'expected_version' => $bill['version']] + $extra;
}
[$db, $service, $waiter] = restaurantFixture();
$before = snapshot($db);
check(count($service->config($waiter, 2)['tables']) === 1 && snapshot($db) === $before, 'restaurant config tenant-scoped and read-only');
$request = restaurantInput($service, $waiter, ['station' => 'bar', 'variations' => [5]]);
$response = $service->dispatch($waiter, 1, $request);
check(count($response['dispatch_tickets']) === 1 && $response['dispatch_tickets'][0]['quantity'] === '6.0000',
    'dispatch response identifies exact newly sent tickets for kitchen printing');
check($db->table('restaurant_tickets')->value('quantity') === '6.0000', 'first round sends total quantity once');
$after = snapshot($db);
check($service->dispatch($waiter, 1, $request) === $response && snapshot($db) === $after, 'dispatch retry never duplicates preparation');
rejects(fn () => $service->dispatch($waiter, 1, restaurantInput($service, $waiter, ['station' => 'bar', 'variations' => [5]])), 'restaurant_workflow');
$preview = $service->preview($waiter, 1);
$edit = ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'expected_version' => $preview['expected_version'],
    'restaurant_version' => $preview['restaurant_version'], 'items' => [['variation_id' => 5, 'quantity' => '8.0000']]];
$service->edit($waiter, 1, $edit);
$secondRound = $service->dispatch($waiter, 1, restaurantInput($service, $waiter, ['station' => 'kitchen', 'variations' => [5]]));
check(count($secondRound['dispatch_tickets']) === 1 && $secondRound['dispatch_tickets'][0]['quantity'] === '2.0000',
    'print response contains only the new kitchen round, never earlier bar quantities');
check($db->table('restaurant_tickets')->orderBy('id', 'desc')->value('quantity') === '2.0000', 'second round dispatch only sends added quantity');
$waiter->permissions[] = 'restaurant.bar';
$queue = $service->queue($waiter, 2, 'bar');
check(count($queue) === 1 && $queue[0]['quantity'] === '6.0000', 'station queue contains only its own round');
$service->ticket($waiter, 1, ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'station' => 'bar', 'ticket_id' => 1, 'status' => 'cooked']);
check($db->table('restaurant_tickets')->where('id', 1)->value('status') === 'cooked', 'authorized preparation completion');
$service->transition($waiter, 1, restaurantInput($service, $waiter, ['action' => 'ready']));
rejects(fn () => $service->preview($waiter, 1), 'restaurant_workflow');
$pay = restaurantInput($service, $waiter, ['payments' => [['method' => 'cash', 'amount' => '1']]]);
rejects(fn () => $service->pay($waiter, 1, $pay), 'restaurant_workflow');
$cashier = clone $waiter;
$cashier->id = 8;
$cashier->permissions = ['sell.view', 'sell.payments', 'location.2'];
$db->table('business_locations')->update(['default_payment_accounts' => '{"cash":{"is_enabled":1},"card":{"is_enabled":1}}']);
$pay = restaurantInput($service, $cashier, ['payments' => [['method' => 'cash', 'amount' => '10.0000'], ['method' => 'card', 'amount' => '20.0000', 'reference' => 'TEST-ONLY']]]);
$stock = $db->table('variation_location_details')->value('qty_available');
$result = $service->pay($cashier, 1, $pay);
check($result['payment_status'] === 'partial' && $result['balance'] === '66.0000' && $service->scheduled === 0, 'mixed partial tender does not fiscalize bill');
check($db->table('transaction_payments')->count() === 2 && $db->table('variation_location_details')->value('qty_available') === $stock, 'clearing only appends payments, never deducts stock twice');
$after = snapshot($db);
check($service->pay($cashier, 1, $pay) === $result && snapshot($db) === $after, 'payment retry does not double-charge');
$overpay = restaurantInput($service, $cashier, ['payments' => [['method' => 'cash', 'amount' => '999']]]);
rejects(fn () => $service->pay($cashier, 1, $overpay), 'restaurant_workflow');
$final = restaurantInput($service, $cashier, ['payments' => [['method' => 'card', 'amount' => '66.0000']]]);
$service->pay($cashier, 1, $final);
check($db->table('transactions')->where('id', 1)->value('payment_status') === 'paid' &&
    $db->table('restaurant_bills')->value('state') === 'closed' && $service->scheduled === 1, 'last tender closes bill and schedules fiscal submission once');
check(App\Services\Sales\SavedSaleWriteGuard::fiscal($db, 1)->payment_status === 'paid', 'paid restaurant bill satisfies persisted fiscal payment guard');
$paidSnapshot = snapshot($db);
$service->retryFiscal($cashier, 1);
check(snapshot($db) === $paidSnapshot && $service->scheduled === 2, 'fiscal retry schedules processing after commit without rewriting payments or inventory');
$db->table('eis_sales')->insert(['transaction_id' => 1, 'business_id' => 1, 'status' => 'submitted']);
$service->retryFiscal($cashier, 1);
check($service->scheduled === 2, 'already submitted bill does not queue another manual fiscal attempt');

foreach (['return_stock', 'waste'] as $disposition) {
    [$db, $service, $waiter] = restaurantFixture();
    $service->dispatch($waiter, 1, restaurantInput($service, $waiter, ['station' => 'bar', 'variations' => [5]]));
    $preview = $service->preview($waiter, 1);
    $input = ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'expected_version' => $preview['expected_version'],
        'restaurant_version' => $preview['restaurant_version'], 'items' => [['variation_id' => 5, 'quantity' => '1.0000']],
        'reason' => 'Customer cancelled test round', 'disposition' => $disposition];
    $stock = $db->table('variation_location_details')->value('qty_available');
    $request = $service->edit($waiter, 1, $input);
    check($request['approval_required'] && $db->table('transaction_sell_lines')->where('id', 1)->value('quantity') === '4.0000',
        'waiter reduction is pending, not applied without manager');
    $decision = ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'approval_id' => $request['approval_id'], 'decision' => 'approve'];
    rejects(fn () => $service->approve($waiter, 1, $decision), 'restaurant_workflow');
    $manager = clone $waiter; $manager->id = 8; $manager->permissions[] = 'restaurant.approve_void';
    $pending = $service->approvals($manager, 2);
    check(count($pending) === 1 && $pending[0]['invoice_no'] !== '' && $pending[0]['table_name'] === 'Table 1' &&
        str_contains($pending[0]['change_summary'], 'Test product: 6.0000 -> 1.0000') && !$pending[0]['is_stale'],
        'manager review identifies table/invoice/product, old/new quantities and dispatched quantity');
    $service->approve($manager, 1, $decision);
    check($db->table('transaction_sell_lines')->where('id', 1)->value('quantity') === '1.0000', 'manager applies exact requested quantities');
    check($db->table('variation_location_details')->value('qty_available') ===
        ($disposition === 'waste' ? $stock : bcadd($stock, '5', 4)), 'manager disposition controls stock return '.$disposition);
    check($db->table('restaurant_approvals')->value('status') === 'applied', 'approval retains manager and reason audit');
    if ($disposition === 'waste') {
        check($db->table('purchase_lines')->where('id', 2)->value('quantity_adjusted') === '5.0000' &&
            $db->table('restaurant_waste')->count() === 2, 'waste remains consumed in purchase ledger with explicit loss records');
    }
    $after = snapshot($db);
    $service->approve($manager, 1, $decision);
    check(snapshot($db) === $after, 'manager approval retry never repeats stock return or waste');
}
[$db, $service, $waiter] = restaurantFixture();
$db->table('restaurant_bills')->delete();
$db->table('saved_sale_split_operations')->delete();
$db->table('transactions')->where('id', 1)->update(['invoice_no' => 'OLD', 'res_table_id' => null]);
$create = ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'location_id' => 2, 'table_id' => 1,
    'contact_id' => 3, 'items' => [['variation_id' => 5, 'quantity' => '1.2500']]];
$result = $service->create($waiter, $create);
check($result['state'] === 'open' && $result['payment_status'] === 'due' && $result['total'] === '12.5000', 'waiter creates server-priced unpaid table bill');
$after = snapshot($db);
check($service->create($waiter, $create) === $result && snapshot($db) === $after, 'new table bill creation is idempotent');
$create['operation_id'] = (string) Illuminate\Support\Str::uuid();
rejects(fn () => $service->create($waiter, $create), 'restaurant_workflow');
check(snapshot($db) === $after, 'occupied table rejection never creates another bill or consumes stock');
$db->table('res_tables')->where('id', 1)->update(['allow_multiple_orders' => 1]);
check((int) $service->config($waiter, 2)['tables'][0]->allow_multiple_orders === 1,
    'desktop table config exposes backend multiple-open-orders flag');
$multiple = $service->create($waiter, $create);
check($multiple['id'] !== $result['id'] && $db->table('restaurant_bills')->count() === 2,
    'enabled table accepts a separate bill while a managed unpaid bill remains open');
$multiAfter = snapshot($db);
check($service->create($waiter, $create) === $multiple && snapshot($db) === $multiAfter,
    'multiple-order setting never bypasses operation idempotency');
$db->table('res_tables')->where('id', 1)->update(['allow_multiple_orders' => 0]);
$create['operation_id'] = (string) Illuminate\Support\Str::uuid();
rejects(fn () => $service->create($waiter, $create), 'restaurant_workflow');
check($db->table('restaurant_bills')->count() === 2, 'disabling flag preserves existing bills but rejects further open bills');
rejects(fn () => (new App\Services\Sales\SavedSaleSplitService($db))->createRestaurantBill($waiter, 2, 3, 1, [5 => '1']), 'restaurant_requires_transaction');

[$db, $service, $waiter] = restaurantFixture();
$db->table('restaurant_bills')->delete();
$db->table('saved_sale_split_operations')->delete();
$db->table('res_tables')->where('id', 1)->update(['allow_multiple_orders' => 1]);
$legacyMultiple = $service->create($waiter, ['operation_id' => (string) Illuminate\Support\Str::uuid(),
    'location_id' => 2, 'table_id' => 1, 'contact_id' => 3, 'items' => [['variation_id' => 5, 'quantity' => '1']]]);
check($legacyMultiple['id'] !== 1 && $db->table('transactions')->where('id', 1)->value('payment_status') !== 'paid',
    'enabled shared table allows a new managed order alongside a legacy unpaid order without changing the old order');

[$db, $service, $waiter] = restaurantFixture();
$split = new App\Services\Sales\SavedSaleSplitService($db);
$preview = $split->preview(1, $waiter);
check(!$preview['eligible'] && $preview['reason']['code'] === 'restaurant_workflow_required', 'restaurant split preview explicitly fences ticket-unaware partitions');
rejects(fn () => $split->split(1, $waiter, ['operation_id' => (string) Illuminate\Support\Str::uuid(),
    'expected_version' => $preview['expected_version'], 'lines' => [['id' => 1, 'quantity' => '1']]]), 'restaurant_workflow_required');
$preview = $split->additionsPreview(1, $waiter);
check($preview['restaurant_managed'] === true, 'Orders editor detects managed restaurant bills from backend');
rejects(fn () => $split->editItems(1, $waiter, ['operation_id' => (string) Illuminate\Support\Str::uuid(),
    'expected_version' => $preview['expected_version'], 'items' => [['variation_id' => 5, 'quantity' => '1']]]), 'restaurant_workflow_required');
foreach (['splitPayment', 'splitPaymentDeletion'] as $method) {
    rejects(function () use ($db, $method) {
        $db->transaction(function () use ($db, $method) {
            if ($method === 'splitPayment') {
                App\Services\Sales\SavedSaleWriteGuard::splitPayment($db, 1, 1, 7, (string) Illuminate\Support\Str::uuid(),
                    str_repeat('a', 64), fn () => throw new RuntimeException('Unexpected legacy payment callback'));
            } else {
                App\Services\Sales\SavedSaleWriteGuard::splitPaymentDeletion($db, 1, 1, 7, 1,
                    fn () => throw new RuntimeException('Unexpected legacy deletion callback'));
            }
        });
    }, 'restaurant_write_fenced');
}
rejects(fn () => $service->transition($waiter, 1, restaurantInput($service, $waiter, ['action' => 'ready'])), 'restaurant_workflow');
$dispatch = restaurantInput($service, $waiter, ['station' => 'bar', 'variations' => [5]]);
$service->dispatch($waiter, 1, $dispatch);
$after = snapshot($db);
rejects(fn () => $service->dispatch($waiter, 1, array_replace($dispatch, ['operation_id' => (string) Illuminate\Support\Str::uuid()])), 'restaurant_workflow');
check(snapshot($db) === $after, 'stale dispatch changes neither rounds nor quantities');
$revoked = clone $waiter; $revoked->permissions = ['sell.update'];
rejects(fn () => $service->dispatch($revoked, 1, $dispatch), 'restaurant_workflow');
$other = clone $waiter; $other->business_id = 2;
rejects(fn () => $service->dispatch($other, 1, $dispatch), 'restaurant_workflow');
$db->table('restaurant_bills')->where('transaction_id', 1)->update(['waiter_id' => 8]);
rejects(fn () => $service->dispatch($waiter, 1, $dispatch), 'restaurant_workflow');
check($db->table('restaurant_tickets')->count() === 1, 'replays revalidate current tenant/location/ownership and cannot send another round');

foreach (['return_stock', 'waste'] as $disposition) {
    [$db, $service, $waiter] = restaurantFixture();
    $service->dispatch($waiter, 1, restaurantInput($service, $waiter, ['station' => 'bar', 'variations' => [5]]));
    $preview = $service->preview($waiter, 1);
    $service->edit($waiter, 1, ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'expected_version' => $preview['expected_version'],
        'restaurant_version' => $preview['restaurant_version'], 'items' => [['variation_id' => 5, 'quantity' => '8']]]);
    $preview = $service->preview($waiter, 1);
    $input = ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'expected_version' => $preview['expected_version'],
        'restaurant_version' => $preview['restaurant_version'], 'items' => [['variation_id' => 5, 'quantity' => '5']],
        'reason' => 'Cancel one sent and two unsent units', 'disposition' => $disposition];
    $stock = $db->table('variation_location_details')->value('qty_available');
    $approval = $service->edit($waiter, 1, $input);
    rejects(fn () => $service->transition($waiter, 1, restaurantInput($service, $waiter, ['action' => 'ready'])), 'restaurant_workflow');
    $manager = clone $waiter; $manager->id = 8; $manager->permissions = ['sell.update', 'location.2', 'restaurant.approve_void'];
    $service->approve($manager, 1, ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'approval_id' => $approval['approval_id'], 'decision' => 'approve']);
    check($db->table('restaurant_tickets')->where('id', 1)->value('quantity') === '5.0000' &&
        $db->table('restaurant_tickets')->where('id', 1)->value('original_quantity') === '6.0000',
        'approval cancels only dispatched excess and retains original ticket audit');
    check($db->table('variation_location_details')->value('qty_available') === bcadd($stock, $disposition === 'waste' ? '2' : '3', 4),
        'undispatched reductions return stock even when the dispatched cancellation is wasted');
    if ($disposition === 'waste') {
        $loss = '0';
        foreach ($db->table('restaurant_waste')->get() as $row) { $loss = bcadd($loss, $row->quantity, 4); }
        check($loss === '1.0000', 'waste consumption exactly matches the single cancelled prepared unit');
    }
}

[$db, $service, $waiter] = restaurantFixture();
$invalids = [null, 'bad', [], [['variation_id' => 5, 'quantity' => 1.5]], [['variation_id' => 5, 'quantity' => '1.00001']],
    [['variation_id' => 5, 'quantity' => '1'], ['variation_id' => 5, 'quantity' => '2']]];
foreach ($invalids as $items) {
    $before = snapshot($db);
    try {
        $service->create($waiter, ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'location_id' => 2, 'table_id' => 1, 'contact_id' => 3, 'items' => $items]);
        throw new RuntimeException('Invalid quantities accepted');
    } catch (App\Services\Sales\SavedSplitException $e) { check($e->httpStatus === 422, 'malformed quantity input produces an explicit client error'); }
    check(snapshot($db) === $before, 'malformed quantities never mutate inventory or workflow');
}

foreach ([true, false] as $inlineTaxEnabled) {
    [$db, $service, $waiter] = restaurantFixture();
    $db->table('business')->update(['enable_inline_tax' => $inlineTaxEnabled ? 1 : 0]);
    $db->table('products')->where('id', 4)->update(['tax' => 6]);
    $db->table('res_tables')->insert(['id' => 2, 'business_id' => 1, 'location_id' => 2, 'name' => 'Table 2']);
    $result = $service->create($waiter, [
        'operation_id' => (string) Illuminate\Support\Str::uuid(),
        'location_id' => 2,
        'table_id' => 2,
        'contact_id' => 3,
        'items' => [['variation_id' => 5, 'quantity' => '2.0000']],
    ]);
    $line = (array) $db->table('transaction_sell_lines')->where('transaction_id', $result['id'])->first();
    $expectedTax = $inlineTaxEnabled ? '2.0000' : '0.0000';
    $expectedUnitTotal = $inlineTaxEnabled ? '12.0000' : '10.0000';
    $expectedBillTotal = $inlineTaxEnabled ? '24.0000' : '20.0000';
    check($line['tax_id'] == ($inlineTaxEnabled ? 6 : null) &&
        $line['item_tax'] === $expectedTax && $line['unit_price_inc_tax'] === $expectedUnitTotal,
        'restaurant line follows enable_inline_tax for tax ID, tax amount and tax-inclusive unit price');
    check($db->table('transactions')->where('id', $result['id'])->value('final_total') === $expectedBillTotal,
        'restaurant bill total follows enable_inline_tax');
}

[$db, $service, $waiter] = restaurantFixture();
$waiter->permissions[] = 'sell.payments';
$db->table('cash_registers')->insert(['user_id' => 7, 'business_id' => 1, 'location_id' => 2, 'status' => 'open']);
$service->dispatch($waiter, 1, restaurantInput($service, $waiter, ['station' => 'kitchen', 'variations' => [5]]));
$service->transition($waiter, 1, restaurantInput($service, $waiter, ['action' => 'ready']));
$pay = restaurantInput($service, $waiter, ['payments' => [['method' => 'cash', 'amount' => '12.3456']]]);
$service->pay($waiter, 1, $pay);
check($db->table('cash_register_transactions')->value('amount') === '12.3456' &&
    $db->table('cash_register_transactions')->value('cash_register_id') === '1', 'cashier open register receives an exact decimal credit once');
$service->pay($waiter, 1, $pay);
check($db->table('cash_register_transactions')->count() === 1, 'cashier payment replay never duplicates register posting');
$invalidPay = restaurantInput($service, $waiter, ['payments' => [['method' => 'advance', 'amount' => '1']]]);
$before = snapshot($db);
rejects(fn () => $service->pay($waiter, 1, $invalidPay), 'restaurant_workflow');
check(snapshot($db) === $before, 'unsupported credit-allocation tender is rejected without touching real payments');

[$db, $service, $waiter] = restaurantFixture();
$waiter->permissions[] = 'sell.payments';
$service->dispatch($waiter, 1, restaurantInput($service, $waiter, ['station' => 'bar', 'variations' => [5]]));
$service->transition($waiter, 1, restaurantInput($service, $waiter, ['action' => 'ready']));
$db->getSchemaBuilder()->create('accounts', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->integer('is_closed'); $t->text('deleted_at')->nullable();
});
$db->table('accounts')->insert(['business_id' => 1, 'is_closed' => 0]);
$db->getSchemaBuilder()->table('account_transactions', function (Blueprint $t) {
    foreach (['amount', 'account_id', 'type', 'sub_type', 'operation_date', 'created_by', 'transaction_payment_id', 'note'] as $c) { $t->text($c)->nullable(); }
});
$db->table('business_locations')->update(['default_payment_accounts' => '{"cash":{"is_enabled":1,"account":1}}']);
$accountPayment = restaurantInput($service, $waiter, ['payments' => [['method' => 'cash', 'amount' => '12.3456']]]);
$before = snapshot($db);
rejects(fn () => $service->pay($waiter, 1, $accountPayment), 'restaurant_workflow');
check(snapshot($db) === $before, 'configured payment account with a disabled account module fails explicitly');
$db->table('business')->update(['enabled_modules' => '["tables","account"]']);
$before = snapshot($db);
rejects(fn () => $service->pay($waiter, 1, $accountPayment), 'restaurant_workflow');
check(snapshot($db) === $before, 'missing account posting listener rolls back payment rather than silently losing accounting');
$listener = new App\Listeners\AddAccountTransaction(new App\Utils\TransactionUtil(), new App\Utils\ModuleUtil());
$db->getEventDispatcher()->listen(App\Events\TransactionPaymentAdded::class, [$listener, 'handle']);
$service->pay($waiter, 1, $accountPayment);
check($db->table('account_transactions')->value('amount') === '12.3456' &&
    $db->table('account_transactions')->value('type') === 'credit', 'real backend event posts exact configured-account amount');
$service->pay($waiter, 1, $accountPayment);
check($db->table('account_transactions')->count() === 1, 'idempotent retries never duplicate account credits');

[$db, $service, $waiter] = restaurantFixture();
$waiter->permissions[] = 'sell.payments';
$service->dispatch($waiter, 1, restaurantInput($service, $waiter, ['station' => 'bar', 'variations' => [5]]));
$service->transition($waiter, 1, restaurantInput($service, $waiter, ['action' => 'ready']));
$db->statement("CREATE TRIGGER reject_restaurant_audit BEFORE INSERT ON restaurant_operations BEGIN SELECT RAISE(ABORT, 'Synthetic final audit failure'); END");
$before = snapshot($db);
$payment = restaurantInput($service, $waiter, ['payments' => [['method' => 'cash', 'amount' => (string) $db->table('transactions')->where('id', 1)->value('final_total')]]]);
try {
    $service->pay($waiter, 1, $payment);
    throw new RuntimeException('Expected failed durable audit');
} catch (Illuminate\Database\QueryException $e) {
    check(str_contains($e->getMessage(), 'Synthetic final audit failure'), 'test injects failure after final payment and fiscal scheduling');
}
check(snapshot($db) === $before && $service->scheduled === 0, 'final audit failure rolls back payments, header, stock and after-commit fiscal dispatch');

[$db, $service, $waiter] = restaurantFixture();
$superadmin = clone $waiter; $superadmin->id = 8; $superadmin->permissions = ['superadmin'];
$preview = $service->preview($superadmin, 1);
check($preview['eligible'] === true, 'tenant-scoped superadmin restaurant edit matches backend capability and ownership rules');
$db->table('restaurant_bills')->delete();
$before = snapshot($db);
check((new App\Services\Sales\SavedSaleSplitService($db))->additionsPreview(1, $waiter)['eligible'],
    'unsent ordinary table orders support table editing');
$db->table('transactions')->where('id', 1)->update(['res_order_status' => 'cooked']);
rejects(fn () => (new App\Services\Sales\SavedSaleSplitService($db))->additionsPreview(1, $waiter), 'unsupported_restaurant_history');
check($db->table('transaction_sell_lines')->count() === 2, 'legacy preparation status still prevents ordinary table edits');

[$db, $service, $waiter] = restaurantFixture();
$db->table('restaurant_bills')->delete();
$db->table('saved_sale_split_operations')->delete();
$db->table('transactions')->where('id', 1)->update(['res_table_id' => null, 'res_waiter_id' => null]);
$db->table('variations')->where('id', 5)->update(['default_sell_price' => '0']);
$before = snapshot($db);
rejects(fn () => $service->create($waiter, ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'location_id' => 2,
    'table_id' => 1, 'contact_id' => 3, 'items' => [['variation_id' => 5, 'quantity' => '1']]]), 'restaurant_positive_total_required');
check(snapshot($db) === $before, 'zero-total bill rejection leaves no permanently occupied/unpayable table or invoice allocation');

[$db, $service, $waiter] = restaurantFixture();
$waiter->permissions[] = 'restaurant.bar';
$service->dispatch($waiter, 1, restaurantInput($service, $waiter, ['station' => 'bar', 'variations' => [5]]));
$preview = $service->preview($waiter, 1);
$approval = $service->edit($waiter, 1, ['operation_id' => (string) Illuminate\Support\Str::uuid(),
    'expected_version' => $preview['expected_version'], 'restaurant_version' => $preview['restaurant_version'],
    'items' => [['variation_id' => 5, 'quantity' => '1']], 'reason' => 'Stale test cancellation', 'disposition' => 'return_stock']);
$service->ticket($waiter, 1, ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'station' => 'bar', 'ticket_id' => 1, 'status' => 'cooked']);
$manager = clone $waiter; $manager->id = 8; $manager->permissions[] = 'restaurant.approve_void';
check($service->approvals($manager, 2)[0]['is_stale'], 'preparation after an approval request marks the review stale');
$decision = ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'approval_id' => $approval['approval_id'], 'decision' => 'approve'];
$before = snapshot($db);
rejects(fn () => $service->approve($manager, 1, $decision), 'restaurant_workflow');
check(snapshot($db) === $before, 'stale approval cannot change stock, tickets or bill quantities');
$decision['operation_id'] = (string) Illuminate\Support\Str::uuid(); $decision['decision'] = 'reject';
$service->approve($manager, 1, $decision);
check($db->table('restaurant_approvals')->value('status') === 'rejected' && $db->table('transaction_sell_lines')->count() === 2,
    'manager can reject a stale request without applying its changes');

foreach ([false, true] as $kitchen) {
    [$db, $service, $waiter] = restaurantFixture();
    $db->table('restaurant_bills')->delete();
    $db->table('saved_sale_split_operations')->delete();
    $db->table('transactions')->where('id', 1)->update(['res_table_id' => null, 'res_waiter_id' => null]);
    $create = ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'location_id' => 2, 'table_id' => 1, 'contact_id' => 3,
        'is_kitchen_order' => $kitchen, 'items' => [['variation_id' => 5, 'quantity' => '1.2500']]];
    $result = $service->create($waiter, $create);
    $id = $result['id'];
    check($db->table('restaurant_tickets')->count() === ($kitchen ? 1 : 0), 'checkbox dispatches the initial round only when checked');
    check($db->table('transaction_payments')->count() === 0 && $service->scheduled === 0, 'kitchen dispatch does not collect payment or fiscalize unpaid bills');
    if ($kitchen) {
        check((int) $db->table('transactions')->where('id', $id)->value('is_kitchen_order') === 1 &&
            $db->table('restaurant_tickets')->value('quantity') === '1.2500', 'kitchen flag and exact quantities persist together with the bill');
        $before = snapshot($db);
        check($service->create($waiter, $create) === $result && snapshot($db) === $before, 'creation retry cannot duplicate kitchen tickets or stock deductions');
        rejects(fn () => $service->dispatch($waiter, $id, restaurantInput($service, $waiter, ['station' => 'kitchen', 'variations' => [5]], $id)), 'restaurant_workflow');
        $cook = clone $waiter; $cook->permissions = ['restaurant.kitchen', 'location.2'];
        $queue = $service->kitchenOrders($cook);
        check(count($queue) === 1 && (int) $queue[0]['transaction_id'] === $id && $queue[0]['quantity'] === '1.2500',
            'existing backend Kitchen feed receives the automatically dispatched round');
        $cook->permissions = ['restaurant.kitchen', 'location.999'];
        check($service->kitchenOrders($cook) === [], 'backend Kitchen feed does not reveal tickets from another location');
        $cook->permissions = ['location.2'];
        rejects(fn () => $service->kitchenOrders($cook), 'restaurant_workflow');
        $cook->permissions = ['restaurant.kitchen', 'location.2'];
        $ticket = ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'ticket_id' => (int) $queue[0]['id'], 'station' => 'kitchen', 'status' => 'cooked'];
        $service->ticket($cook, $id, $ticket);
        check($service->kitchenOrders($cook) === [], 'marking the exact round cooked removes it from received Kitchen feed');
        $service->ticket($cook, $id, $ticket);
        check($db->table('restaurant_tickets')->count() === 1, 'cook retry is idempotent');
    }
}
[$db, $service, $waiter] = restaurantFixture();
$db->table('restaurant_bills')->delete();
$db->table('saved_sale_split_operations')->delete();
$db->table('transactions')->where('id', 1)->update(['res_table_id' => null, 'res_waiter_id' => null]);
$create = ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'location_id' => 2, 'table_id' => 1, 'contact_id' => 3,
    'is_kitchen_order' => true, 'items' => [['variation_id' => 5, 'quantity' => '1']]];
$db->table('business')->update(['enabled_modules' => '["tables"]']);
$before = snapshot($db);
rejects(fn () => $service->create($waiter, $create), 'restaurant_workflow');
check(snapshot($db) === $before, 'disabled backend Kitchen module rejects dispatch before creating a bill or deducting stock');
$db->table('business')->update(['enabled_modules' => '["tables","kitchen"]']);
$before = snapshot($db);
$db->statement("CREATE TRIGGER reject_kitchen_ticket BEFORE INSERT ON restaurant_tickets BEGIN SELECT RAISE(ABORT, 'Synthetic kitchen failure'); END");
try {
    $service->create($waiter, $create);
    throw new RuntimeException('Kitchen failure unexpectedly saved order');
} catch (Illuminate\Database\QueryException $e) { check(str_contains($e->getMessage(), 'Synthetic kitchen failure'), 'test injects kitchen dispatch failure'); }
check(snapshot($db) === $before, 'failed kitchen dispatch rolls back bill, invoice counter, stock, mappings and audit');

// Exercise the Connector formatter without booting Laravel or any live configuration.
[$db, $service, $waiter] = restaurantFixture();
$controllerClass = new ReflectionClass(Modules\Connector\Http\Controllers\Api\SellController::class);
$sellControllerSource = file_get_contents(dirname(__DIR__, 2).'/Modules/Connector/Http/Controllers/Api/SellController.php');
$productSellControllerSource = file_get_contents(dirname(__DIR__, 2).'/Modules/Connector/Http/Controllers/Api/ProductSellController.php');
check(substr_count($sellControllerSource, '$q->whereRaw(\'1 = 0\');') === 1,
    'sales list returns no rows when the user lacks direct-view, own-sale and commission-agent visibility');
check(substr_count($productSellControllerSource, '$q->whereRaw(\'1 = 0\');') === 1,
    'product sales list returns no rows when the user lacks direct-view, own-sale and commission-agent visibility');
check(str_contains($sellControllerSource, '\'desktop_pos\' ? null : $transaction->final_total'),
    'desktop API payment status uses the persisted invoice total rather than the raw pre-storage float');
$controller = $controllerClass->newInstanceWithoutConstructor();
$alignDesktopCashPayment = $controllerClass->getMethod('__alignDesktopPosCashPayment');
$alignDesktopCashPayment->setAccessible(true);
$desktopCatalogPrice = $controllerClass->getMethod('__desktopPosCatalogPrice');
$desktopCatalogPrice->setAccessible(true);
$storedTotal = (object) ['final_total' => '11000.0000'];
$catalogVariation = (object) ['default_sell_price' => '17021.2800', 'sell_price_inc_tax' => '20000.0000'];
$catalogLinePrice = $desktopCatalogPrice->invoke($controller, 'desktop_pos', 17021.28, 0, 50, 50, $catalogVariation);
check($catalogLinePrice === ['unit_price_inc_tax' => '20000.0000', 'item_tax' => '2978.7200'],
    'desktop taxed sale uses the backend catalog inclusive price and its exact tax difference');
$secondCatalogLinePrice = $desktopCatalogPrice->invoke(
    $controller,
    'desktop_pos',
    9361.70,
    0,
    50,
    50,
    (object) ['default_sell_price' => '9361.7000', 'sell_price_inc_tax' => '11000.0000']
);
check($secondCatalogLinePrice === ['unit_price_inc_tax' => '11000.0000', 'item_tax' => '1638.3000']
    && number_format((float) $catalogLinePrice['unit_price_inc_tax']
        + (float) $secondCatalogLinePrice['unit_price_inc_tax'], 4, '.', '') === '31000.0000',
    'multiple taxed desktop lines reconcile to their backend catalog totals without fractional-tax drift');
check($desktopCatalogPrice->invoke($controller, 'api', 17021.28, 0, 50, 50, $catalogVariation) === null,
    'non-desktop sales retain the existing tax calculation');
check($desktopCatalogPrice->invoke($controller, 'desktop_pos', 17021.28, 10, 50, 50, $catalogVariation) === null,
    'discounted desktop lines retain the existing discount-aware tax calculation');
check($desktopCatalogPrice->invoke($controller, 'desktop_pos', 17000, 0, 50, 50, $catalogVariation) === null,
    'custom desktop line prices do not override backend tax calculation');
$cashInput = ['source' => 'desktop_pos', 'status' => 'final',
    'payment' => [['method' => 'cash', 'amount' => '10999.9999']]];
check($alignDesktopCashPayment->invoke($controller, $cashInput, $storedTotal)['payment'][0]['amount'] === '11000.0000',
    'desktop full cash tender is aligned to the persisted backend invoice total');
$creditInput = ['source' => 'desktop_pos', 'status' => 'final', 'payment' => []];
check($alignDesktopCashPayment->invoke($controller, $creditInput, $storedTotal) === $creditInput,
    'desktop credit sale remains unpaid without synthetic payment');
$otherPaymentInput = ['source' => 'api', 'status' => 'final',
    'payment' => [['method' => 'cash', 'amount' => '10999.9999']]];
check($alignDesktopCashPayment->invoke($controller, $otherPaymentInput, $storedTotal) === $otherPaymentInput,
    'non-desktop API payment amounts are not normalized');
$contactUtil = new class extends App\Utils\ContactUtil {
    public function getCustomerGroup($business_id, $contact_id) { return null; }
};
$property = $controllerClass->getProperty('contactUtil'); $property->setAccessible(true); $property->setValue($controller, $contactUtil);
$property = $controllerClass->getProperty('transactionUtil'); $property->setAccessible(true); $property->setValue($controller, new App\Utils\TransactionUtil());
$format = $controllerClass->getMethod('__formatSellData'); $format->setAccessible(true);
foreach ([true, false] as $flag) {
    $data = $format->invoke($controller, ['business_id' => 1, 'location_id' => 2, 'contact_id' => 3, 'is_kitchen_order' => $flag]);
    check((bool) $data['is_kitchen_order'] === $flag, 'normal Connector sale formatter retains explicit kitchen checkbox value');
}
$db->table('business')->update(['enabled_modules' => '["tables"]']);
try {
    $format->invoke($controller, ['business_id' => 1, 'location_id' => 2, 'contact_id' => 3, 'is_kitchen_order' => true]);
    throw new RuntimeException('Disabled Kitchen accepted');
} catch (InvalidArgumentException $e) { check(str_contains($e->getMessage(), 'Kitchen module'), 'normal Connector rejects checked orders when Kitchen is disabled'); }

// Render the actual Kitchen round partial without an application kernel.
$container = Illuminate\Container\Container::getInstance();
$container->instance('url', new class {
    public function action($action, $parameters = [], $absolute = true) {
        return '/kitchen/mark-ticket-as-cooked/'.reset($parameters);
    }
});
$files = new Illuminate\Filesystem\Filesystem();
$compiler = new Illuminate\View\Compilers\BladeCompiler($files, sys_get_temp_dir());
$factory = new Illuminate\View\Factory(new Illuminate\View\Engines\EngineResolver(),
    new Illuminate\View\FileViewFinder($files, []), $db->getEventDispatcher());
$factory->setContainer($container);
$compiled = $compiler->compileString(file_get_contents(dirname(__DIR__, 2).'/resources/views/restaurant/partials/kitchen_rounds.blade.php'));
$renderKitchen = function ($kitchen_tickets) use ($factory, $compiled) {
    $__env = $factory;
    ob_start();
    try { eval('?>'.$compiled); return ob_get_contents(); }
    finally { ob_end_clean(); }
};
$html = $renderKitchen([['id' => 9, 'transaction_id' => 12, 'invoice_no' => 'INV-12', 'table_name' => 'Patio',
    'product_name' => 'Food <script>unsafe</script>', 'quantity' => '1.2500']]);
check(str_contains($html, 'INV-12') && str_contains($html, 'Patio') && str_contains($html, '1.2500') &&
    str_contains($html, '/kitchen/mark-ticket-as-cooked/12') && str_contains($html, 'data-ticket-id="9"'),
    'actual backend Kitchen partial shows the exact item round, table/invoice, quantity and cooked action');
check(!str_contains($html, '<script>unsafe</script>') && str_contains($html, '&lt;script&gt;'), 'kitchen round markup escapes product names');
check(trim($renderKitchen([])) === '', 'no kitchen round cards when nothing was dispatched');

[$db, $service, $waiter] = restaurantFixture();
$schema = $db->getSchemaBuilder();
foreach (['contacts', 'business_locations'] as $table) {
    if (!$schema->hasColumn($table, 'name')) {
        $schema->table($table, function (Blueprint $t) { $t->text('name')->nullable(); });
    }
}
$schema->table('transaction_sell_lines', function (Blueprint $t) { $t->text('res_line_order_status')->nullable(); });
$db->table('transactions')->where('id', 1)->update(['is_kitchen_order' => 1]);
$legacy = (array) $db->table('transactions')->where('id', 1)->first();
unset($legacy['id']);
$legacyId = $db->table('transactions')->insertGetId($legacy);
$line = (array) $db->table('transaction_sell_lines')->where('transaction_id', 1)->first();
unset($line['id']);
$line['transaction_id'] = $legacyId;
$db->table('transaction_sell_lines')->insert($line);
Illuminate\Container\Container::getInstance()->instance(Illuminate\Contracts\Auth\Factory::class, new class {
    public function user() { return new class { public function permitted_locations() { return 'all'; } }; }
});
$legacyOrders = (new App\Utils\RestaurantUtil())->getAllOrders(1, ['line_order_status' => 'received', 'is_kitchen_order' => 1]);
check($legacyOrders->pluck('id')->map(fn ($id) => (int) $id)->all() === [$legacyId],
    'real legacy Kitchen query retains ordinary flagged orders but excludes managed merged quantities');

[$db, $service, $waiter] = restaurantFixture();
$preview = $service->preview($waiter, 1, true);
$edit = ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'expected_version' => $preview['expected_version'],
    'restaurant_version' => $preview['restaurant_version'], 'is_kitchen_order' => true,
    'items' => [['variation_id' => 5, 'quantity' => '7.0000']]];
$db->table('business')->update(['enabled_modules' => '["tables"]']);
$before = snapshot($db);
rejects(fn () => $service->edit($waiter, 1, $edit, true), 'restaurant_workflow');
check(snapshot($db) === $before, 'checked desktop edit rolls back items, stock and audit when Kitchen is disabled');
$db->table('business')->update(['enabled_modules' => '["tables","kitchen"]']);
$response = $service->edit($waiter, 1, $edit, true);
check(count($response['dispatch_tickets']) === 1 && $response['dispatch_tickets'][0]['quantity'] === '7.0000' &&
    (int) $db->table('transactions')->where('id', 1)->value('is_kitchen_order') === 1,
    'checked desktop edit atomically saves quantities and dispatches exact kitchen tickets');
$after = snapshot($db);
check($service->edit($waiter, 1, $edit, true) === $response && snapshot($db) === $after,
    'checked desktop edit replay never duplicates kitchen tickets or stock');
rejects(fn () => $service->preview($waiter, 1, true), 'restaurant_workflow');
$next = $edit;
$next['operation_id'] = (string) Illuminate\Support\Str::uuid();
rejects(fn () => $service->edit($waiter, 1, $next, true), 'restaurant_workflow');
check(snapshot($db) === $after, 'desktop cannot edit a sent bill even with a direct request');
$db->table('restaurant_tickets')->where('transaction_id', 1)->update(['status' => 'cancelled']);
$db->table('transactions')->where('id', 1)->update(['is_kitchen_order' => 0]);
rejects(fn () => $service->preview($waiter, 1, true), 'restaurant_workflow');

[$db, $service, $waiter] = restaurantFixture();
$preview = $service->preview($waiter, 1, true);
$service->dispatch($waiter, 1, restaurantInput($service, $waiter, ['station' => 'bar', 'variations' => [5]]));
$before = snapshot($db);
rejects(fn () => $service->edit($waiter, 1, [
    'operation_id' => (string) Illuminate\Support\Str::uuid(), 'expected_version' => $preview['expected_version'],
    'restaurant_version' => $preview['restaurant_version'], 'items' => [['variation_id' => 5, 'quantity' => '7.0000']]
], true), 'restaurant_workflow');
check(snapshot($db) === $before, 'dispatch between desktop preview and save prevents editing without any write');

[$db, $unused, $waiter] = restaurantFixture();
$db->table('restaurant_bills')->delete();
$db->table('transactions')->where('id', 1)->update(['res_table_id' => null, 'res_waiter_id' => null]);
$editor = new App\Services\Sales\SavedSaleSplitService($db);
$preview = $editor->additionsPreview(1, $waiter);
$edit = ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'expected_version' => $preview['expected_version'],
    'is_kitchen_order' => true, 'items' => [['variation_id' => 5, 'quantity' => '7.0000']]];
$db->table('business')->update(['enabled_modules' => '[]']);
$before = snapshot($db);
rejects(fn () => $editor->editItems(1, $waiter, $edit), 'kitchen_unavailable');
check(snapshot($db) === $before, 'ordinary checked edit with disabled Kitchen changes neither sale nor stock');
$db->table('business')->update(['enabled_modules' => '["kitchen"]']);
$response = $editor->editItems(1, $waiter, $edit);
check($response['bill']['is_kitchen_order'] === true &&
    (int) $db->table('transactions')->where('id', 1)->value('is_kitchen_order') === 1,
    'ordinary sale editor saves and sends to legacy Kitchen atomically');
$after = snapshot($db);
check($editor->editItems(1, $waiter, $edit) === $response && snapshot($db) === $after,
    'ordinary checked edit replay is safe after the order becomes locked');
$collision = $edit;
$collision['is_kitchen_order'] = false;
rejects(fn () => $editor->editItems(1, $waiter, $collision), 'operation_collision');
check(!$editor->additionsPreview(1, $waiter)['eligible'], 'sent ordinary sale is ineligible for desktop editing');
$next = $edit;
$next['operation_id'] = (string) Illuminate\Support\Str::uuid();
$next['expected_version'] = $response['bill']['version'];
rejects(fn () => $editor->editItems(1, $waiter, $next), 'order_already_sent');
check(snapshot($db) === $after, 'ordinary sent sale rejects direct edits without changing stock or items');

[$db, $unused, $waiter] = restaurantFixture();
$db->table('restaurant_bills')->delete();
$db->table('transaction_sell_lines_purchase_lines')->delete();
$db->table('transactions')->where('id', 1)->update(['status' => 'draft', 'res_table_id' => null, 'res_waiter_id' => null]);
$editor = new App\Services\Sales\SavedSaleSplitService($db);
$preview = $editor->additionsPreview(1, $waiter);
check($preview['eligible'], 'ordinary unsent draft remains editable without Kitchen dispatch');
$before = snapshot($db);
rejects(fn () => $editor->editItems(1, $waiter, [
    'operation_id' => (string) Illuminate\Support\Str::uuid(), 'expected_version' => $preview['expected_version'],
    'is_kitchen_order' => true, 'items' => [['variation_id' => 5, 'quantity' => '7.0000']]
]), 'kitchen_unavailable');
check(snapshot($db) === $before, 'ordinary draft cannot claim a Kitchen dispatch from the desktop editor');

[$db, $service, $waiter] = restaurantFixture();
$db->table('res_tables')->insert([
    ['id' => 2, 'business_id' => 1, 'location_id' => 2, 'name' => 'Patio 2'],
    ['id' => 3, 'business_id' => 2, 'location_id' => 2, 'name' => 'Other business'],
    ['id' => 4, 'business_id' => 1, 'location_id' => 99, 'name' => 'Other location'],
]);
$db->table('res_tables')->insert(['id' => 5, 'business_id' => 1, 'location_id' => 2, 'name' => 'Deleted',
    'deleted_at' => '2026-10-23 00:00:00']);
$preview = $service->preview($waiter, 1, true);
check($preview['source']['table_id'] === 1, 'desktop preview includes current managed table assignment');
$edit = ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'expected_version' => $preview['expected_version'],
    'restaurant_version' => $preview['restaurant_version'], 'table_id' => 2,
    'items' => [['variation_id' => 5, 'quantity' => '6.0000']]];
foreach ([null, 0, -1, '2', 3, 4, 5, 999] as $invalidTable) {
    $invalid = $edit;
    $invalid['table_id'] = $invalidTable;
    $before = snapshot($db);
    rejects(fn () => $service->edit($waiter, 1, $invalid, true), 'restaurant_workflow');
    check(snapshot($db) === $before, 'invalid/deleted/cross-tenant/location table change is read-only');
}
$db->table('restaurant_bills')->insert(['transaction_id' => 999, 'business_id' => 1, 'location_id' => 2,
    'table_id' => 2, 'waiter_id' => 7, 'state' => 'open', 'version' => 1]);
$before = snapshot($db);
rejects(fn () => $service->edit($waiter, 1, $edit, true), 'restaurant_workflow');
check(snapshot($db) === $before, 'managed occupied table prevents item and table edits atomically');
$db->table('restaurant_bills')->where('transaction_id', 999)->delete();
$legacy = (array) $db->table('transactions')->where('id', 1)->first();
unset($legacy['id']);
$legacy['res_table_id'] = 2;
$legacyId = $db->table('transactions')->insertGetId($legacy);
$before = snapshot($db);
rejects(fn () => $service->edit($waiter, 1, $edit, true), 'restaurant_workflow');
check(snapshot($db) === $before, 'legacy unpaid occupancy prevents moving to a restricted table');
$db->table('res_tables')->where('id', 2)->update(['allow_multiple_orders' => 1]);
$response = $service->edit($waiter, 1, $edit, true);
check((int) $db->table('transactions')->where('id', 1)->value('res_table_id') === 2 &&
    (int) $db->table('restaurant_bills')->where('transaction_id', 1)->value('table_id') === 2 &&
    $response['bill']['table_id'] === 2,
    'shared table change updates header, workflow and edit response together');
$after = snapshot($db);
check($service->edit($waiter, 1, $edit, true) === $response && snapshot($db) === $after,
    'table-change retry returns original result without duplicate edits');
$collision = $edit;
$collision['table_id'] = 1;
rejects(fn () => $service->edit($waiter, 1, $collision, true), 'restaurant_workflow');
$preview = $service->preview($waiter, 1, true);
$checked = ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'expected_version' => $preview['expected_version'],
    'restaurant_version' => $preview['restaurant_version'], 'table_id' => 1, 'is_kitchen_order' => true,
    'items' => [['variation_id' => 5, 'quantity' => '7.0000']]];
$db->table('business')->update(['enabled_modules' => '["tables"]']);
$before = snapshot($db);
rejects(fn () => $service->edit($waiter, 1, $checked, true), 'restaurant_workflow');
check(snapshot($db) === $before, 'Kitchen failure rolls back a table move as well as item changes');
$db->table('business')->update(['enabled_modules' => '["tables","kitchen"]']);
$response = $service->edit($waiter, 1, $checked, true);
check($response['table_id'] === 1 && $response['dispatch_tickets'][0]['quantity'] === '7.0000' &&
    (int) $db->table('restaurant_bills')->where('transaction_id', 1)->value('table_id') === 1,
    'checked edit dispatches to the newly selected table in the same transaction');
$before = snapshot($db);
$checked['operation_id'] = (string) Illuminate\Support\Str::uuid();
$checked['table_id'] = 2;
rejects(fn () => $service->edit($waiter, 1, $checked, true), 'restaurant_workflow');
check(snapshot($db) === $before, 'sent bill cannot change table through desktop');

[$db, $service, $waiter] = restaurantFixture();
$db->table('restaurant_bills')->delete();
$db->table('transactions')->where('id', 1)->update(['res_table_id' => null, 'res_waiter_id' => null]);
$editor = new App\Services\Sales\SavedSaleSplitService($db);
$preview = $editor->additionsPreview(1, $waiter);
$edit = ['operation_id' => (string) Illuminate\Support\Str::uuid(), 'expected_version' => $preview['expected_version'],
    'table_id' => 1, 'items' => [['variation_id' => 5, 'quantity' => '6.0000']]];
$updateOnly = clone $waiter;
$updateOnly->permissions = ['sell.update', 'location.2'];
check($service->config($updateOnly, 2)['can_edit'] && !$service->config($updateOnly, 2)['can_create'],
    'update-only role can load table choices without bill creation permission');
$before = snapshot($db);
$missing = $edit;
$missing['table_id'] = 999;
rejects(fn () => $editor->editItems(1, $waiter, $missing), 'restaurant_workflow');
check(snapshot($db) === $before, 'ordinary sale rejects invalid table assignment without changing items');
$invalid = $edit;
$invalid['table_id'] = '1';
rejects(fn () => $editor->editItems(1, $waiter, $invalid), 'invalid_add_items_request');
$response = $editor->editItems(1, $waiter, $edit);
check($response['bill']['table_id'] === 1 &&
    (int) $db->table('transactions')->where('id', 1)->value('res_table_id') === 1,
    'ordinary unsent sale editor persists a selected backend table');
$after = snapshot($db);
check($editor->editItems(1, $waiter, $edit) === $response && snapshot($db) === $after,
    'ordinary table edit replay is idempotent');
$collision = $edit;
$collision['table_id'] = null;
rejects(fn () => $editor->editItems(1, $waiter, $collision), 'operation_collision');
$preview = $editor->additionsPreview(1, $waiter);
check($preview['eligible'] && $preview['source']['table_id'] === 1, 'ordinary assigned unsent sale can reopen in editor');
$edit['operation_id'] = (string) Illuminate\Support\Str::uuid();
$edit['expected_version'] = $preview['expected_version'];
$edit['table_id'] = null;
$response = $editor->editItems(1, $waiter, $edit);
check($response['bill']['table_id'] === null &&
    $db->table('transactions')->where('id', 1)->value('res_table_id') === null,
    'ordinary unsent sale can explicitly return to no table');
$preview = $editor->additionsPreview(1, $waiter);
$edit['operation_id'] = (string) Illuminate\Support\Str::uuid();
$edit['expected_version'] = $preview['expected_version'];
$edit['table_id'] = 1;
$edit['is_kitchen_order'] = true;
$response = $editor->editItems(1, $waiter, $edit);
check($response['bill']['table_id'] === 1 && $response['bill']['is_kitchen_order'],
    'ordinary checked edit assigns table and Kitchen flag atomically');

[$db, $unused, $actor] = restaurantFixture();
$tillId = tillFixture($db);
$service = new IsolatedRestaurantService($db, $tillId);
$db->table('res_tables')->where('id', 1)->update(['allow_multiple_orders' => true]);
$created = $service->create($actor, ['operation_id' => (string) Illuminate\Support\Str::uuid(),
    'location_id' => 2, 'table_id' => 1, 'contact_id' => 3,
    'items' => [['variation_id' => 5, 'quantity' => '1']]]);
check((int) $db->table('transactions')->where('id', $created['id'])->value('connector_till_id') === $tillId,
    'Restaurant creation persists Connector till in the workflow transaction');
$service->dispatch($actor, 1, restaurantInput($service, $actor, ['station' => 'bar', 'variations' => [5]]));
$service->transition($actor, 1, restaurantInput($service, $actor, ['action' => 'ready']));
$cashier = clone $actor; $cashier->id = 8;
$cashier->permissions = ['sell.view', 'sell.payments', 'location.2'];
$payment = restaurantInput($service, $cashier, ['payments' => [['method' => 'cash', 'amount' => '1.0000']]]);
$service->pay($cashier, 1, $payment);
check((int) $db->table('transactions')->where('id', 1)->value('connector_till_id') === $tillId,
    'Cashier clearing binds existing restaurant bill to the paying till');
echo "PASS: $checks total isolated restaurant/saved-sale checks. No live writes or fiscal requests.\n";
