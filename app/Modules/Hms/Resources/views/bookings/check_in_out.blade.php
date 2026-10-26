<div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">  
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">@lang('hms::lang.booking') - {{ $transaction->ref_no }}</h4>
      </div>
  
      <div class="modal-body">
            <div class="row">
                <div class="col-md-8">
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
                                                    {{ $extra->name }} /<span class="display_currency" data-currency_symbol="true"> @format_currency($extra->price) </span> - {{ str_replace("_", " ", $extra->price_per) }}
                                                    
                                                </td>
                                            </tr>
                                        @endif
                                        @endforeach
                                    </tbody>
                                </table>
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
                                                <strong class="room_price" > <span class="display_currency" data-currency_symbol="true"> @format_currency($transaction->room_price) </span></strong>
                                            </div>
                                        </div>
                                            <div class="row">
                                            <div class="col-xs-6">
                                                <strong>@lang('hms::lang.extra_price') :</strong>
                                            </div>
                                            <div class="col-xs-6 text-right">
                                                <strong class="extra_price"><span class="display_currency" data-currency_symbol="true"> @format_currency($transaction->extra_price) </span></strong>
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
                                            <strong class="total_discount"> <span class="display_currency" data-currency_symbol="true"> @format_currency($transaction->discount_amount * ( $transaction->extra_price + $transaction->room_price ) / 100 ) </span></strong>
                                        @else
                                        <div class="col-xs-6">
                                                <strong>@lang('hms::lang.discount'):</strong>
                                            </div>
                                            <div class="col-xs-6 text-right">
                                            <strong class="total_discount"> <span class="display_currency" data-currency_symbol="true"> @format_currency($transaction->discount_amount) </span></strong>
                                        @endif
                                            </div>
                                        </div>
                                        <div class="row">
                                        <hr>
                                            <div class="col-xs-6">
                                                <strong>@lang('hms::lang.total'):</strong>
                                            </div>
                                            <div class="col-xs-6 text-right">
                                                <strong class="total"> <span class="display_currency" data-currency_symbol="true"> @format_currency($transaction->final_total) </span></strong>
                                            </div>
                                            <div class="col-xs-6">
                                                <strong>@lang('hms::lang.total_paid'):</strong>
                                            </div>
                                            <div class="col-xs-6 text-right">
                                                <strong class="total"> <span class="display_currency" data-currency_symbol="true">@format_currency($transaction->total_paid) </span></strong>
                                            </div>
                                            <div class="col-xs-6">
                                                <strong>@lang('hms::lang.due'):</strong>
                                            </div>
                                            <div class="col-xs-6 text-right">
                                                <strong class="total"> <span class="display_currency" data-currency_symbol="true"> @format_currency($transaction->final_total - $transaction->total_paid) </span></strong>
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
                </div>
            </div>
        </div>
    {!! Form::open(['url' => action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'post_check_in_out'], $transaction->id), 'method' => 'put', 'id' => 'check_in_out' ]) !!}
    <div class="col-md-12">
        <div class="col-md-3">
                @if(empty($transaction->check_in))
                    <div class="form-group">
                        {!! Form::label('date_from', __('hms::lang.check_in_date_time') . '*') !!}
                        {!! Form::text('in_out_date_time', null, [
                            'class' => 'form-control date_picker',
                            'required',
                            'readonly',
                        ]) !!}
                    </div>
                @elseif (!empty($transaction->check_in) && empty($transaction->check_out))
                    <div class="form-group">
                        {!! Form::label('date_from', __('hms::lang.check_out_date_time') . '*') !!}
                        {!! Form::text('in_out_date_time', null, [
                            'class' => 'form-control date_picker',
                            'required',
                            'readonly',
                        ]) !!}
                    </div>
                @endif
        </div>
    </div>
    <div class="modal-footer">
        @if(empty($transaction->check_in))
            <button type="submit" class="btn btn-primary">@lang( 'hms::lang.confirm_check_in' ) </button>
            <button type="button" class="btn btn-default" data-dismiss="modal">@lang( 'messages.close' )</button>
        @elseif (!empty($transaction->check_in) && empty($transaction->check_out))
            @if($transaction->payment_status != 'paid')
                <h4>@lang('hms::lang.check_out_payment_help_text')</h4>
            @endif
            <button type="submit" @if($transaction->payment_status != 'paid') disabled @endif class="btn btn-primary">@lang( 'hms::lang.confirm_check_out' ) </button>
            <button type="button" class="btn btn-default" data-dismiss="modal">@lang( 'messages.close' )</button>
        @endif
    </div>
    {!! Form::close() !!}
  
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->