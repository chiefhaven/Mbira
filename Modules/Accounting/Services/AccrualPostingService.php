<?php

namespace Modules\Accounting\Services;

use App\Business;
use App\Transaction;
use App\TransactionPayment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Accounting\Entities\AccountingAccountsTransaction;
use RuntimeException;

class AccrualPostingService
{
    private $settingsCache = [];

    private $accountCache = [];

    private $sourceTrackerReady = false;

    public function buildMappingEntries(
        string $sourceType,
        $amount,
        ?int $transactionId,
        ?int $transactionPaymentId,
        int $paymentAccountId,
        int $depositToAccountId,
        int $userId,
        string $operationDate
    ): array {
        $directions = $this->mappingDirections($sourceType);

        if (in_array($sourceType, ['sell', 'purchase'], true)) {
            if ($transactionId === null || $transactionPaymentId !== null) {
                throw new InvalidArgumentException('Invoice mappings require a transaction ID only.');
            }
        } elseif ($transactionPaymentId === null || $transactionId !== null) {
            throw new InvalidArgumentException('Payment mappings require a payment ID only.');
        }

        $common = [
            'transaction_id' => $transactionId,
            'transaction_payment_id' => $transactionPaymentId,
            'amount' => $amount,
            'sub_type' => $sourceType,
            'created_by' => $userId,
            'operation_date' => $operationDate,
        ];

        return [
            array_merge($common, [
                'accounting_account_id' => $paymentAccountId,
                'type' => $directions['payment_account'],
                'map_type' => 'payment_account',
            ]),
            array_merge($common, [
                'accounting_account_id' => $depositToAccountId,
                'type' => $directions['deposit_to'],
                'map_type' => 'deposit_to',
            ]),
        ];
    }

    public function mappingDirections(string $sourceType): array
    {
        switch ($sourceType) {
            case 'sell':
                return ['payment_account' => 'credit', 'deposit_to' => 'debit'];
            case 'purchase':
                return ['payment_account' => 'debit', 'deposit_to' => 'credit'];
            case 'sell_payment':
                return ['payment_account' => 'credit', 'deposit_to' => 'debit'];
            case 'purchase_payment':
                return ['payment_account' => 'debit', 'deposit_to' => 'credit'];
            default:
                throw new InvalidArgumentException('Unsupported accounting mapping source type.');
        }
    }

    public function syncTransaction(Transaction $transaction, bool $deleted = false): void
    {
        $settings = $this->settingsForBusiness((int) $transaction->business_id);
        $sourceType = [
            'sell' => 'sale_invoice',
            'purchase' => 'purchase_bill',
            'expense' => 'expense',
            'payroll' => 'payroll',
        ][$transaction->type] ?? null;

        if ($sourceType === null) {
            return;
        }

        $enabled = !empty($settings['auto_posting_enabled']);
        if ($enabled) {
            $this->assertSourceTrackerReady();
        }
        $accounts = $enabled ? $this->validatedAccounts((int) $transaction->business_id, $settings) : [];
        $eligible = $enabled && !$deleted && (
            ($transaction->type === 'sell' && $transaction->status === 'final')
            || ($transaction->type === 'purchase' && $transaction->status === 'received')
            || ($transaction->type === 'expense' && $transaction->status === 'final')
            || ($transaction->type === 'payroll' && $transaction->status === 'final' && $this->payrollGroupIsFinal($transaction))
        );

        $lines = $eligible
            ? $this->transactionPostings($transaction, $accounts, $settings)
            : [];

        if ($enabled || $this->sourceExists((int) $transaction->business_id, $sourceType, (int) $transaction->id)) {
            $this->syncSource(
                (int) $transaction->business_id,
                $sourceType,
                (int) $transaction->id,
                $lines,
                $transaction->transaction_date,
                (int) $transaction->created_by,
                $deleted ? 'deleted' : ($eligible ? 'posted' : 'inactive'),
                (int) $transaction->id,
                null
            );
        }

        $payments = TransactionPayment::where('transaction_id', $transaction->id)
            ->where('business_id', $transaction->business_id)
            ->whereNull('parent_id')
            ->where('method', '!=', 'advance')
            ->get();

        foreach ($payments as $payment) {
            $this->syncPayment($payment, $deleted, $deleted ? $transaction : null);
        }
    }

