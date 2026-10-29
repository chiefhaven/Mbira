<?php

namespace Modules\EIS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EisStockOperationLine extends Model
{
    protected $table = 'eis_stock_operation_lines';

    protected $guarded = ['id'];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'quantity' => 'decimal:4',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(EisStockOperation::class, 'eis_stock_operation_id');
    }
}
