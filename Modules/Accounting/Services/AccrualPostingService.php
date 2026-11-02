<?php

namespace Modules\Accounting\Services;

use App\Business;
use App\Transaction;
use App\TransactionPayment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\AssetManagement\Entities\Asset;
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

        if ($transaction->type === 'purchase' && DB::connection()->getSchemaBuilder()->hasColumn('assets', 'purchase_transaction_id')) {
            Asset::where('business_id', $transaction->business_id)
                ->where('purchase_transaction_id', $transaction->id)
                ->orderBy('id')
                ->get()
                ->each(function (Asset $asset) {
                    $this->syncAssetAcquisition($asset);
                });
        }
    }

    public function syncAssetAcquisition(Asset $asset, bool $deleted = false): void
    {
        $businessId = (int) $asset->business_id;
        $sourceType = 'asset_acquisition';
        $hasDepreciationHistory = DB::connection()->getSchemaBuilder()->hasTable('accounting_asset_depreciation_postings')
            && DB::table('accounting_asset_depreciation_postings')
                ->where('business_id', $businessId)
                ->where('asset_id', $asset->id)
                ->exists();
        if ($deleted && $this->sourceExists($businessId, $sourceType, (int) $asset->id)
            && $this->sourceIsPosted($businessId, $sourceType, (int) $asset->id)) {
            throw new RuntimeException("Capitalized asset {$asset->asset_code} cannot be deleted; record a disposal instead.");
        }

        $settings = $this->settingsForBusiness($businessId);
        $enabled = !empty($settings['auto_posting_enabled'])
            && !empty($settings['auto_posting_assets_enabled']);
        if (!$enabled && $hasDepreciationHistory
            && $this->sourceIsPosted($businessId, $sourceType, (int) $asset->id)) {
            return;
        }
        $purchase = !empty($asset->purchase_transaction_id)
            ? Transaction::where('business_id', $businessId)
                ->where('type', 'purchase')
                ->find($asset->purchase_transaction_id)
            : null;
        $lines = [];
        $status = 'inactive';

        if ($enabled && !$deleted) {
            $this->assertSourceTrackerReady();
            $accounts = $this->validatedAccounts($businessId, $settings);
            $assetAccounts = $this->validatedAssetAccounts($businessId, $settings);

            if ($asset->purchase_type === 'owned' && $purchase !== null && $purchase->status === 'received') {
                $amount = round((float) $asset->quantity * (float) $asset->unit_price, 4);
                $netBillAmount = round((float) $purchase->final_total - (float) $purchase->tax_amount, 4);
                $otherAssetAmount = (float) DB::table('assets')
                    ->where('business_id', $businessId)
                    ->where('purchase_transaction_id', $purchase->id)
                    ->where('id', '!=', $asset->id)
                    ->sum(DB::raw('quantity * unit_price'));

                if ($amount <= 0 || round($otherAssetAmount + $amount, 4) > $netBillAmount) {
                    throw new RuntimeException("Asset {$asset->asset_code} exceeds the net value of purchase bill #{$purchase->id}.");
                }

                $lines = [
                    $this->line($assetAccounts['fixed_assets'], $amount, 'debit', 'asset_capitalization'),
                    $this->line($accounts['inventory'], $amount, 'credit', 'asset_inventory_reclassification'),
                ];
                $status = 'posted';
            }
        }

        if ($hasDepreciationHistory && $status !== 'posted') {
            throw new RuntimeException("Asset {$asset->asset_code} has depreciation history and cannot be unlinked from an active received purchase bill.");
        }
        if ($hasDepreciationHistory) {
            $source = $this->sourceSnapshot($businessId, $sourceType, (int) $asset->id);
            $previousCapitalization = collect($source['lines'] ?? [])
                ->firstWhere('map_type', 'asset_capitalization');
            $previousTransactionId = !empty($previousCapitalization['ledger_id'])
                ? DB::table('accounting_accounts_transactions')->where('id', $previousCapitalization['ledger_id'])->value('transaction_id')
                : null;
            if ($previousCapitalization === null
                || (int) $previousTransactionId !== (int) $purchase->id
                || abs((float) $previousCapitalization['amount'] - (float) $lines[0]['amount']) > 0.0001) {
                throw new RuntimeException("Asset {$asset->asset_code} acquisition value and purchase bill cannot change after depreciation has been posted.");
            }
        }

        if ($enabled || $this->sourceExists($businessId, $sourceType, (int) $asset->id)) {
            $this->syncSource(
                $businessId,
                $sourceType,
                (int) $asset->id,
                $lines,
                $purchase !== null ? $purchase->transaction_date : ($asset->purchase_date ?: now()),
                (int) $asset->created_by,
                $deleted ? 'deleted' : $status,
                $purchase !== null ? (int) $purchase->id : null,
                null
            );
        }
    }

    public function assertAssetPostingReady(int $businessId): void
    {
        $this->assertBusinessReady($businessId);
        $settings = $this->settingsForBusiness($businessId);
        if (empty($settings['auto_posting_assets_enabled'])) {
            throw new RuntimeException("Asset accounting is not enabled for business {$businessId}.");
        }

        $this->validatedAssetAccounts($businessId, $settings);
        if (!DB::connection()->getSchemaBuilder()->hasTable('accounting_asset_depreciation_postings')) {
            throw new RuntimeException('Apply the Accounting asset-depreciation migration before running depreciation.');
        }
        if (!DB::connection()->getSchemaBuilder()->hasTable('assets')
            || !DB::connection()->getSchemaBuilder()->hasColumn('assets', 'purchase_transaction_id')) {
            throw new RuntimeException('Apply the Asset Management purchase-link migration before running depreciation.');
        }
    }

    public function assetDepreciationCandidates(int $businessId, string $period): array
    {
        $periodStart = \Carbon\Carbon::createFromFormat('Y-m-d', $period)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $candidates = [];

        $assets = Asset::where('business_id', $businessId)
            ->where('purchase_type', 'owned')
            ->whereNotNull('purchase_transaction_id')
            ->whereDate('purchase_date', '<=', $periodEnd->toDateString())
            ->where('depreciation', '>', 0)
            ->orderBy('id')
            ->get();

        foreach ($assets as $asset) {
            $purchase = Transaction::where('business_id', $businessId)
                ->where('type', 'purchase')
                ->where('status', 'received')
                ->find($asset->purchase_transaction_id);
            if ($purchase === null || !$this->sourceIsPosted($businessId, 'asset_acquisition', (int) $asset->id)) {
                continue;
            }

            $alreadyPosted = DB::table('accounting_asset_depreciation_postings')
                ->where('business_id', $businessId)
                ->where('asset_id', $asset->id)
                ->whereDate('period', $periodStart->toDateString())
                ->exists();
            if ($alreadyPosted) {
                continue;
            }

            $assetCost = round((float) $asset->quantity * (float) $asset->unit_price, 4);
            $previousDepreciation = (float) DB::table('accounting_asset_depreciation_postings')
                ->where('business_id', $businessId)
                ->where('asset_id', $asset->id)
                ->where('period', '<', $periodStart->toDateString())
                ->sum('amount');
            $remainingBasis = round(max(0, $assetCost - $previousDepreciation), 4);
            $amount = round(min((float) $asset->depreciation, $remainingBasis), 4);

            if ($amount > 0) {
                $candidates[] = [
                    'asset' => $asset,
                    'purchase' => $purchase,
                    'period' => $periodStart->toDateString(),
                    'amount' => $amount,
                ];
            }
        }

        return $candidates;
    }

    public function postAssetDepreciation(array $candidate): bool
    {
        $asset = $candidate['asset'];
        $purchase = $candidate['purchase'];
        $businessId = (int) $asset->business_id;
        $settings = $this->settingsForBusiness($businessId);
        $this->assertAssetPostingReady($businessId);
        $assetAccounts = $this->validatedAssetAccounts($businessId, $settings);
        $periodStart = \Carbon\Carbon::parse($candidate['period'])->startOfMonth();
        $operationDate = $periodStart->copy()->endOfMonth()->toDateTimeString();

        return DB::transaction(function () use ($asset, $purchase, $businessId, $candidate, $assetAccounts, $periodStart, $operationDate) {
            DB::table('business')->where('id', $businessId)->lockForUpdate()->first();
            $alreadyPosted = DB::table('accounting_asset_depreciation_postings')
                ->where('business_id', $businessId)
                ->where('asset_id', $asset->id)
                ->whereDate('period', $periodStart->toDateString())
                ->exists();
            if ($alreadyPosted) {
                return false;
            }

            $priorAmount = (float) DB::table('accounting_asset_depreciation_postings')
                ->where('business_id', $businessId)
                ->where('asset_id', $asset->id)
                ->where('period', '<', $periodStart->toDateString())
                ->sum('amount');
            $basis = round((float) $asset->quantity * (float) $asset->unit_price, 4);
            $amount = round(min((float) $candidate['amount'], max(0, $basis - $priorAmount)), 4);
            if ($amount <= 0) {
                return false;
            }

            $postingId = DB::table('accounting_asset_depreciation_postings')->insertGetId([
                'business_id' => $businessId,
                'asset_id' => $asset->id,
                'period' => $periodStart->toDateString(),
                'amount' => number_format($amount, 4, '.', ''),
                'created_by' => $asset->created_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $lines = [
                $this->line($assetAccounts['depreciation_expense'], $amount, 'debit', 'asset_depreciation_expense'),
                $this->line($assetAccounts['accumulated_depreciation'], $amount, 'credit', 'asset_accumulated_depreciation'),
            ];

            $this->syncSource(
                $businessId,
                'asset_depreciation',
                (int) $postingId,
                $lines,
                $operationDate,
                (int) $asset->created_by,
                'posted',
                (int) $purchase->id,
                null
            );

            return true;
        });
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
                $this->line($accounts['payroll_expenses'], $grossWages, 'debit', 'payroll_expense'),
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

    private function payrollGroupIsFinal(Transaction $transaction): bool
    {
        $status = DB::table('essentials_payroll_group_transactions as payroll_links')
            ->join('essentials_payroll_groups as payroll_groups', 'payroll_groups.id', '=', 'payroll_links.payroll_group_id')
            ->where('payroll_links.transaction_id', $transaction->id)
            ->where('payroll_groups.business_id', $transaction->business_id)
            ->value('payroll_groups.status');

        return $status === null || $status === 'final';
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

    private function sourceIsPosted(int $businessId, string $sourceType, int $sourceId): bool
    {
        $snapshot = $this->sourceSnapshot($businessId, $sourceType, $sourceId);

        return is_array($snapshot) && ($snapshot['status'] ?? null) === 'posted';
    }

    private function sourceSnapshot(int $businessId, string $sourceType, int $sourceId): ?array
    {
        $source = DB::table('accounting_source_postings')
            ->where('business_id', $businessId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->value('snapshot');

        if ($source === null) {
            return null;
        }

        try {
            $snapshot = json_decode($source, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException("Accounting source snapshot for {$sourceType} #{$sourceId} is invalid.", 0, $exception);
        }
        if (!is_array($snapshot)) {
            throw new RuntimeException("Accounting source snapshot for {$sourceType} #{$sourceId} is invalid.");
        }

        return $snapshot;
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
        if (!empty($configured['payroll_expenses'])) {
            $expectedTypes['payroll_expenses'] = 'expenses';
        }
        $accounts = [];
        foreach ($expectedTypes as $key => $type) {
            $accounts[$key] = (int) ($configured[$key] ?? 0);
        }

        if (count(array_unique($accounts)) !== count($expectedTypes)) {
            throw new RuntimeException('Automatic accounting requires a different account for each posting role.');
        }
        if (!isset($accounts['payroll_expenses'])) {
            $accounts['payroll_expenses'] = (int) ($configured['expenses'] ?? 0);
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

    private function validatedAssetAccounts(int $businessId, array $settings): array
    {
        $configured = $settings['auto_posting_asset_accounts'] ?? [];
        $expectedTypes = [
            'fixed_assets' => 'asset',
            'accumulated_depreciation' => 'asset',
            'depreciation_expense' => 'expenses',
        ];
        $accounts = [];
        foreach ($expectedTypes as $key => $type) {
            $accounts[$key] = (int) ($configured[$key] ?? 0);
        }

        $generalAccounts = array_map('intval', array_values($settings['auto_posting_accounts'] ?? []));
        if (count(array_unique($accounts)) !== count($expectedTypes)
            || count(array_unique(array_merge(array_values($accounts), $generalAccounts)))
                !== count($accounts) + count($generalAccounts)) {
            throw new RuntimeException('Automatic asset accounting requires distinct fixed-asset, accumulated-depreciation, and depreciation-expense accounts.');
        }

        $records = AccountingAccount::where('business_id', $businessId)
            ->where('status', 'active')
            ->whereIn('id', array_values($accounts))
            ->get(['id', 'account_primary_type'])
            ->keyBy('id');

        foreach ($expectedTypes as $key => $type) {
            $account = $records->get($accounts[$key]);
            if ($account === null || $account->account_primary_type !== $type) {
                throw new RuntimeException("Automatic asset accounting is not configured with a valid {$key} account.");
            }
        }

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