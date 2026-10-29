<?php

namespace Modules\EIS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EisProductMap extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'business_id',
        'product_id',
        'eis_product_id',
        'sku',
        'last_synced_at'
    ];
}