    public function syncPayment(TransactionPayment $payment, bool $deleted = false, ?Transaction $sourceTransaction = null): void
    {
        $transaction = $sourceTransaction ?: Transaction::where('business_id', $payment->business_id)
            ->find($payment->transaction_id);

        if ($transaction === null) {
            return;
        }

        $settings = $this->settingsForBusiness((int) $transaction->business_id);
        $types = [
            'sell' => ['customer_payment', 'receivables'],
            'purchase' => ['supplier_payment', 'payables'],
            'expense' => ['expense_payment', 'payables'],
            'payroll' => ['payroll_payment', 'payables'],
        ];
        if (!isset($types[$transaction->type])) {
            return;
        }

        [$sourceType, $clearingCounterpart] = $types[$transaction->type];
        $enabled = !empty($settings['auto_posting_enabled']);
        if (!$enabled && !$this->sourceExists((int) $transaction->business_id, $sourceType, (int) $payment->id)) {
            return;
        }

        if ($enabled) {
            $this->assertSourceTrackerReady();
        }
        $accounts = $enabled ? $this->validatedAccounts((int) $transaction->business_id, $settings) : [];
        $transactionEligible = ($transaction->type === 'sell' && $transaction->status === 'final')
            || ($transaction->type === 'purchase' && $transaction->status === 'received')
            || ($transaction->type === 'expense' && $transaction->status === 'final')
            || ($transaction->type === 'payroll' && $transaction->status === 'final' && $this->payrollGroupIsFinal($transaction));
        $isRefund = (bool) $payment->is_return;
        $isEligible = $enabled
            && !$deleted
            && $transactionEligible
            && empty($payment->parent_id)
            && $payment->method !== 'advance'
            && (float) $payment->amount > 0;
        $lines = [];

        if ($isEligible) {
            if ($transaction->type === 'sell') {
                $lines = $isRefund
                    ? $this->pair($accounts[$clearingCounterpart], $accounts['clearing'], $payment->amount, 'debit', 'credit', 'customer_refund')
                    : $this->pair($accounts['clearing'], $accounts[$clearingCounterpart], $payment->amount, 'debit', 'credit', 'customer_receipt');
            } else {
                $paymentType = $transaction->type === 'payroll' ? 'payroll_payment' : 'supplier_payment';
                $lines = $isRefund
                    ? $this->pair($accounts['clearing'], $accounts[$clearingCounterpart], $payment->amount, 'debit', 'credit', $paymentType . '_refund')
                    : $this->pair($accounts[$clearingCounterpart], $accounts['clearing'], $payment->amount, 'debit', 'credit', $paymentType);
            }
        }

        $this->syncSource(
            (int) $transaction->business_id,
            $sourceType,
            (int) $payment->id,
            $lines,
            $payment->paid_on,
            (int) $payment->created_by,
            $deleted ? 'deleted' : ($isEligible ? 'posted' : 'inactive'),
            (int) $transaction->id,
            (int) $payment->id
        );
    }

