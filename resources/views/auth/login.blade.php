@extends('layouts.auth2')
@section('title', __('lang_v1.login'))
@inject('request', 'Illuminate\Http\Request')
@section('content')
    @php
        $username = old('username');
        $password = null;
        if (config('app.env') == 'demo') {
            $username = 'admin';
            $password = '123456';

            $demo_types = [
                'all_in_one' => 'admin',
                'super_market' => 'admin',
                'pharmacy' => 'admin-pharmacy',
                'electronics' => 'admin-electronics',
                'services' => 'admin-services',
                'restaurant' => 'admin-restaurant',
                'superadmin' => 'superadmin',
                'woocommerce' => 'woocommerce_user',
                'essentials' => 'admin-essentials',
                'manufacturing' => 'manufacturer-demo',
            ];

            if (!empty($_GET['demo_type']) && array_key_exists($_GET['demo_type'], $demo_types)) {
                $username = $demo_types[$_GET['demo_type']];
            }
        }
    @endphp
    <div class="login-shell">
        <div class="login-grid">
            <div class="login-side-panel">
                @if (config('app.env') == 'demo')
                    @component('components.widget', [
                        'class' => 'box-primary login-demo-widget',
                        'header' =>
                            '<h4 class="text-center">Demo Shops <small><i> <br/>Demos are for example purpose only, this application <u>can be used in many other similar businesses.</u></i> <br/><b>Click button to login that business</b></small></h4>',
                    ])
                        <a href="?demo_type=all_in_one" class="btn btn-app bg-olive demo-login" data-toggle="tooltip"
                            title="Showcases all feature available in the application."
                            data-admin="{{ $demo_types['all_in_one'] }}"> <i class="fas fa-star"></i> All In One</a>

                        <a href="?demo_type=pharmacy" class="btn bg-maroon btn-app demo-login" data-toggle="tooltip"
                            title="Shops with products having expiry dates." data-admin="{{ $demo_types['pharmacy'] }}"><i
                                class="fas fa-medkit"></i>Pharmacy</a>

                        <a href="?demo_type=services" class="btn bg-orange btn-app demo-login" data-toggle="tooltip"
                            title="For all service providers like Web Development, Restaurants, Repairing, Plumber, Salons, Beauty Parlors etc."
                            data-admin="{{ $demo_types['services'] }}"><i class="fas fa-wrench"></i>Multi-Service Center</a>

                        <a href="?demo_type=electronics" class="btn bg-purple btn-app demo-login" data-toggle="tooltip"
                            title="Products having IMEI or Serial number code." data-admin="{{ $demo_types['electronics'] }}"><i
                                class="fas fa-laptop"></i>Electronics & Mobile Shop</a>

                        <a href="?demo_type=super_market" class="btn bg-navy btn-app demo-login" data-toggle="tooltip"
                            title="Super market & Similar kind of shops." data-admin="{{ $demo_types['super_market'] }}"><i
                                class="fas fa-shopping-cart"></i> Super Market</a>

                        <a href="?demo_type=restaurant" class="btn bg-red btn-app demo-login" data-toggle="tooltip"
                            title="Restaurants, Salons and other similar kind of shops."
                            data-admin="{{ $demo_types['restaurant'] }}"><i class="fas fa-utensils"></i> Restaurant</a>
                        <hr>

                        <i class="icon fas fa-plug"></i> Premium optional modules:<br><br>

                        <a href="?demo_type=superadmin" class="btn bg-red-active btn-app demo-login" data-toggle="tooltip"
                            title="SaaS & Superadmin extension Demo" data-admin="{{ $demo_types['superadmin'] }}"><i
                                class="fas fa-university"></i> SaaS / Superadmin</a>

                        <a href="?demo_type=woocommerce" class="btn bg-woocommerce btn-app demo-login" data-toggle="tooltip"
                            title="WooCommerce demo user - Open web shop in minutes!!" style="color:white !important"
                            data-admin="{{ $demo_types['woocommerce'] }}"> <i class="fab fa-wordpress"></i> WooCommerce</a>

                        <a href="?demo_type=essentials" class="btn bg-navy btn-app demo-login" data-toggle="tooltip"
                            title="Essentials & HRM (human resource management) Module Demo" style="color:white !important"
                            data-admin="{{ $demo_types['essentials'] }}">
                            <i class="fas fa-check-circle"></i>
                            Essentials & HRM</a>

                        <a href="?demo_type=manufacturing" class="btn bg-orange btn-app demo-login" data-toggle="tooltip"
                            title="Manufacturing module demo" style="color:white !important"
                            data-admin="{{ $demo_types['manufacturing'] }}">
                            <i class="fas fa-industry"></i>
                            Manufacturing Module</a>

                        <a href="?demo_type=superadmin" class="btn bg-maroon btn-app demo-login" data-toggle="tooltip"
                            title="Project module demo" style="color:white !important"
                            data-admin="{{ $demo_types['superadmin'] }}">
                            <i class="fas fa-project-diagram"></i>
                            Project Module</a>

                        <a href="?demo_type=services" class="btn btn-app demo-login" data-toggle="tooltip"
                            title="Advance repair module demo" style="color:white !important; background-color: #bc8f8f"
                            data-admin="{{ $demo_types['services'] }}">
                            <i class="fas fa-wrench"></i>
                            Advance Repair Module</a>

                        <a href="{{ url('docs') }}" target="_blank" class="btn btn-app" data-toggle="tooltip"
                            title="Advance repair module demo" style="color:white !important; background-color: #2dce89">
                            <i class="fas fa-network-wired"></i>
                            Connector Module / API Documentation</a>
                    @endcomponent
                @endif
            </div>

            <div class="login-main-panel">
                <div class="login-card">
                    <div class="login-header-block">
                        <h1>@lang('lang_v1.welcome_back')</h1>
                        <h2>@lang('lang_v1.login_to_your') {{ config('app.name', 'ultimatePOS') }}</h2>
                    </div>

                    <form method="POST" action="{{ route('login') }}" id="login-form">
                        {{ csrf_field() }}

                        <div class="form-group has-feedback {{ $errors->has('username') ? ' has-error' : '' }}">
                            <label class="tw-dw-form-control">
                                <div class="tw-dw-label">
                                    <span class="tw-text-xs md:tw-text-sm tw-font-medium tw-text-black">@lang('business.username')</span>
                                </div>

                                <input
                                    class="tw-border tw-border-[#D1D5DA] tw-outline-none tw-h-12 tw-bg-transparent tw-rounded-lg tw-px-3 tw-font-medium tw-text-black placeholder:tw-text-gray-500 placeholder:tw-font-medium"
                                    id="username" type="text" name="username" value="{{ $username }}" required
                                    autocomplete="username" placeholder="@lang('business.username')" />
                            </label>
                            @if ($errors->has('username'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('username') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="form-group has-feedback {{ $errors->has('password') ? ' has-error' : '' }}">
                            <label class="tw-dw-form-control">
                                <div class="tw-dw-label">
                                    <span class="tw-text-xs md:tw-text-sm tw-font-medium tw-text-black">@lang('lang_v1.password')</span>
                                </div>

                                <input
                                    class="tw-border tw-border-[#D1D5DA] tw-outline-none tw-h-12 tw-bg-transparent tw-rounded-lg tw-px-3 tw-font-medium tw-text-black placeholder:tw-text-gray-500 placeholder:tw-font-medium"
                                    id="password" type="password" name="password" value="{{ $password }}" required
                                    placeholder="@lang('lang_v1.password')" />
                                <button type="button" id="show_hide_icon" class="show_hide_icon" style="position: absolute; top:48px;right:5px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-eye tw-w-6" viewBox="0 0 24 24" stroke-width="1.5" stroke="#000000" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                                        <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" />
                                    </svg>
                                </button>
                            </label>
                            @if ($errors->has('password'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('password') }}</strong>
                                </span>
                            @endif
                        </div>

                        <button type="submit"
                            class="tw-bg-blue-600 tw-h-11 tw-rounded-lg tw-text-sm md:tw-text-base tw-text-white tw-font-semibold tw-w-full tw-max-w-full mt-2 hover:tw-bg-blue-700 focus:tw-outline-none focus:tw-ring-2 focus:tw-ring-blue-500 focus:tw-ring-offset-2 active:tw-bg-blue-800">
                            @lang('lang_v1.enter')
                        </button>
                    </form>
                </div>

                <small class="login-support-text">
                    Supported by
                    <a href="https://havenplustechnologies.co.mw" target="_blank">
                        HavenPlus Technologies Ltd.
                    </a>
                </small>
            </div>

        </div>
    </div>
@stop