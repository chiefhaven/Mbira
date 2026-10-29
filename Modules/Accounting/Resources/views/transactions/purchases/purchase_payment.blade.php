@extends('accounting::transactions.purchases.layout')
@section('sub-tab-title')
    {{ trans_choice('accounting::general.purchase', 1) }} {{ trans_choice('accounting::general.payment', 1) }}
@endsection

@section('modal-content')
    @include('accounting::transactions.partials.map_transactions_modal', [
        'map_type' => 'debit',
        'mapping_for' => 'purchase_payment',
    ])
@endsection

@section('sub-tab-content')
    <!-- Main content -->
    <section class="content no-print">
        @component('components.filters', ['title' => __('report.filters')])
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('purchase_list_filter_location_id', __('purchase.business_location') . ':') !!}
                    {!! Form::select('purchase_list_filter_location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('purchase_list_filter_supplier_id', __('purchase.supplier') . ':') !!}
                    {!! Form::select('purchase_list_filter_supplier_id', $suppliers, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('purchase_list_filter_status', __('purchase.purchase_status') . ':') !!}
                    {!! Form::select('purchase_list_filter_status', $orderStatuses, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('purchase_list_filter_payment_status', __('purchase.payment_status') . ':') !!}
                    {!! Form::select('purchase_list_filter_payment_status', ['paid' => __('lang_v1.paid'), 'due' => __('lang_v1.due'), 'partial' => __('lang_v1.partial'), 'overdue' => __('lang_v1.overdue')], null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('purchase_list_filter_date_range', __('report.date_range') . ':') !!}
                    {!! Form::text('purchase_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']) !!}
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-primary', 'title' => __('purchase.all_purchases')])
            @can('purchase.create')
                @slot('tool')
                    <div class="box-tools">
                        <a class="btn btn-block btn-primary" href="{{ action('PurchaseController@create') }}">
                            <i class="fa fa-plus"></i> @lang('messages.add')</a>
                    </div>
                @endslot
            @endcan
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="purchase_table">
                    <thead>
                        <tr>
                            <th>@lang('accounting::general.mapping')</th>
                            <th>{{ trans_choice('accounting::general.chart_of_account', 1) }}</th>
                            <th>@lang('messages.date')</th>
                            <th>@lang('purchase.ref_no')</th>
                            <th>@lang('purchase.location')</th>
                            <th>@lang('purchase.supplier')</th>
                            <th>@lang('purchase.purchase_status')</th>
                            <th>@lang('purchase.payment_status')</th>
                            <th>@lang('purchase.grand_total')</th>
                            <th>@lang('purchase.payment_due') &nbsp;&nbsp;<i class="fa fa-info-circle text-info no-print" data-toggle="tooltip"
                                    data-placement="bottom" data-html="true" data-original-title="{{ __('messages.purchase_due_tooltip') }}"
                                    aria-hidden="true"></i></th>
                            <th>@lang('lang_v1.added_by')</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr class="bg-gray font-17 text-center footer-total">
                            <td colspan="6"><strong>@lang('sale.total'):</strong></td>
                            <td class="footer_status_count"></td>
                            <td class="footer_payment_status_count"></td>
                            <td class="footer_purchase_total"></td>
                            <td class="text-left"><small>@lang('report.purchase_due') - <span class="footer_total_due"></span><br>
                                    @lang('lang_v1.purchase_return') - <span class="footer_total_purchase_return_due"></span>
                                </small></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endcomponent

        <div class="modal fade product_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
        </div>

        <div class="modal fade payment_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
        </div>

        <div class="modal fade edit_payment_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
        </div>

    </section>

    <section id="receipt_section" class="print_section"></section>
@endsection


@section('sub-tab-javascript')
    <script src="{{ Module::asset('accounting:js/accounting_transactions.js') }}"></script>
    <script src="{{ Module::asset('accounting:js/purchase_payment.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
@endsection
