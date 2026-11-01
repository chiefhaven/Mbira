<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        // 'App\Events\Event' => [
        //     'App\Listeners\EventListener',
        // ],
        \App\Events\TransactionPaymentAdded::class => [
            \App\Listeners\AddAccountTransaction::class,
            \Modules\Accounting\Listeners\SyncAccountingSource::class.'@handlePaymentAdded',
        ],

        \App\Events\TransactionPaymentUpdated::class => [
            \App\Listeners\UpdateAccountTransaction::class,
            \Modules\Accounting\Listeners\SyncAccountingSource::class.'@handlePaymentUpdated',
        ],

        \App\Events\TransactionPaymentDeleted::class => [
            \App\Listeners\DeleteAccountTransaction::class,
            \Modules\Accounting\Listeners\SyncAccountingSource::class.'@handlePaymentDeleted',
        ],

        \App\Events\SellCreatedOrModified::class => [
            \Modules\Accounting\Listeners\SyncAccountingSource::class.'@handleSale',
        ],

        \App\Events\PurchaseCreatedOrModified::class => [
            \Modules\Accounting\Listeners\SyncAccountingSource::class.'@handlePurchase',
        ],

        \App\Events\ExpenseCreatedOrModified::class => [
            \Modules\Accounting\Listeners\SyncAccountingSource::class.'@handleExpense',
        ],

        'eloquent.deleted: App\\Transaction' => [
            \Modules\Accounting\Listeners\SyncAccountingSource::class.'@handleTransactionDeleted',
        ],

        \Modules\EIS\Events\SaleCompleted::class => [
            \Modules\EIS\Listeners\DispatchEisSaleJob::class,
        ],
        \App\Events\StockAdjustmentCreatedOrModified::class => [
            \Modules\EIS\Listeners\QueueStockAdjustmentToEis::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {

        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
    
}
