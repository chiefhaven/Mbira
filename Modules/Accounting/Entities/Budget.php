<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = [
        'chart_of_account_id',
        'business_id',
        'financial_year',
        'month_1',
        'month_2',
        'month_3',
        'month_4',
        'month_5',
        'month_6',
        'month_7',
        'month_8',
        'month_9',
        'month_10',
        'month_11',
        'month_12',
    ];

    protected $appends = ['quarterly', 'yearly'];

    public function getQuarterlyAttribute()
    {
        return collect([
            1 => $this->month_1 + $this->month_2 + $this->month_3,
            2 => $this->month_4 + $this->month_5 + $this->month_6,
            3 => $this->month_7 + $this->month_6 + $this->month_7,
            4 => $this->month_10 + $this->month_11 + $this->month_12
        ]);
    }

    public function getYearlyAttribute()
    {
        return $this->quarterly->sum();
    }
}