    private function transactionPostings(Transaction $transaction, array $accounts, array $settings): array
    {
        $gross = round((float) $transaction->final_total, 4);
        $tax = round((float) $transaction->tax_amount, 4);
        $net = round($gross - $tax, 4);

        if ($gross < 0 || $tax < 0 || $net < 0) {
            throw new RuntimeException('Automatic accounting posting received an invalid transaction total.');
        }

        if ($transaction->type === 'payroll') {
            $deductions = json_decode($transaction->essentials_deductions ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($deductions) || !is_array($deductions['deduction_amounts'] ?? [])) {
                throw new RuntimeException('Automatic payroll accounting received invalid deduction details.');
            }

            $deductionTotal = 0.0;
            foreach ($deductions['deduction_amounts'] ?? [] as $amount) {
                if (!is_numeric($amount) || (float) $amount < 0) {
                    throw new RuntimeException('Automatic payroll accounting received an invalid deduction amount.');
                }
                $deductionTotal += (float) $amount;
            }

            $grossWages = round($gross + $deductionTotal, 4);
            return $this->withoutZeroLines([
                $this->line($accounts['expenses'], $grossWages, 'debit', 'payroll_expense'),
                $this->line($accounts['payables'], $grossWages, 'credit', 'payroll_payable'),
            ]);
        }

        if ($transaction->type === 'sell') {
            $lines = [
                $this->line($accounts['receivables'], $gross, 'debit', 'sale_receivable'),
                $this->line($accounts['sales'], $net, 'credit', 'sale_revenue'),
            ];
            if ($tax > 0) {
                $lines[] = $this->line($accounts['output_tax'], $tax, 'credit', 'sales_tax');
            }

            $cost = $this->saleCost((int) $transaction->id);
            if ($cost > 0) {
                $lines = array_merge($lines, $this->pair(
                    $accounts['cost_of_goods_sold'],
                    $accounts['inventory'],
                    $cost,
                    'debit',
                    'credit',
                    'sale_cost_of_goods_sold'
                ));
            }

            return $this->withoutZeroLines($lines);
        }

        private function payrollGroupIsFinal(Transaction $transaction): bool
        {
            $status = DB::table('essentials_payroll_group_transactions as payroll_links')
                ->join('essentials_payroll_groups as payroll_groups', 'payroll_groups.id', '=', 'payroll_links.payroll_group_id')
                ->where('payroll_links.transaction_id', $transaction->id)
                ->where('payroll_groups.business_id', $transaction->business_id)
                ->value('payroll_groups.status');

            return $status === null || $status === 'final';
        }

        $expenseAccount = $transaction->type === 'purchase' ? 'inventory' : 'expenses';
        $taxAccount = !empty($settings['auto_posting_purchase_tax_recoverable']) ? 'input_tax' : null;
        $expenseNet = $taxAccount === null ? $gross : $net;
        $lines = [
            $this->line($accounts[$expenseAccount], $expenseNet, 'debit', $transaction->type . '_cost'),
            $this->line($accounts['payables'], $gross, 'credit', $transaction->type . '_payable'),
        ];

        if ($tax > 0 && $taxAccount !== null) {
            $lines[] = $this->line($accounts[$taxAccount], $tax, 'debit', 'input_tax');
        }

        return $this->withoutZeroLines($lines);
    }

    private function saleCost(int $transactionId): float
    {
        return round((float) DB::table('transaction_sell_lines as sell_lines')
            ->join('transaction_sell_lines_purchase_lines as line_costs', 'line_costs.sell_line_id', '=', 'sell_lines.id')
            ->join('purchase_lines as purchase_lines', 'purchase_lines.id', '=', 'line_costs.purchase_line_id')
            ->where('sell_lines.transaction_id', $transactionId)
            ->selectRaw('SUM(CASE WHEN COALESCE(line_costs.quantity, 0) > COALESCE(line_costs.qty_returned, 0) THEN (line_costs.quantity - line_costs.qty_returned) * COALESCE(purchase_lines.purchase_price, 0) ELSE 0 END) as cost')
            ->value('cost'), 4);
    }

