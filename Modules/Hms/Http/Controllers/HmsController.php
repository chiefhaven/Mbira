<?php

namespace Modules\Hms\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Transaction;
use Illuminate\Support\Carbon;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Entities\HmsRoomType;
use Modules\Hms\Entities\HmsBookingLine;
use App\Charts\CommonChart;
use App\Utils\ModuleUtil;
use Modules\Hms\Entities\HmsTransactionClass;


class HmsController extends Controller
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

        
            $room_count = (object) [
                'booked_rooms' => 0,
                'pending_rooms' => 0,
                'unbooked_rooms' => 0,
            ];

          
        
            $today = Carbon::now();
            $tonight = Carbon::tomorrow()->startOfDay(); // Assuming "tonight" means until the start of the next day

            $room_count->booked_rooms = $this->room_count('confirmed');
            $room_count->pending_rooms = $this->room_count('pending');

            $booked_room_ids = Transaction::join('hms_booking_lines', 'transactions.id', '=', 'hms_booking_lines.transaction_id')
            ->where('transactions.type', 'hms_booking')
            ->where('transactions.status', 'confirmed')
            ->where('transactions.business_id', $business_id)
            ->whereDate('transactions.hms_booking_arrival_date_time', '<=', $today)
            ->whereDate('transactions.hms_booking_departure_date_time', '>=', $today)
            ->pluck('hms_booking_lines.hms_room_id')
            ->toArray();

            $room_count->unbooked_rooms = HmsRoom::whereNotIn('hms_rooms.id', $booked_room_ids)
                ->leftJoin('hms_room_types', 'hms_rooms.hms_room_type_id', '=', 'hms_room_types.id')
                ->where('hms_room_types.business_id', $business_id)
                ->count(); 

            // Count unbooked rooms by type
            $unbooked_rooms_by_type = HmsRoomType::leftJoin('hms_rooms', 'hms_room_types.id', '=', 'hms_rooms.hms_room_type_id')
            ->where('hms_room_types.business_id', $business_id)
            ->whereNotIn('hms_rooms.id', $booked_room_ids)
            ->select('hms_room_types.type as room_type')
            ->selectRaw('COUNT(hms_rooms.id) as unbooked_count')
            ->groupBy('hms_room_types.id')
            ->get();
            
            // guest count stay tonight
            $guest_count_tonight = HmsBookingLine::join('transactions', 'hms_booking_lines.transaction_id', '=', 'transactions.id')
            ->where('transactions.type', 'hms_booking')
            ->where('transactions.status', 'confirmed')
            ->where('transactions.business_id', $business_id)
            ->whereDate('transactions.hms_booking_arrival_date_time', '<=', $today)
            ->whereDate('transactions.hms_booking_departure_date_time', '>=', $tonight)
            ->selectRaw('SUM(hms_booking_lines.adults) as adult_guests, SUM(hms_booking_lines.childrens) as child_guests')
            ->get();

            // guest count leave today
            $leave_today = $this->leave_arrive_count_today('hms_booking_departure_date_time');
            $arrive_today = $this->leave_arrive_count_today('hms_booking_arrival_date_time');

            $today_arrivales =  $this->get_today_arrival_departure_booking('hms_booking_arrival_date_time');
            $today_departure =  $this->get_today_arrival_departure_booking('hms_booking_departure_date_time');


            $latest_bookig = HmsTransactionClass::where('transactions.business_id', $business_id)
            ->with(['contact', 'hms_booking_lines'])
            ->where('transactions.type', 'hms_booking')
            ->orderBy('transactions.created_at', 'desc')
            ->take(5)
            ->get();
            

        //Chart for booking upcomming 6 days
        $labels = [];
        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $date = \Carbon::now()->addDays($i)->format('Y-m-d');
            $dates[] = $date;

            $labels[] = date('j M Y', strtotime($date));

            $total_booking_on_date = $this->get_booking_count($date);

            if (! empty($total_booking_on_date)) {
                $all_booking_values[] = (float) $total_booking_on_date;
            } else {
                $all_booking_values[] = 0;
            }
        }
        $booking_chart = new CommonChart;

        $booking_chart->labels($labels)->options($this->__chartOptions(__(
            'hms::lang.bookings',
            )));
        
        $booking_chart->dataset(__('hms::lang.bookings'), 'line', $all_booking_values);


         //Chart for booking past 6 days
         $labels = [];
         $dates = [];
         $all_booking_values = [];
         for ($i = 7; $i >= 1; $i--) {
             $date = \Carbon::now()->subDays($i)->format('Y-m-d');
             $dates[] = $date;
 
             $labels[] = date('j M Y', strtotime($date));
 
             $total_booking_on_date = $this->get_booking_count($date);
 
             if (! empty($total_booking_on_date)) {
                 $all_booking_values[] = (float) $total_booking_on_date;
             } else {
                 $all_booking_values[] = 0;
             }
         }

         $past_booking_chart = new CommonChart;
 
         $past_booking_chart->labels($labels)->options($this->__chartOptions(__(
            'hms::lang.bookings',
            )));
         
         $past_booking_chart->dataset(__('hms::lang.bookings'), 'line', $all_booking_values);


        return view('hms::dashboard.index', compact('booking_chart','past_booking_chart', 'room_count', 'unbooked_rooms_by_type', 'guest_count_tonight', 'arrive_today', 'leave_today', 'today_arrivales', 'today_departure', 'latest_bookig'));
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('hms::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        //
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
        return view('hms::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        //
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

    public function room_count($status){
        $today = Carbon::now();
        $business_id = request()->session()->get('user.business_id');

        return Transaction::join('hms_booking_lines', 'transactions.id', '=', 'hms_booking_lines.transaction_id')
            ->where('transactions.type', 'hms_booking')
            ->where('transactions.business_id', $business_id)
            ->whereDate('transactions.hms_booking_arrival_date_time', '<=', $today)
            ->whereDate('transactions.hms_booking_departure_date_time', '>=', $today)
            ->where('status', $status)
            ->count('hms_booking_lines.hms_room_id');
    }

    public function leave_arrive_count_today($type){
            $today = Carbon::now();
            $business_id = request()->session()->get('user.business_id');

            return HmsBookingLine::join('transactions', 'hms_booking_lines.transaction_id', '=', 'transactions.id')
            ->where('transactions.status', 'confirmed')
            ->where('transactions.business_id', $business_id)
            ->where('transactions.type', 'hms_booking')
            ->whereDate('transactions.'.$type.'', '=', $today)
            ->selectRaw('SUM(hms_booking_lines.adults) as adult_guests, SUM(hms_booking_lines.childrens) as child_guests')
            ->get();
    }

    public function get_today_arrival_departure_booking($type){
            $today = Carbon::now();
            $business_id = request()->session()->get('user.business_id');

            return HmsTransactionClass::where('transactions.business_id', $business_id)
            ->where('transactions.status', 'confirmed')
            ->with(['contact', 'hms_booking_lines'])
            ->whereDate('transactions.'.$type.'', '=', $today)
            ->where('transactions.type', 'hms_booking')
            ->get();
    }

    private function __chartOptions($title)
    {
        return [
            'yAxis' => [
                'title' => [
                    'text' => $title,
                ],
            ],
            'legend' => [
                'align' => 'right',
                'verticalAlign' => 'top',
                'floating' => true,
                'layout' => 'vertical',
                'padding' => 20,
            ],
        ];
    }

    public function get_booking_count($date){
        $business_id = request()->session()->get('user.business_id');

        return Transaction::where('transactions.type', 'hms_booking')
            ->where('transactions.business_id', $business_id)
            ->where('status', 'confirmed')
            ->whereDate('transactions.hms_booking_arrival_date_time', '<=', $date)
            ->whereDate('transactions.hms_booking_departure_date_time', '>=', $date)
            ->count();
    }

}
