<?php

namespace Modules\EIS\Services\Sales;

use App\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Events\SaleCompleted;

/**
 * Decides which sales initiate an EIS submission.
 *
 * A final sell initiates it when it is paid in full, by cash or any other method.
 * Drafts, quotations, suspended sales, credit sales and partially paid sales (partial cash included) do not,
 * so they are neither submitted nor left behind as pending EIS rows (pending rows count against the offline
 * limits). Adding/editing payments or settling contact dues initiates submission when a sale becomes
 * fully paid.
 */
class SaleSubmissionRule
{
    /**
     * Ask EIS to submit a sale that was just saved.
     *
     * Safe to call inside or after the sale's database transaction: SaleCompleted is fired once the
     * outermost transaction has committed, and never if it rolls back. The SaleCompleted listener
     * queues the EIS submission after that commit so checkout can return immediately.
     *
     * A failure is retained in EIS sale tracking. It does not undo the already-saved sale.
     */
    public static function initiate(Transaction $transaction, bool $originalCheckout = false): void
    {
        DB::afterCommit(function () use ($transaction, $originalCheckout) {
            try {
                // Payment utilities save a separate model; the caller may still hold an unpaid snapshot.
                $committed = $transaction->newQuery()
                    ->where('business_id', $transaction->business_id)
                    ->findOrFail($transaction->id);
                if (!static::qualifies($committed)) {
                    return;
                }

                if (!app(\Modules\EIS\Services\EisSubscriptionAccess::class)
                    ->allows((int) $committed->business_id)) {
                    return;
                }

                event(new SaleCompleted($committed, $originalCheckout));
            } catch (\Throwable $e) {
                Log::error('EIS submission failed after sale commit', [
                    'transaction_id' => $transaction->id,
                    'message' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * True when the sale must be submitted to EIS: a final sell that is paid in full.
     */
    public static function qualifies(Transaction $transaction): bool
    {
        return static::isFinalSell($transaction) && static::isPaid($transaction);
    }

    /**
     * True when the sale is paid in full (payment_status 'paid', which already nets off the change handed back).
     * A partial payment is not, whatever the payment method.
     */
    public static function isPaid(Transaction $transaction): bool
    {
        return $transaction->payment_status === 'paid';
    }

    /**
     * The same condition as isPaid(), as a constraint on a Transaction query.
     */
    public static function constrainPaid(Builder $query): Builder
    {
        return $query->where('payment_status', 'paid');
    }

    protected static function isFinalSell(Transaction $transaction): bool
    {
        return $transaction->type === 'sell' && $transaction->status === 'final';
    }
}