    private function syncSource(
        int $businessId,
        string $sourceType,
        int $sourceId,
        array $lines,
        $operationDate,
        int $createdBy,
        string $status,
        ?int $transactionId,
        ?int $paymentId
    ): void {
        if ($operationDate === null) {
            throw new RuntimeException('Automatic accounting posting source is missing its operation date.');
        }

        $lines = $this->withoutZeroLines($lines);
        $debits = round(array_sum(array_map(
            fn ($line) => $line['type'] === 'debit' ? (float) $line['amount'] : 0,
            $lines
        )), 4);
        $credits = round(array_sum(array_map(
            fn ($line) => $line['type'] === 'credit' ? (float) $line['amount'] : 0,
            $lines
        )), 4);

        if (abs($debits - $credits) > 0.0001) {
            throw new RuntimeException("Automatic accounting posting for {$sourceType} #{$sourceId} is out of balance.");
        }

        $operationDate = \Carbon\Carbon::parse($operationDate)->toDateTimeString();
        $snapshot = [
            'status' => $status,
            'operation_date' => $operationDate,
            'lines' => $lines,
        ];
        $snapshotJson = json_encode($snapshot, JSON_THROW_ON_ERROR);
        $fingerprint = hash('sha256', $snapshotJson);

        DB::transaction(function () use (
            $businessId,
            $sourceType,
            $sourceId,
            $snapshot,
            $fingerprint,
            $operationDate,
            $createdBy,
            $transactionId,
            $paymentId
        ) {
            DB::table('business')
                ->where('id', $businessId)
                ->lockForUpdate()
                ->first();

            $source = DB::table('accounting_source_postings')
                ->where('business_id', $businessId)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->lockForUpdate()
                ->first();

            if ($source !== null && hash_equals((string) $source->fingerprint, $fingerprint)) {
                return;
            }

            $oldSnapshot = $source !== null ? json_decode($source->snapshot, true) : null;
            foreach (($oldSnapshot['lines'] ?? []) as $oldLine) {
                $this->createLedgerLine(
                    $businessId,
                    $oldLine['accounting_account_id'],
                    $oldLine['amount'],
                    $oldLine['type'] === 'debit' ? 'credit' : 'debit',
                    'automatic_reversal',
                    'auto_reversal:' . $oldLine['map_type'],
                    $createdBy,
                    now()->toDateTimeString(),
                    $transactionId,
                    $paymentId,
                    "Reversal of {$sourceType} #{$sourceId}",
                    true
                );
            }

            $ledgerLines = [];
            foreach ($snapshot['lines'] as $line) {
                $ledgerId = $this->createLedgerLine(
                    $businessId,
                    $line['accounting_account_id'],
                    $line['amount'],
                    $line['type'],
                    $sourceType,
                    'auto:' . $line['map_type'],
                    $createdBy,
                    \Carbon\Carbon::parse($operationDate)->toDateTimeString(),
                    $transactionId,
                    $paymentId,
                    "Automatic posting for {$sourceType} #{$sourceId}"
                );
                $ledgerLines[] = array_merge($line, ['ledger_id' => $ledgerId]);
            }

            $storedSnapshot = json_encode([
                'status' => $snapshot['status'],
                'operation_date' => $snapshot['operation_date'],
                'lines' => $ledgerLines,
            ], JSON_THROW_ON_ERROR);
            $timestamps = ['fingerprint' => $fingerprint, 'snapshot' => $storedSnapshot, 'updated_at' => now()];

            if ($source === null) {
                DB::table('accounting_source_postings')->insert(array_merge($timestamps, [
                    'business_id' => $businessId,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'created_at' => now(),
                ]));
            } else {
                DB::table('accounting_source_postings')
                    ->where('id', $source->id)
                    ->update($timestamps);
            }
        });
    }

    private function createLedgerLine(
        int $businessId,
        int $accountId,
        $amount,
        string $type,
        string $subType,
        string $mapType,
        int $createdBy,
        string $operationDate,
        ?int $transactionId,
        ?int $paymentId,
        string $note,
        bool $allowInactive = false
    ): int {
        $account = AccountingAccount::where('business_id', $businessId)
            ->find($accountId);

        if ($account === null || (!$allowInactive && $account->status !== 'active')) {
            throw new RuntimeException("Accounting account {$accountId} is no longer active for business {$businessId}.");
        }

        $entry = AccountingAccountsTransaction::create([
            'accounting_account_id' => $accountId,
            'transaction_id' => $transactionId,
            'transaction_payment_id' => $paymentId,
            'amount' => number_format((float) $amount, 4, '.', ''),
            'type' => $type,
            'sub_type' => $subType,
            'map_type' => $mapType,
            'created_by' => $createdBy,
            'operation_date' => $operationDate,
            'note' => $note,
        ]);

        return (int) $entry->id;
    }

