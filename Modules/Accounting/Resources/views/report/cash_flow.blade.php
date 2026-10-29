@extends('accounting::layouts.app')
@section('title')
    {{ trans_choice('accounting::general.cash_flow', 1) }}
@endsection

@section('css')
    @include('accounting::report.partials.report_css')
@endsection

@section('content')

    @include('accounting::layouts.nav')
    <!-- Content Header (Page header) -->
    @component('accounting::components.section_header')
        @slot('title')
            {{ trans_choice('accounting::general.accounting', 1) }} {{ trans_choice('accounting::lang.report', 2) }}
        @endslot
        @slot('subtitle')
            {{ trans_choice('accounting::general.cash_flow', 1) }}
        @endslot
    @endcomponent

    <!-- Main content -->
    <section class="content no-print" id="vue-app">
        <div class="row">

            @component('accounting::components.box')
                @slot('header')
                    <div class="box-tools">
                        @component('accounting::components.download_action_button', [
                            'url' => 'report/accounting/cash_flow',
                            'filters' => 'start_date=' . $start_date . '&end_date=' . $end_date . '&location_id=' . $location_id,
                            ])
                        @endcomponent
                    </div>
                @endslot

                @slot('body')
                    <section class="content">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">
                                    {{ trans_choice('accounting::general.cash_statement', 1) }}
                                    @if (!empty($start_date))
                                        {{ trans('accounting::lang.for_period') }}: <b>{{ readable_date($start_date) }}
                                            {{ trans('accounting::lang.to') }}
                                            {{ readable_date($end_date) }}</b>
                                    @endif
                                </h4>
                            </div>
                            <div class="card-body">
                                <form method="get" action="{{ Request::url() }}" class="___class_+?23___">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="control-label"
                                                    for="location_id">{{ trans_choice('accounting::lang.business_location', 1) }}</label>
                                                <select class="form-control select2" name="location_id" id="location_id" required>
                                                    <option value="" disabled selected>{{ trans_choice('accounting::lang.select', 1) }}</option>
                                                    @foreach ($business_locations as $key)
                                                        <option value="{{ $key->id }}" @if ($location_id == $key->id) selected @endif>
                                                            {{ $key->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="control-label"
                                                    for="start_date">{{ trans_choice('accounting::lang.start_date', 1) }}</label>
                                                <input type="text" value="{{ $start_date }}"
                                                    class="form-control datepicker @error('start_date') is-invalid @enderror" name="start_date"
                                                    id="start_date" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="control-label"
                                                    for="end_date">{{ trans_choice('accounting::lang.end_date', 1) }}</label>
                                                <input type="text" value="{{ $end_date }}"
                                                    class="form-control datepicker @error('end_date') is-invalid @enderror" name="end_date"
                                                    id="end_date" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-2 float-right filter-btn-row">
                                            <span class="input-group-btn">
                                                <button type="submit" class="btn bg-olive btn-flat">{{ trans_choice('accounting::lang.submit', 1) }}
                                                </button>
                                            </span>
                                            <span class="input-group-btn">
                                                <a href="{{ Request::url() }}"
                                                    class="btn btn-danger btn-flat pull-right">{{ trans_choice('accounting::lang.clear', 1) }}!</a>
                                            </span>
                                        </div>
                                    </div>
                                </form>

                            </div>
                            <!-- /.box-body -->

                        </div>
                        <!-- /.box -->
                        @if (!empty($start_date))
                            <div style="text-align: center; border: hidden">
                                <h3>{{ trans_choice('accounting::general.cash_flow', 1) }}</h3>
                            </div>
                            @component('accounting::components.tree_view_table', ['table_responsive' => true])
                                <thead>
                                    <tr>
                                        <th>
                                            @if (!empty($location_id) && !empty($data->first()->business_location))
                                                {{ trans_choice('accounting::lang.business_location', 1) }}:
                                                {{ $data->first()->business_location }}
                                            @endif
                                        </th>
                                        <th></th>
                                        <th></th>
                                        <th>{{ trans_choice('accounting::lang.start_date', 1) }}: {{ readable_date($start_date) }}</th>
                                        <th>{{ trans_choice('accounting::lang.end_date', 1) }}: {{ readable_date($end_date) }}</th>
                                    </tr>
                                    <tr>
                                        <th>{{ trans_choice('accounting::lang.account', 1) }}</th>
                                        <th>{{ trans_choice('accounting::general.gl_code', 1) }}</th>
                                        <th style="text-align:right">{{ trans_choice('accounting::general.debit', 1) }}</th>
                                        <th style="text-align:right">{{ trans_choice('accounting::general.credit', 1) }}</th>
                                        <th style="text-align:right">{{ trans_choice('accounting::general.balance', 1) }}</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @php
                                        $parent2_index = 1;
                                    @endphp

                                    @foreach ($account_types as $account_type)
                                        @php
                                            $parent1_index = $parent2_index;
                                        @endphp
                                        <tr class="treegrid-{{ $parent2_index }}">
                                            <td>{{ ucfirst($account_type) }}</td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                        @php
                                            $parent2_index++;
                                            $chart_of_account_type = $data->where('account_type', $account_type);
                                        @endphp

                                        @foreach ($chart_of_account_type as $chart_of_account)
                                            <tr class="treegrid-{{ $parent2_index }} treegrid-parent-{{ $parent1_index }}">
                                                <td style="min-width: 200px">{{ $chart_of_account->name }}</td>
                                                <td>{{ $chart_of_account->gl_code }}</td>
                                                <td style="text-align:right">{{ number_format($chart_of_account->debit, 2) }}</td>
                                                <td style="text-align:right">{{ number_format($chart_of_account->credit, 2) }}</td>
                                                <td style="text-align:right">
                                                    {{ number_format($chart_of_account->credit - $chart_of_account->debit, 2) }}
                                                </td>
                                            </tr>
                                            @php
                                                $parent2_index++;
                                            @endphp
                                        @endforeach

                                        <tr class="font-weight-bold bg-subtotal">
                                            <td>
                                                {{ trans('accounting::lang.total_for') }} {{ $account_type }}
                                            </td>
                                            <td></td>
                                            <td style="text-align:right">{{ number_format($chart_of_account_type->sum('debit'), 2) }}</td>
                                            <td style="text-align:right">{{ number_format($chart_of_account_type->sum('credit'), 2) }}</td>
                                            <td style="text-align:right">
                                                {{ number_format($chart_of_account_type->sum('credit') - $chart_of_account_type->sum('debit'), 2) }}
                                            </td>
                                        </tr>
                                    @endforeach

                                    <tr class="font-weight-bold bg-grand-total">
                                        <td>
                                            {{ trans_choice('accounting::general.net_cash_flow', 1) }}
                                            {{ trans('accounting::general.net_cash_flow_formula') }}
                                        </td>
                                        <td></td>
                                        <td style="text-align:right">{{ number_format($data->sum('debit'), 2) }}</td>
                                        <td style="text-align:right">{{ number_format($data->sum('credit'), 2) }}</td>
                                        <td style="text-align:right">
                                            {{ number_format($data->sum('credit') - $data->sum('debit'), 2) }}
                                        </td>
                                    </tr>
                                </tbody>
                            @endcomponent
                        @endif

                    </section>
                @endslot
            @endcomponent
        </div>
    </section>

@stop
@section('javascript')
    @include('accounting::report.partials.report_js')
@endsection
