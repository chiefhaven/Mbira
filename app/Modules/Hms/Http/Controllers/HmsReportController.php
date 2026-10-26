<?php

namespace Modules\Hms\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Transaction;
use App\Utils\Util;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Hms\Entities\HmsRoomType;
use Modules\Hms\Entities\HmsBookingLine;
use App\Utils\ModuleUtil;
use Modules\Hms\Entities\HmsTransactionClass;


class HmsReportController extends Controller
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
    public function index(Request $request)
    {

        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->date_to && $request->date_from) {

            $business_id = request()->session()->get('user.business_id');

            $date_to= $this->commonUtil->uf_date($request->date_to) . ' 00:00:00';
            $date_from = $this->commonUtil->uf_date($request->date_from) . ' 23:59:59';

            // all booking report
            $total_booking = $this->count_booking_by_status($date_to, $date_from, ['confirmed', 'cancelled', 'pending']);
            // all confirmed booking 
            $total_confirmed_booking =  $this->count_booking_by_status($date_to, $date_from, ['confirmed']);

            //all cancelled booking\\
            $total_cancelled_booking =  $this->count_booking_by_status($date_to, $date_from, ['cancelled']); 

            // all pending booking
            $total_pending_booking =  $this->count_booking_by_status($date_to, $date_from, ['pending']); 
  
            // booking count by room
            $transactions_with_room = Transaction::where('status', 'confirmed')
                ->where('type', 'hms_booking')
                ->where('transactions.business_id', $business_id)
                ->select('transactions.id', DB::raw('COUNT(hms_booking_lines.id) as line_count'))
                ->leftJoin('hms_booking_lines', 'transactions.id', '=', 'hms_booking_lines.transaction_id')
                ->groupBy('transactions.id')
                ->whereBetween('hms_booking_arrival_date_time', [$date_to, $date_from])
                ->get();

            $rooms_by_booking_count = $this->count_booking_by_room($transactions_with_room);
            
            $count_booking_by_night = Transaction::where('status', 'confirmed')
                ->where('transactions.business_id', $business_id)
                ->where('type', 'hms_booking')
                ->whereBetween('hms_booking_arrival_date_time', [$date_to, $date_from])
                ->get();

            $count_by_night = $this->count_booking_by_night($count_booking_by_night);

            // booking count by night 
            $transactions_adult_counts = Transaction::where('status', 'confirmed')
            ->where('transactions.business_id', $business_id)
            ->select('transactions.id')
            ->addSelect(DB::raw('SUM(hms_booking_lines.adults) as total_adults'))
            ->leftJoin('hms_booking_lines', 'transactions.id', '=', 'hms_booking_lines.transaction_id')
            ->groupBy('transactions.id')
            ->whereBetween('hms_booking_arrival_date_time', [$date_to, $date_from])
            ->get();

            $count_by_adults = $this->transactions_adult_counts($transactions_adult_counts);

            // count by room type 
            $pending_room_types = $this->room_type_count('pending', $date_to, $date_from);
            $cancelled_room_types = $this->room_type_count('cancelled', $date_to, $date_from);  
            $confirmed_room_types = $this->room_type_count('confirmed', $date_to, $date_from);
            
            $all_room_types = HmsRoomType::select(
                'hms_room_types.type',
                DB::raw('(SELECT COUNT(DISTINCT transactions.id) FROM hms_booking_lines
                            LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                            WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                                AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ? ) as transactions_count'),
                DB::raw('(SELECT SUM(hms_booking_lines.total_price) FROM hms_booking_lines
                            LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                            WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                                AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ? ) as total_price'),
                DB::raw('(SELECT SUM( DATEDIFF(transactions.hms_booking_departure_date_time, transactions.hms_booking_arrival_date_time)) FROM hms_booking_lines
                            LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                            WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                                AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ? ) as total_days'),
                DB::raw('(SELECT SUM(hms_booking_lines.adults + hms_booking_lines.childrens) FROM hms_booking_lines
                            LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                            WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                                AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ?) as no_of_guest'),
            )
            ->setBindings([$date_to, $date_from, $date_to, $date_from, $date_to, $date_from, $date_to, $date_from])
            ->where('hms_room_types.business_id', $business_id)
            ->get();

            return view('hms::report.index', compact('total_booking', 'total_confirmed_booking', 'total_cancelled_booking', 'total_pending_booking', 'rooms_by_booking_count', 'count_by_night', 'count_by_adults', 'all_room_types', 'confirmed_room_types', 'cancelled_room_types', 'pending_room_types'));
        }
        return view('hms::report.index');
    }


    public function count_booking_by_status($date_to, $date_from, $status){ 
        // all confirmed booking report
        $business_id = session()->get('user.business_id');

        $bookings = HmsTransactionClass::where('transactions.business_id', $business_id)->whereBetween('hms_booking_arrival_date_time', [$date_to, $date_from])->where('type', 'hms_booking')->whereIn('status', $status)->get();

        $count = (object) [
            'total_guest' => 0,
            'total_adult_guest' => 0, 
            'total_childs_guest' => 0,
            'total_amount' => 0,
            'total_nights' => 0,
            'total_count' => 0,
        ];

        $count->total_count = count($bookings);

        foreach ($bookings as $booking) {
            $count->total_guest = $count->total_guest + $booking->hms_booking_lines->sum('childrens') + $booking->hms_booking_lines->sum('adults');

            $count->total_adult_guest = $count->total_adult_guest + $booking->hms_booking_lines->sum('adults');

            $count->total_childs_guest = $count->total_childs_guest + $booking->hms_booking_lines->sum('childrens');

            $count->total_amount += $booking->final_total;  

            $start = Carbon::parse($booking->hms_booking_arrival_date_time);
            $end = Carbon::parse($booking->hms_booking_departure_date_time);
            // Calculate the difference in days
            $difference_in_days = $end->diffInDays($start);

            $count->total_nights += $difference_in_days;  
        }

        return $count;
    }

    public function room_type_count($status, $date_to, $date_from){
        $business_id = session()->get('user.business_id');
        
        return HmsRoomType::select(
            'hms_room_types.type',
            DB::raw('(SELECT COUNT(DISTINCT transactions.id) FROM hms_booking_lines
                       LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                       WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                         AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ?
                         AND transactions.status = "'.$status.'") as transactions_count'),
            DB::raw('(SELECT SUM(hms_booking_lines.total_price) FROM hms_booking_lines
                       LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                       WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                         AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ?
                         AND transactions.status = "'.$status.'") as total_price'),
            DB::raw('(SELECT SUM( DATEDIFF(transactions.hms_booking_departure_date_time, transactions.hms_booking_arrival_date_time)) FROM hms_booking_lines
                       LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                       WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                         AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ?
                         AND transactions.status = "'.$status.'") as total_days'),
            DB::raw('(SELECT SUM(hms_booking_lines.adults + hms_booking_lines.childrens) FROM hms_booking_lines
                       LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                       WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                         AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ?
                         AND transactions.status = "'.$status.'") as no_of_guest'),
        )
        ->setBindings([$date_to, $date_from, $date_to, $date_from, $date_to, $date_from, $date_to, $date_from])
        ->where('hms_room_types.business_id', $business_id)
        ->get();
    }

    public function transactions_adult_counts($transactions){
        $count = (object) [
            'one_adult_count' => 0,
            'two_adults_count' => 0,
            'three_adults_count' => 0,
            'four_adults_count' => 0,
            'five_adults_count' => 0,
            'six_adults_count' => 0,
            'more_than_six_adults_count' => 0,
        ];
        
        foreach ($transactions as $transaction) {
            $totalAdults = $transaction->total_adults;
            switch ($totalAdults) {
                case 1:
                    $count->one_adult_count++;
                    break;
                case 2:
                    $count->two_adults_count++;
                    break;
                case 3:
                    $count->three_adults_count++;
                    break;
                case 4:
                    $count->four_adults_count++;
                    break;
                case 5:
                    $count->five_adults_count++;
                    break;
                case 6:
                    $count->six_adults_count++;
                    break;
                default:
                    $count->more_than_six_adults_count++;
                    break;
            }
        }

        return $count;
    }

    public function count_booking_by_night($bookings){
        $counts = (object) [
            'one_night_count' => 0,
            'two_night_count' => 0,
            'three_night_count' => 0,
            'four_night_count' => 0,
            'five_night_count' => 0,
            'six_night_count' => 0,
            'more_than_six_night_count' => 0,
        ];
        
        foreach ($bookings as $booking) {
            $start = Carbon::parse($booking->hms_booking_arrival_date_time);
            $end = Carbon::parse($booking->hms_booking_departure_date_time);
            // Calculate the difference in days
            $nights = $end->diffInDays($start);

            // echo $nights;

            switch ($nights) {
                case 0:
                    $counts->one_night_count++;
                    break;
                case 1:
                    $counts->one_night_count++;
                    break;
                case 2:
                    $counts->two_night_count++;
                    break;
                case 3:
                    $counts->three_night_count++;
                    break;
                case 4:
                    $counts->four_night_count++;
                    break;
                case 5:
                    $counts->five_night_count++;
                    break;
                case 6:
                    $counts->six_night_count++;
                    break;
                default:
                    if ($nights > 6) {
                        $counts->more_than_six_night_count++;
                    }
            }
        }  
        
        return $counts;
    }

    public function count_booking_by_room($transactions){
        
        $lineCounts = (object) [
            'one_line_count' => 0,
            'two_lines_count' => 0,
            'more_than_two_lines_count' => 0,
        ];
        
        foreach ($transactions as $transaction) {
            $lineCount = $transaction->line_count;
        
            switch ($lineCount) {
                case 1:
                    $lineCounts->one_line_count++;
                    break;
                case 2:
                    $lineCounts->two_lines_count++;
                    break;
                default:
                    $lineCounts->more_than_two_lines_count++;
                    break;
            }
        }

        return $lineCounts;
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
}
  