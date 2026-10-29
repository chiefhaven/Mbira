<?php

namespace Modules\Connector\Transformers;

use App\Utils\Util;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessLocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        $array = parent::toArray($request);
        $default_payment_accounts = ! empty($array['default_payment_accounts']) ? json_decode($array['default_payment_accounts'], true) : [];
        //Add label of each payment methods from business custom labels.
        $util = new Util();
        $methodsKey = 'connector.payment_types.'.$array['business_id'];
        if (! $request->attributes->has($methodsKey)) {
            $request->attributes->set($methodsKey, $util->payment_types(null, false, $array['business_id']));
        }
        $all_payment_methods = $request->attributes->get($methodsKey);
        $payment_methods = [];
        foreach ($default_payment_accounts as $key => $value) {
            if (isset($all_payment_methods[$key]) && $value['is_enabled'] == 1) {
                $payment_methods[] = [
                    'name' => $key,
                    'label' => $all_payment_methods[$key],
                    'account_id' => isset($value['account']) ? $value['account'] : null,
                ];
            }
        }

        unset($array['default_payment_accounts']);
        $array['payment_methods'] = $payment_methods;

        return $array;
    }
}
