<?php

namespace Modules\EIS\Models;

use Illuminate\Database\Eloquent\Model;

class EisTerminalConfiguration extends Model
{

    protected $fillable = [

        'business_id',
        'terminal_id',
        'configuration',
        'last_synced_at',

    ];


    protected $casts = [

        'configuration'=>'array',
        'last_synced_at'=>'datetime',

    ];

}