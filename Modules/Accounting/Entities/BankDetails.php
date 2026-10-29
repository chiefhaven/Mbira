<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class BankDetails extends Model
{
    protected $fillable = [
        'contact_id',
        'account_holder_name',
        'account_number',
        'bank_name',
        'bank_code',
        'branch',
        'tax_payer_id'
    ];
}
