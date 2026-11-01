<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use App\Transaction;
use App\TransactionPayment;
use Modules\Accounting\Entities\AccountingAccountsTransaction;
use Modules\Accounting\Services\AccrualPostingService;

$checks = 0;

function verifyAccountingTrial(bool $condition, string $message): void
{
    global $checks;
    ++$checks;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

$capsule = new Capsule(new Container());
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$container = $capsule->getContainer();
$container->instance('db', $capsule->getDatabaseManager());
Facade::setFacadeApplication($container);
$db = $capsule->getConnection();
$db->getSchemaBuilder()->create('accounting_accounts', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('business_id');
    $table->string('status');
    $table->string('account_primary_type');
});
$db->getSchemaBuilder()->create('accounting_accounts_transactions', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('accounting_account_id');
    $table->integer('transaction_id')->nullable();
    $table->integer('transaction_payment_id')->nullable();
    $table->decimal('amount', 22, 4);
    $table->string('type');
    $table->string('sub_type');
    $table->string('map_type')->nullable();
    $table->integer('created_by');
    $table->dateTime('operation_date');
    $table->text('note')->nullable();
    $table->timestamps();
});

$businessName = 'HavenPlus Technologies';
$businessId = 1;
$db->table('accounting_accounts')->insert([
    ['id' => 110, 'business_id' => $businessId, 'status' => 'active', 'account_primary_type' => 'income'],
    ['id' => 120, 'business_id' => $businessId, 'status' => 'active', 'account_primary_type' => 'asset'],
    ['id' => 210, 'business_id' => $businessId, 'status' => 'active', 'account_primary_type' => 'asset'],
    ['id' => 220, 'business_id' => $businessId, 'status' => 'active', 'account_primary_type' => 'liability'],
    ['id' => 310, 'business_id' => $businessId, 'status' => 'active', 'account_primary_type' => 'asset'],
    ['id' => 320, 'business_id' => $businessId, 'status' => 'active', 'account_primary_type' => 'asset'],
    ['id' => 179, 'business_id' => $businessId, 'status' => 'active', 'account_primary_type' => 'asset'],
    ['id' => 182, 'business_id' => $businessId, 'status' => 'active', 'account_primary_type' => 'income'],
    ['id' => 180, 'business_id' => $businessId, 'status' => 'active', 'account_primary_type' => 'liability'],
    ['id' => 51, 'business_id' => $businessId, 'status' => 'active', 'account_primary_type' => 'asset'],
    ['id' => 72, 'business_id' => $businessId, 'status' => 'active', 'account_primary_type' => 'expenses'],
    ['id' => 185, 'business_id' => $businessId, 'status' => 'active', 'account_primary_type' => 'liability'],
    ['id' => 184, 'business_id' => $businessId, 'status' => 'active', 'account_primary_type' => 'asset'],
    ['id' => 183, 'business_id' => $businessId, 'status' => 'active', 'account_primary_type' => 'expenses'],
    ['id' => 181, 'business_id' => $businessId, 'status' => 'active', 'account_primary_type' => 'asset'],
]);
$service = new AccrualPostingService();
$scenarios = [
    ['sell', 501, null, 110, 120, 'credit', 'debit'],
    ['purchase', 502, null, 210, 220, 'debit', 'credit'],
    ['sell_payment', null, 601, 120, 310, 'credit', 'debit'],
    ['purchase_payment', null, 602, 220, 320, 'debit', 'credit'],
];

