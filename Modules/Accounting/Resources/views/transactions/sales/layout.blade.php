@extends('accounting::layouts.transactions_layout')
@section('tab-title')
    {{ trans_choice('accounting::general.sales', 1) }} @yield('sub-tab-title')
@endsection

@section('tab-content')

    <div class="row">
        <div class="col-md-12">
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs nav-justified">
                    <li class="@if (Request::get('type') == 'payment') active @endif">
                        <a href="{{ request()->type != 'payment' ? url('accounting/transactions/sales?type=payment') : '#' }}"
                            aria-expanded="true">
                            <i class="fas fa-credit-card" aria-hidden="true"></i>
                            {{ trans_choice('accounting::general.payment', 2) }}
                        </a>
                    </li>
                    <li class="@if (Request::get('type') == 'invoice') active @endif">
                        <a href="{{ request()->type != 'invoice' ? url('accounting/transactions/sales?type=invoice') : '#' }}"
                            aria-expanded="true">
                            <i class="fas fa-file" aria-hidden="true"></i>
                            {{ trans_choice('accounting::general.invoice', 2) }}
                        </a>
                    </li>
                </ul>

                {{-- Filter --}}
                @component('components.filters', ['title' => __('report.filters')])
                    <div class="row">
                        @if (empty($only) || in_array('sell_list_filter_location_id', $only))
                            <div class="col-md-3">
                                <div class="form-group">
                                    {!! Form::label('sell_list_filter_location_id', __('purchase.business_location') . ':') !!}
                                    {!! Form::select('sell_list_filter_location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]) !!}
                                </div>
                            </div>
                        @endif
                        @if ((empty($only) || in_array('sell_list_filter_customer_id', $only)) && Request::get('type') == 'invoice')
                            <div class="col-md-3">
                                <div class="form-group">
                                    {!! Form::label('sell_list_filter_customer_id', __('contact.customer') . ':') !!}
                                    {!! Form::select('sell_list_filter_customer_id', $customers, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]) !!}
                                </div>
                            </div>
                        @endif
                        @if (empty($only) || in_array('sell_list_filter_payment_status', $only))
                            <div class="col-md-3">
                                <div class="form-group">
                                    {!! Form::label('sell_list_filter_payment_status', __('purchase.payment_status') . ':') !!}
                                    {!! Form::select('sell_list_filter_payment_status', ['paid' => __('lang_v1.paid'), 'due' => __('lang_v1.due'), 'partial' => __('lang_v1.partial'), 'overdue' => __('lang_v1.overdue')], null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]) !!}
                                </div>
                            </div>
                        @endif
                        @if (empty($only) || in_array('sell_list_filter_date_range', $only))
                            <div class="col-md-3">
                                <div class="form-group">
                                    {!! Form::label('sell_list_filter_date_range', __('report.date_range') . ':') !!}
                                    {!! Form::text('sell_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']) !!}
                                </div>
                            </div>
                        @endif
                        @if ((empty($only) || in_array('created_by', $only)) && !empty($sales_representative))
                            <div class="col-md-3">
                                <div class="form-group">
                                    {!! Form::label('created_by', __('report.user') . ':') !!}
                                    {!! Form::select('created_by', $sales_representative, null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
                                </div>
                            </div>
                        @endif
                        @if (empty($only) || in_array('sales_cmsn_agnt', $only))
                            @if (!empty($is_cmsn_agent_enabled))
                                <div class="col-md-3">
                                    <div class="form-group">
                                        {!! Form::label('sales_cmsn_agnt', __('lang_v1.sales_commission_agent') . ':') !!}
                                        {!! Form::select('sales_cmsn_agnt', $commission_agents, null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
                                    </div>
                                </div>
                            @endif
                        @endif
                        @if (empty($only) || in_array('service_staffs', $only))
                            @if (!empty($service_staffs))
                                <div class="col-md-3">
                                    <div class="form-group">
                                        {!! Form::label('service_staffs', __('restaurant.service_staff') . ':') !!}
                                        {!! Form::select('service_staffs', $service_staffs, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]) !!}
                                    </div>
                                </div>
                            @endif
                        @endif

                        @if (!empty($sources))
                            <div class="col-md-3">
                                <div class="form-group">
                                    {!! Form::label('sell_list_filter_source', __('lang_v1.sources') . ':') !!}
                                    {!! Form::select('sell_list_filter_source', $sources, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]) !!}
                                </div>
                            </div>
                        @endif
                    </div>
                @endcomponent

                <div class="tab-content">
                    <div class="tab-pane active">
                        @yield('sub-tab-content')
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('tab-javascript')
    @yield('sub-tab-javascript')
@endsection
