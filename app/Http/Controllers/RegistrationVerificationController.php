<?php

namespace App\Http\Controllers;

use App\Services\RegistrationOtp;
use App\Services\RegistrationOtpDelivery;
use Illuminate\Http\Request;

class RegistrationVerificationController extends Controller
{
    public function show(Request $request, RegistrationOtp $otp, RegistrationOtpDelivery $delivery)
    {
        if (!config('constants.allow_registration')) {
            return redirect('/');
        }
        if ($request->has('package')) {
            $request->validate(['package' => 'nullable|integer|min:1']);
            $request->session()->put('registration_package', $request->input('package'));
        }
        if ($otp->verifiedContact($request->session()) && !$request->boolean('change')) {
            return redirect()->route('business.getRegister', ['package' => $request->session()->get('registration_package')]);
        }

        return view('business.verify-contact', [
            'challenge' => $otp->state($request->session()),
            'channels' => $delivery->channels(),
        ]);
    }

    public function send(Request $request, RegistrationOtp $otp)
    {
        if (!config('constants.allow_registration')) {
            return redirect('/');
        }
        $request->validate(['contact' => 'required|string|max:254']);
        $otp->send($request->session(), $request->input('contact'), $request->ip());

        return redirect()->route('business.verifyContact');
    }

    public function verify(Request $request, RegistrationOtp $otp)
    {
        if (!config('constants.allow_registration')) {
            return redirect('/');
        }
        $request->validate(['otp' => 'required|digits:6']);
        $otp->verify($request->session(), $request->input('otp'), $request->ip());

        return redirect()->route('business.getRegister', ['package' => $request->session()->get('registration_package')]);
    }
}
