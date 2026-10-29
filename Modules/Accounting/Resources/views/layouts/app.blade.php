@inject('request', 'Illuminate\Http\Request')

@if ($request->segment(1) == 'pos' && ($request->segment(2) == 'create' || $request->segment(3) == 'edit'))
    @php
        $pos_layout = true;
    @endphp
@else
    @php
        $pos_layout = false;
    @endphp
@endif

@php
$whitelist = ['127.0.0.1', '::1'];
@endphp

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}"
    dir="{{ in_array(session()->get('user.language', config('app.locale')), config('constants.langs_rtl')) ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

    <link rel="shortcut icon" href="{{ asset('img/favicon.svg') }}" type="image/x-icon">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    {{-- is_admin() --}}
    <meta name="is_admin" content="{{ is_admin() }}">

    <title>@yield('title') - {{ Session::get('business.name') }}</title>

    @include('accounting::layouts.partials.css')

    {{-- Vue cdn --}}
    <script src="https://cdn.jsdelivr.net/npm/vue@2.6.14"></script>

    @yield('css')
</head>

<body class="@if ($pos_layout) hold-transition lockscreen @else hold-transition 
        @if (!empty(session('business.theme_color')))
    {{ 'skin-' . session('business.theme_color') }}@else{{ 'skin-blue-light' }} @endif sidebar-mini @endif">
    <div class="wrapper thetop">
        <script type="text/javascript">
            if (localStorage.getItem("upos_sidebar_collapse") == 'true') {
                var body = document.getElementsByTagName("body")[0];
                body.className += " sidebar-collapse";
            }
        </script>
        @if (!$pos_layout)
            @include('layouts.partials.header')
            @include('layouts.partials.sidebar')
        @else
            @include('layouts.partials.header-pos')
        @endif

        @if (in_array($_SERVER['REMOTE_ADDR'], $whitelist))
            <input type="hidden" id="__is_localhost" value="true">
        @endif

        <!-- Content Wrapper. Contains page content -->
        <div class="@if (!$pos_layout) content-wrapper @endif">
            <!-- empty div for vuejs -->
            <div id="app">
                @yield('vue')
            </div>

            <!-- Add currency related field-->
            <input type="hidden" id="__code" value="{{ session('currency')['code'] }}">
            <input type="hidden" id="__symbol" value="{{ session('currency')['symbol'] }}">
            <input type="hidden" id="__thousand" value="{{ session('currency')['thousand_separator'] }}">
            <input type="hidden" id="__decimal" value="{{ session('currency')['decimal_separator'] }}">
            <input type="hidden" id="__symbol_placement" value="{{ session('business.currency_symbol_placement') }}">
            <input type="hidden" id="__precision" value="{{ config('constants.currency_precision', 2) }}">
            <input type="hidden" id="__quantity_precision" value="{{ config('constants.quantity_precision', 2) }}">
            <!-- End of currency related field-->

            @include('accounting::layouts.partials.alert-feedback')

            @yield('content')

            <div class='scrolltop no-print'>
                <div class='scroll icon'><i class="fas fa-angle-up"></i></div>
            </div>

            @if (config('constants.iraqi_selling_price_adjustment'))
                <input type="hidden" id="iraqi_selling_price_adjustment">
            @endif

            <!-- This will be printed -->
            <section class="invoice print_section" id="receipt_section">
            </section>

        </div>
        @include('home.todays_profit_modal')
        <!-- /.content-wrapper -->

        @if (!$pos_layout)
            @include('layouts.partials.footer')
        @else
            @include('layouts.partials.footer_pos')
        @endif
    </div>

    @if (!empty($__additional_html))
        {!! $__additional_html !!}
    @endif

    @include('accounting::layouts.partials.javascripts')

    <div class="modal fade view_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>

    @if (!empty($__additional_views) && is_array($__additional_views))
        @foreach ($__additional_views as $additional_view)
            @includeIf($additional_view)
        @endforeach
    @endif
</body>

</html>
