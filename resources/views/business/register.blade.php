@extends('layouts.auth2')
@section('title', __('lang_v1.register'))
@section('body_class', 'mbira-public brand-register-page brand-business-signup')
@section('public_css')
    <link rel="stylesheet" href="{{ asset('brand-public.css?v=' . substr(hash_file('sha256', public_path('brand-public.css')), 0, 12)) }}">
@endsection

@section('content')
    <main class="brand-registration">
        <header class="brand-registration-heading">
            <p class="brand-eyebrow">@lang('registration.workspace_label')</p>
            <h1>@lang('business.register_and_get_started_in_minutes')</h1>
            <p>{{ __('registration.workspace_hint', ['app' => config('app.name')]) }}</p>
        </header>
        <div class="brand-registration-card">
            @if(!empty($verified_contact))
                <p class="brand-verified-contact" role="status">
                    <span class="brand-contact-check" aria-hidden="true">&#10003;</span>
                    <span>
                    {{ __('registration.verified', ['contact' => $verified_contact['contact']]) }}
                    </span>
                    <a href="{{ route('business.verifyContact', ['change' => 1]) }}">@lang('registration.change_contact')</a>
                </p>
            @endif
            <p class="brand-registration-required">@lang('registration.required_fields')</p>
            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            {!! Form::open([
                'url' => route('business.postRegister'),
                'method' => 'post',
                'id' => 'business_register_form',
                'files' => true,
            ]) !!}
                @include('business.partials.register_form', ['is_register' => true])
                {!! Form::hidden('package_id', $package_id) !!}
            {!! Form::close() !!}
        </div>
        <p class="brand-registration-signin">@lang('registration.already_registered') <a href="{{ route('login') }}">@lang('business.sign_in')</a></p>
    </main>
@endsection