    private function sourceExists(int $businessId, string $sourceType, int $sourceId): bool
    {
        if (!DB::connection()->getSchemaBuilder()->hasTable('accounting_source_postings')) {
            return false;
        }

        return DB::table('accounting_source_postings')
            ->where('business_id', $businessId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
    }

    private function settingsForBusiness(int $businessId): array
    {
        if (array_key_exists($businessId, $this->settingsCache)) {
            return $this->settingsCache[$businessId];
        }

        $settings = Business::whereKey($businessId)->value('accounting_settings');
        if ($settings === null || $settings === '') {
            return $this->settingsCache[$businessId] = [];
        }
        if (is_array($settings)) {
            return $this->settingsCache[$businessId] = $settings;
        }

        try {
            $settings = json_decode($settings, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException("Accounting settings for business {$businessId} contain invalid JSON.", 0, $exception);
        }

        if (!is_array($settings)) {
            throw new RuntimeException("Accounting settings for business {$businessId} must be a JSON object.");
        }

        return $this->settingsCache[$businessId] = $settings;
    }

    private function validatedAccounts(int $businessId, array $settings): array
    {
        $configured = $settings['auto_posting_accounts'] ?? [];
        $expectedTypes = [
            'receivables' => 'asset',
            'sales' => 'income',
            'payables' => 'liability',
            'inventory' => 'asset',
            'cost_of_goods_sold' => 'expenses',
            'output_tax' => 'liability',
            'input_tax' => 'asset',
            'expenses' => 'expenses',
            'clearing' => 'asset',
        ];
        $accounts = [];
        foreach ($expectedTypes as $key => $type) {
            $accounts[$key] = (int) ($configured[$key] ?? 0);
        }

        if (count(array_unique($accounts)) !== count($expectedTypes)) {
            throw new RuntimeException('Automatic accounting requires a different account for each posting role.');
        }

        $cacheKey = $businessId . ':' . hash('sha256', json_encode($accounts, JSON_THROW_ON_ERROR));
        if (isset($this->accountCache[$cacheKey])) {
            return $this->accountCache[$cacheKey];
        }

        $records = AccountingAccount::where('business_id', $businessId)
            ->where('status', 'active')
            ->whereIn('id', array_values($accounts))
            ->get(['id', 'account_primary_type'])
            ->keyBy('id');

        foreach ($expectedTypes as $key => $type) {
            $account = $records->get($accounts[$key]);
            if ($account === null || $account->account_primary_type !== $type) {
                throw new RuntimeException("Automatic accounting is not configured with a valid {$key} account.");
            }
        }

        $this->accountCache[$cacheKey] = $accounts;

        return $accounts;
    }

    public function assertBusinessReady(int $businessId): void
    {
        $settings = $this->settingsForBusiness($businessId);
        if (empty($settings['auto_posting_enabled'])) {
            throw new RuntimeException("Automatic accounting is not enabled for business {$businessId}.");
        }

        $this->validatedAccounts($businessId, $settings);
        $this->assertSourceTrackerReady();
    }

    private function assertSourceTrackerReady(): void
    {
        if ($this->sourceTrackerReady) {
            return;
        }

        if (!DB::connection()->getSchemaBuilder()->hasTable('accounting_source_postings')) {
            throw new RuntimeException('Apply the Accounting source-postings migration before posting transactions.');
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            $tableStatus = DB::selectOne(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                ['accounting_source_postings']
            );
            if ($tableStatus === null || strtoupper($tableStatus->ENGINE) !== 'INNODB') {
                throw new RuntimeException('The Accounting source-postings table must use InnoDB before posting transactions.');
            }
        }

        $this->sourceTrackerReady = true;
    }

    private function pair(int $debitAccount, int $creditAccount, $amount, string $debitType, string $creditType, string $mapType): array
    {
        return [
            $this->line($debitAccount, $amount, $debitType, $mapType . '_debit'),
            $this->line($creditAccount, $amount, $creditType, $mapType . '_credit'),
        ];
    }

    private function line(int $accountId, $amount, string $type, string $mapType): array
    {
        if (!in_array($type, ['debit', 'credit'], true)) {
            throw new InvalidArgumentException('Accounting posting direction must be debit or credit.');
        }

        return [
            'accounting_account_id' => $accountId,
            'amount' => number_format((float) $amount, 4, '.', ''),
            'type' => $type,
            'map_type' => $mapType,
        ];
    }

    private function withoutZeroLines(array $lines): array
    {
        return array_values(array_filter($lines, fn ($line) => (float) $line['amount'] > 0));
    }
}