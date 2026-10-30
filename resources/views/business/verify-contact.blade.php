@extends('layouts.auth2')
@section('title', __('registration.verify_title'))
@section('body_class', 'mbira-public brand-register-page brand-verification-page')
@section('public_css')
    <link rel="stylesheet" href="{{ asset('brand-public.css?v=' . substr(hash_file('sha256', public_path('brand-public.css')), 0, 12)) }}">
@endsection

@section('content')
    @php($awaitingCode = $challenge && empty($challenge['verified_until']))
    <main class="brand-registration brand-verification">
        <aside class="brand-verification-intro" aria-label="{{ __('registration.setup_steps') }}">
            <p class="brand-eyebrow">@lang('registration.get_started')</p>
            <h2>@lang('registration.workspace_intro')</h2>
            <p class="brand-verification-description">@lang('registration.setup_intro')</p>
            <ol class="brand-verification-steps">
                <li aria-current="step"><span>1</span><div><strong>@lang('registration.verify_title')</strong><p>@lang('registration.step_contact')</p></div></li>
                <li><span>2</span><div><strong>@lang('registration.step_business')</strong><p>@lang('registration.step_business_hint')</p></div></li>
                <li><span>3</span><div><strong>@lang('registration.step_ready')</strong><p>@lang('registration.step_ready_hint')</p></div></li>
            </ol>
            <p class="brand-verification-security">@lang('registration.security_note')</p>
        </aside>
        <section class="brand-verification-panel" aria-labelledby="verification-title">
        <header class="brand-registration-heading">
            <p class="brand-eyebrow">@lang('registration.step_one')</p>
            <h1 id="verification-title">{{ $awaitingCode ? __('registration.check_messages') : __('registration.verify_title') }}</h1>
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
            @if($awaitingCode)
                <div class="alert alert-success brand-verification-sent" role="status">
                    {{ __('registration.sent', ['contact' => $challenge['contact']]) }}
                </div>
            @endif
            @if(empty($channels))
                <p class="brand-verification-unavailable" role="status">@lang('registration.no_channels')</p>
            @else
                @if($awaitingCode)
                    <form method="post" action="{{ route('business.verifyRegistrationOtp') }}" class="brand-verification-form">
                        @csrf
                        <label for="otp">@lang('registration.code')</label>
                        <input class="brand-otp-input" type="text" name="otp" id="otp" required inputmode="numeric"
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
                        autocomplete="username" autocapitalize="none" spellcheck="false" aria-describedby="contact-hint"
                        placeholder="{{ __('registration.contact_placeholder') }}"
                        value="{{ old('contact', $challenge['contact'] ?? '') }}">
                    <p id="contact-hint" class="brand-verification-hint">@lang('registration.contact_hint')</p>
                    <button type="submit">@lang('registration.send_code')</button>
                </form>
                @if($awaitingCode)
                    </details>
                @endif
            @endif
        </div>
        <p class="brand-registration-signin">@lang('registration.already_registered') <a href="{{ route('login') }}">@lang('business.sign_in')</a></p>
        </section>
    </main>
@endsection
