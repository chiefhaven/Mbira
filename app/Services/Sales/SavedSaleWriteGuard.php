<?php

namespace App\Services\Sales;

use Illuminate\Database\Connection;

/** Legacy writers cannot mutate a split source with stale payloads. A completed
 * result invoice is independent; supported source payment/checkout paths stay scoped.
 * Query-builder/bulk writers MUST call this before any graph/stock side effects.
 */
class SavedSaleWriteGuard
{
    private static $checkout = [];
    private static $payment = [];
    private static $paymentLines = [];
    private static $paymentDeletion = [];
    private static $stockAllocation = [];

    /** No callback is supplied by a caller. Only the verified reconciliation
     * implementation receives an exact-model, exact-attributes saving capability.
     * lock(), header, line, payment and fiscal model guards remain unchanged.
     */
    public static function reconcileStock($transaction)
    {
        $db = $transaction->getConnection();
        if ($db->transactionLevel() < 1 || self::$checkout || self::$stockAllocation) {
            throw new SavedSplitException('stock_reconciliation_invalid', 'Purchase reconciliation requires an enclosing transaction.');
        }
        return (new SavedSaleStockReconciler($db))->reconcile($transaction->getKey(), function ($model) use ($db) {
            if (!$model instanceof \App\TransactionSellLinesPurchaseLines || $model->getConnection() !== $db) {
                throw new SavedSplitException('stock_reconciliation_invalid', 'Invalid stock allocation capability.');
            }
            $key = spl_object_id($model);
            if (isset(self::$stockAllocation[$key])) {
                throw new SavedSplitException('stock_reconciliation_invalid', 'Reentrant allocation write.');
            }
            self::$stockAllocation[$key] = [$db, $db->transactionLevel(), $model->getAttributes(), $model->getRawOriginal()];
            $expected = $model->getAttributes();
            try {
                if ($model->save() !== true) {
                    throw new SavedSplitException('stock_reconciliation_invalid', 'An allocation observer cancelled the exact stock write.');
                }
                $persisted = (array) $db->table('transaction_sell_lines_purchase_lines')->where('id', $model->getKey())->first();
                foreach ($expected as $field => $value) {
                    if (in_array($field, ['created_at', 'updated_at'], true)) { continue; }
                    if (!array_key_exists($field, $persisted) || ($value === null ? $persisted[$field] !== null :
                        (string) $persisted[$field] !== (string) $value)) {
                        throw new SavedSplitException('stock_reconciliation_invalid', 'An allocation observer changed the exact planned stock write.');
                    }
                }
            } finally { unset(self::$stockAllocation[$key]); }
        });
    }

    public static function isStockAllocation($model)
    {
        $scope = self::$stockAllocation[spl_object_id($model)] ?? null;
        return $scope && $model instanceof \App\TransactionSellLinesPurchaseLines
            && $model->getConnection() === $scope[0] && $scope[0]->transactionLevel() === $scope[1]
            && $model->getAttributes() === $scope[2] && $model->getRawOriginal() === $scope[3];
    }

    /** Server-only scope, entered AFTER version/authorization/eligibility checks.
     * It never survives the callback, exception, transaction or request.
     */
    public static function checkout(Connection $db, $id, callable $work)
    {
        if ($db->transactionLevel() < 1) {
            throw new SavedSplitException('checkout_requires_transaction', 'Checkout requires a locked database transaction.');
        }
        $key = spl_object_id($db).':'.$id;
        if (isset(self::$checkout[$key])) {
            throw new SavedSplitException('checkout_reentrant', 'Nested saved checkout is forbidden.');
        }
        self::$checkout[$key] = $db->transactionLevel();
        try { return $work(); } finally { unset(self::$checkout[$key]); }
    }

    public static function isCheckout(Connection $db, $id)
    {
        return isset(self::$checkout[spl_object_id($db).':'.$id]) && $db->transactionLevel() >= self::$checkout[spl_object_id($db).':'.$id];
    }

