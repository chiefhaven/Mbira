<?php
namespace Modules\Hms\Entities;

use App\Transaction;


class HmsTransactionClass extends Transaction
{
    public function hms_booking_lines()
    {
        return $this->hasMany(\Modules\Hms\Entities\HmsBookingLine::class, 'transaction_id', 'id');
    }

    public function hms_booking_extras()
    {
        return $this->hasMany(\Modules\Hms\Entities\HmsBookingExtra::class, 'transaction_id', 'id');    }
}