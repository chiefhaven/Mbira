<?php

namespace Modules\Crm\Http\Controllers;

use App\Contact;
use App\Http\Controllers\Controller;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Modules\Crm\Entities\CrmCallLog;
use Modules\Crm\Entities\Schedule;
use Modules\Crm\Entities\ScheduleLog;

class ScheduleLogController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param CommonUtil
     * @return void
     */
    public function __construct(Util $commonUtil, ModuleUtil $moduleUtil)
    {
        $this->commonUtil = $commonUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $schedule_id = $request->get('schedule_id');
        $modal_content = $request->get('modal_content') == 'false' ? false : true;

        if (request()->ajax()) {
            try {
                $schedule = Schedule::with(['invoices', 'invoices.payment_lines'])
                        ->where('business_id', $business_id)
                        ->findOrFail($schedule_id);

                $schedule_logs = ScheduleLog::with('createdBy')
                                ->where('schedule_id', $schedule_id)
                                ->latest()->get();
                //->simplePaginate(10);

                //if call log is enabled
                $call_logs = [];
                if (config('constants.enable_crm_call_log')) {
                    $call_logs = CrmCallLog::leftJoin('users as created_users', 'crm_call_logs.created_by', '=', 'created_users.id')
                                    ->where('contact_id', $schedule->contact_id)
                                    ->whereIn('created_by', $schedule->users->pluck('id')->toArray())
                                    ->where('start_time', '>=', $schedule->start_datetime)
                                    ->where('end_time', '<=', $schedule->end_datetime)
                                    ->latest()
                                    ->select('crm_call_logs.*',
                                        DB::raw("CONCAT(COALESCE(created_users.surname, ''), ' ', COALESCE(created_users.first_name, ''), ' ', COALESCE(created_users.last_name, '')) as created_user_name")
                                    )
                                    ->get();
                }

                $logs_html = view('crm::schedule_log.partial.log')
                                ->with(compact('schedule_logs', 'modal_content', 'schedule', 'call_logs'))
                                ->render();

                $output = [
                    'success' => true,
                    'msg' => __('lang_v1.success'),
                    'log' => $logs_html,
                ];
            } catch (Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $id = request()->get('schedule_id');
        $schedule = Schedule::where('business_id', $business_id)
                        ->findOrFail($id);
        $customers = Contact::customersDropdown($business_id, false);
        $statuses = Schedule::statusDropdown();

        return view('crm::schedule_log.create')
            ->with(compact('schedule', 'customers', 'statuses'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only('log_type', 'subject', 'description');
            $input['start_datetime'] = $this->commonUtil->uf_date($request->input('start_datetime'), true);
            $input['end_datetime'] = $this->commonUtil->uf_date($request->input('end_datetime'), true);
            $input['created_by'] = $request->user()->id;

            $schedule = Schedule::where('business_id', $business_id)
                        ->findOrFail($request->get('schedule_id'));

            //update schedule status
            if (! empty($request->input('status'))) {
                $schedule->status = $request->input('status');
                $schedule->save();
            }

            $schedule_log = $schedule->scheduleLog()->create($input);

            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $schedule_id = request()->get('schedule_id');

        $schedule_log = ScheduleLog::with('schedule')
                        ->where('schedule_id', $schedule_id)
                        ->findOrFail($id);

        return view('crm::schedule_log.show')
            ->with(compact('schedule_log'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $schedule_id = request()->get('schedule_id');

        $schedule = Schedule::where('business_id', $business_id)
                        ->findOrFail($schedule_id);

        $schedule_log = ScheduleLog::where('schedule_id', $schedule_id)
                            ->findOrFail($id);

        $customers = Contact::customersDropdown($business_id, false);
        $statuses = Schedule::statusDropdown();

        return view('crm::schedule_log.edit')
            ->with(compact('schedule', 'customers', 'schedule_log', 'statuses'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only('log_type', 'subject', 'description');
            $input['start_datetime'] = $this->commonUtil->uf_date($request->input('start_datetime'), true);
            $input['end_datetime'] = $this->commonUtil->uf_date($request->input('end_datetime'), true);

            $schedule_id = $request->get('schedule_id');
            $schedule_log = ScheduleLog::where('schedule_id', $schedule_id)
                            ->findOrFail($id);

            //update schedule status
            if (! empty($request->input('status'))) {
                $schedule = Schedule::where('business_id', $business_id)
                        ->findOrFail($schedule_id);

                $schedule->status = $request->input('status');
                $schedule->save();
            }

            $schedule_log->update($input);

            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $schedule_id = request()->get('schedule_id');
                $schedule_log = ScheduleLog::where('schedule_id', $schedule_id)
                                    ->findOrFail($id);

                $schedule_log->delete();

                $output = [
                    'success' => true,
                    'msg' => __('lang_v1.success'),
                ];
            } catch (Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }
}