    /** Scope the POS payment-only workflow without granting sale or line writes. */
    public static function paymentLines(Connection $db, $id, $userId, callable $work)
    {
        if ($db->transactionLevel() < 1) {
            throw new SavedSplitException('payment_requires_transaction', 'Payment updates require a locked database transaction.');
        }

        $header = $db->table('transactions')->where('id', $id)->lockForUpdate()->first();
        if (!$header || $header->type !== 'sell' || !$db->getSchemaBuilder()->hasTable('saved_sale_split_operations')) {
            return $work(false);
        }
        if ($db->getSchemaBuilder()->hasTable('restaurant_bills') &&
            $db->table('restaurant_bills')->where('transaction_id', $id)->lockForUpdate()->exists() &&
            !RestaurantBillService::isClearing($db, $id)) {
            throw new SavedSplitException('restaurant_write_fenced', 'Use restaurant cashier clearing for this bill.');
        }

        $split = $db->table('saved_sale_split_operations')->where('business_id', $header->business_id)
            ->where(function ($q) use ($id) {
                $q->where('source_id', $id)->orWhere('result_id', $id);
            })->lockForUpdate()->first();
        if (!$split) {
            return $work(false);
        }

        $key = spl_object_id($db).':'.$id;
        if (isset(self::$paymentLines[$key])) {
            throw new SavedSplitException('payment_lines_reentrant', 'Nested split payment updates are forbidden.');
        }
        self::$paymentLines[$key] = [
            'level' => $db->transactionLevel(),
            'header' => (array) $header,
            'user_id' => $userId,
        ];

        try {
            return $work(true);
        } finally {
            unset(self::$paymentLines[$key]);
        }
    }

    public static function hasSplitHistory(Connection $db, $id)
    {
        if (!$id || !$db->getSchemaBuilder()->hasTable('saved_sale_split_operations')) {
            return false;
        }

        $header = $db->table('transactions')->where('id', $id)->first();
        if (!$header || $header->type !== 'sell') {
            return false;
        }
        if (self::isIndependentSplitResult($db, $header->business_id, $id)) {
            return false;
        }

        return $db->table('saved_sale_split_operations')->where('business_id', $header->business_id)
            ->where(function ($q) use ($id) {
                $q->where('source_id', $id)->orWhere('result_id', $id);
            })->exists();
    }

