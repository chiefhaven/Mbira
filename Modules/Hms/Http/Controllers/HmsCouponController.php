<?php

namespace Modules\Hms\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Hms\Entities\HmsRoomType;
use Modules\Hms\Entities\HmsCoupon;
use App\Utils\Util;
use Yajra\DataTables\Facades\DataTables;
use App\Utils\ModuleUtil;


class HmsCouponController extends Controller
{
    protected $commonUtil;
    protected $moduleUtil;

    public function __construct(
        Util $commonUtil, ModuleUtil $moduleUtil

    ) {
        $this->commonUtil = $commonUtil;
        $this->moduleUtil = $moduleUtil;
    }
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {

        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.manage_coupon')){
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $extra = HmsCoupon::where('hms_coupons.business_id', $business_id)
                     ->leftjoin('hms_room_types as type', 'type.id', '=', 'hms_coupons.hms_room_type_id')
                     ->select(['hms_coupons.*', 'type.type as type']);
            return Datatables::of($extra)
                ->editColumn('created_at', '{{@format_datetime($created_at)}}')
                ->addColumn('action', function ($row) {
                    $html = '<a type="button" class="btn btn-primary btn-xs btn-modal-extra " href="' . action([\Modules\Hms\Http\Controllers\HmsCouponController::class, 'edit'], ['coupon' => $row->id]) . '">'
                        . __('hms::lang.edit_coupon') . '</a>';
                    $html .= ' <a href="' . route('delete_coupon', $row->id) . '"
                    class="btn btn-danger btn-xs delete_coupon_confirmation">' . __('messages.delete') . '</a>';

                    return $html;
                })
                ->editColumn('start_date', '{{@format_date($start_date)}}')
                ->editColumn('end_date', '{{@format_date($end_date)}}')

                ->rawColumns(['created_at', 'action', 'start_date', 'end_date'])
                ->make(true);
        }

        return view('hms::coupons.index');
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
        if(!auth()->user()->can( 'hms.manage_coupon')){
            abort(403, 'Unauthorized action.');
        }

        $types = HmsRoomType::where('business_id', $business_id)->pluck('type', 'id')->toArray();

        $discount_type = [
            'fixed' => "Fixed",
            'Percentage' => "Percentage",
        ];

        return view('hms::coupons.create', compact('types', 'discount_type'));
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }
        if(!auth()->user()->can( 'hms.manage_coupon')){
            abort(403, 'Unauthorized action.');
        }

        try{
            $input =  $request->except(['_token']);
            $input['business_id'] = request()->session()->get('user.business_id');
            $input['start_date'] = $this->commonUtil->uf_date($input['start_date']);
            $input['end_date'] = $this->commonUtil->uf_date($input['end_date']);
            HmsCoupon::create($input);

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Hms\Http\Controllers\HmsCouponController::class, 'index'])
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

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('hms::show');
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

        if(!auth()->user()->can( 'hms.manage_coupon')){
            abort(403, 'Unauthorized action.');
        }

        $coupon = HmsCoupon::findOrFail($id);

        $business_id = request()->session()->get('user.business_id');
        $types = HmsRoomType::where('business_id', $business_id)->pluck('type', 'id')->toArray();

        $discount_type = [
            'fixed' => "Fixed",
            'percentage' => "Percentage",
        ];

        return view('hms::coupons.edit', compact('coupon', 'discount_type', 'types'));
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
        if(!auth()->user()->can( 'hms.manage_coupon')){
            abort(403, 'Unauthorized action.');
        }

        try{

            $input =  $request->except(['_token']);
            $input['start_date'] = $this->commonUtil->uf_date($input['start_date']);
            $input['end_date'] = $this->commonUtil->uf_date($input['end_date']);
            $coupon = HmsCoupon::findOrFail($id);
            $coupon->update($input);

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Hms\Http\Controllers\HmsCouponController::class, 'index'])
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

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }
        if(!auth()->user()->can( 'hms.manage_coupon')){
            abort(403, 'Unauthorized action.');
        }
        
        
        try {

            HmsCoupon::where('id', $id)->delete();

            $output = ['success' => 1, 'msg' => __('lang_v1.success')];
            return redirect()
                ->action([\Modules\Hms\Http\Controllers\HmsCouponController::class, 'index'])
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

    public function get_coupon_discount(Request $request){ 

        $booking_date = $this->commonUtil->uf_date($request->booking_date);

        $coupon = HmsCoupon::where('coupon_code', $request->coupon_code)->whereDate('start_date', '<=', $booking_date)->whereDate('end_date', '>=', $booking_date)->first();

        if($coupon){
            $data = ['status'=>1, 'coupon' => $coupon , 'msg' => __('lang_v1.success')];
            return $data;
        }else{
            $data = ['status'=>0, 'msg' => __('messages.something_went_wrong')];
            return $data;
        }
    }
}
     