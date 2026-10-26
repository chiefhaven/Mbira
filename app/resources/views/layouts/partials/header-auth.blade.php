@inject('request', 'Illuminate\Http\Request')

<div class="container-fluid">

    <!-- Language changer -->
    <div class="row">

        <div class="tw-absolute tw-top-2 md:tw-top-5 tw-left-4 md:tw-left-8 tw-flex tw-items-center tw-gap-4"
            style="text-align: left">
            <a href="{{ url('/') }}">
                <div
                    class="lg:tw-w-16 md:tw-h-16 tw-w-12 tw-h-12 tw-flex tw-items-center tw-justify-center tw-mx-auto tw-overflow-hidden tw-p-0.5 tw-mb-4">
                    <img src="{{ asset('img/logo-small.png') }}" alt="lock" class="tw-object-fill opacity-50" />
                </div>
            </a>

            @if(config('constants.SHOW_REPAIR_STATUS_LOGIN_SCREEN') && Route::has('repair-status'))
                <a class="tw-text-white tw-font-medium tw-text-sm md:tw-text-base hover:tw-text-white"
                    href="{{ action([\Modules\Repair\Http\Controllers\CustomerRepairStatusController::class, 'index']) }}">
                    @lang('repair::lang.repair_status')
                </a>
            @endif
                        
            @if(Route::has('member_scanner'))
                <a class="tw-text-white tw-font-medium tw-text-sm md:tw-text-base hover:tw-text-white"
                    href="{{ action([\Modules\Gym\Http\Controllers\MemberController::class, 'member_scanner']) }}">
                    @lang('gym::lang.gym_member_profile')
                </a>
            @endif
        </div>
        <div class="tw-absolute tw-top-3 md:tw-top-8 tw-right-4 md:tw-right-10 tw-flex tw-items-center tw-gap-4 md:tw-gap-10"
            style="text-align: left">
            @if (!($request->segment(1) == 'business' && $request->segment(2) == 'register') && $request->segment(1) != 'login')
                <a class="tw-text-white tw-font-medium tw-text-sm md:tw-text-base hover:tw-text-white"
                    href="{{ action([\App\Http\Controllers\Auth\LoginController::class, 'login']) }}@if (!empty(request()->lang)) {{ '?lang=' . request()->lang }} @endif">{{ __('business.sign_in') }}</a>
            @endif
            <!-- Register  -->
            <div
                class="tw-border-2 tw-border-white tw-rounded-full tw-h-10 md:tw-h-12 tw-w-24 tw-flex tw-items-center tw-justify-center">
                @if (!($request->segment(1) == 'business' && $request->segment(2) == 'register'))

                    <!-- Register Url -->
                    @if (config('constants.allow_registration'))
                        <a href="{{route('business.getRegister')}}@if(!empty(request()->lang)){{'?lang='.request()->lang}}@endif"
                            class="tw-text-white tw-font-medium tw-text-sm md:tw-text-base hover:tw-text-white">{{ __('business.register') }}
                        </a>

                        <!-- pricing url -->
                        @if (Route::has('pricing') && config('app.env') != 'demo' && $request->segment(1) != 'pricing')
                            <a class="tw-text-white tw-font-medium tw-text-sm md:tw-text-base hover:tw-text-white"
                                href="{{ action([\Modules\Superadmin\Http\Controllers\PricingController::class, 'index']) }}">@lang('superadmin::lang.pricing')</a>
                        @endif
                    @endif
                @endif
            </div>
            @include('layouts.partials.language_btn')
        </div>
    </div>
</div>
