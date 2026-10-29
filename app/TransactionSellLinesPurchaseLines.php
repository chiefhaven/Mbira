<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TransactionSellLinesPurchaseLines extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected static function booted()
    {
        $guard = function ($model) {
            $ids = array_unique(array_filter([$model->getRawOriginal('sell_line_id'), $model->sell_line_id]));
            sort($ids, SORT_NUMERIC);
            foreach ($ids as $lineId) {
                $header = $model->getConnection()->table('transaction_sell_lines')->where('id', $lineId)->value('transaction_id');
                \App\Services\Sales\SavedSaleWriteGuard::lock($model->getConnection(), $header);
            }
        };
        static::saving(function ($model) use ($guard) {
            if (!\App\Services\Sales\SavedSaleWriteGuard::isStockAllocation($model)) {
                $guard($model);
            }
        });
        static::deleting($guard);
    }

    public function purchase_line()
    {
        return $this->belongsTo(\App\PurchaseLine::class, 'purchase_line_id');
    }
}
