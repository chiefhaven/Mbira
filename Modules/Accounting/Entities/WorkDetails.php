<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class WorkDetails extends Model
{
    protected $fillable = [
        'contact_id',
        'employer',
        'official_designation',
        'pf_number',
        'employment_terms',
        'basic_salary',
        'gross_salary',
        'net_salary',
        'date_of_employment',
        'date_of_contract_expiry',
    ];
}
