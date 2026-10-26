<?php

namespace Modules\AiAssistance\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Utils\ModuleUtil;
use Modules\AiAssistance\Entities\AiAssistanceHistory;
use OpenAI\Laravel\Facades\OpenAI;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;

class AiAssistanceController extends Controller
{
    public $tools = [];

    protected $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;

        $this->tools = [

            'brandproduct-descriptions' => [
                'name' => 'brandproduct-descriptions',
                'label' => __('aiassistance::lang.brandproduct'),
                'icon' => 'fas fa-tags',
                'description' => __('aiassistance::lang.brandproduct_descriptions'),
                'prompt' => 'As a copywriter, craft a product description for details mentioned. keep it informative, engaging, and persuasive, while also reflecting the brands values and tone of voice. Brand/Product Name: {name} \n Description: {description}',
                'max_token' => 1000
            ],

            'product_review' => [
                'name' => 'product_review',
                'label' => __('aiassistance::lang.product_review'),
                'icon' => 'fas fa-star',
                'description' => __('aiassistance::lang.product_review_desc'),
                'prompt' => 'write a review for {name} by sharing your experience with the product. In your review, please provide some details about the product, such as its purpose, functionality, and design. Include features you liked about the product, such as its ease of use, durability, or any unique capabilities it offers. Your review should help others make an informed decision about whether or not to purchase the product. Description: {description} \n Features liked: {features_liked}',
                'max_token' => 512
            ],

            'review_response' => [
                'name' => 'review_response',
                'label' => __('aiassistance::lang.review_response'),
                'icon' => 'fas fa-reply',
                'description' => __('aiassistance::lang.review_response_desc'),
                'prompt' => 'write a respond to customer review with professional and positive comments. Do not include personal opinions, but rather focus on the product features and benefits. Avoid writing negative or critical comments. Your replies should be concise and to the point. Customer Review: {customer_review}',
                'max_token' => 512
            ],

            'social_post' => [
                'name' => 'social_post',
                'label' => __('aiassistance::lang.social_post'),
                'icon' => 'fas fa-share',
                'description' => __('aiassistance::lang.social_post_desc'),
                'prompt' => 'write a social media post on the topic, make it engaging and informative, include relevant hashtags, include line breaks & format it in a way easy to read. Topic: {description}',
                'max_token' => 512
            ],

            'google-ads' => [
                'name' => 'google-ads',
                'label' => __('aiassistance::lang.google_ads'),
                'icon' => 'fab fa-google',
                'description' => __('aiassistance::lang.google_ads_desc'),
                'prompt' => 'write a google ads for the product using the relevant details provided. Start with short and catchy headlines that would grab the attention of potential customers and entice them to click. Brand/Product Name: {name} \n Description: {description}',
                'max_token' => 512
            ],

            'fb-ads' => [
                'name' => 'fb-ads',
                'label' => __('aiassistance::lang.fb_ads'),
                'icon' => 'fab fa-facebook',
                'description' => __('aiassistance::lang.fb_ads_desc'),
                'prompt' => 'write a facebook ads for the product using the relevant details provided. Start with short and catchy headlines that would grab the attention of potential customers and entice them to click. Brand/Product Name: {name} \n Description: {description}',
                'max_token' => 512
            ],

            'email' => [
                'name' => 'email',
                'label' => __('aiassistance::lang.email'),
                'icon' => 'fas fa-envelope',
                'description' => __('aiassistance::lang.email_desc'),
                'prompt' => 'write an email that impresses & get replies based on the provided details using {tone} tone. Sender: {sender} \n Recipient: {recipient} \n Email About: {email_about}' ,
                'max_token' => 512
            ],

            //https://venngage.com/blog/business-proposal/#3
            'proposal' => [
                'name' => 'proposal',
                'label' => __('aiassistance::lang.proposal'),
                'icon' => 'fas fa-file-alt',
                'description' => __('aiassistance::lang.proposal_desc'),
                'prompt' => 'write an business proposal having applicable sections from executive summary, statement of problem, approach & metholodogy, qualification, schedule & Benchnmark, cost/payment/legal and Benefits. Sender business details: {what_biz_does} \n What can do for customer: {what_do_for_client}' ,
                'max_token' => 512
            ],

            'kb' => [
                'name' => 'kb',
                'label' => __('aiassistance::lang.kb'),
                'icon' => 'fas fa-book',
                'description' => __('aiassistance::lang.kb_desc'),
                'prompt' => 'write a step-by-step guide on the provided details. Knowledge base topic/details: {kb_details}' ,
                'max_token' => 512
            ],

            
        ];
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (!(auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'aiassistance_module')))) {
            abort(403, 'Unauthorized action.');
        }

        //Display remaining tokens in top.
        $token_remaining_display = false;
        $token_details = $this->_tokenDetails($business_id);
        if ($token_details) {
            $token_remaining_display = $token_details['remaining_tokens'] . '/' . $token_details['max_token'] . ' ' . __('aiassistance::lang.token_remaining');
        }

        $tools = $this->tools;
        return view('aiassistance::index')->with(compact('tools', 'token_remaining_display'));
    }

    protected function _tokenDetails($business_id)
    {
        if ($this->moduleUtil->isSuperadminInstalled()) {
            $package = \Modules\Superadmin\Entities\Subscription::active_subscription($business_id);

            if (!empty($package)) {
                $max_token = isset($package->package_details['aiassistance_max_token']) ? $package->package_details['aiassistance_max_token'] : 0;

                $used_tokens = AiAssistanceHistory::whereBetween('created_at', [$package->start_date, $package->end_date])
                    ->where('business_id', $business_id)
                    ->sum('tokens_used');

                $remaining_tokens = $max_token - $used_tokens;

                return ['max_token' => $max_token, 'used_tokens' => $used_tokens, 'remaining_tokens' => $remaining_tokens];
            } else {
                return ['max_token' => 0, 'used_tokens' => 0, 'remaining_tokens' => 0];
            }
        } else {
            return false;
        }
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create($tool)
    {
        $tools = $this->tools;

        abort_if(!isset($tools[$tool]), 404);

        $tool_details = $tools[$tool];
        $tones = [
            'polite' => __('aiassistance::lang.polite'),
            'persuasive' => __('aiassistance::lang.persuasive'),
            'professional' => __('aiassistance::lang.professional'),
            'casual' => __('aiassistance::lang.casual'),
            'witty' => __('aiassistance::lang.witty')
        ];

        $config_languages = config('constants.langs');
        $languages = [];
        foreach ($config_languages as $key => $value) {
            $languages[] = $value['short_name'];
        }
        $default_lang = request()->session()->get('user.language');
        $default_lang = config('constants.langs')[$default_lang]['short_name'];

        return view('aiassistance::create')->with(compact('tool_details', 'tones', 'languages', 'default_lang'));
    }

    /**
     * Store a newly created resource in storage.
     * @param String $tool
     * @param Request $request
     * @return Renderable
     */
    public function generate($tool, Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $user_id = request()->session()->get('user.id');

        if (!(auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'aiassistance_module')))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $tools = $this->tools;
            abort_if(!isset($tools[$tool]), 404);

            //Check for token
            $token_details = $this->_tokenDetails($business_id);
            if ($token_details) {
                // if ($token_details['remaining_tokens'] <= 0) {
                //     return ['success' => false, 'msg' => __('aiassistance::lang.no_token')];
                // }
            }

            $input = $request->all();
            $prompt = $tools[$tool]['prompt'];
            $prompt .= '\n Add HTML line breaks & formatting to make it easy to read';
            $prompt .= '\n Write in ' . $input['language'] . ' language';

            foreach ($input as $k => $v) {
                $prompt = str_replace('{' . $k . '}', $v, $prompt);
            }
            $max_token = ($token_details != false) ? (min($token_details['remaining_tokens'], $tools[$tool]['max_token'])) : $tools[$tool]['max_token'];

            //make openai request
            $result = OpenAI::completions()->create([
                'model' => 'text-davinci-003',
                'prompt' => $prompt,
                //'max_tokens' => $max_token,
                'max_tokens' => $tools[$tool]['max_token'],
                'temperature' => 0
            ]);
            $text = $result['choices'][0]['text'];

            //save response in database
            $history = new AiAssistanceHistory;
            $history->business_id = $business_id;
            $history->user_id = $user_id;
            $history->tool_type = $tools[$tool]['name'];
            $history->input_data = $input;
            $history->output_data = $result['choices'][0]['text'];
            $history->tokens_used = $result->usage->totalTokens;;
            $history->save();

            //$output = 'sdd';
            $html = view('aiassistance::generate', compact('text'))->render();
            return ['success' => true, 'html' => $html];
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        //send response
    }

    /**
     * Show the entire history of previous generation
     * @return Renderable
     */
    public function history()
    {
        $business_id = request()->session()->get('user.business_id');

        if (!(auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'aiassistance_module')))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $query = AiAssistanceHistory::where('aiassistance_history.business_id', $business_id)
                ->leftJoin('users as u', 'aiassistance_history.user_id', '=', 'u.id')
                ->select(['aiassistance_history.*', DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as added_by")]);
            
            if (request()->input('tool_type') != '') {
                $query->where('tool_type', request()->input('tool_type'));
            }

            return Datatables::of($query)
                ->editColumn('input_data', function ($row) {

                    $output = '';
                    foreach ($row->input_data as $k => $v) {
                        if (!empty($output)) {
                            $output .= '<br/>';
                        }
                        $output .= ucfirst($k) . ': ' . $v;
                    }
                    return $output;
                })
                ->editColumn('created_at', function ($row) {
                    return $this->moduleUtil->format_date($row->created_at, true);
                })
                ->rawColumns(['input_data', 'output_data'])
                ->make(true);
        }

        $tools = $this->tools;
        $tools = collect($tools)->pluck('label', 'name');
        
        return view('aiassistance::history')->with(compact('tools'));
    }
}
