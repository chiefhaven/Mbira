<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class AccountDetailType extends Model
{
    protected $fillable = [
        'business_id',
        'account_subtype_id',
        'name',
        'description',
        'active'
    ];

    /**
     * Get the account_subtype that owns the AccountDetailType
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account_subtype()
    {
        return $this->belongsTo(AccountSubtype::class, 'account_subtype_id')->orderBy('name')->withDefault();
    }

    public function scopeActive($query)
    {
        return $query->where('account_detail_types.active', 1);
    }

    public function scopeForBusiness($query)
    {
        return $query->where('account_detail_types.business_id', 0)
            ->orWhere('account_detail_types.business_id', session('business.id'));
    }

    public function getIsDefaultTypeAttribute()
    {
        return $this->business_id == 0;
    }
}
