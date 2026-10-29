<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Contains a global scope that orders records by name
 */
class Country extends Model
{
    protected $table = "countries";
    protected $fillable = [];

    protected static function boot()
    {
        parent::boot();
        //Global scope that orders by their name
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('name', 'asc');
        });
    }
}
