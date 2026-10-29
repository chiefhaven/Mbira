@extends('accounting::layouts.app')
@section('title')
    @yield('tab-title')
@endsection

@section('content')

    @include('accounting::layouts.nav')
    {{-- Content Header (Page header) --}}
    <section class="content-header">
        <h1>
            {{ trans_choice('accounting::general.accounting', 1) }} {{ trans_choice('accounting::lang.settings', 1) }}
        </h1>
    </section>

    <div id="vue-app-with-modal">
        {{-- Main content --}}
        <section class="content">
            <div class="row">
                <div class="col-xs-12">
                    {{-- <pos-tab-container> --}}
                    <div class="col-xs-12 pos-tab-container">
                        <div class="col-lg-2 col-md-2 col-sm-2 col-xs-2 pos-tab-menu">
                            {{-- <div class="list-group"> --}}
                            <div class="list-group-link">
                                @php
                                    $nav_items = [
                                        'accounting/settings/account_subtypes' => ' ' . trans_choice('accounting::general.account_subtype', 2),
                                        'accounting/settings/detail_types' => ' ' . trans_choice('accounting::general.detail_type', 2),
                                    ];
                                    
                                    function isActiveTab($url)
                                    {
                                        $first_three_segments = request()->segment(1) . '/' . request()->segment(2) . '/' . request()->segment(3);
                                        return $first_three_segments == $url;
                                    }
                                @endphp

                                {{-- Loop through the nav items to print the side nav --}}
                                @foreach ($nav_items as $url => $label)
                                    <a href="{{ url($url) }}"
                                        class="list-group-item text-center @if (isActiveTab($url)) active @endif">
                                        {{ $label }}
                                    </a>
                                @endforeach

                            </div>
                        </div>
                        <div class="col-lg-10 col-md-10 col-sm-10 col-xs-10 pos-tab">
                            @yield('tab-content')
                        </div>
                    </div>
                    {{-- </pos-tab-container> --}}
                </div>
            </div>
        </section>
        {{-- /.content --}}

        @yield('tab-modal-content')
    </div>

@stop
@section('javascript')
    @yield('tab-javascript')
@endsection
