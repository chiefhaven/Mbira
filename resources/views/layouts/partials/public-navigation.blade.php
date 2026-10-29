<nav class="brand-navigation" aria-label="Main navigation">
    <a class="brand-wordmark" href="{{ url('/') }}">
        <span class="brand-monogram" aria-hidden="true">M<span></span></span>
        <span>{{ config('app.name') }}</span>
    </a>
    <div class="brand-navigation-links">
        <a href="{{ url('/') }}">Home</a>
        @if(Route::has('pricing') && config('app.env') != 'demo')
            <a href="{{ route('pricing') }}" @if(request()->routeIs('pricing')) aria-current="page" @endif>@lang('superadmin::lang.pricing')</a>
        @endif
        @if(Route::has('cms.contact.us'))
            <a href="{{ route('cms.contact.us') }}">Contact us</a>
        @endif
        @if(!request()->is('login'))
            <a href="{{ route('login') }}">@lang('business.sign_in')</a>
        @endif
        @if(config('constants.SHOW_REPAIR_STATUS_LOGIN_SCREEN') && Route::has('repair-status'))
            <a href="{{ route('repair-status') }}">@lang('repair::lang.repair_status')</a>
        @endif
        @if(Route::has('member_scanner'))
            <a href="{{ action([\Modules\Gym\Http\Controllers\MemberController::class, 'member_scanner']) }}">@lang('gym::lang.gym_member_profile')</a>
        @endif
        @if(config('constants.allow_registration'))
            <a class="brand-register" href="{{ route('business.getRegister', request()->filled('lang') ? ['lang' => request('lang')] : []) }}">@lang('business.register')</a>
        @endif
        @include('layouts.partials.language_btn')
    </div>
</nav>
