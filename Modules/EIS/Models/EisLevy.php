<?php

namespace Modules\EIS\Models;

use Illuminate\Database\Eloquent\Model;

class EisLevy extends Model
{

    protected $fillable = [

        'configuration_id',
        'eis_levy_id',
        'name',
        'charge_mode',
        'rate',
        'is_active',

    ];


    protected $casts = [

        'is_active'=>'boolean'

    ];


    public function configuration()
    {
        return $this->belongsTo(
            EisConfiguration::class,
            'configuration_id'
        );
    }

}