<?php

namespace Modules\EIS\Events;

use App\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SaleCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Transaction $transaction,
        public bool $originalCheckout = false
    ) {}
}