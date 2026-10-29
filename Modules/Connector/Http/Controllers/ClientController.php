<?php

namespace Modules\Connector\Http\Controllers;

use App\Utils\ModuleUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

class ClientController extends Controller
{
    public function __construct(Util $util)
    {
        $this->util = $util;
    }

    /**
     * Check whether the current user can manage connector clients.
     *
     * @return bool
     */
    protected function canManageClients()
    {
        $business_id = request()->session()->get('user.business_id');
        $moduleUtil = new ModuleUtil();

        return auth()->user()->can('superadmin') || $moduleUtil->hasThePermissionInSubscription($business_id, 'connector_module');
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! $this->canManageClients()) {
            abort(403, 'Unauthorized action.');
        }

        $is_demo = (config('app.env') == 'demo');

        $business_id = request()->session()->get('user.business_id');
        $clients = Passport::client()
                    ->leftJoin('users as u', 'oauth_clients.user_id', '=', 'u.id')
                    ->where('u.business_id', $business_id)
                    ->where('password_client', 1)
                    ->select('oauth_clients.*')
                    ->get()
                    ->makeVisible('secret');

        $can_manage_clients = $this->canManageClients();

        return view('connector::clients.index')->with(compact('clients', 'is_demo', 'can_manage_clients'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('connector::create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        if (! $this->canManageClients()) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $client = Passport::client()->forceFill([
                'user_id' => auth()->user()->id,
                'name' => $request->input('name'),
                'secret' => Str::random(40),
                'redirect' => 'http://localhost',
                'personal_access_client' => 0,
                'password_client' => 1,
                'revoked' => false,
            ]);

            $client->save();

            $output = ['success' => true,
                'msg' => __('lang_v1.added_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with('status', $output);
    }

    /**
     * Show the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        return view('connector::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        return view('connector::edit');
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
        if (! $this->canManageClients()) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $clients = Passport::client()
                        ->leftJoin('users as u', 'oauth_clients.user_id', '=', 'u.id')
                        ->where('u.business_id', $business_id)
                        ->where('oauth_clients.id', $id)
                        ->delete();

        $output = ['success' => true,
            'msg' => __('lang_v1.deleted_success'),
        ];

        return redirect()->back()->with('status', $output);
    }

    public function regenerate()
    {
        if (! $this->canManageClients()) {
            abort(403, 'Unauthorized action.');
        }

        try {
            Artisan::call('passport:install --force');
            // Artisan::call('scribe:generate');

            $output = ['success' => 1,
                'msg' => __('lang_v1.success'),
            ];
        } catch (Exception $e) {
            $output = ['success' => 1,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()->back()->with('status', $output);
    }
}
