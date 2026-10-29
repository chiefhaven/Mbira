@extends('layouts.app')
@section('title', __( 'sale.list_pos'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('sale.pos_sale')
    </h1>
</section>

<!-- Main content -->
<section class="content no-print">
    @component('components.filters', ['title' => __('report.filters')])
        @include('sell.partials.sell_list_filters')
    @endcomponent

    @component('components.widget', ['class' => 'box-primary', 'title' => __( 'sale.list_pos')])
        @can('sell.create')
            @slot('tool')
                {{--  <div class="box-tools">
                    <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                            href="{{action([\App\Http\Controllers\SellPosController::class, 'create'])}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M12 5l0 14" />
                                <path d="M5 12l14 0" />
                            </svg> @lang('messages.add')
                        </a>
                </div>  --}}
                <div class="box-tools tw-mr-2">
                    <a class="submit-all-bills-btn tw-bg-gradient-to-r tw-dw-btn tw-from-green-600 tw-to-green-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right mr-2"
                    href="{{ route('sells.eis-submit-all-bills') }}" >
                        <i class="fa fa-cash"></i> @lang('lang_v1.submit_to_eis')
                    </a>
                </div>
                <div class="box-tools tw-mr-2">
                    <a class="clear-all-bills-btn tw-bg-gradient-to-r tw-dw-btn tw-from-green-600 tw-to-green-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right mr-2"
                    href="{{ route('sells.clear-all-bill') }}" >
                        <i class="fa fa-cash"></i> @lang('lang_v1.clear_all_bills')
                    </a>
                </div>
            @endslot

        @endcan
        @can('sell.view')
            <input type="hidden" name="is_direct_sale" id="is_direct_sale" value="0">
            @include('sale_pos.partials.sales_table')
        @endcan
    @endcomponent
</section>
<!-- /.content -->
<div class="modal fade payment_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>

<div class="modal fade edit_payment_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>

<div class="modal fade register_details_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>
<div class="modal fade close_register_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>

<!-- This will be printed -->
<section class="invoice print_section" id="receipt_section">
</section> 


@stop

@section('javascript')
@include('sale_pos.partials.sale_table_javascript')
@include('sale_pos.partials.clear_bills_javascript')
@include('sale_pos.partials.eis_submit_bills_javascript')
@include('sell.partials.eis_void_tools_javascript')
@include('sell.partials.eis_sale_sync_javascript')
<script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
@endsection