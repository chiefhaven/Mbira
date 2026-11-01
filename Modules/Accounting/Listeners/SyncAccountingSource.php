<?php

namespace Modules\Accounting\Listeners;

use App\Events\ExpenseCreatedOrModified;
use App\Events\PurchaseCreatedOrModified;
use App\Events\SellCreatedOrModified;
use App\Events\TransactionPaymentAdded;
use App\Events\TransactionPaymentDeleted;
use App\Events\TransactionPaymentUpdated;
use App\Transaction;
use Modules\Accounting\Services\AccrualPostingService;
use UnexpectedValueException;

class SyncAccountingSource
{
    protected $postingService;

    public function __construct(AccrualPostingService $postingService)
    {
        $this->postingService = $postingService;
    }

    public function handleSale(SellCreatedOrModified $event): void
    {
        $this->postingService->syncTransaction($event->transaction);
    }

    public function handlePurchase(PurchaseCreatedOrModified $event): void
    {
        $this->postingService->syncTransaction($event->transaction, (bool) $event->isDeleted);
    }

    public function handleExpense(ExpenseCreatedOrModified $event): void
    {
        if (!$event->expense instanceof Transaction) {
            throw new UnexpectedValueException('Expense accounting event did not contain a transaction.');
        }

        $this->postingService->syncTransaction($event->expense, (bool) $event->isDeleted);
    }

    public function handlePaymentAdded(TransactionPaymentAdded $event): void
    {
        $this->postingService->syncPayment($event->transactionPayment);
    }

    public function handlePaymentUpdated(TransactionPaymentUpdated $event): void
    {
        $this->postingService->syncPayment($event->transactionPayment);
    }

    public function handlePaymentDeleted(TransactionPaymentDeleted $event): void
    {
        $this->postingService->syncPayment($event->transactionPayment, true);
    }

    public function handleTransactionDeleted(Transaction $transaction): void
    {
        $this->postingService->syncTransaction($transaction, true);
    }
}
