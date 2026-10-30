@extends('layouts.auth2')
@section('title', __('lang_v1.register'))
@section('body_class', 'mbira-public brand-register-page')
@section('public_css')
    <link rel="stylesheet" href="{{ asset('brand-public.css?v=' . $asset_v) }}">
@endsection

@section('content')
    <main class="brand-registration">
        <header class="brand-registration-heading">
            <p class="brand-eyebrow">Your next chapter starts here</p>
            <h1>@lang('business.register_and_get_started_in_minutes')</h1>
            <p>Set up your business workspace with {{ config('app.name') }}.</p>
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
        <p class="brand-registration-signin">Already have an account? <a href="{{ route('login') }}">@lang('business.sign_in')</a></p>
    </main>
@endsection
