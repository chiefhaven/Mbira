<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class PaymentDetail extends Model
{
    protected $table = "payment_details";
    protected $fillable = [];

    public function getPaymentTypeAttribute()
    {
        $payment_types = PaymentType::getTypesCollection();
        $default_type = PaymentType::getDefaultPaymentType();
        return $payment_types->filter(function ($payment_type) {
            return $payment_type->id == $this->payment_type_id;
        })->first() ?? $default_type;
    }

    public function getHasMoreInfoAttribute()
    {
        return !empty($this->account_number) ||
            !empty($this->cheque_number) ||
            !empty($this->routing_code) ||
            !empty($this->receipt) ||
            !empty($this->bank_name) ||
            !empty($this->description);
    }
}
