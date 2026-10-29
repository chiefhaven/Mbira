@extends('accounting::transactions.purchases.layout')
@section('sub-tab-title')
    {{ trans_choice('accounting::general.purchase', 1) }} {{ trans_choice('accounting::general.order', 1) }}
@endsection

@section('modal-content')
    @include('accounting::transactions.partials.map_transactions_modal', [
        'map_type' => 'debit',
        'mapping_for' => 'purchase_order',
    ])
@endsection

@section('sub-tab-content')
    <!-- Main content -->
    <section class="content no-print">
        @component('components.filters', ['title' => __('report.filters')])
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('po_list_filter_location_id', __('purchase.business_location') . ':') !!}
                    {!! Form::select('po_list_filter_location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('po_list_filter_supplier_id', __('purchase.supplier') . ':') !!}
                    {!! Form::select('po_list_filter_supplier_id', $suppliers, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('po_list_filter_status', __('sale.status') . ':') !!}
                    {!! Form::select('po_list_filter_status', $purchaseOrderStatuses, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]) !!}
                </div>
            </div>
            @if (!empty($shipping_statuses))
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('shipping_status', __('lang_v1.shipping_status') . ':') !!}
                        {!! Form::select('shipping_status', $shipping_statuses, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]) !!}
                    </div>
                </div>
            @endif
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('po_list_filter_date_range', __('report.date_range') . ':') !!}
                    {!! Form::text('po_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']) !!}
                </div>
            </div>
        @endcomponent
        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.all_purchase_orders')])
            @can('purchase_order.create')
                @slot('tool')
                    <div class="box-tools">
                        <a class="btn btn-block btn-primary" href="{{ action('PurchaseOrderController@create') }}">
                            <i class="fa fa-plus"></i> @lang('messages.add')</a>
                    </div>
                @endslot
            @endcan

            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="purchase_order_table">
                    <thead>
                        <tr>
                            <th>@lang('accounting::general.mapping')</th>
                            <th>{{ trans_choice('accounting::general.chart_of_account', 1) }}</th>
                            <th>@lang('messages.date')</th>
                            <th>@lang('purchase.ref_no')</th>
                            <th>@lang('purchase.location')</th>
                            <th>@lang('purchase.supplier')</th>
                            <th>@lang('sale.status')</th>
                            <th>@lang('lang_v1.quantity_remaining')</th>
                            <th>@lang('lang_v1.shipping_status')</th>
                            <th>@lang('lang_v1.added_by')</th>
                        </tr>
                    </thead>
                </table>
            </div>
        @endcomponent
        <div class="modal fade edit_pso_status_modal" tabindex="-1" role="dialog"></div>
    </section>
    <!-- /.content -->
@endsection


@section('sub-tab-javascript')
    <script src="{{ Module::asset('accounting:js/accounting_transactions.js') }}"></script>
    @include('accounting::transactions.js.purchase_order_transactions_js')
@endsection
