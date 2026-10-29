<?php

namespace Modules\Hms\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Hms\Entities\HmsRoomType;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Entities\HmsRoomTypePricing;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use App\Media;
use App\Category;
use App\Utils\ModuleUtil;

class RoomController extends Controller
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
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }
        

        if(!auth()->user()->can( 'hms.manage_rooms')){
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $types = HmsRoomType::where('business_id', $business_id);
            return Datatables::of($types)
                ->editColumn('created_at', '{{@format_datetime($created_at)}}')
                ->addColumn('action', function ($row) {
                    $html = '<a type="button" class="btn btn-primary btn-xs " href="' . action([\Modules\Hms\Http\Controllers\RoomController::class, 'edit'], ['room' => $row->id]) . '">'
                        . __('hms::lang.edit_room') . '</a>';
                    $html .= ' <a href="' . action([\Modules\Hms\Http\Controllers\RoomController::class, 'destroy'], [$row->id]) . '"
                    class="btn btn-danger btn-xs delete_room_confirmation">' . __('messages.delete') . '</a>';
                if(auth()->user()->can( 'hms.manage_price')){
                    $html .= ' <a href="' . action([\Modules\Hms\Http\Controllers\RoomController::class, 'pricing']) . '?room_id='. $row->id .'"
                    class="btn btn-info btn-xs ">' . __('hms::lang.pricing') . '</a>';
                }

                return $html;
                })
                ->editColumn('description', '{!! $description !!}') 
                ->rawColumns(['created_at', 'action', 'description'])
                ->make(true);
        }
        return view('hms::rooms.index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {   
        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))  ) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.manage_rooms')){
            abort(403, 'Unauthorized action.');
        }

        $amenities = Category::where('business_id', $business_id)
                            ->where('category_type', 'amenities')
                            ->select(['name', 'id'])->get();
                
        return view('hms::rooms.create', compact('amenities'));
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

        if(!auth()->user()->can( 'hms.manage_rooms')){
            abort(403, 'Unauthorized action.');
        }

        DB::beginTransaction();
        try {
            $input =  $request->except(['_token', 'amenities']);
            $input['created_by'] = auth()->user()->id;
            $input['business_id'] = request()->session()->get('user.business_id');

            $type = HmsRoomType::create($input);

            Media::uploadMedia($type->business_id, $type, $request, 'images');

            $rooms = $input['rooms'] ?? [];
            $amenities = $request->amenities ?? [];

            $type->categories()->sync($amenities);

            $room_lines = [];
            foreach ($rooms as $room) {
                $room_lines[] = new HmsRoom([
                    'room_number' => $room,
                ]);
            }

            $type->rooms()->saveMany($room_lines);
            DB::commit();

            $output = [
                'success' => 1,
                'msg' => __('hms::lang.room_created_succesfully'),
            ];

            if($input['submit_type'] == 'save_and_pricing'){
                return redirect()->action(
                    [\Modules\Hms\Http\Controllers\RoomController::class, 'pricing'],
                    ['room_id' => $type->id]);
            }

            return redirect()
                ->action([\Modules\Hms\Http\Controllers\RoomController::class, 'index'])
                ->with('status', $output);
        } catch (\Exception $e) {
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

        if(!auth()->user()->can( 'hms.manage_rooms')){
            abort(403, 'Unauthorized action.');
        }
        $room_type = HmsRoomType::where('business_id', $business_id)->with(['rooms','media'])->findOrFail($id);

        $existing_amenities = $room_type->categories->map(function ($category) {
            return $category->id;
        })->all();

        $business_id = request()->session()->get('user.business_id');

        $amenities = Category::where('business_id', $business_id)
                            ->where('category_type', 'amenities')
                            ->select(['name', 'id'])->get();

        return view('hms::rooms.edit', compact('room_type', 'amenities', 'existing_amenities'));
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

        if(!auth()->user()->can( 'hms.manage_rooms')){
            abort(403, 'Unauthorized action.');
        }

        $type = HmsRoomType::where('business_id', $business_id)->findOrFail($id);
        DB::beginTransaction();
        try {
            $input =  $request->except(['_token']);

            $type = $type->update($input);

            $rooms = $input['rooms'] ?? [];

            $type= HmsRoomType::find($id);

            $amenities = $request->amenities ?? [];
            $type->categories()->sync($amenities);

            Media::uploadMedia($type->business_id, $type, $request, 'images');

            $existing_room_ids = $type->rooms->pluck('id')->toArray();

            foreach ($rooms as $room) {
                if (isset($room['id']) && in_array($room['id'], $existing_room_ids)) {
                    // Update the existing room if it exists in the database
                    HmsRoom::where('id', $room['id'])->update(['room_number' => $room['name']]);
                } else {
                    // Create a new room if it doesn't have an ID or doesn't exist in the database
                    $type->rooms()->create(['room_number' => $room['name']]);
                }
            }

            // Delete rooms that are not in the updated list
            $rooms_to_delete = array_diff($existing_room_ids, array_column($rooms, 'id'));

            HmsRoom::whereIn('id', $rooms_to_delete)->delete();


            DB::commit();

            $output = [
                'success' => 1,
                'msg' => __('hms::lang.room_updated_succesfully'),
            ];

            if($input['submit_type'] == 'save_and_pricing'){
                return redirect()->action(
                    [\Modules\Hms\Http\Controllers\RoomController::class, 'pricing'],
                    ['room_id' => $type->id]);
            }

            return redirect()
                ->action([\Modules\Hms\Http\Controllers\RoomController::class, 'index'])
                ->with('status', $output);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }
    }

    // pricing index page retune
    public function pricing(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.manage_price')){
            abort(403, 'Unauthorized action.');
        }

       
        $id = $request->input('room_id');

        $room_type = [];
        $default_pricing = [];
        $spacial_pricing = [];

        if(!empty($id)){
            $room_type = HmsRoomType::where('business_id', $business_id)->findOrFail($id);

            $default_pricing = HmsRoomTypePricing::where('hms_room_type_id', $id)->whereNull('adults')->whereNull('childrens')->first();

            $spacial_pricing = HmsRoomTypePricing::where('hms_room_type_id', $id)->whereNotNull('adults')->whereNotNull('childrens')->get();
        }

        $types = HmsRoomType::where('business_id', $business_id)->pluck('type', 'id')->toArray();
        
        return view('hms::rooms.pricing', compact('room_type', 'default_pricing', 'spacial_pricing', 'types'));
    }

    // get htlm for pricing add more pricing
    public function get_spacial_pricing_html(Request $request)
    {
        $currentIndex = $request->input('currentIndex');
        $id = $request->input('id');
        $room_type = HmsRoomType::findOrFail($id);
        return view('hms::rooms.spacial_pricing', compact('currentIndex', 'room_type'));
    }

    // store or update  pricing 
    public function post_pricing(Request $request)
    {
        $input =  $request->except(['_token']);
        $type = HmsRoomType::findOrFail($input['type_id']);

        try {
            DB::beginTransaction();
            $existing_ids = $type->pricings->pluck('id')->toArray();

            $new_created_id = [];

            foreach ($input['pricing'] as $pricing) {
                if (isset($pricing['id']) && in_array($pricing['id'], $existing_ids)) {
                    // Update the existing pricing if it exists in the database
                        HmsRoomTypePricing::where('id', $pricing['id'])->update(
                        [
                            'season_type' => $input['season_type'],
                            'adults' => $pricing['adults'] ?? null,
                            'childrens' => $pricing['childrens'] ?? null,
                            'default_price_per_night' => $pricing['default_price'] ?? null,
                            'price_monday' => $pricing['monday'] ?? null,
                            'price_tuesday' => $pricing['tuesday'] ?? null,
                            'price_wednesday' => $pricing['wednesday'] ?? null,
                            'price_thursday' => $pricing['thursday'] ?? null,
                            'price_friday' => $pricing['friday'] ?? null,
                            'price_saturday' => $pricing['saturday'] ?? null,
                            'price_sunday' => $pricing['sunday'] ?? null,
                        ]
                    );

                } else {
                    // Create a pricing if it doesn't have an ID or doesn't exist in the database
                    $type_pricing = $type->pricings()->create(
                                [
                                    'season_type' => $input['season_type'],
                                    'adults' => $pricing['adults'] ?? null,
                                    'childrens' => $pricing['childrens'] ?? null,
                                    'default_price_per_night' => $pricing['default_price'] ?? null,
                                    'price_monday' => $pricing['monday'] ?? null,
                                    'price_tuesday' => $pricing['tuesday'] ?? null,
                                    'price_wednesday' => $pricing['wednesday'] ?? null,
                                    'price_thursday' => $pricing['thursday'] ?? null,
                                    'price_friday' => $pricing['friday'] ?? null,
                                    'price_saturday' => $pricing['saturday'] ?? null,
                                    'price_sunday' => $pricing['sunday'] ?? null,
                                ]
                    );
                    $new_created_id [] = $type_pricing->id;
                }
            }


            // Delete pricing that are not in the updated list
            $pricing_to_delete = array_diff($existing_ids, array_column($input['pricing'], 'id'));
            HmsRoomTypePricing::whereIn('id', $pricing_to_delete)->delete();

            DB::commit();

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                    ->action([\Modules\Hms\Http\Controllers\RoomController::class, 'pricing'], ['room_id' => $input['type_id']])
                    ->with('status', $output);
        } catch (\Exception $e) {
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
       $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.manage_rooms')){
            abort(403, 'Unauthorized action.');
        }

        try {

            // Soft delete the HmsRoomType record
            $type = HmsRoomType::find($id);
            $type->delete();

            // Soft delete the associated HmsRoom records
            HmsRoom::where('hms_room_type_id', $id)->delete();

            $output = ['success' => 1, 'msg' => __('lang_v1.success')];
            return redirect()
                ->action([\Modules\Hms\Http\Controllers\RoomController::class, 'index'])
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
