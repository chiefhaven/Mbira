<?php

namespace Modules\Crm\Http\Controllers;

use App\Media;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\ProposalTemplate;

class ProposalTemplateController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $proposal_template = $this->__getProposalTemplate($business_id);

        return view('crm::proposal_template.index')
            ->with(compact('proposal_template'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('crm.add_proposal_template'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! empty($this->__getProposalTemplate($business_id))) {
            return redirect()
                ->action([\Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'index'])
                ->with('status', ['success' => 0,
                    'msg' => __('crm::lang.template_is_already_created'),
                ]);
        }

        return view('crm::proposal_template.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('crm.add_proposal_template'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! empty($this->__getProposalTemplate($business_id))) {
            return redirect()
                ->action([\Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'index'])
                ->with('status', ['success' => 0,
                    'msg' => __('crm::lang.template_is_already_created'),
                ]);
        }

        $request->validate([
            'subject' => 'required',
            'body' => 'required',
        ]);

        try {
            $input = $request->only(['subject', 'body']);
            $input['business_id'] = $business_id;
            $input['created_by'] = request()->session()->get('user.id');

            $attachments = $request->file('attachments');

            DB::beginTransaction();
            $proposal_template = ProposalTemplate::create($input);
            if (! empty($attachments)) {
                Media::uploadMedia($business_id, $proposal_template, request(), 'attachments');
            }
            DB::commit();
            $output = ['success' => 1,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()
            ->action([\Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'index'])
            ->with('status', $output);
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

    private function __getProposalTemplate($business_id)
    {
        $proposal_template = ProposalTemplate::with(['media'])
                                ->where('business_id', $business_id)
                                ->first();

        return $proposal_template;
    }

    public function getEdit()
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('crm.add_proposal_template'))) {
            abort(403, 'Unauthorized action.');
        }

        if (empty($this->__getProposalTemplate($business_id))) {
            return redirect()
                ->action([\Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'create'])
                ->with('status', ['success' => 0,
                    'msg' => __('crm::lang.please_add_template'),
                ]);
        }

        $proposal_template = $this->__getProposalTemplate($business_id);

        return view('crm::proposal_template.edit')
            ->with(compact('proposal_template'));
    }

    public function postEdit(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('crm.add_proposal_template'))) {
            abort(403, 'Unauthorized action.');
        }

        if (empty($this->__getProposalTemplate($business_id))) {
            return redirect()
                ->action([\Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'create'])
                ->with('status', ['success' => 0,
                    'msg' => __('crm::lang.please_add_template'),
                ]);
        }

        $request->validate([
            'subject' => 'required',
            'body' => 'required',
        ]);

        try {
            $input = $request->only(['subject', 'body', 'cc', 'bcc']);

            $attachments = $request->file('attachments');

            DB::beginTransaction();
            $proposal_template = ProposalTemplate::where('business_id', $business_id)
                                        ->first();

            $proposal_template->subject = $input['subject'];
            $proposal_template->body = $input['body'];
            $proposal_template->cc = $input['cc'];
            $proposal_template->bcc = $input['bcc'];
            $proposal_template->save();

            if (! empty($attachments)) {
                Media::uploadMedia($business_id, $proposal_template, request(), 'attachments');
            }
            DB::commit();
            $output = ['success' => 1,
                'msg' => __('lang_v1.updated'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()
            ->action([\Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'index'])
            ->with('status', $output);
    }

    public function getView()
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (empty($this->__getProposalTemplate($business_id))) {
            return redirect()
                ->action([\Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'create'])
                ->with('status', ['success' => 0,
                    'msg' => __('crm::lang.please_add_template'),
                ]);
        }

        $proposal_template = $this->__getProposalTemplate($business_id);

        return view('crm::proposal_template.view')
            ->with(compact('proposal_template'));
    }

    public function send()
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (empty($this->__getProposalTemplate($business_id))) {
            return redirect()
                ->action([\Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'create'])
                ->with('status', ['success' => 0,
                    'msg' => __('crm::lang.please_add_template'),
                ]);
        }

        $proposal_template = $this->__getProposalTemplate($business_id);

        $contacts = CrmContact::getCustomerAndLeadsDropdown($business_id);

        return view('crm::proposal_template.send')
            ->with(compact('proposal_template', 'contacts'));
    }

    public function deleteProposalMedia(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                Media::deleteMedia($business_id, $id);

                $output = ['success' => true,
                    'msg' => __('lang_v1.success'),
                ];
            } catch (\Exception $e) {
                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }
}
