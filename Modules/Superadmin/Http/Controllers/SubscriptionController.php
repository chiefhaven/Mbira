<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Business;
use App\System;
use App\Utils\ModuleUtil;
use Illuminate\Console\View\Components\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Notifications\SubscriptionOfflinePaymentActivationConfirmation;
use Notification;
use Paystack;
use Pesapal;
use Razorpay\Api\Api;
use Srmklive\PayPal\Services\ExpressCheckout;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Stripe;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SubscriptionController extends BaseController
{
    protected $provider;

    public function __construct(ModuleUtil $moduleUtil = null)
    {
        if (! defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6);
        }

        if (! defined('CURLOPT_SSLVERSION')) {
            define('CURLOPT_SSLVERSION', 6);
        }

        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        //Get active subscription and upcoming subscriptions.
        $active = Subscription::active_subscription($business_id);

        $nexts = Subscription::upcoming_subscriptions($business_id);
        $waiting = Subscription::waiting_approval($business_id);

        $packages = Package::active()->orderby('sort_order')->get();

        //Get all module permissions and convert them into name => label
        $permissions = $this->moduleUtil->getModuleData('superadmin_package');
        $permission_formatted = [];
        foreach ($permissions as $permission) {
            foreach ($permission as $details) {
                $permission_formatted[$details['name']] = $details['label'];
            }
        }

        $intervals = ['days' => __('lang_v1.days'), 'months' => __('lang_v1.months'), 'years' => __('lang_v1.years')];

        return view('superadmin::subscription.index')
            ->with(compact('packages', 'active', 'nexts', 'waiting', 'permission_formatted', 'intervals'));
    }

    /**
     * Show pay form for a new package.
     *
     * @return Response
     */
    public function pay($package_id, $form_register = null)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

            $business_id = request()->session()->get('user.business_id');

            $package = Package::active()->find($package_id);

            //Check if superadmin only package
            if ($package->is_private == 1 && ! auth()->user()->can('superadmin')) {
                $output = ['success' => 0, 'msg' => __('superadmin::lang.not_allowed_for_package')];

                return redirect()
                        ->back()
                        ->with('status', $output);
            }

            //Check if one time only package
            if (empty($form_register) && $package->is_one_time) {
                $count_subcriptions = Subscription::where('business_id', $business_id)
                                                ->where('package_id', $package_id)
                                                ->count();

                if ($count_subcriptions > 0) {
                    $output = ['success' => 0, 'msg' => __('superadmin::lang.maximum_subscription_limit_exceed')];

                    return redirect()
                        ->back()
                        ->with('status', $output);
                }
            }

            //Check for free package & subscribe it.
            if ($package->price == 0) {
                $gateway = null;
                $payment_transaction_id = 'FREE';
                $user_id = request()->session()->get('user.id');

                $this->_add_subscription($business_id, $package, $gateway, $payment_transaction_id, $user_id);

                DB::commit();

                if (empty($form_register)) {
                    $output = ['success' => 1, 'msg' => __('lang_v1.success')];

                    return redirect()
                        ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                        ->with('status', $output);
                } else {
                    $output = ['success' => 1, 'msg' => __('superadmin::lang.registered_and_subscribed')];

                    return redirect()
                        ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                        ->with('status', $output);
                }
            }

            $gateways = $this->_payment_gateways();

            $system_currency = System::getCurrency();

            DB::commit();

            if (empty($form_register)) {
                $layout = 'layouts.app';
            } else {
                $layout = 'layouts.auth';
            }

            $user = request()->session()->get('user');

            $offline_payment_details = System::getProperty('offline_payment_details');

            return view('superadmin::subscription.pay')
                ->with(compact('package', 'gateways', 'system_currency', 'layout', 'user', 'offline_payment_details'));
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0, 'msg' => 'File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage()];

            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                ->with('status', $output);
        }
    }

    /**
     * Show pay form for a new package.
     *
     * @return Response
     */
    public function registerPay($package_id)
    {
        return $this->pay($package_id, 1);
    }

    /**
     * Save the payment details and add subscription details
     *
     * @return Response
     */
    public function confirm($package_id, Request $request)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        try {

            //Disable in demo
            if (config('app.env') == 'demo') {
                $output = ['success' => 0,
                    'msg' => 'Feature disabled in demo!!',
                ];

                return back()->with('status', $output);
            }

            //Confirm for pesapal payment gateway
            if (isset($this->_payment_gateways()['pesapal']) && (strpos($request->merchant_reference, 'PESAPAL') !== false)) {
                return $this->confirm_pesapal($package_id, $request);
            }

            DB::beginTransaction();

            $business_id = request()->session()->get('user.business_id');
            $business_name = request()->session()->get('business.name');
            $user_id = request()->session()->get('user.id');
            $package = Package::active()->find($package_id);

            //Call the payment method
            $pay_function = 'pay_'.request()->gateway;
            $payment_transaction_id = null;
            if (method_exists($this, $pay_function)) {
                $payment_transaction_id = $this->$pay_function($business_id, $business_name, $package, $request);
            }

            //Add subscription details after payment is succesful
            $this->_add_subscription($business_id, $package_id, request()->gateway, $payment_transaction_id, $user_id);
            DB::commit();

            $msg = __('lang_v1.success');
            if (request()->gateway == 'offline') {
                $msg = __('superadmin::lang.notification_sent_for_approval');
            }
            $output = ['success' => 1, 'msg' => $msg];
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            echo 'File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage();
            exit;
            $output = ['success' => 0, 'msg' => $e->getMessage()];
        }

        return redirect()
            ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
            ->with('status', $output);
    }

    /**
     * Confirm for pesapal gateway
     * when payment gateway is PesaPal payment gateway request package_id
     * is transaction_id & merchant_reference in session contains
     * the package_id.
     *
     * @return Response
     */
    protected function confirm_pesapal($transaction_id, $request)
    {
        $merchant_reference = $request->merchant_reference;
        $pesapal_session = $request->session()->pull('pesapal');

        if ($pesapal_session['ref'] == $merchant_reference) {
            $package_id = $pesapal_session['package_id'];

            $business_id = request()->session()->get('user.business_id');
            $business_name = request()->session()->get('business.name');
            $user_id = request()->session()->get('user.id');
            $package = Package::active()->find($package_id);

            $this->_add_subscription($business_id, $package, 'pesapal', $transaction_id, $user_id);
            $output = ['success' => 1, 'msg' => __('superadmin::lang.waiting_for_confirmation')];

            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                ->with('status', $output);
        }
    }

    /**
     * Stripe payment method
     *
     * @return Response
     */
    protected function pay_stripe($business_id, $business_name, $package, $request)
    {
        Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

        $metadata = ['business_id' => $business_id, 'business_name' => $business_name, 'stripe_email' => $request->stripeEmail, 'package_name' => $package->name];

        $customer = Customer::create([
            'name' => 'Stripe User',
            'email' => $request->stripeEmail,
            'source' => $request->stripeToken,
            'metadata' => $metadata,
            'description' => 'Stripe payment',
        ]);

        $system_currency = System::getCurrency();

        $charge = Charge::create([
            'amount' => $package->price,
            'currency' => strtolower($system_currency->code),
            'customer' => $customer,
            'metadata' => $metadata,
        ]);

        return $charge->id;
    }

    /**
     * Offline payment method
     *
     * @return Response
     */
    protected function pay_offline($business_id, $business_name, $package, $request)
    {

        //Disable in demo
        if (config('app.env') == 'demo') {
            $output = ['success' => 0,
                'msg' => 'Feature disabled in demo!!',
            ];

            return back()->with('status', $output);
        }

        //Send notification
        $email = System::getProperty('email');
        $business = Business::find($business_id);

        if (! $this->moduleUtil->IsMailConfigured()) {
            return null;
        }
        $system_currency = System::getCurrency();
        $package->price = $system_currency->symbol.number_format($package->price, 2, $system_currency->decimal_separator, $system_currency->thousand_separator);

        Notification::route('mail', $email)
            ->notify(new SubscriptionOfflinePaymentActivationConfirmation($business, $package));

        return null;
    }

    /**
     * Paypal payment method
     *
     * @return Response
     */
    protected function pay_paypal($business_id, $business_name, $package, $request)
    {
        //Set config to use the currency
        $system_currency = System::getCurrency();
        $provider = new ExpressCheckout();
        config(['paypal.currency' => $system_currency->code]);

        $provider = new ExpressCheckout();
        $response = $provider->getExpressCheckoutDetails($request->token);

        $token = $request->get('token');
        $PayerID = $request->get('PayerID');
        $invoice_id = $response['INVNUM'];

        // if response ACK value is not SUCCESS or SUCCESSWITHWARNING we return back with error
        if (! in_array(strtoupper($response['ACK']), ['SUCCESS', 'SUCCESSWITHWARNING'])) {
            return back()
                ->with('status', ['success' => 0, 'msg' => 'Something went wrong with paypal transaction']);
        }

        $data = [];
        $data['items'] = [
            [
                'name' => $package->name,
                'price' => (float) $package->price,
                'qty' => 1,
            ],
        ];
        $data['invoice_id'] = $invoice_id;
        $data['invoice_description'] = "Order #{$data['invoice_id']} Invoice";
        $data['return_url'] = action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'confirm'], [$package->id]);
        $data['cancel_url'] = action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pay'], [$package->id]);
        $data['total'] = (float) $package->price;

        // if payment is not recurring just perform transaction on PayPal and get the payment status
        $payment_status = $provider->doExpressCheckoutPayment($data, $token, $PayerID);
        $status = isset($payment_status['PAYMENTINFO_0_PAYMENTSTATUS']) ? $payment_status['PAYMENTINFO_0_PAYMENTSTATUS'] : null;

        if (! empty($status) && $status != 'Invalid') {
            return $invoice_id;
        } else {
            $error = 'Something went wrong with paypal transaction';
            throw new \Exception($error);
        }
    }

    /**
     * Paypal payment method - redirect to paypal url for payments
     *
     * @return Response
     */
    public function paypalExpressCheckout(Request $request, $package_id)
    {

        //Disable in demo
        if (config('app.env') == 'demo') {
            $output = ['success' => 0,
                'msg' => 'Feature disabled in demo!!',
            ];

            return back()->with('status', $output);
        }

        // Get the cart data or package details.
        $package = Package::active()->find($package_id);

        $data = [];
        $data['items'] = [
            [
                'name' => $package->name,
                'price' => (float) $package->price,
                'qty' => 1,
            ],
        ];
        $data['invoice_id'] = Str::random(5);
        $data['invoice_description'] = "Order #{$data['invoice_id']} Invoice";
        $data['return_url'] = action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'confirm'], [$package_id]).'?gateway=paypal';
        $data['cancel_url'] = action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pay'], [$package_id]);
        $data['total'] = (float) $package->price;

        // send a request to paypal
        // paypal should respond with an array of data
        // the array should contain a link to paypal's payment system
        $system_currency = System::getCurrency();
        $provider = new ExpressCheckout();
        $response = $provider->setCurrency(strtoupper($system_currency->code))->setExpressCheckout($data);

        // if there is no link redirect back with error message
        if (! $response['paypal_link']) {
            return back()
                ->with('status', ['success' => 0, 'msg' => 'Something went wrong with paypal transaction']);
            //For the actual error message dump out $response and see what's in there
        }

        // redirect to paypal
        // after payment is done paypal
        // will redirect us back to $this->expressCheckoutSuccess
        return redirect($response['paypal_link']);
    }

    /**
     * Razor pay payment method
     *
     * @return Response
     */
    protected function pay_razorpay($business_id, $business_name, $package, $request)
    {
        $razorpay_payment_id = $request->razorpay_payment_id;
        $razorpay_api = new Api(env('RAZORPAY_KEY_ID'), env('RAZORPAY_KEY_SECRET'));

        $payment = $razorpay_api->payment->fetch($razorpay_payment_id)->capture(['amount' => $package->price * 100]); // Captures a payment

        if (empty($payment->error_code)) {
            return $payment->id;
        } else {
            $error_description = $payment->error_description;
            throw new \Exception($error_description);
        }
    }

    /**
     * Redirect the User to Paystack Payment Page
     *
     * @return Url
     */
    public function getRedirectToPaystack()
    {
        return Paystack::getAuthorizationUrl()->redirectNow();
    }

    /**
     * Obtain Paystack payment information
     *
     * @return void
     */
    public function postPaymentPaystackCallback()
    {
        $payment = Paystack::getPaymentData();
        $business_id = $payment['data']['metadata']['business_id'];
        $package_id = $payment['data']['metadata']['package_id'];
        $gateway = $payment['data']['metadata']['gateway'];
        $payment_transaction_id = $payment['data']['reference'];
        $user_id = $payment['data']['metadata']['user_id'];

        if ($payment['status']) {
            //Add subscription
            $this->_add_subscription($business_id, $package_id, $gateway, $payment_transaction_id, $user_id);

            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                ->with('status', ['success' => 1, 'msg' => __('lang_v1.success')]);
        } else {
            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pay'], [$package_id])
                ->with('status', ['success' => 0, 'msg' => __('messages.something_went_wrong')]);
        }
    }

    /**
     * Obtain Flutterwave payment information
     *
     * @return response
     */
    public function postFlutterwavePaymentCallback(Request $request)
    {
        $url = 'https://api.flutterwave.com/v3/transactions/'.$request->get('transaction_id').'/verify';
        $header = [
            'Content-Type: application/json',
            'Authorization: Bearer '.env('FLUTTERWAVE_SECRET_KEY'),
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $header,
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        $payment = json_decode($response, true);

        if ($payment['status'] == 'success') {
            //Add subscription
            $business_id = $payment['data']['meta']['business_id'];
            $package_id = $payment['data']['meta']['package_id'];
            $gateway = $payment['data']['meta']['gateway'];
            $payment_transaction_id = $payment['data']['tx_ref'];
            $user_id = $payment['data']['meta']['user_id'];

            $this->_add_subscription($business_id, $package_id, $gateway, $payment_transaction_id, $user_id);

            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                ->with('status', ['success' => 1, 'msg' => __('lang_v1.success')]);
        } else {
            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pay'], [$package_id])
                ->with('status', ['success' => 0, 'msg' => __('messages.something_went_wrong')]);
        }
    }

    /**
     * Step 1: Initiate OneKhusa Payment
     */
    public function initiateOneKhusaPayment(Request $request)
    {
        try {
            $request->validate([
                'package_id' => 'required|integer'
            ]);

            $package = Package::findOrFail($request->package_id);

            $business_id = $request->session()->get('user.business_id');
            $user_id = $request->session()->get('user.id');
            $business = Business::find($business_id);
            $merchantAccountNumber = config('services.onekhusa.merchant_account_number');
            $apiKey = config('services.onekhusa.api_key');
            $apiSecret = config('services.onekhusa.api_secret');
            $organisationId = config('services.onekhusa.organisation_id');
            $baseUrl = rtrim(config('services.onekhusa.base_url'), '/');
            $moduleCode = 'MbiraERP';
            $uniqueNumber = Str::upper(Str::random(6));

            // Unique reference (this is fine to generate locally)
            $reference = 'SUB-' . time() . '-' . Str::upper(Str::random(6));

            // Idempotency key (valid format)
            $idempotencyKey = "{$merchantAccountNumber}-{$moduleCode}-{$uniqueNumber}";

            // Store session BEFORE API call
            $paymentData = [
                'package_id' => $package->id,
                'reference' => $reference,
                'amount' => $package->price,
                'business_id' => $business_id,
                'user_id' => $user_id,
            ];

            session([
                "onekhusa_payment_reference" => $paymentData,
            ]);

            // Get access token
            $accessToken = $this->getOneKhusaAccessToken();

            if (!$accessToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication failed'
                ]);
            }

            // Build payload (NO paymentTransactionId here)
            $payload = [
                'authentication' => [
                    'apiKey' => $apiKey,
                    'apiSecret' => $apiSecret,
                ],
                'merchant' => [
                    'organisationId' => $organisationId,
                    'merchantAccountNumber' => (int) $merchantAccountNumber,
                ],
                'payment' => [
                    'sourceReferenceNumber' => $reference,
                    'description' => 'Subscription payment - ' . $package->name,
                    'amount' => (int) $package->price,
                ],
                'route' => [
                    'successRedirectionUrl' => route('superadmin.onekhusa.success', [
                        'reference' => $reference
                    ]),
                    'failureRedirectionUrl' => route('superadmin.onekhusa.failed'),
                    'callbackApiUrl' => route('superadmin.onekhusa.webhook'),
                ],
            ];

            // Step 1: Initiate RTP
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => "Bearer $accessToken",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Idempotency-Key' => $idempotencyKey,
                ])
                ->post("$baseUrl/checkout/rtp/initiate", $payload);

            $data = $response->json();

            Log::info('OneKhusa Initiate Response', $data);

            // Step 2: Extract REAL paymentTransactionId from response
            $paymentTransactionId =
                $data['paymentTransactionId']
                ?? $data['data']['paymentTransactionId']
                ?? null;

            if (!$paymentTransactionId) {
                Log::error('OneKhusa: Missing paymentTransactionId', $data);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payment gateway response'
                ]);
            }

            // Store full transaction mapping
            session([
                "onekhusa_payment_txn_$paymentTransactionId" => array_merge($paymentData, [
                    'payment_transaction_id' => $paymentTransactionId
                ])
            ]);

            // Step 3: Build checkout URL (MANDATORY flow)
            $checkoutUrl = 'https://checkout.onekhusa.com/requestToPay/initiate?' . http_build_query([
                'ptid' => $paymentTransactionId
            ]);

            return response()->json([
                'success' => true,
                'payment_url' => $checkoutUrl,
                'reference' => $reference,
                'payment_transaction_id' => $paymentTransactionId,
            ]);

        } catch (\Exception $e) {
            Log::error('OneKhusa Initiate Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Step 2: Access token
     */
    private function getOneKhusaAccessToken()
    {
        try {
            $baseUrl = rtrim(config('services.onekhusa.base_url'), '/');

            $idempotencyKey = 'TOKEN-' . Str::upper(Str::random(6));

            $response = Http::timeout(60)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Idempotency-Key' => $idempotencyKey,
                    'Accept-language' => 'en',
                ])
                ->post("$baseUrl/account/getAccessToken", [
                    'apiKey' => config('services.onekhusa.api_key'),
                    'apiSecret' => config('services.onekhusa.api_secret'),
                    'organisationId' => config('services.onekhusa.organisation_id'),
                    'merchantAccountNumber' => (int) config('services.onekhusa.merchant_account_number'),
                ]);

            $data = $response->json();

            return $data['accessToken'] ?? null;
        } catch (\Exception $e) {
            Log::error('OneKhusa Token Error', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Success redirect
     */
    public function oneKhusaSuccess(Request $request)
    {
        $reference = $request->get('reference');
        $ptid = $request->get('ptid');

        $paymentData =
            session("onekhusa_payment_$reference")
            ?? session("onekhusa_payment_txn_$ptid");

        if (!$paymentData) {
            return redirect()->back()
                ->with('status', [
                    'success' => 0,
                    'msg' => 'Payment session expired'
                ]);
        }

        $this->_add_subscription(
            $paymentData['business_id'],
            $paymentData['package_id'],
            'onekhusa',
            $paymentData['reference'],
            $paymentData['user_id']
        );

        return redirect()
            ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
            ->with('status', [
                'success' => 1,
                'msg' => 'Payment successful'
            ]);
    }

    /**
     * Failed redirect
     */
    public function oneKhusaFailed()
    {
        \Log::warning('OneKhusa Payment Failed or Cancelled', request()->all());

        return redirect()
            ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
            ->with('status', [
                'success' => 0,
                'msg' => 'Payment failed or cancelled'
            ]);
    }

    public function oneKhusaWebhook(Request $request)
    {
        $payload = $request->all();

        Log::info('OneKhusa Webhook Received', $payload);

    }

    /**
     * Show the specified resource.
     *
     * @return Response
     */
    public function show($id)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $subscription = Subscription::where('business_id', $business_id)
                                    ->with(['package', 'created_user', 'business'])
                                    ->find($id);

        $system_settings = System::getProperties([
            'invoice_business_name',
            'email',
            'invoice_business_landmark',
            'invoice_business_city',
            'invoice_business_zip',
            'invoice_business_state',
            'invoice_business_country',
        ]);
        $system = [];
        foreach ($system_settings as $setting) {
            $system[$setting['key']] = $setting['value'];
        }

        return view('superadmin::subscription.show_subscription_modal')
            ->with(compact('subscription', 'system'));
    }

    /**
     * Retrieves list of all subscriptions for the current business
     *
     * @return \Illuminate\Http\Response
     */
    public function allSubscriptions()
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $subscriptions = Subscription::where('subscriptions.business_id', $business_id)
                        ->leftjoin(
                            'packages as P',
                            'subscriptions.package_id',
                            '=',
                            'P.id'
                        )
                        ->leftjoin(
                            'users as U',
                            'subscriptions.created_id',
                            '=',
                            'U.id'
                        )
                        ->addSelect(
                            'P.name as package_name',
                            DB::raw("CONCAT(COALESCE(U.surname, ''), ' ', COALESCE(U.first_name, ''), ' ', COALESCE(U.last_name, '')) as created_by"),
                            'subscriptions.*'
                        );

        return Datatables::of($subscriptions)
             ->editColumn(
                 'start_date',
                 '@if(!empty($start_date)){{@format_date($start_date)}}@endif'
             )
             ->editColumn(
                 'end_date',
                 '@if(!empty($end_date)){{@format_date($end_date)}}@endif'
             )
             ->editColumn(
                 'trial_end_date',
                 '@if(!empty($trial_end_date)){{@format_date($trial_end_date)}}@endif'
             )
             ->editColumn(
                 'package_price',
                 '<span class="display_currency" data-currency_symbol="true">{{$package_price}}</span>'
             )
             ->editColumn(
                 'created_at',
                 '@if(!empty($created_at)){{@format_date($created_at)}}@endif'
             )
             ->filterColumn('created_by', function ($query, $keyword) {
                 $query->whereRaw("CONCAT(COALESCE(U.surname, ''), ' ', COALESCE(U.first_name, ''), ' ', COALESCE(U.last_name, '')) like ?", ["%{$keyword}%"]);
             })
             ->addColumn('action', function ($row) {
                 return '<button type="button" class="btn btn-primary btn-xs btn-modal" data-container=".view_modal" data-href="'.action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'show'], $row->id).'" ><i class="fa fa-eye" aria-hidden="true"></i> '.__('messages.view').'</button>';
             })
             ->rawColumns(['package_price', 'action'])
             ->make(true);
    }
    
    /**
     * Returns available payment gateways
     */
    public function _payment_gateways()
    {
        $gateways = [];
        
        if (env('STRIPE_KEY')) {
            $gateways['stripe'] = 'Stripe';
        }
        
        if (env('PAYPAL_CLIENT_ID') && env('PAYPAL_SECRET_ID')) {
            $gateways['paypal'] = 'PayPal';
        }
        
        if (env('RAZORPAY_KEY_ID') && env('RAZORPAY_KEY_SECRET')) {
            $gateways['razorpay'] = 'Razorpay';
        }
        
        if (env('PAYSTACK_PUBLIC_KEY') && env('PAYSTACK_SECRET_KEY')) {
            $gateways['paystack'] = 'Paystack';
        }
        
        if (env('FLUTTERWAVE_PUBLIC_KEY') && env('FLUTTERWAVE_SECRET_KEY')) {
            $gateways['flutterwave'] = 'Flutterwave';
        }
        
        // Add OneKhusa gateway
        if (env('ONEKHUSA_API_KEY') && env('ONEKHUSA_API_SECRET')) {
            $gateways['onekhusa'] = 'OneKhusa';
        }
        
        // Offline payment
        $offline_payment_details = System::getProperty('offline_payment_details');
        if (!empty($offline_payment_details)) {
            $gateways['offline'] = 'Offline Payment';
        }
        
        return $gateways;
    }
}