foreach ($scenarios as [$type, $transactionId, $paymentId, $paymentAccountId, $depositToId, $paymentDirection, $depositDirection]) {
    $entries = $service->buildMappingEntries(
        $type,
        '125.00',
        $transactionId,
        $paymentId,
        $paymentAccountId,
        $depositToId,
        7,
        '2026-10-15 12:00:00'
    );

    verifyAccountingTrial(count($entries) === 2, "{$businessName}: {$type} creates a balanced pair");
    verifyAccountingTrial($entries[0]['type'] === $paymentDirection, "{$businessName}: {$type} first direction");
    verifyAccountingTrial($entries[1]['type'] === $depositDirection, "{$businessName}: {$type} counter-entry direction");
    verifyAccountingTrial($entries[0]['amount'] === $entries[1]['amount'], "{$businessName}: {$type} amounts match");
    verifyAccountingTrial($entries[0]['transaction_id'] === $transactionId, "{$businessName}: {$type} invoice source is preserved");
    verifyAccountingTrial($entries[0]['transaction_payment_id'] === $paymentId, "{$businessName}: {$type} payment source is preserved");

    foreach ($entries as $entry) {
        verifyAccountingTrial(
            $db->table('accounting_accounts')
                ->where('business_id', $businessId)
                ->where('status', 'active')
                ->where('id', $entry['accounting_account_id'])
                ->exists(),
            "{$businessName}: {$type} uses an active account from the trial business"
        );
        AccountingAccountsTransaction::updateOrCreateMapTransaction($entry);
    }

    foreach ($service->buildMappingEntries(
        $type,
        '126.00',
        $transactionId,
        $paymentId,
        $paymentAccountId,
        $depositToId,
        7,
        '2026-10-15 12:00:00'
    ) as $entry) {
        AccountingAccountsTransaction::updateOrCreateMapTransaction($entry);
    }
}

verifyAccountingTrial(
    $db->table('accounting_accounts_transactions')->count() === 8,
    "{$businessName}: re-saving invoice and payment mappings updates entries without duplicates"
);
verifyAccountingTrial(
    (float) $db->table('accounting_accounts_transactions')->where('type', 'debit')->sum('amount') === 504.0
        && (float) $db->table('accounting_accounts_transactions')->where('type', 'credit')->sum('amount') === 504.0,
    "{$businessName}: mapped trial balance remains balanced after re-saving"
);
verifyAccountingTrial(
    $db->table('accounting_accounts_transactions')->where('amount', '126.0000')->count() === 8,
    "{$businessName}: re-saving updates the original posting amounts"
);

foreach ([
    'business' => function (Blueprint $table) {
        $table->integer('id')->primary();
        $table->text('accounting_settings')->nullable();
    },
    'transactions' => function (Blueprint $table) {
        $table->increments('id');
        $table->integer('business_id');
        $table->string('type');
        $table->string('status');
        $table->decimal('final_total', 22, 4);
        $table->decimal('tax_amount', 22, 4)->default(0);
        $table->dateTime('transaction_date');
        $table->integer('created_by');
    },
    'transaction_payments' => function (Blueprint $table) {
        $table->increments('id');
        $table->integer('business_id');
        $table->integer('transaction_id');
        $table->integer('parent_id')->nullable();
        $table->string('method');
        $table->decimal('amount', 22, 4);
        $table->boolean('is_return')->default(false);
        $table->dateTime('paid_on');
        $table->integer('created_by');
    },
    'transaction_sell_lines' => function (Blueprint $table) {
        $table->increments('id');
        $table->integer('transaction_id');
    },
    'transaction_sell_lines_purchase_lines' => function (Blueprint $table) {
        $table->increments('id');
        $table->integer('sell_line_id');
        $table->integer('purchase_line_id');
        $table->decimal('quantity', 22, 4);
        $table->decimal('qty_returned', 22, 4)->default(0);
    },
    'purchase_lines' => function (Blueprint $table) {
        $table->increments('id');
        $table->decimal('purchase_price', 22, 4);
    },
    'accounting_source_postings' => function (Blueprint $table) {
        $table->increments('id');
        $table->integer('business_id');
        $table->string('source_type');
        $table->integer('source_id');
        $table->string('fingerprint');
        $table->text('snapshot');
        $table->timestamps();
        $table->unique(['business_id', 'source_type', 'source_id'], 'accounting_source_unique');
    },
] as $tableName => $definition) {
    $db->getSchemaBuilder()->create($tableName, $definition);
}

$autoPostingAccounts = [
    'receivables' => 179,
    'sales' => 182,
    'payables' => 180,
    'inventory' => 51,
    'cost_of_goods_sold' => 72,
    'output_tax' => 185,
    'input_tax' => 184,
    'expenses' => 183,
    'clearing' => 181,
];
$db->table('business')->insert([
    'id' => $businessId,
    'accounting_settings' => json_encode([
        'auto_posting_enabled' => true,
        'auto_posting_accounts' => $autoPostingAccounts,
        'auto_posting_purchase_tax_recoverable' => true,
    ]),
]);
$service->assertBusinessReady($businessId);

