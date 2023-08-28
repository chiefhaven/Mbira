<!doctype html>
<html lang="en">
    <head>

        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <!-- custom metas -->
        @if(!empty($__site_details['meta_tags']))
            {!!$__site_details['meta_tags']!!}
        @endif

        @yield('meta')

        <!-- font awesome 5 free -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css"/>
        <!-- Bootstrap 5 -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"/>

        <!-- Your Custom CSS file that will include your blocks CSS -->
        <link rel="stylesheet" type="text/css" href="{{ asset('modules/cms/css/cms.css?v=' . $asset_v) }}">
        <script src="https://unpkg.com/tua-body-scroll-lock"></script>
        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title') | {{config('app.name', 'ultimatePOS')}}</title>
        <!-- custom css code -->
        @if(!empty($__site_details['custom_css']))
            {!!$__site_details['custom_css']!!}
        @endif

        <!-- in app chat widget css -->
        @if(
            isset($__site_details['chat']) && 
            isset($__site_details['chat']['enable']) && 
            $__site_details['chat']['enable'] == 'in_app_chat'
        )
            @includeIf('cms::components.chat_widget.css.chat-widget-style.chat_widget-style1')
            @includeIf('cms::components.chat_widget.css.chat-widget-colors.color-green')
        @endif

        @yield('css')
        <style type="text/css">
            .far.fa-comments{
                padding-top: 3px !important;
                font-size: 25px !important;
            }
        </style>
    </head>
    <body>
        @yield('content')

        @if(
            isset($__site_details['chat']) && 
            isset($__site_details['chat']['enable']) && 
            $__site_details['chat']['enable'] == 'in_app_chat'
        )
            @includeIf('cms::components.chat_widget.chat_widget')
        @endif

        @includeIf('cms::frontend.layouts.footer')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.9.2/umd/popper.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://unpkg.com/tua-body-scroll-lock"></script>
        <script src="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.0.7/dist/js/splide.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/sticky-js/1.3.0/sticky.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
        <script src="{{ asset('modules/cms/js/cms.js?v=' . $asset_v) }}"></script>

        <!-- Google analytics code -->
        @if(!empty($__site_details['google_analytics']))
            {!!$__site_details['google_analytics']!!}
        @endif

        <!-- facebook pixel code -->
        @if(!empty($__site_details['fb_pixel']))
            {!!$__site_details['fb_pixel']!!}
        @endif

        <!-- custom js -->
        @if(!empty($__site_details['custom_js']))
            {!!$__site_details['custom_js']!!}
        @endif

        <!-- 3rd party chat_widget -->
        @if(
            (
                isset($__site_details['chat']) && 
                isset($__site_details['chat']['enable']) && 
                $__site_details['chat']['enable'] == 'other' &&
                !empty($__site_details['chat_widget'])
            ) ||
            (
                !isset($__site_details['chat']) &&
                empty($__site_details['chat']) &&
                !empty($__site_details['chat_widget'])
            )
        )
            {!!$__site_details['chat_widget']!!}
        @endif
        <!-- in app chat js -->
        @if(
            isset($__site_details['chat']) && 
            isset($__site_details['chat']['enable']) && 
            $__site_details['chat']['enable'] == 'in_app_chat'
        )
            @includeIf('cms::components.chat_widget.js.chat_widget-style1')
        @endif
        @yield('javascript')
    </body>
</html>