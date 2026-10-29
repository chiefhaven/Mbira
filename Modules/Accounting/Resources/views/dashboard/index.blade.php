@extends('accounting::layouts.app')
@section('title', __('accounting::lang.accounting') . ' ' . __('business.dashboard'))

@section('content')
    @include('accounting::layouts.nav')
    <!-- Content Header (Page header) -->
    <section class="content-header no-print">
        <h1>
            @lang('accounting::lang.accounting')
            <small>@lang('business.dashboard')</small>
        </h1>
    </section>
    <!-- Main content -->
    <section class="content no-print">

        <div class="row">

            <!-- ./col -->
            <div class="col-lg-4 col-xs-6">
                <!-- small box -->
                <div class="small-box bg-red">
                    <div class="inner">
                        <h4><strong><span class="no_charts_of_account">&nbsp;</span></strong></h4>
                        <p>{{ __('accounting::lang.charts_of_accounts') }}</p>
                    </div>
                    <div class="icon">
                        <i class="ion ion-pie-graph"></i>
                    </div>
                    <a href="{{ url('accounting/chart_of_account') }}" class="small-box-footer">@lang('accounting::lang.more_info') <i
                            class="fa fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <!-- ./col -->

            <div class="col-lg-4 col-xs-6">
                <!-- small box -->
                <div class="small-box bg-aqua">
                    <div class="inner">
                        <h4><strong><span class="no_journal_entries">&nbsp;</span></strong></h4>
                        <p>{{ __('accounting::lang.journal_of_entries') }}</p>
                    </div>
                    <div class="icon">
                        <i class="ion ion-stats-bars"></i>
                    </div>
                    <a href="{{ url('accounting/journal_entry') }}" class="small-box-footer">@lang('accounting::lang.more_info') <i
                            class="fa fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <!-- ./col -->


            <!-- /.col -->
            @php
                $accounting_transactions_index_page = get_accounting_transactions_index();
            @endphp
            @if (!empty($accounting_transactions_index_page))
                <div class="col-lg-4 col-xs-6">
                    <div class="small-box bg-green">
                        <div class="inner">
                            <h4><strong><span class="all_transactions">&nbsp;</span></strong></h4>
                            <p>{{ __('accounting::lang.list_of_all_transactions') }}</p>
                        </div>
                        <div class="icon">
                            <i class="ion ion-stats-bars"></i>
                        </div>
                        <a href="{{ url($accounting_transactions_index_page) }}" class="small-box-footer">@lang('accounting::lang.more_info')<i
                                class="fa fa-arrow-circle-right"></i></a>
                    </div>
                </div>
            @endif
            <!-- ./col -->

            {{-- <div class="col-lg-4 col-xs-6">
                <!-- small box -->
                <div class="small-box bg-yellow">
                    <div class="inner">
                        <h4><strong><span class="">&nbsp;</span></strong></h4>
                        <p>{{ __('accounting::lang.transfer') }}</p>
                    </div>
                    <div class="icon">
                        <i class="ion ion-person-add"></i>
                    </div>
                    <a href="#" class="small-box-footer">@lang('accounting::lang.more_info') <i class="fa fa-arrow-circle-right"></i></a>
                </div>
            </div> --}}
            <!-- ./col -->

            <!-- ./col -->
            {{-- <div class="col-lg-4 col-xs-6">
                <!-- small box -->
                <div class="small-box bg-red">
                    <div class="inner">
                        <h4><strong><span class="">&nbsp;</span></strong></h4>
                        <p>{{ __('accounting::lang.total_profit_loss') }}</p>
                    </div>
                    <div class="icon">
                        <i class="ion ion-pie-graph"></i>
                    </div>
                    <a href="#" class="small-box-footer">@lang('accounting::lang.more_info') <i class="fa fa-arrow-circle-right"></i></a>
                </div>
            </div> --}}
            <!-- ./col -->


            {{-- <div class="col-lg-4 col-xs-6">
                <!-- small box -->
                <div class="small-box bg-aqua">
                    <div class="inner">
                        <h4><strong><span>&nbsp;</span></strong></h4>
                        <p>{{ __('accounting::lang.total_expense') }}</p>
                    </div>
                    <div class="icon">
                        <i class="ion ion-stats-bars"></i>
                    </div>
                    <a href="{{ action('ExpenseController@index') }}" class="small-box-footer">
                        @lang('accounting::lang.more_info') <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div> --}}
            <!-- ./col -->


            <!-- /.col -->

            {{-- <div class="col-lg-4 col-xs-6">
                <div class="small-box bg-green">
                    <div class="inner">
                        <h4><strong><span class="total_income">&nbsp;</span></strong></h4>
                        <p>{{ __('accounting::lang.total_incomes') }}</p>
                    </div>
                    <div class="icon">
                        <i class="ion ion-stats-bars"></i>
                    </div>
                    <a href="{{ action('IncomeController@index') }}" class="small-box-footer">@lang('accounting::lang.more_info')<i class="fa fa-arrow-circle-right"></i></a>
                </div>
            </div> --}}
            <!-- ./col -->

            {{-- <div class="col-lg-4 col-xs-6">
                <!-- small box -->
                <div class="small-box bg-yellow">
                    <div class="inner">
                        <h4><strong><span class="">&nbsp;</span></strong></h4>
                        <p>{{ __('accounting::lang.total_cash_flow') }}</p>
                    </div>
                    <div class="icon">
                        <i class="ion ion-person-add"></i>
                    </div>
                    <a href="#" class="small-box-footer">@lang('accounting::lang.more_info') <i class="fa fa-arrow-circle-right"></i></a>
                </div>
            </div> --}}
            <!-- ./col -->


        </div>

        <div class="row">
            <div class="col-md-12">
                @component('accounting::components.box')
                    @slot('title')
                        {{ trans('lang_v1.balance') }} {{ trans('accounting::lang.by') }}
                        {{ trans('account.account_type') }}
                    @endslot

                    @slot('body')
                        <div class="col-md-6">
                            <table class="table table-striped table-condensed table-hover">
                                <thead>
                                    <tr>
                                        <th>
                                            {{ trans_choice('accounting::lang.account', 1) }} {{ trans_choice('accounting::lang.type', 1) }}
                                        </th>
                                        <th>
                                            {{ trans('accounting::lang.current') }}
                                            {{ trans_choice('accounting::lang.balance', 1) }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($account_types as $account_type)
                                        <tr>
                                            <td>
                                                <span>{{ trans_choice('accounting::general.' . $account_type, 1) }}</span>
                                            </td>

                                            <td>
                                                <span>{{ currency_code() }}
                                                    {{ $chart_of_accounts->where('account_type', $account_type)->sum('current_balance') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <canvas id="balance_summary_chart"></canvas>
                        </div>
                    @endslot
                @endcomponent
            </div>

        </div>

        <div class="row">
            <div class="col-md-12">
                @component('accounting::components.widget', ['class' => 'box-primary'])
                    {!! $expense_chart->container() !!}
                @endcomponent
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                @component('accounting::components.widget', ['class' => 'box-primary'])
                    {!! $current_financial_year_chart->container() !!}
                @endcomponent
            </div>
            <div class="col-md-6">
                @component('accounting::components.widget', ['class' => 'box-primary'])
                    {!! $last_30_days_financial_year_chart->container() !!}
                @endcomponent
            </div>
        </div>

    </section>


@stop

@section('javascript')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="{{ Module::asset('accounting:js/accounting.js') }}"></script>
    <script src="{{ asset('js/report.js?v=' . $asset_v) }}"></script>
    {!! $expense_chart->script() !!}
    {!! $current_financial_year_chart->script() !!}
    {!! $last_30_days_financial_year_chart->script() !!}
    <script>
        const data = {
            labels: {!! $balance_summary_chart->labels !!},
            datasets: [{
                label: 'My First Dataset',
                data: {!! $balance_summary_chart->values !!},
                backgroundColor: [
                    'rgb(255, 99, 132)',
                    'rgb(54, 162, 235)',
                    'rgb(255, 205, 86)',
                    '#00a65a',
                    '#605ca8',
                ],
                hoverOffset: 4
            }],
        };

        const config = {
            type: 'pie',
            data: data,
        };

        const myChart = new Chart(
            document.getElementById('balance_summary_chart'),
            config
        );
    </script>
@endsection