$db->table('transactions')->insert([
    'id' => 705,
    'business_id' => $businessId,
    'type' => 'sell',
    'status' => 'draft',
    'final_total' => 100,
    'tax_amount' => 0,
    'transaction_date' => '2026-10-19 11:00:00',
    'created_by' => 7,
]);
$db->table('transaction_payments')->insert([
    'id' => 803,
    'business_id' => $businessId,
    'transaction_id' => 705,
    'parent_id' => null,
    'method' => 'cash',
    'amount' => 10,
    'is_return' => false,
    'paid_on' => '2026-10-19 11:30:00',
    'created_by' => 7,
]);
$service->syncPayment(TransactionPayment::findOrFail(803));
verifyAccountingTrial(
    $db->table('accounting_accounts_transactions')->where('transaction_payment_id', 803)->count() === 0,
    "{$businessName}: payments for draft invoices are not posted before the invoice is final"
);

$db->table('transactions')->insert([
    'id' => 701,
    'business_id' => $businessId,
    'type' => 'sell',
    'status' => 'final',
    'final_total' => 120,
    'tax_amount' => 20,
    'transaction_date' => '2026-10-15 12:00:00',
    'created_by' => 7,
]);
$db->table('transaction_sell_lines')->insert(['id' => 1, 'transaction_id' => 701]);
$db->table('purchase_lines')->insert(['id' => 1, 'purchase_price' => 30]);
$db->table('transaction_sell_lines_purchase_lines')->insert([
    'sell_line_id' => 1,
    'purchase_line_id' => 1,
    'quantity' => 2,
    'qty_returned' => 0,
]);
$sale = Transaction::findOrFail(701);
$service->syncTransaction($sale);

$saleLines = $db->table('accounting_accounts_transactions')->where('transaction_id', 701)->get();
verifyAccountingTrial($saleLines->count() === 5, "{$businessName}: sale posts receivable, net sales, tax, and inventory cost");
verifyAccountingTrial(
    (float) $saleLines->where('type', 'debit')->sum('amount') === 180.0
        && (float) $saleLines->where('type', 'credit')->sum('amount') === 180.0,
    "{$businessName}: sale and cost of goods sold entries balance"
);
verifyAccountingTrial(
    (float) $saleLines->where('accounting_account_id', 72)->where('type', 'debit')->sum('amount') === 60.0
        && (float) $saleLines->where('accounting_account_id', 51)->where('type', 'credit')->sum('amount') === 60.0,
    "{$businessName}: sale recognizes the purchase-linked cost of goods sold"
);

$initialAutoLineCount = $db->table('accounting_accounts_transactions')->count();
$service->syncTransaction($sale);
verifyAccountingTrial(
    $db->table('accounting_accounts_transactions')->count() === $initialAutoLineCount,
    "{$businessName}: repeating a source event does not duplicate automatic postings"
);

$db->table('transactions')->where('id', 701)->update([
    'final_total' => 132,
    'tax_amount' => 22,
    'transaction_date' => '2026-10-16 12:00:00',
]);
$service->syncTransaction(Transaction::findOrFail(701));
$updatedSaleLines = $db->table('accounting_accounts_transactions')
    ->where('transaction_id', 701)
    ->where('sub_type', 'sale_invoice')
    ->get();
$saleReversalLines = $db->table('accounting_accounts_transactions')
    ->where('transaction_id', 701)
    ->where('sub_type', 'automatic_reversal')
    ->get();
verifyAccountingTrial(
    $updatedSaleLines->count() === 10,
    "{$businessName}: the edited sale retains both original and replacement entries"
);
verifyAccountingTrial(
    $saleReversalLines->count() === 5,
    "{$businessName}: editing a sale creates reversing entries for the prior entry set"
);
$netReceivable = (float) $db->table('accounting_accounts_transactions')
    ->where('transaction_id', 701)
    ->where('accounting_account_id', 179)
    ->where('type', 'debit')
    ->sum('amount') - (float) $db->table('accounting_accounts_transactions')
        ->where('transaction_id', 701)
        ->where('accounting_account_id', 179)
        ->where('type', 'credit')
        ->sum('amount');
