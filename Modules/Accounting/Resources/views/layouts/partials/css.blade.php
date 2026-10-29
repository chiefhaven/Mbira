<link rel="stylesheet" href="{{ asset('css/vendor.css?v=' . $asset_v) }}">

@if (in_array(session()->get('user.language', config('app.locale')), config('constants.langs_rtl')))
    <link rel="stylesheet" href="{{ asset('css/rtl.css?v=' . $asset_v) }}">
@endif

@yield('css')

<!-- app css -->
<link rel="stylesheet" href="{{ asset('css/app.css?v=' . $asset_v) }}">

{{-- vue select style --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/vue-select/3.10.0/vue-select.min.css"
    integrity="sha512-p9Rnc/ifofEKT1/zS29PIGEYYzefru0lLszFCpKJUvlEEaR15qXkE220qFZcQ77useHPZ+++e7ufPqrb1Cbr7Q==" crossorigin="anonymous"
    referrerpolicy="no-referrer" />

<link rel="stylesheet" href="{{ Module::asset('accounting:css/theme.custom.css') }}">

<link rel="stylesheet" href="{{ Module::asset('accounting:css/plugins/bootstrap.custom.css') }}">

@if (isset($pos_layout) && $pos_layout)
    <style type="text/css">
        .content {
            padding-bottom: 0px !important;
        }

    </style>
@endif
<style type="text/css">
    /*
 * Pattern lock css
 * Pattern direction
 * http://ignitersworld.com/lab/patternLock.html
 */
    .patt-wrap {
        z-index: 10;
    }

    .patt-circ.hovered {
        background-color: #cde2f2;
        border: none;
    }

    .patt-circ.hovered .patt-dots {
        display: none;
    }

    .patt-circ.dir {
        background-image: url("{{ asset('/img/pattern-directionicon-arrow.png') }}");
        background-position: center;
        background-repeat: no-repeat;
    }

    .patt-circ.e {
        -webkit-transform: rotate(0);
        transform: rotate(0);
    }

    .patt-circ.s-e {
        -webkit-transform: rotate(45deg);
        transform: rotate(45deg);
    }

    .patt-circ.s {
        -webkit-transform: rotate(90deg);
        transform: rotate(90deg);
    }

    .patt-circ.s-w {
        -webkit-transform: rotate(135deg);
        transform: rotate(135deg);
    }

    .patt-circ.w {
        -webkit-transform: rotate(180deg);
        transform: rotate(180deg);
    }

    .patt-circ.n-w {
        -webkit-transform: rotate(225deg);
        transform: rotate(225deg);
    }

    .patt-circ.n {
        -webkit-transform: rotate(270deg);
        transform: rotate(270deg);
    }

    .patt-circ.n-e {
        -webkit-transform: rotate(315deg);
        transform: rotate(315deg);
    }

</style>
@if (!empty($__system_settings['additional_css']))
    {!! $__system_settings['additional_css'] !!}
@endif
