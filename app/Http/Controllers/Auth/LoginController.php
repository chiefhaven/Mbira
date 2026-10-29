<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = RouteServiceProvider::HOME;
    protected $businessUtil;
    protected $moduleUtil;

    public function __construct(BusinessUtil $businessUtil, ModuleUtil $moduleUtil)
    {
        $this->middleware('guest')->except('logout');
        $this->businessUtil = $businessUtil;
        $this->moduleUtil = $moduleUtil;
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function username()
    {
        return 'username';
    }

    protected function validateLogin(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);
    }

    public function logout(Request $request)
    {
        $user = auth()->user();
        if ($user && ! $user->can('superadmin')) {
            $this->businessUtil->activityLog($user, 'logout');
        }

        Cookie::queue(Cookie::forget(Auth::getRecallerName()));
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    protected function authenticated(Request $request, $user)
    {
        if ($user->can('superadmin')) {
            return;
        }

        $this->businessUtil->activityLog($user, 'login', null, [], false, $user->business_id);

        if (empty($user->business)) {
            Auth::logout();
            return redirect('/login')->with('status', ['success' => 0, 'msg' => __('lang_v1.business_inactive')]);
        }

        if (!$user->business->is_active) {
            Auth::logout();
            return redirect('/login')->with('status', ['success' => 0, 'msg' => __('lang_v1.business_inactive')]);
        } elseif ($user->status != 'active') {
            Auth::logout();
            return redirect('/login')->with('status', ['success' => 0, 'msg' => __('lang_v1.user_inactive')]);
        } elseif (!$user->allow_login) {
            Auth::logout();
            return redirect('/login')->with('status', ['success' => 0, 'msg' => __('lang_v1.login_not_allowed')]);
        }
    }

    protected function redirectTo()
    {
        $user = Auth::user();
        if ($user->can('superadmin')) {
            return '/superadmin';
        }
        if (!$user->can('dashboard.data') && $user->can('sell.create')) {
            return '/pos/create';
        }
        return '/home';
    }
}
