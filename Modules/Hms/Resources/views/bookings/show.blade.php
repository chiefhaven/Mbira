@extends('layouts.app')
@section('title', __('hms::lang.booking'))
@section('content')
    @include('hms::layouts.nav')
    <!-- Main content -->
    <section class="content">
       <div class="col-md-8">
        <div class="box box-solid">
            <div class="box-header">
                <h3 class="box-title">@lang('hms::lang.booking') - {{ $transaction->ref_no }}</h3>
                <a class="btn btn-warning pull-right" href="{{ action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'print'], [$transaction->id]) }}" target="_blank">
                    <i class="fa fa-print"></i>
                    @lang('hms::lang.print_format_1')
                </a>
            </div>
            <div class="box-body">   
                {{-- <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('customer', __('hms::lang.customer') . ':') !!}
                         {{ $transaction->name }}
                    </div>
                </div> --}}
                <div class="col-md-6">
		    		<b>{{ __('sale.customer_name') }}:</b>
		    			{{ $transaction->contact->name }}<br>
			        <b>{{ __('business.address') }}:</b><br>
			        @if(!empty($transaction->billing_address()))
			          {{$transaction->billing_address()}}
			        @else
			          @if($transaction->contact->landmark)
			              {{ $transaction->contact->landmark }},
			          @endif

			          {{ $transaction->contact->city }}

			          @if($transaction->contact->state)
			              {{ ', ' . $transaction->contact->state }}
			          @endif
			          <br>
			          @if($transaction->contact->country)
			              {{ $transaction->contact->country }}
			          @endif
			          @if($transaction->contact->mobile)
			          <br>
			              {{__('contact.mobile')}}: {{ $transaction->contact->mobile }}
			          @endif
			          @if($transaction->contact->alternate_number)
			          <br>
			              {{__('contact.alternate_contact_number')}}:
			              {{ $transaction->contact->alternate_number }}
			          @endif
			          @if($transaction->contact->landline)
			            <br>
			              {{__('contact.landline')}}:
			              {{ $transaction->contact->landline }}
			          @endif
			        @endif
		    	</div>
                <div class="col-md-6">
                    
                    @if($transaction->status == 'confirmed')
                        <div class="form-group">
                            {!! Form::label('status', __('hms::lang.status') . ':') !!}
                            <h6 class="bg-green badge">{{ucfirst($transaction->status) }}</h6>
                        </div>
                    @elseif($transaction->status == 'pending')
                        <div class="form-group">
                            {!! Form::label('status', __('hms::lang.status') . ':') !!}
                            <h6 class="bg-yellow badge">{{ucfirst($transaction->status) }}</h6>
                        </div>
                    @elseif($transaction->status == 'cancelled')
                        <div class="form-group">
                            {!! Form::label('status', __('hms::lang.status') . ':') !!}
                            <h6 class="bg-red badge">{{ucfirst($transaction->status) }}</h6>
                        </div>
                    @endif
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('arrival_date', __('hms::lang.arrival_date') . ':') !!}
                        {{ @format_date($transaction->hms_booking_arrival_date_time) }}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('arrival_time', __('hms::lang.arrival_time') . ':') !!}
                        {{ @format_time($transaction->hms_booking_arrival_date_time) }}
                    </div>
                </div>
                 <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('departure_date', __('hms::lang.departure_date') . ':') !!}
                        {{ @format_date($transaction->hms_booking_departure_date_time) }}
                    </div>
                    <div class="days_count"></div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('departure_time', __('hms::lang.departure_time') . ':') !!}
                        {{ @format_time($transaction->hms_booking_departure_date_time) }}
                    </div>
                </div>
                @if(!empty($transaction->check_in))
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('check_in', __('hms::lang.check_in') . ':') !!}
                            {{ @format_datetime($transaction->check_in) }}
                        </div>
                    </div>
                @endif
                @if(!empty($transaction->check_out))
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('check_out', __('hms::lang.check_out') . ':') !!}
                            {{ @format_datetime($transaction->check_out) }}
                        </div>
                    </div>
                @endif
                <div class="col-md-12">
                    <hr>
                </div>
                <div>
                    <h3 class="col-md-12">
                        @lang('hms::lang.rooms_and_extras')
                    </h3>
                </div>
                <div class="col-md-8 booking_add_room">
                    <table class="table table-bordered">
                        <thead>
                            <tr class="bg-light-green">
                                <th>@lang('hms::lang.type')</th>
                                <th>@lang('hms::lang.room_no')</th>
                                <th>@lang('hms::lang.no_of_adult')</th>
                                <th>@lang('hms::lang.no_of_child')</th>
                                <th>@lang('hms::lang.price')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($booking_rooms as $room)
                                <tr>
                                    <td>
                                        {{ $room->type }}
                                    
                                    </td>
                                    <td>
                                        {{ $room->room_number }}
                                    </td>
                                    <td>
                                        {{ $room->adults }}
                                    </td>
                                    <td>
                                        {{ $room->childrens }}
                                    </td>
                                    <td class="price-td">
                                        @format_currency($room->total_price)
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="col-md-4">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>@lang('hms::lang.extras')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ( $extras as $index => $extra)
                            @if (in_array($extra->id, $extras_id))
                                <tr>
                                    <td>
                                        {{ $extra->name }} /<span class="display_currency" data-currency_symbol="true"> {{ $extra->price }} </span> - {{ str_replace("_", " ", $extra->price_per) }}
                                        
                                    </td>
                                </tr>
                            @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
       </div>
       <div class="col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                     @lang('hms::lang.status')
                    <span class="pull-right status_value">{{ $transaction->status }}</span>
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-6">
                            <strong>@lang('hms::lang.room_price') :</strong>
                        </div>
                        <div class="col-xs-6 text-right">
                            <strong class="room_price" > <span class="display_currency" data-currency_symbol="true"> {{ $transaction->room_price }} </span></strong>
                        </div>
                    </div>
                        <div class="row">
                        <div class="col-xs-6">
                            <strong>@lang('hms::lang.extra_price') :</strong>
                        </div>
                        <div class="col-xs-6 text-right">
                            <strong class="extra_price"><span class="display_currency" data-currency_symbol="true"> {{ $transaction->extra_price }} </span></strong>
                        </div>
                    </div>
                    <div class="row">
                    @php
                        $discount_percent_disable  = 0;

                        if(!empty($transaction->hms_coupon_id)){
                            $discount_percent_disable = 1;
                        }

                    @endphp
                    @if($discount_percent_disable == 0 && $transaction->discount_amount > 0)
                        <div class="col-xs-6">
                            <strong>@lang('hms::lang.discount'):</strong> ( {{ number_format($transaction->discount_amount, 2)  }} % )
                        </div>
                        <div class="col-xs-6 text-right">
                        <strong class="total_discount"> <span class="display_currency" data-currency_symbol="true"> {{ $transaction->discount_amount * ( $transaction->extra_price + $transaction->room_price ) / 100 }} </span></strong>
                    @else
                    <div class="col-xs-6">
                            <strong>@lang('hms::lang.discount'):</strong>
                        </div>
                        <div class="col-xs-6 text-right">
                        <strong class="total_discount"> <span class="display_currency" data-currency_symbol="true"> {{ $transaction->discount_amount }} </span></strong>
                    @endif
                        </div>
                    </div>
                    <div class="row">
                    <hr>
                        <div class="col-xs-6">
                            <strong>@lang('hms::lang.total'):</strong>
                        </div>
                        <div class="col-xs-6 text-right">
                            <strong class="total"> <span class="display_currency" data-currency_symbol="true"> {{ $transaction->final_total }} </span></strong>
                        </div>
                    </div>
                </div>
            </div>
            @if (!empty($transaction->coupon_code))
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        @lang('hms::lang.apply_coupon')
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="form-group">
                                {!! Form::label('coupon_code', __('hms::lang.coupon_code') . ':') !!}
                                {{ $transaction->coupon_code }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
       <div>
           
    </section>
    <!-- /.content -->
@endsection

