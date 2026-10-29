<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class BranchCapital extends Model
{
    public $table = 'branch_capital';
    protected $appends = ['amount'];

    protected $fillable = [
        'business_id',
        'location_id',
        'created_by_id',
        'debit',
        'credit',
        'description',
        'date'
    ];

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by_id')->withDefault();
    }

    public function branch()
    {
        return $this->belongsTo(BusinessLocation::class, 'location_id')->withDefault();
    }

    public function getAmountAttribute()
    {
        return $this->credit - $this->debit;
    }
}
