@extends('accounting::layouts.app')
@section('title')
    @yield('tab-title')
@endsection

@section('content')

    @include('accounting::layouts.nav')
    {{-- Content Header (Page header) --}}
    <section class="content-header">
        <h1>
            @yield('tab-title')
        </h1>
    </section>

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
                                    'accounting/transactions/sales?type=payment' => trans_choice('accounting::general.sales', 2),
                                    'accounting/transactions/expenses' => trans_choice('accounting::general.expense', 2),
                                    'accounting/transactions/purchases?type=purchase_order' => trans_choice('accounting::general.purchase', 2),
                                ];
                                
                                function isActiveTab($url)
                                {
                                    $first_two_segments = request()->segment(1) . '/' . request()->segment(2) . '/' . request()->segment(3);
                                    return $first_two_segments == getUrlWithoutParams($url);
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
                @yield('modal-content')
            </div>
        </div>
    </section>
    {{-- /.content --}}
@stop
@section('javascript')
    @yield('tab-javascript')
    <script>
        const hello = new Vue({
            el: '#mapTransactionsModal',
            data() {
                return {
                    chart_of_accounts: {!! json_encode($chart_of_accounts) !!},
                    chart_of_account_id: '{{ old('chart_of_account_id') }}'
                }
            },
        })
    </script>
@endsection
