<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Entities\BusinessLocation;
use Modules\Accounting\Entities\User;

class JournalEntry extends Model
{
    protected $table = "journal_entries";
    protected $fillable = ['reversed'];

    public function chart_of_account()
    {
        return $this->hasOne(ChartOfAccount::class, 'id', 'chart_of_account_id')->withDefault();
    }

    public function business_location()
    {
        return $this->hasOne(BusinessLocation::class, 'id', 'location_id')
            ->where('business_locations.business_id', session('business.id'))
            ->withDefault();
    }

    public function created_by()
    {
        return $this->hasOne(User::class, 'id', 'created_by_id')->withDefault();
    }

    public function scopeNotReversed($query)
    {
        return $query->where('reversed', 0);
    }

    public function scopeReconcileEntry($query)
    {
        return $query->where('transaction_type', 'reconcile_entry');
    }
    
    public function scopeForBusiness($query)
    {
        return $query->whereHas('business_location', function ($q) {
            $q->where('business_locations.business_id', session('business.id'));
        });
    }

    public function getAmountAttribute()
    {
        return $this->credit - $this->debit;
    }
}
