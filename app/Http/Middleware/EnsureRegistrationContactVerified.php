<?php

namespace App\Http\Middleware;

use App\Services\RegistrationOtp;
use Closure;
use Illuminate\Http\Request;

class EnsureRegistrationContactVerified
{
    public function __construct(private RegistrationOtp $otp)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (!config('constants.allow_registration')) {
            return redirect('/');
        }
        if ($request->isMethod('get') && $request->has('package')) {
            $request->validate(['package' => 'nullable|integer|min:1']);
            $request->session()->put('registration_package', $request->input('package'));
        }
        $proceed = function () use ($request, $next) {
            $contact = $this->otp->verifiedContact($request->session());
            if (!$contact) {
                return redirect()->route('business.verifyContact')->withErrors([
                    'contact' => __('registration.required'),
                ]);
            }
            if ($request->isMethod('post')) {
                $field = $contact['channel'] === 'email' ? 'email' : 'mobile';
                if ($request->input($field) !== $contact['contact']) {
                    return redirect()->route('business.getRegister')->withErrors([
                        $field => __('registration.contact_mismatch'),
                    ])->withInput($request->except(['password', 'confirm_password', $field]));
                }
            }
            $request->attributes->set('verified_registration_contact', $contact);

            return $request->isMethod('post')
                ? app(\App\Services\RegistrationContacts::class)->registration($request, fn () => $next($request))
                : $next($request);
        };

        // Keep the proof single-use even when two registration POSTs arrive together.
        return $request->isMethod('post') ? $this->otp->locked($request->session(), $proceed) : $proceed();
    }
}
