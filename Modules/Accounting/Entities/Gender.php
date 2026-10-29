<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class Gender extends Model
{
    public static function forDropdown()
    {
        return [
            'male' => __('accounting::core.male'),
            'female' => __('accounting::core.female'),
            'other' => __('accounting::core.other'),
            'unspecified' => __('accounting::core.unspecified'),
        ];
    }
}