verifyAccountingTrial(
    abs($netReceivable - 132.0) < 0.0001,
    "{$businessName}: edits reverse the previous sale before posting its new totals"
);

$db->table('transaction_payments')->insert([
    'id' => 801,
    'business_id' => $businessId,
    'transaction_id' => 701,
    'parent_id' => null,
    'method' => 'cash',
    'amount' => 40,
    'is_return' => false,
    'paid_on' => '2026-10-16 13:00:00',
    'created_by' => 7,
]);
$payment = TransactionPayment::findOrFail(801);
$service->syncPayment($payment);
$paymentLines = $db->table('accounting_accounts_transactions')->where('transaction_payment_id', 801)->get();
verifyAccountingTrial(
    $paymentLines->count() === 2
        && (float) $paymentLines->where('accounting_account_id', 181)->where('type', 'debit')->sum('amount') === 40.0
        && (float) $paymentLines->where('accounting_account_id', 179)->where('type', 'credit')->sum('amount') === 40.0,
    "{$businessName}: customer receipts debit clearing and credit receivables"
);

$db->table('transaction_payments')->where('id', 801)->update([
    'amount' => 50,
    'paid_on' => '2026-10-16 14:00:00',
]);
$service->syncPayment(TransactionPayment::findOrFail(801));
$service->syncPayment(TransactionPayment::findOrFail(801), true);
$paymentLines = $db->table('accounting_accounts_transactions')->where('transaction_payment_id', 801)->get();
verifyAccountingTrial(
    $paymentLines->count() === 8
        && (float) $paymentLines->where('accounting_account_id', 181)->where('type', 'debit')->sum('amount')
            - (float) $paymentLines->where('accounting_account_id', 181)->where('type', 'credit')->sum('amount') === 0.0
        && (float) $paymentLines->where('accounting_account_id', 179)->where('type', 'credit')->sum('amount')
            - (float) $paymentLines->where('accounting_account_id', 179)->where('type', 'debit')->sum('amount') === 0.0,
    "{$businessName}: payment updates and deletions post balanced reversals"
);

$service->syncTransaction(Transaction::findOrFail(701), true);
$saleNetByAccount = $db->table('accounting_accounts_transactions')
    ->where('transaction_id', 701)
    ->select('accounting_account_id')
    ->selectRaw("SUM(CASE WHEN type = 'debit' THEN amount ELSE -amount END) as net_amount")
    ->groupBy('accounting_account_id')
    ->get();
verifyAccountingTrial(
    $saleNetByAccount->every(fn ($line) => abs((float) $line->net_amount) < 0.0001)
        && $db->table('accounting_source_postings')
            ->where('business_id', $businessId)
            ->where('source_type', 'sale_invoice')
            ->where('source_id', 701)
            ->exists(),
    "{$businessName}: deleting a sale reverses its invoice and linked payment postings"
);

$db->table('transactions')->insert([
    'id' => 702,
    'business_id' => $businessId,
    'type' => 'purchase',
    'status' => 'received',
    'final_total' => 220,
    'tax_amount' => 20,
    'transaction_date' => '2026-10-17 12:00:00',
    'created_by' => 7,
]);
$service->syncTransaction(Transaction::findOrFail(702));
$purchaseLines = $db->table('accounting_accounts_transactions')->where('transaction_id', 702)->get();
verifyAccountingTrial(
    $purchaseLines->count() === 3
        && (float) $purchaseLines->where('accounting_account_id', 51)->where('type', 'debit')->sum('amount') === 200.0
        && (float) $purchaseLines->where('accounting_account_id', 184)->where('type', 'debit')->sum('amount') === 20.0
        && (float) $purchaseLines->where('accounting_account_id', 180)->where('type', 'credit')->sum('amount') === 220.0,
    "{$businessName}: received purchases separate recoverable input tax"
);

$db->table('transactions')->insert([
    'id' => 703,
    'business_id' => $businessId,
    'type' => 'expense',
    'status' => 'final',
    'final_total' => 55,
    'tax_amount' => 5,
    'transaction_date' => '2026-10-18 12:00:00',
    'created_by' => 7,
]);
$service->syncTransaction(Transaction::findOrFail(703));
$expenseLines = $db->table('accounting_accounts_transactions')->where('transaction_id', 703)->get();
verifyAccountingTrial(
    $expenseLines->count() === 3
        && (float) $expenseLines->where('accounting_account_id', 183)->where('type', 'debit')->sum('amount') === 50.0
        && (float) $expenseLines->where('accounting_account_id', 184)->where('type', 'debit')->sum('amount') === 5.0
        && (float) $expenseLines->where('accounting_account_id', 180)->where('type', 'credit')->sum('amount') === 55.0,
    "{$businessName}: expenses separate recoverable input tax"
);

