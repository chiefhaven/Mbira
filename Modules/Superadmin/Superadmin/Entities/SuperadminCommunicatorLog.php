<?php

namespace Modules\Superadmin\Entities;

use Illuminate\Database\Eloquent\Model;

class SuperadminCommunicatorLog extends Model
{
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'business_ids' => 'array',
    ];
}
