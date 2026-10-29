<?php

namespace Modules\Hms\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Hms\Entities\HmsExtra;
use Yajra\DataTables\Facades\DataTables;
use App\Utils\ModuleUtil;


class ExtraController extends Controller
{
    protected $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin')  || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.manage_extra')){
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $extra = HmsExtra::where('business_id', $business_id);
            return Datatables::of($extra)
                ->editColumn('created_at', '{{@format_datetime($created_at)}}')

                // ->editColumn('price', '{{ $price }} {{ str_replace("_", " ", $price_per) }}' )

                ->editColumn('price', '<span>@format_currency($price) </span> {{str_replace("_", " ", $price_per)}}<span></span>')

                ->addColumn('action', function ($row) {
                    $html = '<a type="button" class="btn btn-primary btn-xs btn-modal-extra " href="' . action([\Modules\Hms\Http\Controllers\ExtraController::class, 'edit'], ['extra' => $row->id]) . '">'
                        . __('hms::lang.edit_extra') . '</a>';
                    $html .= ' <a href="' . route('delete_extra', $row->id) . '"
                    class="btn btn-danger btn-xs delete_extra_confirmation">' . __('messages.delete') . '</a>';

                    return $html;
                })
                ->rawColumns(['created_at', 'action', 'price'])
                ->make(true);
        }
        return view('hms::extras.index');
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

        $price_per = [
            'per_day' => 'Per Day',
            'per_booking' => 'Per Booking',
            'per_person' => 'Per Person',
            'per_day_per_person' => 'Per Day / Per Person',
        ];

        return view('hms::extras.create', compact('price_per'));
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

        if(!auth()->user()->can( 'hms.manage_extra')){
            abort(403, 'Unauthorized action.');
        }
        
        try {
            $input =  $request->except(['_token']);
            $input['created_by'] = auth()->user()->id;
            $input['business_id'] = request()->session()->get('user.business_id');
            $extra = HmsExtra::create($input);

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Hms\Http\Controllers\ExtraController::class, 'index'])
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
    
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.manage_extra')){
            abort(403, 'Unauthorized action.');
        }


        $extra = HmsExtra::findOrFail($id);

        $price_per = [
            'per_day' => 'Per Day',
            'per_booking' => 'Per Booking',
            'per_person' => 'Per Person',
            'per_day_per_person' => 'Per Day / Per Person',
        ];

        return view('hms::extras.edit',compact('price_per', 'extra'));
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
     
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.manage_extra')){
            abort(403, 'Unauthorized action.');
        }

        $extra = HmsExtra::findOrFail($id);
      
        try {
            $input =  $request->except(['_token']);
            $extra = $extra->update($input);
            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Hms\Http\Controllers\ExtraController::class, 'index'])
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


        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.manage_extra')){
            abort(403, 'Unauthorized action.');
        }

        try {

            HmsExtra::where('id', $id)->delete();

            $output = ['success' => 1, 'msg' => __('lang_v1.success')];
            return redirect()
                ->action([\Modules\Hms\Http\Controllers\ExtraController::class, 'index'])
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