$db->table('transactions')->insert([
    'id' => 704,
    'business_id' => $businessId,
    'type' => 'sell',
    'status' => 'final',
    'final_total' => 100,
    'tax_amount' => 0,
    'transaction_date' => '2026-10-19 12:00:00',
    'created_by' => 7,
]);
$service->syncTransaction(Transaction::findOrFail(704));
$db->table('transaction_payments')->insert([
    'id' => 802,
    'business_id' => $businessId,
    'transaction_id' => 704,
    'parent_id' => null,
    'method' => 'cash',
    'amount' => 30,
    'is_return' => true,
    'paid_on' => '2026-10-19 13:00:00',
    'created_by' => 7,
]);
$service->syncPayment(TransactionPayment::findOrFail(802));
$refundLines = $db->table('accounting_accounts_transactions')->where('transaction_payment_id', 802)->get();
verifyAccountingTrial(
    $refundLines->count() === 2
        && (float) $refundLines->where('accounting_account_id', 179)->where('type', 'debit')->sum('amount') === 30.0
        && (float) $refundLines->where('accounting_account_id', 181)->where('type', 'credit')->sum('amount') === 30.0,
    "{$businessName}: customer refunds debit receivables and credit clearing"
);

$db->table('business')->where('id', $businessId)->update([
    'accounting_settings' => json_encode([
        'auto_posting_enabled' => false,
        'auto_posting_accounts' => $autoPostingAccounts,
        'auto_posting_purchase_tax_recoverable' => true,
    ]),
]);
$service = new AccrualPostingService();
$service->syncTransaction(Transaction::findOrFail(704));
$disabledSaleLines = $db->table('accounting_accounts_transactions')->where('transaction_id', 704)->get();
verifyAccountingTrial(
    $disabledSaleLines->count() === 8
        && $disabledSaleLines->groupBy('accounting_account_id')->every(function ($entries) {
            $debits = (float) $entries->where('type', 'debit')->sum('amount');
            $credits = (float) $entries->where('type', 'credit')->sum('amount');
            return abs($debits - $credits) < 0.0001;
        }),
    "{$businessName}: disabling auto-posting reverses existing invoices and return payments"
);

$service->syncTransaction(Transaction::findOrFail(702));
$reconciledPurchaseLines = $db->table('accounting_accounts_transactions')
    ->where('transaction_id', 702)
    ->get();
verifyAccountingTrial(
    $reconciledPurchaseLines->count() === 6
        && abs((float) $reconciledPurchaseLines->where('accounting_account_id', 51)->where('type', 'debit')->sum('amount')
            - (float) $reconciledPurchaseLines->where('accounting_account_id', 51)->where('type', 'credit')->sum('amount')) < 0.0001
        && abs((float) $reconciledPurchaseLines->where('accounting_account_id', 184)->where('type', 'debit')->sum('amount')
            - (float) $reconciledPurchaseLines->where('accounting_account_id', 184)->where('type', 'credit')->sum('amount')) < 0.0001,
    "{$businessName}: disabling auto-posting reverses existing source entries"
);

verifyAccountingTrial(
    $service->mappingDirections('sell') === $service->mappingDirections('sell_payment'),
    "{$businessName}: sale receipt clears receivables rather than posting revenue again"
);
verifyAccountingTrial(
    $service->mappingDirections('purchase') === $service->mappingDirections('purchase_payment'),
    "{$businessName}: purchase settlement follows the payable-side debit/credit direction"
);

try {
    $service->mappingDirections('expense');
    verifyAccountingTrial(false, "{$businessName}: unsupported mapping types are rejected");
} catch (InvalidArgumentException $exception) {
    verifyAccountingTrial(true, "{$businessName}: unsupported mapping types are rejected");
}

echo "PASS: {$checks} HavenPlus Technologies accounting posting checks\n";