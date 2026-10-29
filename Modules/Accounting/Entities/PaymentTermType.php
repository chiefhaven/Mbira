<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class PaymentTermType extends Model
{
    public static function forDropdown()
    {
        return [
            'months' => __('lang_v1.months'),
            'days' => __('lang_v1.days')
        ];
    }
}
