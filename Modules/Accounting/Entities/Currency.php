<?php

namespace Modules\Accounting\Entities;

use App\Currency as AppCurrency;

class Currency extends AppCurrency
{
    protected $table = "currencies";
    protected $fillable = [];

    public function getNameAttribute()
    {
        $split_currency = explode("-", $this->currency);

        if (!strlen($split_currency[1]) > 0) {
            return "";
        }

        $split_name_code =  explode("(", $split_currency[1]);
        $name = trim($split_name_code[0]);

        return $name;
    }

    public function getCurrencyAttribute($value)
    {
        return "{$this->country} - {$value}({$this->code})";
    }
}
