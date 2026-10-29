<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class ContactRestriction extends Model
{
    protected $fillable = [
        'business_id',
        'contact_id',
        'location_id',
        'maximum_number_of_open_loans',
        'maximum_principal_outstanding',
        'maximum_total_outstanding',
        'block_defaulters',
        'block_loans_to_borrower',
        'block_loans_to_borrower_until_date',
        'minimum_savings_balance_for_loan',
        'minimum_share_balance_for_loan',
        'description'
    ];

    public function scopeForBusiness($query)
    {
        return $query->where('contact_restrictions.business_id', session('business.id'));
    }
}
