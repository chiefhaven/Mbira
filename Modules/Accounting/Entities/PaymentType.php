<?php

namespace Modules\Accounting\Entities;

use App\Utils\Util;
use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    protected $table = "payment_types";
    protected $fillable = [];

    public function scopeByBusiness($query, $business_id)
    {
        return $query->where('business_id', $business_id);
    }

    public function scopeDefaultPaymentTypes($query)
    {
        return $query->where('business_id', 0);
    }

    private static function getTypes()
    {
        return (new Util)->payment_types(null, false, session('business.id'));
    }

    public static function getTypesCollection()
    {
        $payment_types_array = self::getTypes();
        $payment_types_collection = [];

        foreach ($payment_types_array as $key => $value) {
            array_push($payment_types_collection, (object)[
                'id' => $key,
                'name' => $value
            ]);
        }

        return collect($payment_types_collection);
    }

    public static function getDefaultPaymentType()
    {
        return (object) [
            'id' => trans('accounting::core.none'),
            'name' => trans('accounting::core.none')
        ];
    }
}
