<?php

namespace Modules\Crm\Http\Controllers;

use App\Category;
use App\Contact;
use App\User;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Crm\Entities\CrmCallLog;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\Schedule;
use Modules\Crm\Utils\CrmUtil;

class CrmDashboardController extends Controller
{
    protected $crmUtil;

    /**
     * Constructor
     *
     * @param  Util  $commonUtil
     * @return void
     */
    public function __construct(CrmUtil $crmUtil)
    {
        $this->crmUtil = $crmUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        $contacts = Contact::where('business_id', $business_id)
                    ->Active()
                    ->get();

        $customers = $contacts->whereIn('type', ['customer', 'both']);

        $leads = $contacts->where('type', 'lead');

        $total_customers = $customers->count();

        $total_leads = $leads->count();
        $sources = Category::where('business_id', $business_id)
                                ->where('category_type', 'source')
                                ->get();
        $total_sources = $sources->count();

        $life_stages = Category::where('business_id', $business_id)
                                ->where('category_type', 'life_stage')
                                ->get();

        $total_life_stage = $life_stages->count();
        $leads_by_life_stage = $leads->groupBy('crm_life_stage');

        $contacts_count_by_source = CrmContact::getContactsCountBySourceOfGivenTyps($business_id);

        $leads_count_by_source = CrmContact::getContactsCountBySourceOfGivenTyps($business_id, ['lead']);

        $customers_count_by_source = CrmContact::getContactsCountBySourceOfGivenTyps($business_id, ['customer', 'both']);

        $todays_birthdays = array_merge($this->getBirthdays($customers)['todays_birthdays'], $this->getBirthdays($leads)['todays_birthdays']);

        $upcoming_birthdays = array_merge($this->getBirthdays($customers)['upcoming_birthdays'], $this->getBirthdays($leads)['upcoming_birthdays']);

        $my_follow_ups = $this->myFollowUps();

        $my_follow_ups_arr = [];
        foreach ($my_follow_ups as $follow_up) {
            if (! empty($follow_up->status)) {
                $my_follow_ups_arr[$follow_up->status] = $follow_up->total_follow_ups;
            } else {
                $my_follow_ups_arr['__other'] = $follow_up->total_follow_ups;
            }
        }

        $statuses = Schedule::statusDropdown();

        $my_leads = $this->myLeads();

        $my_conversion = $this->myConversion();

        $todays_followups = $this->todaysFollowUp();

        $my_call_logs = [];
        if (config('constants.enable_crm_call_log')) {
            $my_call_logs = $this->getMyCallLogs();
        }

        $followup_category = Category::forDropdown($business_id, 'followup_category');

        $is_admin = $this->crmUtil->is_admin(auth()->user());

        return view('crm::crm_dashboard.index')->with(compact('total_customers', 'total_leads', 'total_sources', 'total_life_stage', 'leads_by_life_stage', 'sources', 'life_stages', 'todays_birthdays', 'upcoming_birthdays', 'leads_count_by_source', 'contacts_count_by_source', 'customers_count_by_source', 'my_follow_ups_arr', 'statuses', 'my_leads', 'my_conversion', 'todays_followups', 'my_call_logs', 'followup_category', 'is_admin'));
    }

    /**
     * Function to fetch all the followups of the logged in user
     */
    private function myFollowUps()
    {
        $my_follow_ups = User::user()
                    ->where('users.id', auth()->user()->id)
                    ->join('crm_schedule_users as su', 'su.user_id', '=', 'users.id')
                    ->join('crm_schedules as follow_ups', 'follow_ups.id', '=', 'su.schedule_id')
                    ->select(
                        'follow_ups.status',
                        DB::raw('COUNT(su.id) as total_follow_ups')
                    )->groupBy('follow_ups.status')->get();

        return $my_follow_ups;
    }

    /**
     * Function to fetch call log statistic of the logged in user
     */
    private function getMyCallLogs()
    {
        $business_id = request()->session()->get('user.business_id');

        $today = \Carbon::now()->format('Y-m-d');
        $yesterday = \Carbon::yesterday()->format('Y-m-d');
        $first_day_of_month = \Carbon::now()->startOfMonth()->format('Y-m-d');

        $my_call_logs = CrmCallLog::where('business_id', $business_id)
                ->where('created_by', auth()->user()->id)
                ->whereDate('start_time', '>=', $first_day_of_month)
                ->select(
                    DB::raw("SUM(IF(DATE(start_time)='{$today}', 1, 0)) as calls_today"),
                    DB::raw("SUM(IF(DATE(start_time)='{$yesterday}', 1, 0)) as calls_yesterday"),
                    DB::raw('COUNT(id) as calls_this_month')
                )->first();

        return $my_call_logs;
    }

    /**
     * Function to fetch all the followups of the logged in user
     */
    private function todaysFollowUp()
    {
        $todays_followups = Schedule::whereHas('users', function ($q) {
            $q->where('user_id', auth()->user()->id);
        })
                    ->whereIn('status', ['open', 'scheduled'])
                    ->whereDate('start_datetime', \Carbon::now()->format('Y-m-d'))
                    ->count();

        return $todays_followups;
    }

    /**
     * Function to count all the leads of the logged in user
     */
    private function myLeads()
    {
        $business_id = request()->session()->get('user.business_id');

        $total_leads = CrmContact::where('contacts.business_id', $business_id)
                        ->where('contacts.type', 'lead')
                        ->whereHas('leadUsers', function ($q) {
                            $q->where('user_id', auth()->user()->id);
                        })->count();

        return $total_leads;
    }

    /**
     * Function to count all the leads to customer conversion of the logged in user
     */
    private function myConversion()
    {
        $count = Contact::where('converted_by', auth()->user()->id)
                        ->count();

        return $count;
    }

    private function getBirthdays($contacts)
    {
        $todays_birthdays = [];
        $upcoming_birthdays = [];

        $today = \Carbon::now();
        $thirty_days_from_today = \Carbon::now()->addDays(30)->format('Y-m-d');
        foreach ($contacts as $contact) {
            if (empty($contact->dob)) {
                continue;
            }

            $dob = \Carbon::parse($contact->dob);
            $dob_md = $dob->format('m-d');

            $next_birthday = \Carbon::parse($today->format('Y').'-'.$dob_md);
            if ($next_birthday->lt($today)) {
                $next_birthday->addYear();
            }

            if ($today->format('m-d') == $dob->format('m-d')) {
                $todays_birthdays[] = ['id' => $contact->id, 'name' => $contact->name];
            } elseif ($next_birthday->between($today->format('Y-m-d'), $thirty_days_from_today)) {
                $upcoming_birthdays[] = ['name' => $contact->name, 'id' => $contact->id, 'dob' => $dob->format('m-d')];
            }
        }

        return [
            'todays_birthdays' => $todays_birthdays,
            'upcoming_birthdays' => $upcoming_birthdays,
        ];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('crm::create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        return view('crm::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        return view('crm::edit');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }
}
