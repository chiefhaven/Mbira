<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class AccountSubtype extends Model
{
    protected $fillable = [
        'business_id',
        'account_type',
        'name',
        'description',
        'active'
    ];

    public function getAccountTypeNameAttribute()
    {
        return ucfirst($this->account_type);
    }

    public function scopeActive($query)
    {
        return $query->where('account_subtypes.active', 1);
    }

    public function scopeForBusiness($query)
    {
        return $query->where('account_subtypes.business_id', 0)
            ->orWhere('account_subtypes.business_id', session('business.id'));
    }

    public function getIsDefaultTypeAttribute()
    {
        return $this->business_id == 0;
    }
}
