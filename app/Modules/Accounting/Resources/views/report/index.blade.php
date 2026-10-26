@extends('layouts.app')

@section('title', __('accounting::lang.journal_entry'))

@section('content')

@include('accounting::layouts.nav')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang( 'accounting::lang.reports' )</h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-6">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang( 'accounting::lang.trial_balance')</h3>
                </div>

                <div class="box-body">
                    @lang( 'accounting::lang.trial_balance_description')
                    <br/>
                    <a href="{{route('accounting.trialBalance')}}" class="btn btn-primary btn-sm pt-2">@lang( 'accounting::lang.view_report')</a>
                </div>

            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang( 'accounting::lang.ledger_report')</h3>
                </div>

                <div class="box-body">
                    @lang( 'accounting::lang.ledger_report_description')
                    <br/>
                    <a @if($ledger_url) href="{{$ledger_url}}" @else onclick="alert(' @lang( 'accounting::lang.ledger_add_account') ')" @endif class="btn btn-primary btn-sm pt-2">@lang( 'accounting::lang.view_report')</a>
                </div>

            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang( 'accounting::lang.balance_sheet')</h3>
                </div>

                <div class="box-body">
                    @lang( 'accounting::lang.balance_sheet_description')
                    <br/>
                    <a href="{{route('accounting.balanceSheet')}}" class="btn btn-primary btn-sm pt-2">@lang( 'accounting::lang.view_report')</a>
                </div>

            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang( 'accounting::lang.account_recievable_ageing_report')</h3>
                </div>
                <div class="box-body">
                    @lang( 'accounting::lang.account_recievable_ageing_report_description')
                    <br/>
                    <a href="{{route('accounting.account_receivable_ageing_report')}}" 
                    class="btn btn-primary btn-sm pt-2">@lang( 'accounting::lang.view_report')</a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang( 'accounting::lang.account_payable_ageing_report')</h3>
                </div>
                <div class="box-body">
                    @lang( 'accounting::lang.account_payable_ageing_report_description')
                    <br/>
                    <a href="{{route('accounting.account_payable_ageing_report')}}" 
                    class="btn btn-primary btn-sm pt-2">@lang( 'accounting::lang.view_report')</a>
                </div>
            </div>
        </div>

    </div>
</section>

@stop