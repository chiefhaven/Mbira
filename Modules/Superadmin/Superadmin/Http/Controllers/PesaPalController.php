<?php

namespace Modules\Superadmin\Http\Controllers;

use Modules\Superadmin\Entities\Subscription;
use Illuminate\Routing\Controller;

class PesaPalController extends Controller
{
    //This method get called from app/Http/Controllers/PesaPalController
    public function pesaPalPaymentConfirmation($transaction_id, $status, $payment_method, $merchant_reference)
    {
        $subscription = Subscription::where('payment_transaction_id', $transaction_id)->first();

        \Log::emergency('subscription transaction_id:'.$transaction_id.'status:'.$status.'payment_method:'.$payment_method);

        if ($status == 'COMPLETED') {
            if ($subscription->status != 'approved') {
                //Update the date
                $dates = $this->_get_package_dates($subscription->business_id, $subscription->package);
                $subscription->status = 'approved';
                $subscription->start_date = $dates['start'];
                $subscription->end_date = $dates['end'];
                $subscription->trial_end_date = $dates['trial'];
                $subscription->update();
            }
        } else {
            $subscription->status = 'waiting';
            $subscription->start_date = null;
            $subscription->end_date = null;
            $subscription->trial_end_date = null;
            $subscription->update();
        }
    }
}
