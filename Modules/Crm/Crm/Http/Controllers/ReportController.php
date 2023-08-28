<?php

namespace Modules\Crm\Http\Controllers;

use App\Contact;
use App\User;
use App\Utils\Util;
use DB;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Crm\Entities\Schedule;
use Yajra\DataTables\Facades\DataTables;

class ReportController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        $is_admin = $this->commonUtil->is_admin(auth()->user(), $business_id);

        if (! $is_admin) {
            abort(403, 'Unauthorized action.');
        }

        $statuses = Schedule::statusDropdown();

        return view('crm::reports.index')->with(compact('statuses'));
    }

    /**
     * Lists follow ups count assigned to users.
     *
     * @return Response
     */
    public function followUpsByUser()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $statuses = Schedule::statusDropdown();
            $start_date = request()->get('start_date');
            $end_date = request()->get('end_date');
            $followup_category_id = request()->get('followup_category_id');

            $formatted_start_date = $this->commonUtil->format_date($start_date);
            $formatted_end_date = $this->commonUtil->format_date($end_date);

            $query = User::where('users.business_id', $business_id)
                        ->user()
                        ->where('is_cmmsn_agnt', 0)
                        ->join('crm_schedule_users as su', 'su.user_id', '=', 'users.id')
                        ->join('crm_schedules as follow_ups', 'follow_ups.id', '=', 'su.schedule_id')
                        ->select(
                            DB::raw("CONCAT(COALESCE(users.surname, ''), ' ', COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as full_name"),
                            DB::raw('COUNT(su.id) as total_follow_ups'),
                            DB::raw('SUM( IF(follow_ups.status IS NULL AND follow_ups.id IS NOT NULL, 1, 0) ) as count_nulled'), 'users.id as user_id')
                        ->groupBy('users.id');

            //category filter
            if (! empty($followup_category_id)) {
                $query->where('followup_category_id', '=', $followup_category_id);
            }

            //date check.
            if (! empty($start_date) && ! empty($end_date)) {
                $query->whereDate('follow_ups.start_datetime', '>=', "$start_date")
                    ->whereDate('follow_ups.start_datetime', '<=', "$end_date");
            }

            foreach ($statuses as $key => $value) {
                $query->addSelect(DB::raw("SUM(IF(follow_ups.status='$key', 1, 0)) as count_$key"));
            }

            return Datatables::of($query)
                    ->filterColumn('full_name', function ($query, $keyword) {
                        $query->whereRaw("CONCAT(COALESCE(users.surname, ''), ' ', COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) like ?", ["%{$keyword}%"]);
                    })
                    ->editColumn('count_scheduled', function ($row) use ($formatted_start_date, $formatted_end_date, $followup_category_id) {
                        $html = $row->count_scheduled.'<br/>';

                        $html .= '<a target="_blank" href="'.action([\Modules\Crm\Http\Controllers\ScheduleController::class, 'index'])."?status=scheduled&start_date=$formatted_start_date&end_date=$formatted_end_date&assigned_to=$row->user_id&followup_category_id=$followup_category_id".'" >'.__('crm::lang.view').'</a>';

                        return $html;
                    })
                    ->editColumn('count_open', function ($row) use ($formatted_start_date, $formatted_end_date, $followup_category_id) {
                        $html = $row->count_open.'<br/>';

                        $html .= '<a target="_blank" href="'.action([\Modules\Crm\Http\Controllers\ScheduleController::class, 'index'])."?status=open&start_date=$formatted_start_date&end_date=$formatted_end_date&assigned_to=$row->user_id&followup_category_id=$followup_category_id".'" >'.__('crm::lang.view').'</a>';

                        return $html;
                    })
                    ->editColumn('count_cancelled', function ($row) use ($formatted_start_date, $formatted_end_date, $followup_category_id) {
                        $html = $row->count_cancelled.'<br/>';

                        $html .= '<a target="_blank" href="'.action([\Modules\Crm\Http\Controllers\ScheduleController::class, 'index'])."?status=cancelled&start_date=$formatted_start_date&end_date=$formatted_end_date&assigned_to=$row->user_id&followup_category_id=$followup_category_id".'" >'.__('crm::lang.view').'</a>';

                        return $html;
                    })
                    ->editColumn('count_completed', function ($row) use ($formatted_start_date, $formatted_end_date, $followup_category_id) {
                        $html = $row->count_completed.'<br/>';

                        $html .= '<a target="_blank" href="'.action([\Modules\Crm\Http\Controllers\ScheduleController::class, 'index'])."?status=completed&start_date=$formatted_start_date&end_date=$formatted_end_date&assigned_to=$row->user_id&followup_category_id=$followup_category_id".'" >'.__('crm::lang.view').'</a>';

                        return $html;
                    })
                    ->editColumn('count_nulled', function ($row) use ($formatted_start_date, $formatted_end_date, $followup_category_id) {
                        $html = $row->count_nulled.'<br/>';

                        $html .= '<a target="_blank" href="'.action([\Modules\Crm\Http\Controllers\ScheduleController::class, 'index'])."?status=none&start_date=$formatted_start_date&end_date=$formatted_end_date&assigned_to=$row->user_id&followup_category_id=$followup_category_id".'" >'.__('crm::lang.view').'</a>';

                        return $html;
                    })
                    ->editColumn('total_follow_ups', function ($row) use ($formatted_start_date, $formatted_end_date, $followup_category_id) {
                        $html = $row->total_follow_ups.'<br/>';

                        $html .= '<a target="_blank" href="'.action([\Modules\Crm\Http\Controllers\ScheduleController::class, 'index'])."?start_date=$formatted_start_date&end_date=$formatted_end_date&assigned_to=$row->user_id&followup_category_id=$followup_category_id".'" >'.__('crm::lang.view').'</a>';

                        return $html;
                    })
                    ->rawColumns(['count_scheduled', 'count_open', 'count_cancelled', 'count_completed',
                        'count_nulled', 'total_follow_ups', ])
                    ->make(true);
        }
    }

    /**
     * Lists follow ups count assigned to contacts
     *
     * @return Response
     */
    public function followUpsContact()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $statuses = Schedule::statusDropdown();

            $query = Contact::where('contacts.business_id', $business_id)
                        ->join('crm_schedules as follow_ups', 'follow_ups.contact_id', '=', 'contacts.id')
                        ->select(
                            'contacts.name',
                            'contacts.supplier_business_name',
                            DB::raw('COUNT(follow_ups.id) as total_follow_ups'),
                            DB::raw('SUM( IF(follow_ups.status IS NULL AND follow_ups.id IS NOT NULL, 1, 0) ) as count_nulled')
                        )->groupBy('contacts.id');

            foreach ($statuses as $key => $value) {
                $query->addSelect(DB::raw("SUM(IF(follow_ups.status='$key', 1, 0)) as count_$key"));
            }

            return Datatables::of($query)
                    ->addColumn('contact_name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}} <br> @endif {{$name}}')
                    ->rawColumns(['contact_name'])
                    ->filterColumn('contact_name', function ($query, $keyword) {
                        $query->where(function ($q) use ($keyword) {
                            $q->where('contacts.name', 'like', "%{$keyword}%")
                                ->orWhere('contacts.supplier_business_name', 'like', "%{$keyword}%");
                        });
                    })
                    ->make(true);
        }
    }

    /**
     * Lists leads to customer conversion count
     *
     * @return Response
     */
    public function leadToCustomerConversion()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $query = User::where('users.business_id', $business_id)
                        ->user()
                        ->where('is_cmmsn_agnt', 0)
                        ->join('contacts as c', 'c.converted_by', '=', 'users.id')
                        ->select(
                            DB::raw("CONCAT(COALESCE(users.surname, ''), ' ', COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as full_name"),
                            DB::raw('COUNT(c.id) as total_conversions'),
                            'users.id as DT_RowId'
                        )->groupBy('users.id');

            return Datatables::of($query)
                    ->filterColumn('full_name', function ($query, $keyword) {
                        $query->whereRaw("CONCAT(COALESCE(users.surname, ''), ' ', COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) like ?", ["%{$keyword}%"]);
                    })
                    ->make(true);
        }
    }

    /**
     * Lists leads to customer conversion details by a users
     *
     * @return Response
     */
    public function showLeadToCustomerConversionDetails($user_id)
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $contacts = Contact::where('business_id', $business_id)
                            ->where('converted_by', $user_id)
                            ->orderBy('converted_on', 'desc')
                            ->get();

            return view('crm::reports.leads_to_customer_details')
                    ->with(compact('contacts'));
        }
    }
}
