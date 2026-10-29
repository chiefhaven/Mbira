<?php

namespace Modules\EIS\Models;

use Illuminate\Database\Eloquent\Model;

class EisTaxMapping extends Model
{
    protected $table = 'eis_tax_mappings';

    protected $fillable = [
        'business_id',
        'tax_id',
        'eis_tax_rate_id',
        'tax_name',
        'rate',
        'is_default',
    ];
}