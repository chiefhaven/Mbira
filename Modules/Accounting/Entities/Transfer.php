<?php

namespace Modules\Accounting\Entities;

use Modules\Accounting\Entities\User;
use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    protected $fillable = [
        'journal_transaction_number',
        'transfer_from_id',
        'transfer_to_id',
        'transfer_by_id',
        'amount',
    ];

    public function transfer_from()
    {
        return $this->belongsTo(ChartOfAccount::class, 'transfer_from_id');
    }

    public function transfer_to()
    {
        return $this->belongsTo(ChartOfAccount::class, 'transfer_to_id');
    }

    public function transfer_by()
    {
        return $this->belongsTo(User::class, 'transfer_by_id');
    }

    public function scopeForBusiness($query)
    {
        return $query->whereHas('transfer_by', function ($q) {
            $q->where('business_id', session('business.id'));
        });
    }
}
