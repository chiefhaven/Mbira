<?php

namespace Modules\EIS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EisStockOperation extends Model
{
    protected $table = 'eis_stock_operations';

    protected $guarded = ['id'];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(EisStockOperationLine::class, 'eis_stock_operation_id');
    }
}
