@extends('accounting::layouts.app')
@section('title')
    {{ trans_choice('accounting::report.budget_overview', 1) }}
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
            {{ trans_choice('accounting::report.budget_overview', 1) }}
        @endslot
    @endcomponent

    <!-- Main content -->
    <section class="content no-print" id="vue-app">
        <div class="row">

            @component('accounting::components.box')
                @slot('header')
                    <div class="box-tools">
                        @component('accounting::components.download_action_button', [
                            'url' => 'report/accounting/budget_overview',
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
                                    {{ trans_choice('accounting::report.budget_overview', 1) }}
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

                    </section>
                @endslot
            @endcomponent

            @component('accounting::components.box')
                @slot('body')
                    @component('accounting::components.tree_view_table')
                        <tbody>
                            <tr>
                                <td colspan="13">
                                    <div class="treegrid-container"><span class="treegrid-expander"></span>
                                        <h4 class="text-center no-margin-top-20 no-margin-left-24">
                                            {{ trans_choice('accounting::report.budget_overview', 1) }}
                                        </h4>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="13">
                                    <div class="treegrid-container"><span class="treegrid-expander"></span>
                                        <p class="text-center no-margin-top-20 no-margin-left-24">{{ readable_date($start_date) }} -
                                            {{ readable_date($end_date) }}</p>
                                    </div>
                                </td>
                            </tr>
                            <tr class="tr_header">
                                <td class="text-bold">
                                    <div class="treegrid-container"><span class="treegrid-expander"></span>Date</div>
                                </td>
                                <td class="text-bold">Transaction type</td>
                                <td class="text-bold">Customer</td>
                                <td class="text-bold">Description</td>
                                <td class="text-bold">Split</td>
                                <td class="total_amount text-bold">Amount</td>
                                <td class="total_amount text-bold">Balance</td>
                            </tr>
                            <tr class="treegrid-2  parent-node expanded">
                                <td class="parent">
                                    Accounts Receivable (A/R)
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr class="treegrid-8  tr_total font-weight-bold">
                                <td>
                                    Accounts Receivable (A/R)
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="total_amount">
                                    {{ $currency_code }} 0.00
                                </td>
                                <td></td>
                            </tr>
                            <tr class="treegrid-10  parent-node expanded">
                                <td class="parent">
                                    Cash and cash equivalents
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr class="treegrid-11 treegrid-parent-10">
                                <td>
                                    {{ readable_date($start_date) }}
                                </td>
                                <td>
                                    Journal Entry
                                </td>
                                <td>

                                </td>
                                <td>
                                    Purchased Rickshaw for cash
                                </td>
                                <td>
                                    Split
                                </td>
                                <td class="total_amount">
                                    {{ $currency_code }} 1,000.00
                                </td>
                                <td class="total_amount">
                                    {{ $currency_code }} 1,000.00
                                </td>
                            </tr>
                            <tr class="treegrid-15 tr_total font-weight-bold">
                                <td class="parent">
                                    Total for Cash and cash equivalents
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="total_amount">
                                    {{ $currency_code }} 1,000.00
                                </td>
                                <td></td>
                            </tr>
                            <tr class="treegrid-21  parent-node expanded">
                                <td class="parent">
                                    Inventory
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr class="treegrid-25  tr_total font-weight-bold">
                                <td>
                                    Total for Inventory
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="total_amount">
                                    {{ $currency_code }} 0.00
                                </td>
                                <td></td>
                            </tr>
                            <tr class="treegrid-26  parent-node expanded">
                                <td class="parent">
                                    Inventory Asset
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr class="treegrid-30  tr_total font-weight-bold">
                                <td>
                                    Total for Inventory Asset
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="total_amount">
                                    {{ $currency_code }} 0.00
                                </td>
                                <td></td>
                            </tr>
                            <tr class="treegrid-31  parent-node expanded">
                                <td class="parent">
                                    Uncategorised Asset
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr class="treegrid-32 treegrid-parent-31">
                                <td>
                                    <div class="treegrid-container" style="margin-left: 47.9861px;"><span class="treegrid-expander"></span>
                                        {{ readable_date($start_date) }}
                                    </div>
                                </td>
                                <td>
                                    Journal Entry
                                </td>
                                <td>

                                </td>
                                <td>
                                    Purchased Rickshaw for cash
                                </td>
                                <td>
                                    Split
                                </td>
                                <td class="total_amount">
                                    {{ $currency_code }} -1,000.00
                                </td>
                                <td class="total_amount">
                                    {{ $currency_code }} -1,000.00
                                </td>
                            </tr>
                            <tr class="treegrid-34  tr_total font-weight-bold">
                                <td>
                                    Total for Uncategorised Asset
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="total_amount">
                                    {{ $currency_code }} -1,000.00
                                </td>
                                <td></td>
                            </tr>
                            <tr class="treegrid-79  parent-node expanded">
                                <td class="parent">
                                    Long-term debt
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr class="treegrid-83  tr_total font-weight-bold">
                                <td>
                                    Total for Long-term debt
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="total_amount">
                                    {{ $currency_code }} 0.00
                                </td>
                                <td></td>
                            </tr>
                            <tr class="treegrid-108  parent-node expanded">
                                <td class="parent">
                                    Sales of Product Income
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr class="treegrid-112  tr_total font-weight-bold">
                                <td>
                                    Total for Sales of Product Income
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="total_amount">
                                    {{ $currency_code }} 0.00
                                </td>
                                <td></td>
                            </tr>
                            <tr class="treegrid-180  parent-node expanded">
                                <td class="parent">
                                    Office expenses
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr class="treegrid-183 treegrid-parent-182">
                                <td>
                                    {{ readable_date($start_date) }}
                                </td>
                                <td>
                                    Journal Entry
                                </td>
                                <td>

                                </td>
                                <td>
                                    Ajie
                                </td>
                                <td>
                                    Split
                                </td>
                                <td class="total_amount">
                                    {{ $currency_code }} 0.00
                                </td>
                                <td class="total_amount">
                                    {{ $currency_code }} 0.00
                                </td>
                            </tr>
                            <tr class="treegrid-186  tr_total font-weight-bold">
                                <td>
                                    Total for Office expenses
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="total_amount">
                                    {{ $currency_code }} 0.00
                                </td>
                                <td></td>
                            </tr>
                            <tr class="treegrid-205  parent-node expanded">
                                <td class="parent">
                                    Purchases
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr class="treegrid-206 treegrid-parent-205">
                                <td>
                                    {{ readable_date($start_date) }}
                                </td>
                                <td>
                                    Journal Entry
                                </td>
                                <td>

                                </td>
                                <td>
                                    test
                                </td>
                                <td>
                                    Split
                                </td>
                                <td class="total_amount">
                                    {{ $currency_code }} 0.00
                                </td>
                                <td class="total_amount">
                                    {{ $currency_code }} 0.00
                                </td>
                            </tr>
                            <tr class="treegrid-212  tr_total font-weight-bold">
                                <td>
                                    Total for Purchases
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="total_amount">
                                    {{ $currency_code }} 0.00
                                </td>
                                <td></td>
                            </tr>
                        </tbody>
                    @endcomponent
                @endslot
            @endcomponent

        </div>
    </section>
@stop

@section('javascript')
    @include('accounting::report.partials.report_js')
@endsection