    private static function isIndependentSplitResult(Connection $db, $businessId, $id): bool
    {
        if (!$id || !$db->getSchemaBuilder()->hasTable('saved_sale_split_operations')) {
            return false;
        }

        $sourceOperations = $db->table('saved_sale_split_operations')->where('business_id', $businessId)
            ->where('source_id', $id)->where(function ($query) use ($id) {
                $query->whereNull('result_id')->orWhere('result_id', '<>', $id);
            })->get(['allocation_json']);
        foreach ($sourceOperations as $operation) {
            $allocation = json_decode($operation->allocation_json, true);
            if (!is_array($allocation) || array_key_exists('bill_b_quantities', $allocation)) {
                return false;
            }
        }

        $operations = $db->table('saved_sale_split_operations')->where('business_id', $businessId)
            ->where('result_id', $id)->where('source_id', '<>', $id)->whereNotNull('response_json')
            ->get(['allocation_json', 'response_json']);
        foreach ($operations as $operation) {
            $allocation = json_decode($operation->allocation_json, true);
            $response = json_decode($operation->response_json, true);
            if (is_array($allocation) && array_key_exists('bill_b_quantities', $allocation) &&
                is_array($response) && (int) ($response['result_id'] ?? 0) === (int) $id &&
                isset($response['bill_a'], $response['bill_b'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Run the normal payment workflow for a split participant with a locked,
     * idempotent ledger entry. Other sales retain the existing payment path.
     */
    public static function splitPayment(Connection $db, $id, $businessId, $userId, $operation, $payloadHash, callable $work)
    {
        if ($db->transactionLevel() < 1) {
            throw new SavedSplitException('payment_requires_transaction', 'Split payment requires a locked database transaction.');
        }

        $header = $db->table('transactions')->where('id', $id)->where('business_id', $businessId)
            ->lockForUpdate()->first();
        if (!$header || $header->type !== 'sell' || !$db->getSchemaBuilder()->hasTable('saved_sale_split_operations')) {
            return $work(false);
        }
        if (self::isIndependentSplitResult($db, $businessId, $id)) {
            return $work(false);
        }
        if ($db->getSchemaBuilder()->hasTable('restaurant_bills') &&
            $db->table('restaurant_bills')->where('transaction_id', $id)->exists() &&
            !RestaurantBillService::isClearing($db, $id)) {
            throw new SavedSplitException('restaurant_write_fenced', 'Use restaurant cashier clearing for this bill.');
        }

        $split = $db->table('saved_sale_split_operations')->where('business_id', $businessId)
            ->where(function ($q) use ($id) {
                $q->where('source_id', $id)->orWhere('result_id', $id);
            })->lockForUpdate()->first();
        if (!$split) {
            return $work(false);
        }

        $paymentVersion = hash('sha256', json_encode([
            'header' => (array) $header,
            'payments' => $db->table('transaction_payments')->where('transaction_id', $id)->orderBy('id')
                ->lockForUpdate()->get()->map(function ($row) {
                    return (array) $row;
                })->all(),
        ], JSON_THROW_ON_ERROR));

        if (!is_string($operation) || !\Illuminate\Support\Str::isUuid($operation) ||
            !is_string($payloadHash) || !preg_match('/^[a-f0-9]{64}$/D', $payloadHash)) {
            throw new SavedSplitException('invalid_payment_request', 'Reload the payment form and submit a valid payment operation.');
        }

        $operation = strtolower($operation);
        $existing = $db->table('saved_sale_split_operations')->where('business_id', $businessId)
            ->where('operation_id', $operation)->lockForUpdate()->first();
        if ($existing) {
            $allocation = json_decode($existing->allocation_json, true);
            if ((string) $existing->user_id !== (string) $userId ||
                !hash_equals((string) $existing->payload_hash, $payloadHash) ||
                ($allocation['kind'] ?? null) !== 'payment') {
                throw new SavedSplitException('operation_collision', 'Payment operation UUID already belongs to another actor or request.');
            }
            if (!$existing->response_json) {
                throw new SavedSplitException('operation_incomplete', 'Stored payment operation is incomplete; manual investigation is required.');
            }

            return json_decode($existing->response_json, true, 512, JSON_THROW_ON_ERROR);
        }

        if ($header->payment_status === 'paid') {
            return $work(false);
        }

        $now = date('Y-m-d H:i:s');
        $operationRowId = $db->table('saved_sale_split_operations')->insertGetId([
            'business_id' => $businessId,
            'user_id' => $userId,
            'operation_id' => $operation,
            'source_id' => $id,
            'result_id' => $id,
            'payload_hash' => $payloadHash,
            'expected_version' => $paymentVersion,
            'allocation_json' => json_encode([
                'kind' => 'payment',
                'transaction_id' => (int) $id,
                'before_version' => $paymentVersion,
            ], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $key = spl_object_id($db).':'.$id;
        if (isset(self::$payment[$key])) {
            throw new SavedSplitException('payment_reentrant', 'Nested split payments are forbidden.');
        }
        self::$payment[$key] = ['level' => $db->transactionLevel(), 'header' => (array) $header, 'user_id' => $userId];

        try {
            $response = $work(true);
            if (!is_array($response) || empty($response['success']) || empty($response['payment_id'])) {
                throw new SavedSplitException('payment_incomplete', 'Split payment did not create a payment; no operation was recorded.');
            }

            $db->table('saved_sale_split_operations')->where('id', $operationRowId)->update([
                'allocation_json' => json_encode([
                    'kind' => 'payment',
                    'transaction_id' => (int) $id,
                    'payment_id' => (int) $response['payment_id'],
                ], JSON_THROW_ON_ERROR),
                'response_json' => json_encode($response, JSON_THROW_ON_ERROR),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return $response;
        } finally {
            unset(self::$payment[$key]);
        }
    }

    public static function splitPaymentDeletion(Connection $db, $id, $businessId, $userId, $paymentId, callable $work)
    {
        if ($db->transactionLevel() < 1) {
            throw new SavedSplitException('payment_requires_transaction', 'Split payment deletion requires a locked database transaction.');
        }

        $header = $db->table('transactions')->where('id', $id)->where('business_id', $businessId)
            ->lockForUpdate()->first();
        if (!$header || $header->type !== 'sell' || !$db->getSchemaBuilder()->hasTable('saved_sale_split_operations')) {
            return $work(false);
        }
        if (self::isIndependentSplitResult($db, $businessId, $id)) {
            return $work(false);
        }
        if ($db->getSchemaBuilder()->hasTable('restaurant_bills') &&
            $db->table('restaurant_bills')->where('transaction_id', $id)->exists()) {
            throw new SavedSplitException('restaurant_write_fenced', 'Restaurant tender corrections require an audited refund workflow; direct payment deletion is unavailable.');
        }

        $split = $db->table('saved_sale_split_operations')->where('business_id', $businessId)
            ->where(function ($q) use ($id) {
                $q->where('source_id', $id)->orWhere('result_id', $id);
            })->lockForUpdate()->first();
        if (!$split) {
            return $work(false);
        }

        $payment = $db->table('transaction_payments')->where('id', $paymentId)
            ->where('transaction_id', $id)->lockForUpdate()->first();
        if (!$payment) {
            throw new SavedSplitException('payment_not_found', 'Payment not found for this split sale.', 404);
        }
        if (!empty($payment->parent_id)) {
            throw new SavedSplitException('split_payment_delete_unsupported',
                'Payments allocated from a customer advance cannot be deleted here.');
        }

        $operation = (string) \Illuminate\Support\Str::uuid();
        $payloadHash = hash('sha256', json_encode([
            'kind' => 'payment_delete',
            'business_id' => (int) $businessId,
            'user_id' => (int) $userId,
            'transaction_id' => (int) $id,
            'payment_id' => (int) $paymentId,
            'payment' => (array) $payment,
        ], JSON_THROW_ON_ERROR));
        $version = hash('sha256', json_encode([
            'header' => (array) $header,
            'payments' => $db->table('transaction_payments')->where('transaction_id', $id)
                ->orderBy('id')->lockForUpdate()->get()->map(function ($row) {
                    return (array) $row;
                })->all(),
        ], JSON_THROW_ON_ERROR));
        $now = date('Y-m-d H:i:s');
        $operationId = $db->table('saved_sale_split_operations')->insertGetId([
            'business_id' => $businessId,
            'user_id' => $userId,
            'operation_id' => $operation,
            'source_id' => $id,
            'result_id' => $id,
            'payload_hash' => $payloadHash,
            'expected_version' => $version,
            'allocation_json' => json_encode([
                'kind' => 'payment_delete',
                'transaction_id' => (int) $id,
                'payment_id' => (int) $paymentId,
                'before_version' => $version,
            ], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $key = spl_object_id($db).':'.$id;
        if (isset(self::$paymentDeletion[$key])) {
            throw new SavedSplitException('payment_delete_reentrant', 'Nested split payment deletions are forbidden.');
        }
        self::$paymentDeletion[$key] = [
            'level' => $db->transactionLevel(),
            'header' => (array) $header,
            'payment_id' => $paymentId,
        ];

        try {
            $response = $work(true);
            if (!is_array($response) || empty($response['success'])) {
                throw new SavedSplitException('payment_delete_incomplete', 'Split payment was not deleted; no operation was recorded.');
            }

            $db->table('saved_sale_split_operations')->where('id', $operationId)->update([
                'response_json' => json_encode($response, JSON_THROW_ON_ERROR),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return $response;
        } finally {
            unset(self::$paymentDeletion[$key]);
        }
    }

    private static function isPayment(Connection $db, $id)
    {
        $scope = self::$payment[spl_object_id($db).':'.$id] ?? null;
        return $scope && $db->transactionLevel() >= $scope['level'];
    }

    private static function isPaymentDeletion(Connection $db, $id)
    {
        $scope = self::$paymentDeletion[spl_object_id($db).':'.$id] ?? null;
        return $scope && $db->transactionLevel() >= $scope['level'];
    }

    private static function isPaymentLines(Connection $db, $id)
    {
        $scope = self::$paymentLines[spl_object_id($db).':'.$id] ?? null;
        return $scope && $db->transactionLevel() >= $scope['level'];
    }

    private static function validatePaymentModel($model, $headerModel, $id)
    {
        $db = $model->getConnection();
        $scope = self::$payment[spl_object_id($db).':'.$id] ?? null;
        $deletion = self::$paymentDeletion[spl_object_id($db).':'.$id] ?? null;
        if ($deletion && $db->transactionLevel() >= $deletion['level']) {
            if ($headerModel) {
                $dirty = array_diff(array_keys($model->getDirty()), ['payment_status', 'updated_at']);
                if (!$model instanceof \App\Transaction || !$model->exists ||
                    (string) $model->getKey() !== (string) $id ||
                    (string) $model->getRawOriginal('business_id') !== (string) $deletion['header']['business_id'] ||
                    $dirty || !in_array($model->payment_status, ['due', 'partial', 'paid'], true)) {
                    throw new SavedSplitException('split_write_fenced',
                        'Split payment deletion may only update payment status on its saved sale.');
                }
                return;
            }

            if (!$model instanceof \App\TransactionPayment || !$model->exists ||
                (string) $model->getKey() !== (string) $deletion['payment_id'] ||
                (string) $model->getRawOriginal('transaction_id') !== (string) $id ||
                !empty($model->parent_id) || $model->isDirty()) {
                throw new SavedSplitException('split_write_fenced',
                    'Split payment deletion may only delete the selected payment.');
            }
            return;
        }

        if (!$scope || $model->getConnection()->transactionLevel() < $scope['level']) {
            throw new SavedSplitException('split_write_fenced', 'Invalid split payment write scope.');
        }

        $header = $scope['header'];
        if ($headerModel) {
            $dirty = array_diff(array_keys($model->getDirty()), ['payment_status', 'updated_at']);
            if (!$model instanceof \App\Transaction || !$model->exists || (string) $model->getKey() !== (string) $id ||
                $model->getRawOriginal('type') !== 'sell' ||
                (string) $model->getRawOriginal('business_id') !== (string) $header['business_id'] ||
                $dirty || !in_array($model->payment_status, ['due', 'partial', 'paid'], true)) {
                throw new SavedSplitException('split_write_fenced', 'Split payments may only update payment status on their saved sale.');
            }

            return;
        }

        $allowed = ['transaction_id', 'business_id', 'payment_for', 'created_by', 'amount', 'method',
            'card_transaction_number', 'card_number', 'card_type', 'card_holder_name', 'card_month', 'card_year',
            'card_security', 'cheque_number', 'bank_account_number', 'note', 'paid_on', 'transaction_no',
            'payment_ref_no', 'account_id', 'document', 'created_at', 'updated_at'];
        $attributes = $model->getAttributes();
        if (!$model instanceof \App\TransactionPayment || $model->exists ||
            (string) ($attributes['transaction_id'] ?? '') !== (string) $id ||
            (string) ($attributes['business_id'] ?? '') !== (string) $header['business_id'] ||
            (string) ($attributes['created_by'] ?? '') !== (string) $scope['user_id'] ||
            (string) ($attributes['payment_for'] ?? '') !== (string) $header['contact_id'] ||
            !is_numeric($attributes['amount'] ?? null) || (float) $attributes['amount'] <= 0 ||
            empty($attributes['method']) || array_diff(array_keys($attributes), $allowed)) {
            throw new SavedSplitException('split_write_fenced', 'Split payments may only add a valid payment to their saved sale.');
        }
    }

    private static function validatePaymentLinesModel($model, $headerModel, $id)
    {
        $db = $model->getConnection();
        $scope = self::$paymentLines[spl_object_id($db).':'.$id] ?? null;
        if (!$scope || $db->transactionLevel() < $scope['level']) {
            throw new SavedSplitException('split_write_fenced', 'Invalid split payment update scope.');
        }

        $header = $scope['header'];
        if ($headerModel) {
            $dirty = array_diff(array_keys($model->getDirty()), ['payment_status', 'is_suspend', 'updated_at']);
            if (!$model instanceof \App\Transaction || !$model->exists ||
                (string) $model->getKey() !== (string) $id ||
                $model->getRawOriginal('type') !== 'sell' ||
                (string) $model->getRawOriginal('business_id') !== (string) $header['business_id'] ||
                $dirty || !in_array($model->payment_status, ['due', 'partial', 'paid'], true)) {
                throw new SavedSplitException('split_write_fenced', 'Split payment updates may only change payment state.');
            }

            return;
        }

        if (!$model instanceof \App\TransactionPayment ||
            (string) ($model->transaction_id ?? '') !== (string) $id ||
            (string) ($model->business_id ?? '') !== (string) $header['business_id'] ||
            (string) ($model->payment_for ?? '') !== (string) $header['contact_id'] ||
            !is_numeric($model->amount ?? null) || (float) $model->amount <= 0 ||
            empty($model->method) || !empty($model->parent_id)) {
            throw new SavedSplitException('split_write_fenced', 'Split payment rows must remain valid payments for their saved sale.');
        }

        if ($model->exists) {
            $original = $model->getRawOriginal();
            $mutable = ['payment_for', 'amount', 'method', 'is_return', 'card_transaction_number', 'card_number',
                'card_type', 'card_holder_name', 'card_month', 'card_year', 'card_security', 'cheque_number',
                'bank_account_number', 'note', 'paid_on', 'transaction_no', 'payment_ref_no', 'account_id',
                'document', 'updated_at'];
            if ((string) ($original['transaction_id'] ?? '') !== (string) $id ||
                (string) ($original['business_id'] ?? '') !== (string) $header['business_id'] ||
                !empty($original['parent_id']) ||
                array_diff(array_keys($model->getDirty()), $mutable)) {
                throw new SavedSplitException('split_write_fenced', 'Only payment details for this saved sale may be changed.');
            }

            return;
        }

        $allowed = ['transaction_id', 'business_id', 'payment_for', 'created_by', 'amount', 'method', 'is_return',
            'card_transaction_number', 'card_number', 'card_type', 'card_holder_name', 'card_month', 'card_year',
            'card_security', 'cheque_number', 'bank_account_number', 'note', 'paid_on', 'transaction_no',
            'payment_ref_no', 'account_id', 'document', 'created_at', 'updated_at'];
        if ((string) ($model->created_by ?? '') !== (string) $scope['user_id'] ||
            array_diff(array_keys($model->getAttributes()), $allowed)) {
            throw new SavedSplitException('split_write_fenced', 'Only valid payments for this saved sale may be added.');
        }
    }

    /** Token-only initialization must not save a stale sale model or edit its graph. */
    public static function invoiceToken(Connection $db, $id, $businessId): string
    {
        return $db->transaction(function () use ($db, $id, $businessId) {
            $header = $db->table('transactions')->where('business_id', $businessId)
                ->where('id', $id)->lockForUpdate()->first();
            if (!$header) {
                throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(\App\Transaction::class, [$id]);
            }
            if (!empty($header->invoice_token)) {
                return $header->invoice_token;
            }
            $token = (new \App\Utils\Util())->generateToken();
            $updated = $db->table('transactions')->where('business_id', $businessId)->where('id', $id)
                ->update(['invoice_token' => $token]);
            if ($updated !== 1) {
                throw new SavedSplitException('invoice_token_not_saved', 'The invoice token could not be initialized.');
            }
            return $token;
        });
    }

    public static function lock(Connection $db, $id, $allowIndependentResult = true)
    {
        if (!$id || !$db->getSchemaBuilder()->hasTable('saved_sale_split_operations')) {
            return null; // Feature cannot split anything before its migration.
        }
        $header = $db->table('transactions')->where('id', $id)->lockForUpdate()->first();
        if (!$header || $header->type !== 'sell') {
            return $header;
        }
        // Never use a consistent-read snapshot here: a split may have committed
        // while this writer waited for the header lock.
        if ($db->getSchemaBuilder()->hasTable('restaurant_bills') &&
            $db->table('restaurant_bills')->where('transaction_id', $id)->lockForUpdate()->exists() &&
            !self::isCheckout($db, $id) && !self::isPayment($db, $id) &&
            !self::isPaymentLines($db, $id) && !self::isPaymentDeletion($db, $id)) {
            throw new SavedSplitException('restaurant_write_fenced', 'Use the audited restaurant workflow to modify this bill.');
        }
        $split = $db->table('saved_sale_split_operations')
            ->where('business_id', $header->business_id)
            ->where(function ($q) use ($id) {
                $q->where('source_id', $id)->orWhere('result_id', $id);
            })->lockForUpdate()->first();
        $independentResult = self::isIndependentSplitResult($db, $header->business_id, $id);
        if ($split && (!$independentResult || !$allowIndependentResult || self::$stockAllocation) &&
            !self::isCheckout($db, $id) && !self::isPayment($db, $id) &&
            !self::isPaymentLines($db, $id) &&
            !self::isPaymentDeletion($db, $id)) {
            throw new SavedSplitException('split_write_fenced',
                'This saved split source bill is read-only to legacy writers. Use a split-aware payment or checkout.');
        }
        if (in_array($header->status, ['draft', 'final'], true) && $db->transactionLevel() < 1) {
            throw new SavedSplitException('draft_write_requires_transaction',
                'A saved draft writer must open a database transaction before locking the sale.');
        }

        return $header;
    }

    /** Fiscal tracking is not a legacy sale edit. Only a committed paid split
     * with a recorded payment or checkout may reach MyISAM/EIS.
     */
    public static function fiscal(Connection $db, $id)
    {
        return $db->transaction(function () use ($db, $id) {
            $h = $db->table('transactions')->where('id', $id)->lockForUpdate()->first();
            if (!$h || $h->type !== 'sell') { return $h; }
            if ($db->getSchemaBuilder()->hasTable('saved_sale_split_operations')) {
                $rows = $db->table('saved_sale_split_operations')->where('business_id', $h->business_id)
                    ->where(function ($q) use ($id) { $q->where('source_id', $id)->orWhere('result_id', $id); })
                    ->lockForUpdate()->get();
                if ($rows->count() && !self::isIndependentSplitResult($db, $h->business_id, $id)) {
                    $completed = $rows->contains(function ($r) use ($db, $id) {
                        $allocation = json_decode($r->allocation_json, true);
                        if (empty($r->response_json)) {
                            return false;
                        }
                        if (($allocation['kind'] ?? null) === 'checkout') {
                            return true;
                        }
                        return ($allocation['kind'] ?? null) === 'payment' && !empty($allocation['payment_id']) &&
                            $db->table('transaction_payments')->where('id', $allocation['payment_id'])
                                ->where('transaction_id', $id)->exists();
                    });
                    if (!$completed || $h->status !== 'final' || $h->payment_status !== 'paid' || $db->transactionLevel() > 1) {
                        throw new SavedSplitException('split_write_fenced', 'Only committed paid split checkouts may create fiscal tracking.');
                    }
                }
            }
            if ($h->status === 'draft') {
                throw new SavedSplitException('draft_fiscal_state_blocked', 'Draft sales must not create fiscal tracking.');
            }
            if ($h->payment_status !== 'paid') {
                throw new SavedSplitException('unpaid_fiscal_state_blocked', 'Unpaid/partial sales must not create fiscal tracking.');
            }
            return $h;
        });
    }

    /** Only copy the verified fiscal receipt projection, never stale Eloquent
     * attributes, lines, status, payments or arbitrary supplied fields.
     */
    public static function recordFiscalReceipt(Connection $db, $id)
    {
        self::fiscal($db, $id);
        return $db->transaction(function () use ($db, $id) {
            $h = $db->table('transactions')->where('id', $id)->lockForUpdate()->first();
            $receipt = $db->table('eis_sales')->where('transaction_id', $id)->where('business_id', $h->business_id)
                ->where('status', 'submitted')->first();
            if ($h->type !== 'sell' || $h->status !== 'final' || $h->payment_status !== 'paid' || !$receipt) {
                throw new SavedSplitException('fiscal_receipt_unverified', 'A committed paid sale and submitted tenant receipt are required.');
            }
            $db->table('transactions')->where('id', $id)->where('business_id', $h->business_id)->update([
                'is_fiscalized' => 1, 'fiscal_invoice_number' => $receipt->fiscal_invoice_number,
                'receipt_number' => $receipt->receipt_number]);
        });
    }

    public static function model($model, $headerModel = false)
    {
        $ids = $headerModel
            ? [$model->exists ? $model->getKey() : null, $model->return_parent_id, $model->recur_parent_id, $model->transfer_parent_id]
            : [$model->getRawOriginal('transaction_id'), $model->transaction_id];
        $ids = array_unique(array_filter($ids));
        sort($ids, SORT_NUMERIC);
        foreach ($ids as $id) {
            if (self::isPaymentLines($model->getConnection(), $id)) {
                self::validatePaymentLinesModel($model, $headerModel, $id);
            } elseif (self::isPayment($model->getConnection(), $id) ||
                self::isPaymentDeletion($model->getConnection(), $id)) {
                self::validatePaymentModel($model, $headerModel, $id);
            }
            self::lock($model->getConnection(), $id);
        }
    }
}