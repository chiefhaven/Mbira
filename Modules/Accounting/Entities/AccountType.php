<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
    protected $fillable = [];

    public static function getTypes()
    {
        return collect([
            (object)['id' => 'asset', 'name' => trans_choice('accounting::lang.asset', 1), 'plural_name' => trans_choice('accounting::lang.asset', 2)],
            (object)['id' => 'expense', 'name' => trans_choice('accounting::lang.expense', 1), 'plural_name' => trans_choice('accounting::lang.expense', 2)],
            (object)['id' => 'income', 'name' => trans_choice('accounting::lang.income', 1), 'plural_name' => trans_choice('accounting::lang.income', 2)],
            (object)['id' => 'equity', 'name' => trans_choice('accounting::lang.equity', 1), 'plural_name' => trans_choice('accounting::lang.equity', 2)],
            (object)['id' => 'liability', 'name' => trans_choice('accounting::lang.liability', 1), 'plural_name' => trans_choice('accounting::lang.liability', 2)],
        ]);
    }
}
