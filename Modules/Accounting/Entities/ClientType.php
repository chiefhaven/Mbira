<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class ClientType extends Model
{
    protected $table = 'client_types';
    public $timestamps = false;
    protected $fillable = [];
}
