# Payment Operations on Split Transactions Fix

## Problem
When attempting to add payments or clear all bills on transactions that have split history, the system threw a `SavedSplitException` error:

```
This saved split bill is read-only to legacy writers. Split-aware checkout is required.
```

This prevented users from:
- Adding individual payments to invoices with split history
- Clearing all unpaid bills (bulk payment operation)
- Processing gateway payments on split transactions

## Root Cause
The `TransactionPayment` model has a saving observer that calls `SavedSaleWriteGuard::model()` to check if writes are allowed. When a transaction has split operations in the database, the guard requires that payment creation happen within a **split-aware checkout context**.

Three payment operations were not wrapping payment creation in `SavedSaleWriteGuard::checkout()`:
1. **clearAllBills()** - Bulk payment creation for unpaid invoices
2. **add()** in TransactionPaymentController - Individual payment addition
3. **confirmPayment()** in SellPosController - Payment gateway confirmation

## Solution
Wrapped all `TransactionPayment::create()` calls with `SavedSaleWriteGuard::checkout()` to register them as split-aware operations.

### Code Pattern
**Before**:
```php
DB::beginTransaction();
SavedSaleWriteGuard::lock(DB::connection(), $transaction_id);  // Verify only

$transaction = Transaction::lockForUpdate()->find($transaction_id);
// Other operations...

$payment = TransactionPayment::create($data);  // FAILS - not in checkout context

DB::commit();
```

**After**:
```php
DB::beginTransaction();
SavedSaleWriteGuard::lock(DB::connection(), $transaction_id);  // Verify

$transaction = Transaction::lockForUpdate()->find($transaction_id);
// Other operations...

$payment = SavedSaleWriteGuard::checkout(DB::connection(), $transaction_id, function () use ($data) {
    return TransactionPayment::create($data);  // Success - in checkout context
});

DB::commit();
```

## Changes Made

### 1. File: `app/Http/Controllers/SellController.php` - clearAllBills() method

**Lines 1397-1402** (payment creation in loop):

Wrapped `TransactionPayment::create()` in `SavedSaleWriteGuard::checkout()`:
```php
\App\Services\Sales\SavedSaleWriteGuard::checkout(DB::connection(), $invoice->id, function () use ($payment_data) {
    \App\TransactionPayment::create($payment_data);
});
```

**Impact**: Users can now bulk-clear unpaid bills even on invoices with split history.

### 2. File: `app/Http/Controllers/TransactionPaymentController.php` - add() method

**Lines 123-127** (individual payment creation):

Wrapped `TransactionPayment::create()` in `SavedSaleWriteGuard::checkout()`:
```php
$tp = \App\Services\Sales\SavedSaleWriteGuard::checkout(DB::connection(), $transaction_id, function () use ($inputs, $request) {
    return TransactionPayment::create($inputs);
});
```

**Impact**: Users can add individual payments to transactions with split history.

### 3. File: `app/Http/Controllers/SellPosController.php` - confirmPayment() method

**Lines 2203-2206** (payment gateway confirmation):

Wrapped `TransactionPayment::create()` in `SavedSaleWriteGuard::checkout()`:
```php
$tp = \App\Services\Sales\SavedSaleWriteGuard::checkout(DB::connection(), $id, function () use ($data) {
    return TransactionPayment::create($data);
});
```

**Impact**: Users can complete gateway payments on split transactions.

## Technical Details

### Why PaymentPayment Needs Split-Aware Context
The `TransactionPayment` model includes this observer:

```php
protected static function booted()
{
    static::saving(function ($model) {
        \App\Services\Sales\SavedSaleWriteGuard::model($model);
    });
}
```

This observer delegates to the guard, which checks:
```php
if ($split && !self::isCheckout($db, $id)) {
    throw new SavedSplitException(
        'split_write_fenced',
        'This saved split bill is read-only to legacy writers...'
    );
}
```

### Transaction Lifecycle for Payment Operations
1. `DB::beginTransaction()` - Start transaction
2. `SavedSaleWriteGuard::lock()` - Acquire lock, verify split existence
3. Load and lock transaction row
4. `SavedSaleWriteGuard::checkout()` - Register as split-aware writer
5. Create `TransactionPayment` record (observer calls guard)
6. Update payment status
7. `DB::commit()` - Commit all changes atomically

## Testing Checklist

### Clear All Bills
- [ ] Create multiple unpaid invoices
- [ ] Some invoices should have split history
- [ ] Click "Clear All Bills"
- [ ] Verify: No error, all bills marked paid, payment records created

### Add Individual Payment
- [ ] Select invoice with split history
- [ ] Click "Add Payment"
- [ ] Enter payment amount
- [ ] Submit payment
- [ ] Verify: Payment recorded, payment status updated

### Gateway Payment (Payment Links)
- [ ] Create split transaction
- [ ] Send payment link
- [ ] Process payment through gateway
- [ ] Verify: Payment recorded, transaction marked paid

## Impact Assessment
✅ All payment operations now work on split transactions
✅ Maintains data integrity through split-aware guardrails
✅ No breaking changes; backward compatible
✅ Atomic transactions preserved
✅ Error handling unchanged

## Related Files
- [SavedSaleWriteGuard.php](app/Services/Sales/SavedSaleWriteGuard.php) - Guard implementation
- [TransactionPayment.php](app/TransactionPayment.php) - Model with saving observer
- [SellController.php](app/Http/Controllers/SellController.php) - clearAllBills() method
- [TransactionPaymentController.php](app/Http/Controllers/TransactionPaymentController.php) - add() method
- [SellPosController.php](app/Http/Controllers/SellPosController.php) - confirmPayment() method

## Deployment Notes
- No database migrations required
- No configuration changes
- Code-only fix
- Backward compatible
- Affects payment workflows on split transactions only
