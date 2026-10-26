<?php

namespace Modules\Hms\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Entities\HmsRoomUnavailable;
use App\Utils\Util;
use Yajra\DataTables\Facades\DataTables;
use App\Utils\ModuleUtil;

class UnavailableController extends Controller
{
    protected $commonUtil;
    protected $notificationUtil;
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

        if(!auth()->user()->can( 'hms.manage_unavailable')){
            abort(403, 'Unauthorized action.');
        }


        if (request()->ajax()) {
            $unavailables = HmsRoomUnavailable::
                    leftjoin('hms_rooms as room', 'room.id', '=', 'hms_room_unavailables.hms_rooms_id')
                    ->leftjoin('hms_room_types as type', 'type.id', '=', 'room.hms_room_type_id')
                    ->where('type.business_id', $business_id)
                    ->select(['hms_room_unavailables.id as id', 'hms_room_unavailables.date_from','hms_room_unavailables.date_to','hms_room_unavailables.unavailable_type','hms_room_unavailables.hms_rooms_id','hms_room_unavailables.created_at as created_at','type.type as type', 'room.room_number as room_number']);
            return Datatables::of($unavailables)
                ->editColumn('created_at', '{{@format_datetime($created_at)}}')

                ->editColumn('room_number', function($row){
                    return $row->type .' - '.$row->room_number;
                })
                ->editColumn('unavailable_type', function($row){
                    return str_replace('_',' ', $row->unavailable_type);
                })
                ->addColumn('action', function ($row) {
                    $html = '<a type="button" class="btn btn-primary btn-xs btn-modal-unavailable" href="' . action([\Modules\Hms\Http\Controllers\UnavailableController::class, 'edit'], ['unavailable' => $row->id]) . '">'
                        . __('hms::lang.edit_unavailable') . '</a>';
                    $html .= ' <a href="' . route('delete_unavailable', $row->id) . '"
                    class="btn btn-danger btn-xs delete_unavailable_confirmation">' . __('messages.delete') . '</a>';

                    return $html;
                })
                ->editColumn('date_from', '{{@format_date($date_from)}}')
                ->editColumn('date_to', '{{@format_date($date_from)}}')

                ->rawColumns(['created_at', 'action', 'room_number', 'date_from', 'date_to'])
                ->make(true);
        }

        return view('hms::unavailables.index');
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

        if(!auth()->user()->can( 'hms.manage_unavailable')){
            abort(403, 'Unauthorized action.');
        }


        $rooms = HmsRoom::leftjoin('hms_room_types as type', 'type.id', '=', 'hms_rooms.hms_room_type_id')
                            ->where('type.business_id', $business_id)
                            ->selectRaw("CONCAT( type.type, ' - ', hms_rooms.room_number) AS room_description, hms_rooms.id")
                            ->pluck('room_description', 'hms_rooms.id')->toArray();
    
        $types = [
            'unavailable' => 'Unavailable',
            'stop_from_web' => 'Stop from web',
            'external_booking' => 'External Booking',
        ];

        return view('hms::unavailables.create', compact('rooms', 'types'));
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

        if(!auth()->user()->can( 'hms.manage_unavailable')){
            abort(403, 'Unauthorized action.');
        }


        try{

            foreach($request->post('rooms') as $room){
                HmsRoomUnavailable::create([
                    'hms_rooms_id' => $room, 
                    'date_from' =>  $this->commonUtil->uf_date($request->post('date_from')),
                    'date_to' =>  $this->commonUtil->uf_date($request->post('date_to')),
                    'unavailable_type' => $request->post('unavailable_type'),
                ]);
            }

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Hms\Http\Controllers\UnavailableController::class, 'index'])
                ->with('status', $output);

        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output);
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
        if(!auth()->user()->can( 'hms.manage_unavailable')){
            abort(403, 'Unauthorized action.');
        }


        $unavailable = HmsRoomUnavailable::findOrFail($id);


        $rooms = HmsRoom::leftjoin('hms_room_types as type', 'type.id', '=', 'hms_rooms.hms_room_type_id')
                            ->where('type.business_id', $business_id)
                            ->selectRaw("CONCAT( type.type, ' - ', hms_rooms.room_number) AS room_description, hms_rooms.id")
                            ->pluck('room_description', 'hms_rooms.id')->toArray();
    
        $types = [
            'unavailable' => 'Unavailable',
            'stop_from_web' => 'Stop from web',
            'external_booking' => 'External Booking',
        ];

        return view('hms::unavailables.edit', compact('rooms', 'types', 'unavailable'));
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

        if(!auth()->user()->can( 'hms.manage_unavailable')){
            abort(403, 'Unauthorized action.');
        }


        $unavailable = HmsRoomUnavailable::findOrFail($id);
        try{

            $unavailable->hms_rooms_id = $request->post('room_id');
            $unavailable->date_from = $this->commonUtil->uf_date($request->post('date_from'));
            $unavailable->date_to = $this->commonUtil->uf_date($request->post('date_to'));
            $unavailable->unavailable_type = $request->post('unavailable_type');
            $unavailable->update();
    
            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Hms\Http\Controllers\UnavailableController::class, 'index'])
                ->with('status', $output);

        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output);
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

        if(!auth()->user()->can( 'hms.manage_unavailable')){
            abort(403, 'Unauthorized action.');
        }


        try {

            HmsRoomUnavailable::where('id', $id)->delete();

            $output = ['success' => 1, 'msg' => __('lang_v1.success')];
            return redirect()
                ->action([\Modules\Hms\Http\Controllers\UnavailableController::class, 'index'])
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
