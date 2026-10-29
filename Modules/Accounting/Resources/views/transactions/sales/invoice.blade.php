@extends('accounting::transactions.sales.layout')
@section('sub-tab-title')
    {{ trans_choice('accounting::general.invoice', 2) }}
@endsection

@section('modal-content')
    @include('accounting::transactions.partials.map_transactions_modal', [
        'map_type' => 'credit',
        'mapping_for' => 'invoice',
    ])
@endsection

@section('sub-tab-content')
    <!-- Main content -->
    <section class="content no-print">

        @component('components.widget', ['class' => 'box-primary', 'title' => ''])
            @if (auth()->user()->can('direct_sell.view') ||
    auth()->user()->can('view_own_sell_only') ||
    auth()->user()->can('view_commission_agent_sell'))
                @php
                    $custom_labels = json_decode(session('business.custom_labels'), true);
                @endphp
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="sell_table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>@lang('accounting::general.mapping')</th>
                                <th>{{ trans_choice('accounting::general.chart_of_account', 1) }}</th>
                                <th>@lang('messages.date')</th>
                                <th>@lang('sale.invoice_no')</th>
                                <th>@lang('sale.customer_name')</th>
                                <th>@lang('sale.location')</th>
                                <th>@lang('sale.payment_status')</th>
                                <th>@lang('lang_v1.payment_method')</th>
                                <th>@lang('sale.total_amount')</th>
                                <th>@lang('sale.total_paid')</th>
                                <th>@lang('lang_v1.sell_due')</th>
                                <th>@lang('lang_v1.sell_return_due')</th>
                                <th>@lang('lang_v1.total_items')</th>
                                <th>@lang('lang_v1.types_of_service')</th>
                                <th>{{ $custom_labels['types_of_service']['custom_field_1'] ?? __('lang_v1.service_custom_field_1') }}</th>
                                <th>@lang('lang_v1.added_by')</th>
                                <th>@lang('sale.sell_note')</th>
                                <th>@lang('sale.staff_note')</th>
                                <th>@lang('restaurant.table')</th>
                                <th>@lang('restaurant.service_staff')</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr class="bg-gray font-17 footer-total text-center">
                                <td colspan="7"><strong>@lang('sale.total'):</strong></td>
                                <td class="footer_payment_status_count"></td>
                                <td class="payment_method_count"></td>
                                <td class="footer_sale_total"></td>
                                <td class="footer_total_paid"></td>
                                <td class="footer_total_remaining"></td>
                                <td class="footer_total_sell_return_due"></td>
                                <td colspan="2"></td>
                                <td class="service_type_count"></td>
                                <td colspan="7"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        @endcomponent
    </section>
    <!-- /.content -->
    <div class="modal fade payment_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    </div>

    <div class="modal fade edit_payment_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    </div>
@endsection

@section('sub-tab-javascript')
    {{-- For the mapping_modal --}}
    <script src="{{ Module::asset('accounting:js/accounting_transactions.js') }}"></script>
    {{-- For Datatable --}}
    @include('accounting::transactions.js.payment_js')
    <script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
@endsection
