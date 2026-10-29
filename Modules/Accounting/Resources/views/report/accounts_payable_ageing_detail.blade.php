@extends('accounting::layouts.app')
@section('title')
    {{ trans_choice('accounting::report.accounts_payable_ageing_detail', 1) }}
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
            {{ trans_choice('accounting::report.accounts_payable_ageing_detail', 1) }}
        @endslot
    @endcomponent

    <!-- Main content -->
    <section class="content no-print" id="vue-app">
        <div class="row">

            @component('accounting::components.box')
                @slot('header')
                    <div class="box-tools">
                        @component('accounting::components.download_action_button', [
                            'url' => 'report/accounting/accounts_payable_ageing_detail',
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
                                    {{ trans_choice('accounting::report.accounts_payable_ageing_detail', 1) }}
                                    @if (!empty($start_date))
                                        {{ trans('accounting::lang.for_period') }}: <b>{{ readable_date($start_date) }}
                                            {{ trans('accounting::lang.to') }}
                                            {{ readable_date($end_date) }}</b>
                                    @endif
                                </h4>
                            </div>
                            <div class="card-body">
                                <form method="get" action="{{ Request::url() }}">
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

                    </section>
                @endslot
            @endcomponent

            @component('accounting::components.box')

                @slot('body')
                    <!-- Main content -->
                    <section class="content no-print">

                        <h3 class="text-center">{{ trans_choice('accounting::report.accounts_payable_ageing_detail', 1) }}</h3>
                        @component('accounting::components.tree_view_table', ['table_responsive' => true])
                            <thead>
                                <tr>
                                    <th>{{ trans_choice('accounting::lang.account', 1) }}</th>
                                    <th>
                                        {{ trans_choice('accounting::lang.account', 1) }}
                                        {{ trans_choice('accounting::general.detail_type', 1) }}
                                    </th>
                                    <th>{{ trans_choice('accounting::lang.date', 1) }}</th>
                                    <th>{{ trans_choice('accounting::lang.transaction', 1) }} {{ trans_choice('accounting::lang.type', 1) }}</th>
                                    <th>{{ trans_choice('accounting::general.reference', 1) }} {{ trans_choice('accounting::lang.no', 1) }}</th>
                                    <th style="text-align:right">{{ trans_choice('accounting::lang.amount', 1) }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @php
                                    $row_counter = 1;
                                @endphp

                                @foreach ($days_passed_options as $days_passed => $days_passed_label)
                                    @php
                                        $parent1_index = $row_counter;
                                    @endphp
                                    <tr class="treegrid-{{ $row_counter }}">
                                        <td>{{ ucfirst($days_passed_label) }}</td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                    @php
                                        $row_counter++;
                                        $chart_of_account_subtype = $data->where('days_passed', $days_passed);
                                        $filtered_account_subtypes = $chart_of_account_subtype->pluck('account_subtype', 'account_subtype_id');
                                    @endphp

                                    @foreach ($filtered_account_subtypes as $account_subtype_id => $account_subtype_name)
                                        @php
                                            $parent2_index = $row_counter;
                                        @endphp
                                        <tr class="treegrid-{{ $row_counter }} treegrid-parent-{{ $parent1_index }}">
                                            <td>
                                                {{ ucfirst($account_subtype_name) }}</td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                        @php
                                            $row_counter++;
                                            $chart_of_account_subtype = $chart_of_account_subtype->where('account_subtype_id', $account_subtype_id);
                                        @endphp

                                        @foreach ($chart_of_account_subtype as $chart_of_account)
                                            <tr class="treegrid-{{ $row_counter }} treegrid-parent-{{ $parent2_index }}">
                                                <td style="min-width: 200px">{{ $chart_of_account->chart_of_account }}</td>
                                                <td>{{ $chart_of_account->account_detail_type }}</td>
                                                <td>{{ readable_date($chart_of_account->transaction_date) }}</td>
                                                <td>{{ $chart_of_account->transaction_type }}</td>
                                                <td>{{ $chart_of_account->ref_no }}</td>
                                                <td style="text-align: right">{{ number_format($chart_of_account->amount_due, 2) }}</td>
                                            </tr>
                                            @php
                                                $row_counter++;
                                            @endphp
                                        @endforeach
                                    @endforeach

                                    <tr class="font-weight-bold bg-subtotal">
                                        <td>
                                            {{ trans('accounting::lang.total_for') }} {{ $days_passed_label }}
                                        </td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td style="text-align:right">{{ number_format($chart_of_account_subtype->sum('amount_due'), 2) }}</td>
                                    </tr>
                                @endforeach

                                <tr class="font-weight-bold bg-grand-total">
                                    <td>
                                        {{ trans_choice('accounting::lang.total', 1) }}
                                    </td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td style="text-align:right">{{ number_format($data->sum('amount_due'), 2) }}</td>
                                </tr>
                            </tbody>
                        @endcomponent

                    </section>
                @endslot
            @endcomponent

        </div>
    </section>
@stop

@section('javascript')
    @include('accounting::report.partials.report_js')
@endsection
