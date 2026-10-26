<?php

namespace Modules\Hms\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Hms\Entities\HmsExtra;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Entities\HmsRoomType;
use Modules\Hms\Entities\HmsRoomTypePricing;
use App\Utils\Util;
use Carbon\Carbon;
use App\Contact;
use App\Transaction;
use App\Business;
use Illuminate\Support\Facades\DB;
use Modules\Hms\Entities\HmsBookingLine;
use Modules\Hms\Entities\HmsBookingExtra;
use Yajra\DataTables\Facades\DataTables;
use App\Utils\NotificationUtil;
use App\NotificationTemplate;
use Notification;
use App\Utils\ContactUtil;
use App\Utils\TransactionUtil;
use App\CustomerGroup;
use Modules\Hms\Notifications\CustomerNotification;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\BusinessUtil;
use App\Account;
use Modules\Hms\Entities\HmsTransactionClass;


class HmsBookingController extends Controller
{
    protected $commonUtil;
    protected $notificationUtil;
    protected $contactUtil;
    protected $transactionUtil;
    protected $dummyPaymentLine;
    protected $productUtil;
    protected $businessUtil;


    public function __construct(
        Util $commonUtil, NotificationUtil $notificationUtil, ContactUtil $contactUtil, TransactionUtil $transactionUtil, ModuleUtil $moduleUtil, ProductUtil $productUtil, BusinessUtil $businessUtil,

    ) {
        $this->commonUtil = $commonUtil;
        $this->notificationUtil = $notificationUtil;
        $this->contactUtil = $contactUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
        $this->productUtil = $productUtil;
        $this->businessUtil = $businessUtil;

        $this->dummyPaymentLine = ['method' => 'cash', 'amount' => 0, 'note' => '', 'card_transaction_number' => '', 'card_number' => '', 'card_type' => '', 'card_holder_name' => '', 'card_month' => '', 'card_year' => '', 'card_security' => '', 'cheque_number' => '', 'bank_account_number' => '',
        'is_return' => 0, 'transaction_no' => '', ];
    }
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);

            $booking = Transaction::where('transactions.business_id', $business_id)
                        ->with(['payment_lines'])
                        ->leftjoin('contacts as c', 'transactions.contact_id', '=', 'c.id')
                        ->where('transactions.type', 'hms_booking')
                        ->select('transactions.*', 'c.name as c_name', DB::raw('(SELECT SUM(IF(TP.is_return = 1,-1*TP.amount,TP.amount)) FROM transaction_payments AS TP WHERE
                        TP.transaction_id=transactions.id) as total_paid'));
            
            // filter with contact
            if($request->customer_id){
                $booking = $booking->where('c.id',$request->customer_id);
            }
            // filter with status
            if($request->status){
                $booking = $booking->where('transactions.status',$request->status);
            }

            // filtter with status
            if (! empty(request()->input('payment_status')) && request()->input('payment_status') != 'overdue') {
                $booking->where('transactions.payment_status', request()->input('payment_status'));
            } elseif (request()->input('payment_status') == 'overdue') {
                $booking->whereIn('transactions.payment_status', ['due', 'partial'])
                    ->whereNotNull('transactions.pay_term_number')
                    ->whereNotNull('transactions.pay_term_type')
                    ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
            }

            return Datatables::of($booking)

                ->editColumn('created_at', '{{@format_datetime($created_at)}}')
                
                ->addColumn('action', function ($row) {
                    $html = '';
                    if(auth()->user()->can( 'hms.edit_booking')){
                        $html = '<a type="button" class="btn btn-primary btn-xs btn-modal-extra " href="' . action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'edit'], ['booking' => $row->id]) . '">'
                        . __('hms::lang.edit_booking') . '</a>';
                    }
                   
                    if($row->status == 'confirmed'){
                        if(empty($row->check_in)){
                            $html .= '<a type="button" class="btn btn-info btn-xs btn-modal-checkIn" href="' . action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'get_check_in_out'], ['id' => $row->id]) . '" style="margin:4px">'
                        . __('hms::lang.check_in') . '</a>';
                        }else if (!empty($row->check_in) && empty($row->check_out)){
                            $html .= '<a type="button" class="btn btn-danger btn-xs btn-modal-checkIn" href="' . action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'get_check_in_out'], ['id' => $row->id]) . '" style="margin:4px">'
                        . __('hms::lang.check_out') . '</a>';
                        }
                        
                    }
                    $html .= '<a type="button" class="btn btn-success btn-xs btn-modal-extra" href="' . action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'show'], ['booking' => $row->id]) . '" style="margin:4px">'
                    . __('hms::lang.view') . '</a>';
                    return $html;
                })
                ->editColumn(
                    'payment_status',
                    function ($row) {
                        $payment_status = Transaction::getPaymentStatus($row);

                        return (string) view('sell.partials.payment_status', ['payment_status' => $payment_status, 'id' => $row->id]);
                    }
                )
                ->editColumn('stay', '{{@format_datetime($hms_booking_arrival_date_time)}} - {{ @format_datetime($hms_booking_departure_date_time) }}') 

                ->editColumn('status', function($row){
                    if($row->status == 'confirmed'){

                        if(!empty($row->check_in) && empty($row->check_out)){
                            return '<h6 class="badge bg-green">'.ucfirst($row->status).'</h6> <br> <h6 class="badge bg-info">'.__('hms::lang.check_in'). ' '. $this->commonUtil->format_date($row->check_in, true).'</h6>';
                        }else if (!empty($row->check_in) && !empty($row->check_out)){
                            return '<h6 class="badge bg-green">'.ucfirst($row->status).'</h6> <br> <h6 class="badge bg-info">'.__('hms::lang.check_in'). ' '. $this->commonUtil->format_date($row->check_in, true).'</h6> <br> <h6 class="badge bg-red">'.__('hms::lang.check_out'). ' '. $this->commonUtil->format_date($row->check_out, true).'</h6>';
                        }

                        return '<h6 class="badge bg-green">'.ucfirst($row->status).'</h6>';

                    }elseif($row->status == 'pending'){
                        return '<h6 class="badge bg-yellow">'.ucfirst($row->status).'</h6>';
                    }elseif($row->status == 'cancelled'){
                        return '<h6 class="badge bg-red">'.ucfirst($row->status).'</h6>';
                    }
                }) 
                ->addColumn('payment_methods', function ($row) use ($payment_types) {
                    $methods = array_unique($row->payment_lines->pluck('method')->toArray());
                    $count = count($methods);
                    $payment_method = '';
                    if ($count == 1) {
                        $payment_method = $payment_types[$methods[0]] ?? '';
                    } elseif ($count > 1) {
                        $payment_method = __('lang_v1.checkout_multi_pay');
                    }

                    $html = ! empty($payment_method) ? '<span class="payment-method" data-orig-value="'.$payment_method.'" data-status-name="'.$payment_method.'">'.$payment_method.'</span>' : '';

                    return $html;
                })
                ->editColumn(
                    'final_total',
                    '<span class="final-total" data-orig-value="{{$final_total}}">@format_currency($final_total)</span>'
                )
                ->editColumn(
                    'total_paid',
                    '<span class="total-paid" data-orig-value="{{$total_paid}}">@format_currency($total_paid)</span>'
                )
                ->addColumn('total_remaining', function ($row) {
                    $total_remaining = $row->final_total - $row->total_paid;
                    $total_remaining_html = '<span class="payment_due" data-orig-value="'.$total_remaining.'">'.$this->transactionUtil->num_f($total_remaining, true).'</span>';

                    return $total_remaining_html;
                })
                ->rawColumns(['created_at', 'action', 'stay', 'status', 'payment_status', 'payment_methods', 'final_total', 'total_paid', 'total_remaining'])
                ->make(true);
        }

        $customers = Contact::customersDropdown($business_id, false);
        $status = [
            'pending' => __('hms::lang.pending'),
            'confirmed' => __('hms::lang.confirmed'),
            'cancelled' => __('hms::lang.cancelled'),
        ];
        return view('hms::bookings.index', compact('customers', 'status'));
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.add_booking')){
            abort(403, 'Unauthorized action.');
        }

        $status = [
            'pending' => __('hms::lang.pending'),
            'confirmed' => __('hms::lang.confirmed'),
            'cancelled' => __('hms::lang.cancelled'),
        ];

       

        $extras = HmsExtra::where('business_id', $business_id)->get();


        $walk_in_customer = $this->contactUtil->getWalkInCustomer($business_id);

        $types = [];
        if (auth()->user()->can('supplier.create')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->user()->can('customer.create')) {
            $types['customer'] = __('report.customer');
        }
        if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }

        $customer_groups = CustomerGroup::forDropdown($business_id);

        $payment_line = $this->dummyPaymentLine;

        $change_return = $this->dummyPaymentLine;
    
        $payment_types = $this->productUtil->payment_types(null, true, $business_id);

        $business_details = $this->businessUtil->getDetails($business_id);

        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false, true);
        }

        return view('hms::bookings.create', compact('status', 'extras', 'walk_in_customer', 'types', 'customer_groups', 'payment_line', 'payment_types', 'pos_settings', 'change_return', 'accounts'));
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
 
        // return $request;
        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.add_booking')){
            abort(403, 'Unauthorized action.');
        }

        DB::beginTransaction();
        try{
            $arrival_date_time = $this->commonUtil->uf_date($request->arrival_date).' '.$this->commonUtil->uf_time($request->arrival_time);
            $departure_date_time = $this->commonUtil->uf_date($request->departure_date).' '.$this->commonUtil->uf_time($request->departure_time);

            $business_id = request()->session()->get('user.business_id');

            $busines = Business::findOrFail($business_id);

            $prefix = json_decode($busines->hms_settings)->prefix ?? null;

<<<<<<< HEAD
            $ref_no = null;

            $ref_count = $this->commonUtil->setAndGetReferenceCount("hms_booking", $business_id);
            //Generate reference number
=======
            $ref_count = $this->commonUtil->setAndGetReferenceCount("hms_booking", $business_id);
                //Generate reference number
>>>>>>> master
            $ref_no = $this->commonUtil->generateReferenceNumber('hms_booking', $ref_count, $business_id, $prefix);

            // store in transsaction discount_amount
            $transaction = new HmsTransactionClass();
            $transaction->business_id = $business_id;
            $transaction->type = 'hms_booking';
            $transaction->status = $request->status;
            $transaction->contact_id = $request->contact_id;
            $transaction->created_by = auth()->user()->id;
            $transaction->ref_no = $ref_no;
            $transaction->total_before_tax = (is_null($request->total_booking_amount) ? 0 : $request->total_booking_amount) + (is_null($request->total_discount) ? 0 : $request->total_discount);
            $transaction->final_total = is_null($request->total_booking_amount) ? 0 : $request->total_booking_amount;

            $transaction->tax_amount = is_null($request->total_discount) ? 0 : $request->total_discount;

            $transaction->discount_amount = is_null($request->total_discount) ? 0 : $request->total_discount;

            $transaction->hms_coupon_id = $request->coupon_id; 
            $transaction->discount_type = $request->discount_type; 

            $transaction->hms_booking_arrival_date_time = $arrival_date_time;
            $transaction->hms_booking_departure_date_time = $departure_date_time;
            $transaction->save();

            $adults =0;
            $childrens = 0;
            // store in booking room
            $rooms = $request->rooms ?? [];
            $room_lines = [];
            foreach ($rooms as $room) {
                $room_lines[] = new HmsBookingLine([
                    'hms_room_id' => $room['room_id'],
                    'hms_room_type_id' => $room['type_id'],
                    'adults' => $room['no_of_adult'],
                    'childrens' => $room['no_of_child'],
                    'price' => $room['price'],
                    'total_price' => $room['total_price'],
                ]);
            $adults = $adults + $room['no_of_adult'];
            $childrens = $childrens + $room['no_of_child'];
            }
            $transaction->hms_booking_lines()->saveMany($room_lines);

            // store in booking room
            $extras = $request->extras ?? [];

            $extra_lines = [];
            foreach ($extras as $extra) {
                if(isset($extra['id'])){
                    $extra_lines[] = new HmsBookingExtra([
                        'hms_extra_id' => $extra['id'],
                        'price' => $extra['price'],
                    ]);
                }
            }
            $transaction->hms_booking_extras()->saveMany($extra_lines);

             //Add change return
             $input = $request->except('_token');
              //Add change return
            $change_return = $this->dummyPaymentLine;
            if (! empty($input['payment']['change_return'])) {
                $change_return = $input['payment']['change_return'];
                unset($input['payment']['change_return']);
            }

             $change_return['amount'] = $input['change_return'] ?? 0;
             $change_return['is_return'] = 1;

             $input['payment'][] = $change_return;



            if (! empty($input['payment'])) {
                $this->transactionUtil->createOrUpdatePaymentLines($transaction, $input['payment']);
            }

            $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);

            // send notification to customer
            $template = NotificationTemplate::where('template_for', 'hms_new_booking')->where('business_id', $business_id)->first();

            if($template && $template->auto_send){

                $data = [
                    'email_body' => $template->email_body,
                    'subject' => $template->subject,
                ];
    
                $customer = Contact::findOrFail($transaction->contact_id);
    
                $tag_replaced_data = $this->notificationUtil->replaceHmsBookingTags($data, $transaction, $adults, $childrens, $customer);
                
                $orig_data = [
                    'email_body' => $tag_replaced_data['email_body'],
                    'subject' => $tag_replaced_data['subject'],
                    'cc' => $template->cc,
                    'bcc' => $template->cc,
                ];
                Notification::route('mail', $customer->email)->notify(new CustomerNotification($orig_data));
            }
             
            DB::commit();

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

                return redirect()->action(
                    [\Modules\Hms\Http\Controllers\HmsBookingController::class, 'index'])
                        ->with('status', $output);

        } 
        catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $business_id = request()->session()->get('user.business_id');

        $transaction =  HmsTransactionClass::where('transactions.business_id', $business_id)
                        ->with(['contact'])
                        ->leftJoin('hms_booking_lines as hbl', 'transactions.id', '=', 'hbl.transaction_id')
                        ->leftJoin('hms_booking_extras as hbe', 'transactions.id', '=', 'hbe.transaction_id')
                        ->leftJoin('hms_coupons as coupons', 'transactions.hms_coupon_id', '=', 'coupons.id')
                        ->where('transactions.type', 'hms_booking')
                        ->select(
                            'transactions.*',
                            DB::raw('(SELECT SUM(total_price) FROM hms_booking_lines WHERE transaction_id = transactions.id) as room_price'),
                            DB::raw('(SELECT SUM(price) FROM hms_booking_extras WHERE transaction_id = transactions.id) as extra_price'),
                            'coupons.coupon_code'
                        )
                        ->groupBy('transactions.id') // Group by transaction ID
                        ->findOrFail($id);
                        
                                            
        $extras_id =  HmsBookingExtra::where('transaction_id', $id)->pluck('hms_extra_id')->toArray();

        $booking_rooms = HmsBookingLine::where('transaction_id',$id)
                            ->leftjoin('hms_rooms as room', 'room.id', '=', 'hms_booking_lines.hms_room_id')
                            ->leftjoin('hms_room_types as type', 'type.id', '=', 'hms_booking_lines.hms_room_type_id')
                            ->get();

        $extras = HmsExtra::where('business_id', $business_id)->get();


        return view('hms::bookings.show', compact('extras','transaction', 'extras_id', 'booking_rooms'));

    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {

        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.edit_booking')){
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $transaction = Transaction::where('transactions.business_id', $business_id)
                        ->leftjoin('hms_coupons as coupon', 'transactions.hms_coupon_id', '=', 'coupon.id')
                        ->select(['transactions.*', 'coupon.coupon_code'])
                        ->findOrFail($id);
        $status = [
            'pending' => __('hms::lang.pending'),
            'confirmed' => __('hms::lang.confirmed'),
            'cancelled' => __('hms::lang.cancelled'),
        ];

        $customer_due = $this->transactionUtil->getContactDue($transaction->contact_id, $transaction->business_id);

        $types = [];
        if (auth()->user()->can('supplier.create')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->user()->can('customer.create')) {
            $types['customer'] = __('report.customer');
        }
        if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }

        $customer_groups = CustomerGroup::forDropdown($business_id);

        $customer_due = $customer_due != 0 ? $this->transactionUtil->num_f($customer_due, true) : '';

        $extras_id =  HmsBookingExtra::where('transaction_id', $id)->pluck('hms_extra_id')->toArray();

        $booking_rooms = HmsBookingLine::where('transaction_id',$id)
                            ->leftjoin('hms_rooms as room', 'room.id', '=', 'hms_booking_lines.hms_room_id')
                            ->leftjoin('hms_room_types as type', 'type.id', '=', 'hms_booking_lines.hms_room_type_id')
                            ->get();

        $business_id = request()->session()->get('user.business_id');

        $extras = HmsExtra::where('business_id', $business_id)->get();


        $payment_types = $this->productUtil->payment_types(null, true, $business_id);

        $business_details = $this->businessUtil->getDetails($business_id);

        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $payment_lines = $this->transactionUtil->getPaymentDetails($id);
        //If no payment lines found then add dummy payment line.
        if (empty($payment_lines)) {
            $payment_lines[] = $this->dummyPaymentLine;
        }
        
        $change_return = $this->dummyPaymentLine;

        
        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false, true);
        }


        return view('hms::bookings.edit', compact('status', 'extras', 'transaction', 'extras_id', 'booking_rooms', 'types', 'customer_groups', 'customer_due', 'payment_types', 'pos_settings', 'payment_lines', 'change_return', 'accounts'));
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.edit_booking')){
            abort(403, 'Unauthorized action.');
        }
        
        DB::beginTransaction();
        try{
            $arrival_date_time = $this->commonUtil->uf_date($request->arrival_date).' '.$this->commonUtil->uf_time($request->arrival_time);
            $departure_date_time = $this->commonUtil->uf_date($request->departure_date).' '.$this->commonUtil->uf_time($request->departure_time);

            $business_id = request()->session()->get('user.business_id');

            // store in transsaction
            $transaction = HmsTransactionClass::findOrFail($id);

            $transaction->status = $request->status;
            $transaction->status = $request->status;
            $transaction->contact_id = $request->contact_id;

            $transaction->total_before_tax = (is_null($request->total_booking_amount) ? 0 : $request->total_booking_amount) + (is_null($request->total_discount) ? 0 : $request->total_discount);

            $transaction->tax_amount = is_null($request->total_discount) ? 0 : $request->total_discount;

            $transaction->final_total = is_null($request->total_booking_amount) ? 0 : $request->total_booking_amount;

            $transaction->discount_amount = is_null($request->total_discount) ? 0 : $request->total_discount;
            $transaction->hms_coupon_id = $request->coupon_id; 
            $transaction->discount_type = $request->discount_type; 

            $transaction->hms_booking_arrival_date_time = $arrival_date_time;
            $transaction->hms_booking_departure_date_time = $departure_date_time;
            $transaction->update();


            HmsBookingLine::where('transaction_id', $id)->delete();
            // store in booking room

            $rooms = $request->rooms ?? [];
            $room_lines = [];
            foreach ($rooms as $room) {
                $room_lines[] = new HmsBookingLine([
                    'hms_room_id' => $room['room_id'],
                    'hms_room_type_id' => $room['type_id'],
                    'adults' => $room['no_of_adult'],
                    'childrens' => $room['no_of_child'],
                    'price' => $room['price'],
                    'total_price' => $room['total_price'],
                ]);
            }
            $transaction->hms_booking_lines()->saveMany($room_lines);

            HmsBookingExtra::where('transaction_id', $id)->delete();
            // store in HmsBookingExtra 
            $extras = $request->extras ?? [];
            $extra_lines = [];
            foreach ($extras as $extra) {
                if(isset($extra['id'])){
                    $extra_lines[] = new HmsBookingExtra([
                        'hms_extra_id' => $extra['id'],
                        'price' => $extra['price'],
                    ]);
                }
            }
            $transaction->hms_booking_extras()->saveMany($extra_lines);

             //Add change return
             $input = $request->except('_token');
             $change_return = $this->dummyPaymentLine;
             if (! empty($input['payment']['change_return'])) {
                 $change_return = $input['payment']['change_return'];
                 unset($input['payment']['change_return']);
             }


              //Add change return
              $change_return['amount'] = $input['change_return'] ?? 0;
              $change_return['is_return'] = 1;
              if (! empty($input['change_return_id'])) {
                  $change_return['payment_id'] = $input['change_return_id'];
              }
              $input['payment'][] = $change_return;


              
            if (! empty($input['payment'])) {
                $this->transactionUtil->createOrUpdatePaymentLines($transaction, $input['payment']);
            }

            $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);



            DB::commit();

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

                return redirect()->action(
                    [\Modules\Hms\Http\Controllers\HmsBookingController::class, 'index'])->with('status', $output);;

        } 
        catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }

    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }

    // this function return modal for add room during booking
    public function booking_room_add(){

        $business_id = request()->session()->get('user.business_id');

        $types = HmsRoomType::where('business_id', $business_id)->whereRaw('EXISTS (SELECT 1 FROM hms_room_type_pricings WHERE hms_room_type_id = hms_room_types.id)')->pluck('type', 'id')->toArray();
        
        return view('hms::bookings.add_room', compact('types'));
    }

    // this function return modal for edit singal room during booking
    public function booking_room_edit(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        $no_of_child = $request->input('no_of_child');
        $no_of_adult = $request->input('no_of_adult');
        $room_id = $request->input('room_id');
        $type_id = $request->input('type_id');

        $type = HmsRoomType::find($type_id);

        $types = HmsRoomType::where('business_id', $business_id)->whereRaw('EXISTS (SELECT 1 FROM hms_room_type_pricings WHERE hms_room_type_id = hms_room_types.id)')->pluck('type', 'id')->toArray();
        
        $room = HmsRoom::find($request->input('room_id'));

        $existing_rooms = [];

        if(!empty($request->input('room_ids')))
        {
            $existing_rooms = $request->input('room_ids');
            $existing_rooms = array_diff($existing_rooms, [$room_id]);
        }

       $rooms = HmsRoom::where('hms_room_type_id', $type_id)->whereNotIn('id', $existing_rooms)->pluck('room_number', 'id')->toArray();

       return view('hms::bookings.edit_room', compact('types', 'type', 'rooms', 'room_id', 'no_of_child', 'no_of_adult'));

    }

    // this function return room according to type
    public function get_room_type_by(Request $request){
        $type_id = $request->input('type_id');

        $arrival_date_time = $this->commonUtil->uf_date($request->arrival_date).' '.$this->commonUtil->uf_time($request->arrival_time);

        $departure_date_time = $this->commonUtil->uf_date($request->departure_date).' '.$this->commonUtil->uf_time($request->departure_time);

        $type = HmsRoomType::find($type_id);
        $existing_rooms = [];

        if(!empty($request->input('room_ids')))
        {
            $existing_rooms = $request->input('room_ids');
        }

       $rooms = HmsRoom::non_booking_rooms($type_id, $arrival_date_time, $departure_date_time, $existing_rooms, $this->commonUtil->uf_date($request->arrival_date), $this->commonUtil->uf_date($request->departure_date)); 

        return view('hms::bookings.room_type_by', compact('rooms', 'type'));
    }

    // this function view after select room during booking with calculation
    public function get_room_detail(Request $request)
    {
        $currentIndex = $request->input('current_index');
        $type = HmsRoomType::find($request->input('type_id'));
        $room = HmsRoom::find($request->input('room_id'));
        $no_of_child = $request->input('no_of_child');
        $no_of_adult = $request->input('no_of_adult');
        $is_edit = true;

        if($request->input('is_edit')){
            $is_edit = false;
        }

        $arrival_date= $this->commonUtil->uf_date($request->input('arrival_date'));
        $departure_date = $this->commonUtil->uf_date($request->input('departure_date'));
        // Parse the input dates using Carbon
        $start = Carbon::parse($arrival_date);
        $end = Carbon::parse($departure_date);
        // Calculate the difference in days
        $difference_in_days = $end->diffInDays($start);

        $price = $this->get_price($type->id, $arrival_date, $no_of_adult, $no_of_child);

        if($difference_in_days <= 0){
            ++$difference_in_days;
        }

        $total_price = ($difference_in_days * $price);

        $data = [
            'no_of_child' => $no_of_child,
            'no_of_adult' => $no_of_adult,
            'total_price' => $total_price,
            'price' => $price,
        ];

        return view('hms::bookings.room_detail', compact('type', 'room', 'data', 'currentIndex', 'is_edit'));
    }  

    // return price according to start day from pricing table
    public function get_price($type_id, $arrival_date, $no_of_adult, $no_of_child)
    {
        // Create a Carbon instance from the date string
        $carbon_date = Carbon::createFromFormat('Y-m-d', $arrival_date);
        // Get the day of the week as a string (e.g., "Sunday")
        $price_day = strtolower($carbon_date->format('l'));

        $price_column = 'price_' . $price_day;
        
        $pricing =  HmsRoomTypePricing::where('adults', $no_of_adult)->where('childrens', $no_of_child)->where('hms_room_type_id', $type_id)->first();

        if($pricing){

            $price =  $pricing->$price_column;

            if(is_null($price)){
                return $this->day_wise_or_default_price($type_id, $price_column);
            }
            return $price;
        }

        return $this->day_wise_or_default_price($type_id, $price_column);

    }

    // return price according to day if null return default price
    public function day_wise_or_default_price($type_id, $price_day)
    {
        $pricing = HmsRoomTypePricing::whereNull('adults')->whereNull('childrens')->where('hms_room_type_id', $type_id)->first();

        if(!is_null($pricing->$price_day)){
            return $pricing->$price_day;
        }
        return $pricing->default_price_per_night;
    }

    // display list of booking in calender view
    
    public function calendar(Request $request){

        $business_id = request()->session()->get('user.business_id');

        $types = HmsRoomType::where('business_id', $business_id)->pluck('type', 'id')->toArray();

        $rooms = HmsRoom::leftjoin('hms_room_types as type', 'type.id', '=', 'hms_rooms.hms_room_type_id')
                            ->where('type.business_id', $business_id)
                            ->select('hms_rooms.*', 'type.type', 'type.id as type_id');

                            if($request->type_id){
                                $rooms= $rooms->where('type.id', $request->type_id);
                            }

                            $rooms= $rooms->get();

        $start_date = now();

        // return $start_date;

        if($request->day_next){
            $start_date = now()->startOfWeek()->addDays($request->day_next);
        }


        if($request->week_next){
            $start_date = $start_date->addWeeks($request->week_next);
        }

    
        if($request->date){
            $start_date = Carbon::parse($request->date);
        }

        


        $date_html = '';
        $html = '';
        $class = '';
         $header_date = $start_date->copy();

        for ($i =0; $i<=6; $i++) {

                $header_date = $start_date->copy();

                if($request->day_next){
                    // Clone the $header_date object to avoid modifying it
                    $current_date = $header_date->clone();
                    
                    // Add $i days to the current date
                    $current_date->addDays($i);

                
                    if($current_date->format('Y-m-d') == now()->format('Y-m-d')){
                        $class = 'bg-success';
                    }
                    // Generate the HTML for the table header
                    $date_html .= '<th style="width: 100px;" class="text-center '.$class.'">
                                '.$current_date->format('d').' <br>
                                '.$current_date->format("l") .'
                                </th>';
                }else{

                    if($header_date->startOfWeek()->addDays($i)->format('Y-m-d') == now()->format('Y-m-d')){
                        $class = 'bg-success';
                    }

                    $date_html .= '<th style="width: 100px;" class="text-center '.$class.'">
                    '.$header_date->startOfWeek()->addDays($i)->format('d').' <br>
                    '.$header_date->startOfWeek()->addDays($i)->format("l") .'
                    </th>';
                }
                $class = '';
        }

        foreach($rooms as $room){
            $html .= '<tr><th class="text-center">'.$room->room_number.' <br> <small>'.$room->type.'</small/></th>';

            $ref_no = '';

           
            $size = 100;

            $temp_size = 100;

            for ($j =0; $j<=6; $j++) {
                $row_date  =  $start_date->copy();
                $days = $j;

                if($request->day_next){
                    $date = $row_date->addDays($days)->format('Y-m-d');
                }else{
                    $date = $row_date->startOfWeek()->addDays($days)->format('Y-m-d');
                }
                

                $is_booking = $this->is_booking($date, $room->id);
                if($is_booking){

                    if($ref_no == $is_booking->ref_no){
                        $size = $size + 100;
                        $html .= '<td></td>';
                    }else{
                        $html .= '<td><div class="hotel-reservation-outer tooltip-demo">
                        <a href="' . action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'index']) . '" class="hotel-reservation" data-toggle="tooltip" data-html="true" data-placement="bottom" title="" style=" left: 0%;" data-original-title="'.$is_booking->email.'<br></a>Phone: '.$is_booking->mobile.'<br/>Adults: '.$is_booking->adults.', Children: '.$is_booking->childrens.'<br/>ID: '.$is_booking->ref_no.'">
                            <div class="hotel-reservation-inner bg-confirmed" style="width: 100%;"'.$is_booking->ref_no.'><strong>'.$is_booking->name.'</strong></div>
                        </a>
                        </div></td>';
                    $ref_no = $is_booking->ref_no;
                    $size = 100;
                    $temp_size = 100;
                    }

                }else{
                    $html .= '<td class="text-center add_booking">
                        <div class="add_booking_div"><a title="Add Booking" href="'.action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'create']).'?booking_date='.$date.'"><i class="fa fa-fw fa-plus"></i></a></div>
                    </td>';
                }
                if($is_booking){
                    $ref_no = $is_booking->ref_no;
                }else{
                    $ref_no = '';
                }
                
                if($size >= 100){
                    $html = str_replace('style="width: '.$temp_size.'%;"'.$ref_no.'', 'style="width: '.$size.'%;"'.$ref_no.'', $html);
                    $temp_size = $size;
                }
            }
            $html .= '</tr>';    
            
            
        }
        return view('hms::bookings.calender', compact('types', 'rooms', 'start_date', 'html', 'date_html'));
    }

    public function is_booking($date, $id){
        $bookings = HmsBookingLine::leftjoin('transactions', 'transactions.id', '=', 'hms_booking_lines.transaction_id')
        ->where('hms_booking_lines.hms_room_id', $id)
        ->whereDate('transactions.hms_booking_arrival_date_time', '<=', $date)
        ->whereDate('transactions.hms_booking_departure_date_time', '>=', $date)
        ->where('transactions.status', 'confirmed')
        ->leftJoin('contacts AS c', 'transactions.contact_id', '=', 'c.id')
        ->first();

        return $bookings;


    }

    public function print($id){

        $business_id = request()->session()->get('user.business_id');

        $business = Business::find($business_id);

        $transaction =  Transaction::where('transactions.business_id', $business_id)
        ->with(['contact'])
        ->leftJoin('hms_booking_lines as hbl', 'transactions.id', '=', 'hbl.transaction_id')
        ->leftJoin('hms_booking_extras as hbe', 'transactions.id', '=', 'hbe.transaction_id')
        ->leftJoin('hms_coupons as coupons', 'transactions.hms_coupon_id', '=', 'coupons.id')
        ->where('transactions.type', 'hms_booking')
        ->select(
            'transactions.*',
            DB::raw('(SELECT SUM(total_price) FROM hms_booking_lines WHERE transaction_id = transactions.id) as room_price'),
            DB::raw('(SELECT SUM(price) FROM hms_booking_extras WHERE transaction_id = transactions.id) as extra_price'),
            'coupons.coupon_code',DB::raw('(SELECT SUM(IF(TP.is_return = 1,-1*TP.amount,TP.amount)) FROM transaction_payments AS TP WHERE
            TP.transaction_id=transactions.id) as total_paid')
        )
        ->groupBy('transactions.id') // Group by transaction ID
        ->findOrFail($id);

        $booking_rooms = HmsBookingLine::where('transaction_id',$id)
                            ->leftjoin('hms_rooms as room', 'room.id', '=', 'hms_booking_lines.hms_room_id')
                            ->leftjoin('hms_room_types as type', 'type.id', '=', 'hms_booking_lines.hms_room_type_id')
                            ->get();

                                             
        $extras_id =  HmsBookingExtra::where('transaction_id', $id)->pluck('hms_extra_id')->toArray();

        $extras = HmsExtra::where('business_id', $business_id)->get();

        
        $html = view('hms::bookings.print_pdf')->with(compact('business', 'transaction', 'booking_rooms', 'extras_id', 'extras'))->render();
        $mpdf = new \Mpdf\Mpdf(['tempDir' => public_path('uploads/temp'),
            'mode' => 'utf-8',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'autoVietnamese' => true,
            'autoArabic' => true,
            'margin_top' => 8,
            'margin_bottom' => 8,
        ]);
        $mpdf->useSubstitutions = true;
        $mpdf->SetTitle(__('hms::lang.booking').' |'. $transaction->ref_no);
        $mpdf->WriteHTML($html);
        $mpdf->Output('booking.pdf', 'I');
    }

    public function get_check_in_out($id)
    {

        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $transaction =  HmsTransactionClass::where('transactions.business_id', $business_id)
                        ->with(['contact'])
                        ->leftJoin('hms_booking_lines as hbl', 'transactions.id', '=', 'hbl.transaction_id')
                        ->leftJoin('hms_booking_extras as hbe', 'transactions.id', '=', 'hbe.transaction_id')
                        ->leftJoin('hms_coupons as coupons', 'transactions.hms_coupon_id', '=', 'coupons.id')
                        ->where('transactions.type', 'hms_booking')
                        ->select(
                            'transactions.*',
                            DB::raw('(SELECT SUM(total_price) FROM hms_booking_lines WHERE transaction_id = transactions.id) as room_price'),
                            DB::raw('(SELECT SUM(price) FROM hms_booking_extras WHERE transaction_id = transactions.id) as extra_price'),
                            'coupons.coupon_code', DB::raw('(SELECT SUM(IF(TP.is_return = 1,-1*TP.amount,TP.amount)) FROM transaction_payments AS TP WHERE
                            TP.transaction_id=transactions.id) as total_paid')
                        )
                        ->groupBy('transactions.id') // Group by transaction ID
                        ->findOrFail($id);
                        
                                            
        $extras_id =  HmsBookingExtra::where('transaction_id', $id)->pluck('hms_extra_id')->toArray();

        $booking_rooms = HmsBookingLine::where('transaction_id',$id)
                            ->leftjoin('hms_rooms as room', 'room.id', '=', 'hms_booking_lines.hms_room_id')
                            ->leftjoin('hms_room_types as type', 'type.id', '=', 'hms_booking_lines.hms_room_type_id')
                            ->get();

        $extras = HmsExtra::where('business_id', $business_id)->get();

        return view('hms::bookings.check_in_out', compact('extras','transaction', 'extras_id', 'booking_rooms'));
        
    }

    public function post_check_in_out(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }
        
        $transaction = Transaction::where('business_id', $business_id)
                        ->findOrFail($id);

        try{

            if (! empty($request->in_out_date_time)) {
                $in_out_date_time = $this->commonUtil->uf_date($request->in_out_date_time, true); 
            }
    
            $check_in = $transaction->check_in;
            
            if(empty($check_in)){
                $transaction->check_in = $in_out_date_time;
            }
    
            if(!empty($check_in)){
                $transaction->check_out = $in_out_date_time;
            }
    
            $transaction->update();

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'index'])
                ->with('status', $output);

        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }
    }
}
