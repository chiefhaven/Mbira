<?php

namespace Modules\Crm\Http\Controllers;

use App\BusinessLocation;
use App\Http\Controllers\Controller;
use App\User;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\CrmContactPersonCommission;
use Modules\Crm\Utils\CrmUtil;
use Yajra\DataTables\Facades\DataTables;

class ContactLoginController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    protected $crmUtil;

    /**
     * Constructor
     *
     * @param CommonUtil
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil, CrmUtil $crmUtil)
    {
        $this->moduleUtil = $moduleUtil;
        $this->crmUtil = $crmUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module')) || ! auth()->user()->can('crm.access_contact_login')) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->ajax()) {
            $query = User::with('contact')
                    ->where('business_id', $business_id)
                    ->whereHas('contact', function ($q) {
                        $q->whereNotNull('id');
                    });

            if (! empty($request->get('contact_id'))) {
                $query->where('crm_contact_id', $request->get('contact_id'));
            }

            if (! empty($request->get('crm_contact_id'))) {
                $query->where('crm_contact_id', $request->get('crm_contact_id'));
            }

            $users = $query->select('username', 'email', 'id', 'crm_contact_id', 'surname', 'first_name', 'last_name', 'crm_department', 'crm_designation');

            return Datatables::of($users)
                    ->addColumn('action', function ($row) {
                        $html = '<div class="btn-group">
                                    <button class="btn btn-info dropdown-toggle btn-xs" type="button"  data-toggle="dropdown" aria-expanded="false">
                                        '.__('messages.action').'
                                        <span class="caret"></span>
                                        <span class="sr-only">
                                        '.__('messages.action').'
                                        </span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-left" role="menu">
                                        <li>
                                            <a data-href="'.action([\Modules\Crm\Http\Controllers\ContactLoginController::class, 'edit'], [$row->id, 'crm_contact_id' => $row->crm_contact_id]).'" class="cursor-pointer edit_contact_login">
                                                <i class="fa fa-edit"></i>
                                                '.__('messages.edit').'
                                            </a>
                                        </li>
                                        <li>
                                            <a data-href="'.action([\Modules\Crm\Http\Controllers\ContactLoginController::class, 'destroy'], [$row->id, 'crm_contact_id' => $row->crm_contact_id]).'"  id="delete_contact_login" class="cursor-pointer">
                                                <i class="fas fa-trash"></i>
                                                '.__('messages.delete').'
                                            </a>
                                        </li>
                                    </ul>
                                </div>';

                        return $html;
                    })
                ->editColumn('name', function ($row) {
                    return $row->surname.' '.$row->first_name.' '.$row->last_name;
                })
                ->editColumn('contact', function ($row) {
                    return $row['contact']->prefix.' '.$row['contact']->first_name.' '.$row['contact']->last_name;
                })
                ->removeColumn('id')
                ->rawColumns(['action', 'contact', 'name'])
                ->make(true);
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
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module')) || ! auth()->user()->can('crm.access_contact_login')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $crm_contact_id = request()->get('contact_id');
            $crud_type = request()->get('crud_type');
            $contacts = [];
            if (! empty($crud_type)) {
                $contacts = CrmContact::contactsDropdownForLogin($business_id);
            }

            return view('crm::contact_login.create')
                ->with(compact('crm_contact_id', 'contacts'));
        }
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
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module')) || ! auth()->user()->can('crm.access_contact_login')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only('crm_contact_id', 'surname', 'first_name', 'last_name', 'email', 'username', 'password', 'contact_no', 'alt_number', 'family_number', 'crm_department', 'crm_designation', 'cmmsn_percent');

            $input['status'] = ! empty($request->input('is_active')) ? 'active' : 'inactive';
            $input['business_id'] = $business_id;

            $input['allow_login'] = ! empty($request->input('allow_login')) ? 1 : 0;

            $user = $this->crmUtil->creatContactPerson($input);

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
        //
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
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module')) || ! auth()->user()->can('crm.access_contact_login')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $crm_contact_id = request()->get('crm_contact_id');
            $crud_type = request()->get('crud_type');

            $contacts = [];
            if (! empty($crud_type)) {
                $contacts = CrmContact::contactsDropdownForLogin($business_id);
            }

            $user = User::where('business_id', $business_id)
                        ->where('crm_contact_id', $crm_contact_id)
                        ->findOrFail($id);

            return view('crm::contact_login.edit')
                ->with(compact('user', 'contacts'));
        }
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
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module')) || ! auth()->user()->can('crm.access_contact_login')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only('surname', 'first_name', 'last_name', 'email', 'username', 'contact_no', 'alt_number', 'family_number', 'crm_department', 'crm_designation', 'cmmsn_percent');

            $input['status'] = ! empty($request->input('is_active')) ? 'active' : 'inactive';

            if (! empty($request->input('password'))) {
                $input['password'] = Hash::make($request->input('password'));
            }

            $input['crm_contact_id'] = $request->get('crm_contact_id');

            $user = User::where('business_id', $business_id)
                        ->where('id', $id)
                        ->update($input);

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
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module')) || ! auth()->user()->can('crm.access_contact_login')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $crm_contact_id = request()->get('crm_contact_id');

                $user = User::where('business_id', $business_id)
                            ->where('crm_contact_id', $crm_contact_id)
                            ->findOrFail($id);

                $user->delete();

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

    public function allContactsLoginList()
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module')) || ! auth()->user()->can('crm.access_contact_login')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $contacts = CrmContact::contactsDropdownForLogin($business_id, true);

        return view('crm::contact_login.all_contacts_login')
            ->with(compact('contacts'));
    }

    /**
     * Display a listing of sales commissions of contact persons.
     */
    public function commissions(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module')) || ! auth()->user()->can('crm.access_contact_login')) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->ajax()) {
            $query = CrmContactPersonCommission::join('users as u', 'u.id', 'crm_contact_person_commissions.contact_person_id')
                    ->where('u.business_id', $business_id)
                    ->join('contacts as c', 'u.crm_contact_id', '=', 'c.id')
                    ->join('transactions as t', 't.id', '=', 'crm_contact_person_commissions.transaction_id')
                    ->join('business_locations as bl', 't.location_id', '=', 'bl.id');

            if (! empty($request->get('crm_contact_id'))) {
                $query->where('u.id', $request->get('crm_contact_id'));
            }

            if (! empty($request->get('contact_id'))) {
                $query->where('u.crm_contact_id', $request->get('contact_id'));
            }

            if (! empty($request->get('location_id'))) {
                $query->where('t.location_id', $request->get('location_id'));
            }

            if (! empty(request()->input('start_date_time')) && ! empty(request()->input('end_date_time'))) {
                $start_date = request()->input('start_date_time');
                $end_date = request()->input('end_date_time');
                $query->whereBetween(DB::raw('date(t.transaction_date)'), [$start_date, $end_date]);
            }

            $commissions = $query->select('t.transaction_date', 'u.username', 't.invoice_no', 'bl.name as location', 'commission_amount',
                DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as full_name"), 'u.contact_no', 'c.name as contact_name', 'c.supplier_business_name');

            return Datatables::of($commissions)
                ->editColumn('commission_amount', function ($row) {
                    return $this->moduleUtil->num_f($row->commission_amount, true);
                })
                ->addColumn('commission_amount_uf', '{{$commission_amount}}')
                ->editColumn('transaction_date', function ($row) {
                    return $this->moduleUtil->format_date($row->transaction_date, true);
                })
                ->addColumn('contact', function ($row) {
                    $contact_name = $row->contact_name;

                    if (! empty($row->supplier_business_name)) {
                        $contact_name .= ' - '.$row->supplier_business_name;
                    }

                    return $contact_name;
                })
                ->removeColumn('id')
                ->filterColumn('full_name', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->filterColumn('contact', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(c.name, ''), ' - ', COALESCE(c.supplier_business_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->make(true);
        }

        $crm_contact_persons = User::where('business_id', $business_id)
                    ->whereNotNull('crm_contact_id')
                    ->pluck('username', 'id');

        $business_locations = BusinessLocation::forDropdown($business_id);

        $contacts = CrmContact::contactsDropdownForLogin($business_id, true);

        return view('crm::contact_login.commissions')->with(compact('crm_contact_persons', 'business_locations', 'contacts'));
    }
}
