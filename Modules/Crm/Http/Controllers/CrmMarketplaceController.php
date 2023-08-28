<?php

namespace Modules\Crm\Http\Controllers;

use App\Category;
use App\User;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\CrmMarketplace;
use Modules\Crm\Entities\Schedule;
use Modules\Crm\Utils\CrmUtil;

class CrmMarketplaceController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    public function __construct(Util $commonUtil, CrmUtil $crmUtil)
    {
        $this->commonUtil = $commonUtil;
        $this->crmUtil = $crmUtil;
        $this->enable_b2b_marketplace = config('constants.enable_b2b_marketplace');
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        $is_admin = $this->commonUtil->is_admin(auth()->user());

        if (! $is_admin || ! $this->enable_b2b_marketplace) {
            abort(403, 'Unauthorized action.');
        }

        $marketplace = CrmMarketplace::first();
        $sources = Category::forDropdown($business_id, 'source');

        $users = User::forDropdown($business_id, false);

        return view('crm::marketplace.index')->with(compact('marketplace', 'users', 'sources'));
    }

    /**
     * Saves marketplace details
     *
     * @return Response
     */
    public function save(Request $request)
    {
        $is_admin = $this->commonUtil->is_admin(auth()->user());

        if (! $is_admin || ! $this->enable_b2b_marketplace) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        CrmMarketplace::updateOrCreate(
            [
                'business_id' => $business_id,
                'marketplace' => $request->input('marketplace'),
            ],
            [
                'site_key' => $request->input('site_key'),
                'site_id' => $request->input('site_id'),
                'assigned_users' => $request->input('assigned_users'),
                'crm_source_id' => $request->input('crm_source_id'),
            ]
        );

        return redirect()->back();
    }

    /**
     * Fetches leads from api and creates leads and followups
     *
     * @param  Request  $request
     * @return Response
     */
    public function importLeads(Request $request)
    {
        $is_admin = $this->commonUtil->is_admin(auth()->user());

        if (! $is_admin || ! $this->enable_b2b_marketplace) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');
            $marketplace = CrmMarketplace::first();

            $url = 'https://my.exportersindia.com/api-inquiry-detail.php?k='.$marketplace->site_key.'&email='.$marketplace->site_id;

            $response = file_get_contents($url);

            $data = json_decode($response, true);

            DB::beginTransaction();
            foreach ($data as $user_data) {
                if (empty($user_data['email'])) {
                    continue;
                }

                //check if email exists
                $lead = CrmContact::where('email', $user_data['email'])
                                ->first();

                //if lead don't exist create one
                if (empty($lead)) {
                    $name = $user_data['name'];
                    $name_array = explode(' ', $name, 2);
                    $lead_data = [
                        'type' => 'lead',
                        'business_id' => $business_id,
                        'name' => $name,
                        'first_name' => $name_array[0] ?? '',
                        'last_name' => $name_array[1] ?? '',
                        'email' => $user_data['email'],
                        'supplier_business_name' => $user_data['company'] ?? '',
                        'city' => $user_data['city'] ?? '',
                        'country' => $user_data['country'] ?? '',
                        'state' => $user_data['state'] ?? '',
                        'mobile' => $user_data['mobile'] ?? '',
                        'created_by' => auth()->user()->id,
                        'crm_source' => $marketplace->crm_source_id,
                    ];

                    $lead = CrmContact::createNewLead($lead_data, $marketplace->assigned_users);
                }

                //check if same follow up exists, if not create new
                $inq_id = $user_data['inq_id'] ?? '';

                if (! empty($user_data['inq_id'])) {
                    $schedule = Schedule::where('business_id', $business_id)
                                        ->where('contact_id', $lead->id)
                                        ->where('title', 'like', "%{$inq_id}%")
                                        ->first();

                    if (! empty($schedule)) {
                        continue;
                    }
                }

                $subject = $user_data['subject'] ?? '';
                $product = $user_data['product'] ?? '';
                $detail_req = $user_data['detail_req'] ?? '';

                $title = 'ExportersIndia - '.$subject.' - '.$lead->name." (Inquiry id: {$inq_id})";

                $description = 'Subject: '.$subject;
                $description .= '<br>';

                $description .= 'Product: '.$product;
                $description .= '<br>';
                $description .= $detail_req;

                $follow_up_data = [
                    'business_id' => $business_id,
                    'contact_id' => $lead->id,
                    'title' => $title,
                    'status' => 'open',
                    'start_datetime' => \Carbon::now()->toDateTimeString(),
                    'end_datetime' => \Carbon::now()->addHours(1)->toDateTimeString(),
                    'schedule_type' => 'call',
                    'description' => $description,
                    'user_id' => $marketplace->assigned_users,
                ];

                $this->crmUtil->addFollowUp($follow_up_data, auth()->user());
            }

            DB::commit();

            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => false,
                'msg' => 'File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage(),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }
}
