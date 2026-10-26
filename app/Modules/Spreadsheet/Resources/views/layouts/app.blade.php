<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{in_array(session()->get('user.language', config('app.locale')), config('constants.langs_rtl')) ? 'rtl' : 'ltr'}}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- Tell the browser to be responsive to screen width -->
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title') - {{ Session::get('business.name') }}</title>
        @include('layouts.partials.css')
        {{-- spreadsheet --}}
        <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet/dist/plugins/css/pluginsCss.css' />
        <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet/dist/plugins/plugins.css' />
        <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet/dist/css/luckysheet.css' />
        <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet/dist/assets/iconfont/iconfont.css' />
        {{-- /spreadsheet --}}
        <style type="text/css">
            .luckysheet-share-logo, .luckysheet_info_detail_back, .luckysheet_info_detail_update, .luckysheet_info_detail_save {
                display: none !important;
            }
            .spreadsheet {
                position:absolute;
                width:100%;
                height: 85vh;
                top: 20px;
                bottom: 0px;
                right: 0px;
                left: 0px;
                top: 0px;
            }
        </style>
        @yield('css')

    </head>
    <body>
        @yield('content')

        {{-- spreadsheet --}}
        <!-- <script src="https://cdn.jsdelivr.net/npm/luckysheet/dist/plugins/js/plugin.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/luckysheet@2.1.12/dist/luckysheet.umd.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/luckysheet.umd.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/luckyexcel/dist/luckyexcel.umd.js"></script> -->

        <script src="https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/plugins/js/plugin.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/luckysheet@2.1.12/dist/luckysheet.umd.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/luckyexcel/dist/luckyexcel.umd.js"></script>

        {{-- /spreadsheet --}}
        @yield('javascript')
    </body>
</html>
