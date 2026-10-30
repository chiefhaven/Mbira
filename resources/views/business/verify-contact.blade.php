@extends('layouts.auth2')
@section('title', __('registration.verify_title'))
@section('body_class', 'mbira-public brand-register-page')
@section('public_css')
    <link rel="stylesheet" href="{{ asset('brand-public.css?v=' . $asset_v) }}">
@endsection

@section('content')
    <main class="brand-registration brand-verification">
        <header class="brand-registration-heading">
            <p class="brand-eyebrow">@lang('business.register')</p>
            <h1>@lang('registration.verify_title')</h1>
            <p>@lang('registration.verify_intro')</p>
        </header>
        <div class="brand-registration-card">
            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if($challenge && empty($challenge['verified_until']))
                <div class="alert alert-success" role="status">
                    {{ __('registration.sent', ['contact' => $challenge['contact']]) }}
                </div>
            @endif
            @if(empty($channels))
                <p role="status">@lang('registration.no_channels')</p>
            @else
                @if($challenge && empty($challenge['verified_until']))
                    <form method="post" action="{{ route('business.verifyRegistrationOtp') }}" class="brand-verification-form">
                        @csrf
                        <label for="otp">@lang('registration.code')</label>
                        <input type="text" name="otp" id="otp" required inputmode="numeric"
                            autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6"
                            aria-describedby="otp-hint">
                        <p id="otp-hint" class="brand-verification-hint">@lang('registration.code_hint')</p>
                        <button type="submit">@lang('registration.verify_code')</button>
                    </form>
                    <details class="brand-code-form">
                        <summary>@lang('registration.resend_or_change')</summary>
                @else
                    <p class="brand-verification-hint">{{ __('registration.available_channels', [
                        'channels' => implode(', ', array_map(fn ($channel) => __('registration.'.$channel), $channels)),
                    ]) }}</p>
                @endif
                <form method="post" action="{{ route('business.sendRegistrationOtp') }}" class="brand-verification-form">
                    @csrf
                    <label for="contact">@lang('registration.contact')</label>
                    <input type="text" name="contact" id="contact" required maxlength="254"
                        autocomplete="username" aria-describedby="contact-hint"
                        value="{{ old('contact', $challenge['contact'] ?? '') }}">
                    <p id="contact-hint" class="brand-verification-hint">@lang('registration.contact_hint')</p>
                    <button type="submit">@lang('registration.send_code')</button>
                </form>
                @if($challenge && empty($challenge['verified_until']))
                    </details>
                @endif
            @endif
        </div>
        <p class="brand-registration-signin"><a href="{{ route('login') }}">@lang('business.sign_in')</a></p>
    </main>
@endsection
