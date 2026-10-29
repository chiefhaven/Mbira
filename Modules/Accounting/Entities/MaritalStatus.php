<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class MaritalStatus extends Model
{
    public static function forDropdown()
    {
        return [
            'single' => __('accounting::core.single'),
            'married' => __('accounting::core.married'),
            'divorced' => __('accounting::core.divorced'),
            'widowed' => __('accounting::core.widowed'),
        ];
    }
}
