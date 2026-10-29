@extends('layouts.app')
@section('title', __('hms::lang.add_bookings'))
@section('content')
    @include('hms::layouts.nav')
    <!-- Main content -->
    <section class="content">
    <div class="row">
       <div class="col-md-8">
            <div class="box box-solid">
                <div class="box-header">
                    <h3 class="box-title"> @lang('hms::lang.add_bookings')</h3>
                </div>
                <div class="box-body">
                    {!! Form::open([
                        'url' => action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'store']),
                        'method' => 'post',
                        'id' => 'create_booking',
                        'files' => true
                    ]) !!}

                    <input type="hidden" name="total_booking_amount" id="final_total_input">
                    <input type="hidden" name="total_discount" id="total_discount">
                    <input type="hidden" name="coupon_id" id="coupon_id">
                    <input type="hidden" name="discount_type" id="discount_type">
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('contact_id', __('contact.customer') . ':*') !!}
                            <div class="input-group">
                                <span class="input-group-addon">
                                    <i class="fa fa-user"></i>
                                </span>
                                <input type="hidden" id="default_customer_id" 
                                value="{{ $walk_in_customer['id']}}" >
                                <input type="hidden" id="default_customer_name" 
                                value="{{ $walk_in_customer['name']}}" >
                                <input type="hidden" id="default_customer_balance" value="{{ $walk_in_customer['balance'] ?? ''}}" >
                                <input type="hidden" id="default_customer_address" value="{{ $walk_in_customer['shipping_address'] ?? ''}}" >
                                @if(!empty($walk_in_customer['price_calculation_type']) && $walk_in_customer['price_calculation_type'] == 'selling_price_group')
                                    <input type="hidden" id="default_selling_price_group" 
                                value="{{ $walk_in_customer['selling_price_group_id'] ?? ''}}" >
                                @endif
                                {!! Form::select('contact_id', 
                                    [], null, ['class' => 'form-control mousetrap', 'id' => 'customer_id', 'placeholder' => 'Enter Customer name / phone', 'required']); !!}
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default bg-white btn-flat add_new_customer" data-name=""><i class="fa fa-plus-circle text-primary fa-lg"></i></button>
                                </span>
                            </div>
                            <small class="text-danger hide contact_due_text"><strong>@lang('account.customer_due'):</strong> <span></span></small>
                        </div>
                        <small>
                        <strong>
                            @lang('lang_v1.billing_address'):
                        </strong>
                        <div id="billing_address_div">
                            {!! $walk_in_customer['contact_address'] ?? '' !!}
                        </div>
                        </small>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('status', __('hms::lang.status') . ':*') !!}
                            {!! Form::select('status', $status, '', [
                                'class' => 'form-control status',
                                'required',
                                'placeholder' => __('hms::lang.status'),
                            ]) !!}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('arrival_date', __('hms::lang.arrival_date') . ':') !!}
                            {!! Form::text('arrival_date', request()->input('booking_date') ? @format_date(request()->input('booking_date')) : null, [
                                'class' => 'form-control date_picker',
                                'placeholder' => __('hms::lang.arrival_date'),
                                'readonly',
                                'required',
                                'id' => 'arrival_date',
                            ]) !!}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('arrival_time', __('hms::lang.arrival_time') . ':') !!}
                            {!! Form::text('arrival_time', null, [
                                'class' => 'form-control time_picker',
                                'placeholder' => __('hms::lang.arrival_time'),
                                'readonly',
                                'required',
                                'id' => 'arrival_time',
                            ]) !!}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('departure_date', __('hms::lang.departure_date') . ':') !!}
                            {!! Form::text('departure_date', request()->input('booking_date') ? @format_date(request()->input('booking_date')) : null, [
                                'class' => 'form-control departure_date',
                                'placeholder' => __('hms::lang.departure_date'),
                                'readonly',
                                'required',
                                'id' => 'departure_date',
                            ]) !!}
                        </div>
                        <p class="days_count text-success"> 1 days</p>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('departure_time', __('hms::lang.departure_time') . ':') !!}
                            {!! Form::text('departure_time', null, [
                                'class' => 'form-control time_picker',
                                'placeholder' => __('hms::lang.departure_time'),
                                'readonly',
                                'required',
                                'id' => 'departure_time',
                            ]) !!}
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
                                    <th>@lang('messages.action')</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-primary add-room">  @lang('messages.add') @lang('hms::lang.rooms')</button>
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
                                <tr>
                                    <td>
                                        <input type="checkbox" name="extras[{{ $extra->id }}][id]" class="extra" value="{{$extra->id}}" data-value="{{$extra->price_per}}" data-price="{{$extra->price}}">
                                        <input type="hidden" name="extras[{{ $extra->id }}][price]" value="" id="{{ $extra->id }}">
                                        {{ $extra->name }} / <span class="display_currency" data-currency_symbol="true"> {{ $extra->price }}</span> - {{ str_replace("_", " ", $extra->price_per) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
       </div>
       <div class="col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading status-heading">
                    <h3 class="panel-title">
                     @lang('hms::lang.status')
                    <span class="pull-right status_value">@lang('hms::lang.status')</span>
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-6">
                            <strong>@lang('hms::lang.room_price') :</strong>
                        </div>
                        <div class="col-xs-6 text-right">
                            <strong class="room_price">0.00</strong>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-6">
                            <strong>@lang('hms::lang.extra_price') :</strong>
                        </div>
                        <div class="col-xs-6 text-right">
                            <strong class="extra_price">0.00</strong>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-6">
                            <strong>@lang('hms::lang.discount') :</strong>
                        </div>
                        <div class="col-xs-6 text-right">
                            <strong class="total_discount_show">0.00</strong>
                        </div>
                    </div>
                    <div class="row">
                    <hr>
                        <div class="col-xs-6">
                            <div class="form-group">
                                {!! Form::number('discount_percent', null, [
                                    'class' => 'form-control',
                                    'id' => 'discount_percent',
                                    'placeholder' => __('hms::lang.discount_percent'),
                                ]) !!}
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-6">
                            <strong>Total:</strong>
                        </div>
                        <div class="col-xs-6 text-right">
                            <strong class="total">0.00</strong>
                        </div>
                    </div>
                </div>
            </div>
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
                                {!! Form::text('coupon_code', null, [
                                    'class' => 'form-control',
                                    'placeholder' => __('hms::lang.coupon_code'),
                                    'required',
                                ]) !!}
                                <div class="coupon-box" style="display:none"></div>
                            </div>
                            <a class="btn btn-success apply_coupon">@lang('hms::lang.apply_coupon')</a>
                        </div>
                    </div>
                </div>
            </div>
       </div>
    </div>
       <div class="row">
            <div class="col-md-12">
                <div class="box box-solid">
                    <div class="box-header">
                        <h3 class="box-title"> @lang('hms::lang.add_payment')</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="box-body payment_row">
                                @include('hms::partials.payment_row_form', ['row_index' => 0, 'show_date' => true, 'show_denomination' => true])
                            </div>
                        </div>
                        <div class="payment_row">
                            <div class="row">
                                <div class="col-md-12">
                                    <hr>
                                    <strong>
                                        @lang('lang_v1.change_return'):
                                    </strong>
                                    <br/>
                                    <span class="lead text-bold change_return_span">0</span>
                                    {!! Form::hidden("change_return", $change_return['amount'], ['class' => 'form-control change_return input_number', 'required', 'id' => "change_return"]); !!}
                                    <!-- <span class="lead text-bold total_quantity">0</span> -->
                                    @if(!empty($change_return['id']))
                                        <input type="hidden" name="change_return_id" 
                                        value="{{$change_return['id']}}">
                                    @endif
                                </div>
                            </div>
                            <div class="hide payment_row" id="change_return_payment_data">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        {!! Form::label("change_return_method" , __('lang_v1.change_return_payment_method') . ':*') !!}
                                        <div class="input-group">
                                            <span class="input-group-addon">
                                                <i class="fas fa-money-bill-alt"></i>
                                            </span>
                                            @php
                                                $_payment_method = empty($change_return['method']) && array_key_exists('cash', $payment_types) ? 'cash' : $change_return['method'];
        
                                                $_payment_types = $payment_types;
                                                if(isset($_payment_types['advance'])) {
                                                    unset($_payment_types['advance']);
                                                }
                                            @endphp
                                            {!! Form::select("payment[change_return][method]", $_payment_types, $_payment_method, ['class' => 'form-control col-md-12 payment_types_dropdown', 'id' => 'change_return_method', 'style' => 'width:100%;']); !!}
                                        </div>
                                    </div>
                                </div>
                                @if(!empty($accounts))
                                <div class="col-md-6">
                                    <div class="form-group">
                                        {!! Form::label("change_return_account" , __('lang_v1.change_return_payment_account') . ':') !!}
                                        <div class="input-group">
                                            <span class="input-group-addon">
                                                <i class="fas fa-money-bill-alt"></i>
                                            </span>
                                            {!! Form::select("payment[change_return][account_id]", $accounts, !empty($change_return['account_id']) ? $change_return['account_id'] : '' , ['class' => 'form-control select2', 'id' => 'change_return_account', 'style' => 'width:100%;']); !!}
                                        </div>
                                    </div>
                                </div>
                                @endif
                                @include('hms::partials.payment_type_details', ['payment_line' => $change_return, 'row_index' => 'change_return'])
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="pull-right"><strong>@lang('lang_v1.balance'):</strong> <span class="balance_due">0.00</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
       </div>
       <div class="col-md-12 text-center">
        <button type="submit" name="submit_action" value="save" id="submit_action" class="btn btn-primary btn-big">@lang('messages.save')</button>
        </div>
       {!! Form::close() !!}
           {{-- model  --}}
            <div class="modal fade view_modal_room" tabindex="-1" role="dialog" 
            aria-labelledby="gridSystemModalLabel"></div>
            <div class="modal fade contact_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
                @include('contact.create', ['quick_add' => true])
            </div>
    </section>
    <!-- /.content -->
@endsection

@section('javascript')
    {{-- <script src="{{ asset('js/pos.js?v=' . $asset_v) }}"></script> --}}

 
    
    <script>
        $(document).ready(function() {
            var current_index = 0;
            var row = null;
            var coupon_id = null;
            var discount_type = null;

            $('.add-room').on('click', function(e) {
                e.preventDefault();
                $.ajax({
                    url: "{{ route('booking_room_add') }}",
                    dataType: 'html',
                    success: function(result) {
                        $('.view_modal_room')
                            .html(result)
                            .modal('show');
                    },
                });
            });

            $(".view_modal_room").on("show.bs.modal", function() {
                $("#add_booking_room").submit(function(event) {
                    current_index++;
                    event.preventDefault();
                    $.ajax({
                        url: "{{ route('get_room_detail') }}",
                        dataType: 'html',
                        data: {
                            'current_index' : current_index, 
                            'type_id': $('#type').val(),
                            'room_id': $('#room_no').val(),
                            'no_of_child': $('#no_of_child').val(),
                            'no_of_adult': $('#no_of_adult').val(),
                            'arrival_date': $('#arrival_date').val(),
                            'departure_date': $('#departure_date').val(),
                        },
                        success: function(result) {
                            $('.booking_add_room table tbody').append(result);
                            calculateAllPrice();
                        },
                    });

                    $('.view_modal_room').modal('hide');
                    return false
                });


                $("#edit_booking_room").submit(function(event) {
                    current_index++;
                    event.preventDefault();
                    $.ajax({
                        url: "{{ route('get_room_detail') }}",
                        dataType: 'html',
                        data: {
                            'current_index' : current_index, 
                            'type_id': $('#type').val(),
                            'room_id': $('#room_no').val(),
                            'no_of_child': $('#no_of_child').val(),
                            'no_of_adult': $('#no_of_adult').val(),
                            'arrival_date': $('#arrival_date').val(),
                            'departure_date': $('#departure_date').val(),
                            'is_edit': true,
                        },
                        success: function(result) {
                            row.html(result);
                            calculateAllPrice();
                        },
                    });

                    $('.view_modal_room').modal('hide');
                    return false
                });

                $('#type').on('change', function(){
                    var roomIds = []; // Array to store the room_ids
                    $('.room-id-input').each(function () {
                        var roomId = $(this).val();
                        roomIds.push(roomId);
                    });
                    
                    $.ajax({
                        url: "{{ route('get_room_type_by') }}",
                        dataType: 'html',
                        data: {
                        'type_id': $(this).val(),
                        'arrival_date': $('#arrival_date').val(),
                        'departure_date': $('#departure_date').val(),
                        'arrival_time': $('#arrival_time').val(),
                        'departure_time': $('#departure_time').val(),
                        'room_ids': roomIds,
                        },
                        success: function(result) {
                            $('.modify_field').html(result);
                            // calculateAllPrice();
                        },
                    });
                    
                });
            });

            var currentDate = new Date();
            var currentDateTime = moment(currentDate);

            $('.date_picker').datetimepicker({
                format: moment_date_format,
                ignoreReadonly: true,
                defaultDate: currentDateTime
            });

            var bookingDate = "{{ request()->input('booking_date') }}";

            if(!bookingDate){
                $('.departure_date').datetimepicker({
                    format: moment_date_format,
                    ignoreReadonly: true,
                    defaultDate: currentDateTime,
                    minDate:currentDateTime,
                });  
            }else{
                $('.departure_date').datetimepicker({
                    format: moment_date_format,
                    ignoreReadonly: true,
                    defaultDate: currentDateTime,
                });
            }

            var initialDate;
            var previousDate;
            var changeEventBound = true;
            
            $('.date_picker').on('dp.change', function (e) {
                if (!changeEventBound) {
                    return;
                }
                
                var selectedDate = e.date;
                previousDate = e.oldDate;

                if (!initialDate) {
                    initialDate = selectedDate;
                }

                // Update the minimum date of the departure datepicker
                var rowCount = $(".booking_add_room table tbody tr").length;

                if(rowCount > 0){
                    changeEventBound = false;
                    swal({
                        text: "{{ __('hms::lang.changing_date_will_reset_rooms_selection_do_you_want_to_continue') }} ",
                        title: "{{ __('hms::lang.are_you_sure') }}",
                        icon: "warning",
                        buttons: true,
                        dangerMode: true,
                    }).then((confirmed) => {
                        if (confirmed) {
                            // Perform deletion logic here
                            $(".booking_add_room table tbody tr").remove();
                            $('#coupon_code').val('');
                            initialDate = selectedDate;
                            $('.departure_date').data('DateTimePicker').minDate(selectedDate);
                            calculateAllPrice();
                        } else {
                            initialDate = previousDate;
                            $('.date_picker').data("DateTimePicker").date(previousDate);
                        }
                        changeEventBound = true;
                    });
                }else{
                    $('.departure_date').data('DateTimePicker').minDate(selectedDate);
                    calculateAllPrice();
                }

            });

            var initialDate;
            var previousDate;
            var changeEventBound = true;

            var previousValue = $('#departure_date').val();

            $('#departure_date').on('dp.change', function (e) {

                if (!changeEventBound) {
                    return;
                }
                
                var selectedDate = e.date;
                previousDate = e.oldDate;

                if (!initialDate) {
                    initialDate = selectedDate;
                }

                // Update the minimum date of the departure datepicker
                var rowCount = $(".booking_add_room table tbody tr").length;

                var currentValue = $('#departure_date').val();

                if(rowCount > 0 && currentValue !== previousValue){
                    changeEventBound = false;
                    swal({
                        text: " {{ __('hms::lang.changing_date_will_reset_rooms_selection_do_you_want_to_continue') }} ",
                        title: "{{ __('hms::lang.are_you_sure') }}",
                        icon: "warning",
                        buttons: true,
                        dangerMode: true,
                    }).then((confirmed) => {
                        if (confirmed) {
                            // Perform deletion logic here
                            $(".booking_add_room table tbody tr").remove();
                            initialDate = selectedDate;
                            $('#departure_date').data('DateTimePicker').minDate(selectedDate);
                            $('#coupon_code').val('');
                            calculateAllPrice();
                        } else {
                            initialDate = previousDate;
                            $('#departure_date').data("DateTimePicker").date(previousDate);
                        }
                        changeEventBound = true;
                    });
                }else{
                    calculateAllPrice();
                }
            });


            $('.time_picker').datetimepicker({
                format: moment_time_format,
                ignoreReadonly: true,
                defaultDate: moment(),
            });

            // Remove row functionality
            $(document).on('click', '.remove', function(e) {
                e.preventDefault();
                swal({
                    title: "{{ __('hms::lang.delete_booking_room') }}",
                    text: "{{ __('hms::lang.are_you_sure_you_want_to_delete_selected_items') }}",
                    icon: "warning",
                    buttons: true,
                    dangerMode: true,
                }).then((confirmed) => {
                    if (confirmed) {
                        $(this).closest('tr').remove();
                        calculateAllPrice();
                    }
                });
            });

            $(document).on('click', '.edit', function(e) {
                e.preventDefault();
                current_index ++;
                row = $(this).closest('tr');
                var type_id = row.find('input[name^="rooms["][name$="][type_id]"]').val();
                var room_id = row.find('input[name^="rooms["][name$="][room_id]"]').val();
                var no_of_adult = row.find('input[name^="rooms["][name$="][no_of_adult]"]').val();
                var no_of_child = row.find('input[name^="rooms["][name$="][no_of_child]"]').val();
                var roomIds = []; // Array to store the room_ids
                $('.room-id-input').each(function () {
                    var roomId = $(this).val();
                    roomIds.push(roomId);
                });
                // console.log(roomIds); edit_booking_room
                $.ajax({
                    url: "{{ route('booking_room_edit') }}",
                    dataType: 'html',
                    data: {
                        'type_id': type_id,
                        'room_id': room_id,
                        'no_of_adult': no_of_adult,
                        'no_of_child': no_of_child,
                        'room_ids':roomIds,
                    },
                    success: function(result) {
                        $('.view_modal_room')
                            .html(result)
                            .modal('show');
                    },
                });
            });
            $(document).on('click', '.price_calculate, .extra', function(){
                calculateAllPrice();
            });

            $('.status').on('change', function(){
                if($(this).val() == 'cancelled'){
                    $('.status-heading').css('background-color', 'red');
                    $('.status_value').html("Cancelled");
                } else if($(this).val() == 'confirmed'){
                    $('.status_value').html("Confirmed");
                    $('.status-heading').css('background-color', 'green');
                } else if($(this).val() == 'pending'){
                    $('.status-heading').css('background-color', 'yellow');
                    $('.status_value').html("Pending")
                }else{
                    $('.status-heading').css('background-color', '#f5f5f5');
                    $('.status_value').html("Status")
                }
            })

            $('.apply_coupon').on('click', function(){
                calculateAllPrice();
            });

            $("form#create_booking").validate();

            async function getCouponDiscount(price, type_id) {
                var discount = 0;
                    const result = await $.ajax({
                        url: "{{ route('get_coupon_discount') }}",
                        dataType: 'json',
                        data: {
                            'coupon_code': $('#coupon_code').val(),
                            'booking_date': $('#arrival_date').val(),
                        },
                    });
                    if (result.status === 1) {
                        if (result.coupon.hms_room_type_id == type_id) {
                            if(result.coupon.discount_type == 'fixed'){
                                discount = result.coupon.discount;
                            }else{
                                discount = (result.coupon.discount * price) / 100;
                            }
                            coupon_id = result.coupon.id; 
                        }else{
                            discount = 0;
                        }
                    }
                return {
                    'discount':discount, 
                    'coupon_id': coupon_id,
                };
            }

       

            async function calculateAllPrice() {
                    calculateDays()
                    var extra_amount = 0;
                    var person = 0;
                    var room_price = 0;
                    var total = 0;
                    var total_discount = 0;
                    var type_id = null;

                    await Promise.all(
                        $('.booking_add_room table tbody tr').map(async function() {
                            
                            const tr = $(this);
                            const price = parseFloat(tr.find('input[name^="rooms["][name$="][price]"]').val());
                            const type_id = tr.find('input[name^="rooms["][name$="][type_id]"]').val();
                            if($('#coupon_code').val() != ''){
                                discount = await getCouponDiscount(price, type_id);
                                total_discount = total_discount + parseFloat(discount.discount);
                                coupon_id = discount.coupon_id;
                            }

                           

                            var total_price = calculateDays() * price;
                            // tr.find('.price-td').text(total_price.toFixed(0));
                            tr.find('.price-td').text(__currency_trans_from_en(total_price.toFixed(2) , true));
                            tr.find('input[name^="rooms["][name$="][total_price]"]').val(total_price);
                            room_price += total_price;
                            person += parseFloat(tr.find('input[name^="rooms["][name$="][no_of_adult]"]').val()) +
                                parseFloat(tr.find('input[name^="rooms["][name$="][no_of_child]"]').val());
                        })
                    );
                    
                    $('.extra').each(async function() {

                        if ($(this).prop('checked')) {
                            var per_type = $(this).data('value');
                            var price = parseFloat($(this).data('price'));
                            if (per_type == 'per_booking') {
                                extra_amount =  parseFloat(extra_amount) + price;
                                $('#' + $(this).val()).val(price);
                            }
                            if (per_type == 'per_person') {
                                extra_amount = parseFloat(extra_amount) + person * price;
                                $('#' + $(this).val()).val(person * price);
                            }
                            if (per_type == 'per_day') {
                                extra_amount = parseFloat(extra_amount) + calculateDays() * price;
                                $('#' + $(this).val()).val(calculateDays() * price);
                            }
                            if (per_type == 'per_day_per_person') {
                                extra_amount = parseFloat(extra_amount) +  calculateDays() * person * price;
                                $('#' + $(this).val()).val(calculateDays() * person * price);
                            }
                        } else {
                            $('#' + $(this).val()).val('');
                        }

                    });

                    var extra = parseFloat(extra_amount);
                    var room_price = parseFloat(room_price);

                    // input field discount logic

                    if($('#discount_percent').val() != '' && $('#discount_percent').val() > 0){
                       discount = ($('#discount_percent').val() * (extra + room_price) / 100)
                       total_discount = discount;
                       coupon_id = null;
                       $('#discount_type').val('Percentage');
                       $('#total_discount').val($('#discount_percent').val());
                       $('.total_discount_show').text(__currency_trans_from_en(total_discount.toFixed(2) , true));
                    }

                    total = extra + room_price - total_discount;
                    $('#final_total_input').val(total);
                    $('.payment-amount').val(total);
                   

                    if($('#coupon_code').val() != ''){
                        if(total_discount > 0){
                            $('#coupon_id').val(coupon_id);
                            $('#discount_type').val('Fixed');
                            $('#total_discount').val(total_discount);
                            $('.total_discount_show').text(__currency_trans_from_en(total_discount.toFixed(2) , true));
                            $('.coupon-box').show();
                            $('.coupon-box').html("{{ __('hms::lang.applied_successfully') }}").css({'font-weight': 'bold','font-size': '16px', 'color': 'green'});
                        }else{
                            $('#coupon_id').val(null);
                            $('#discount_type').val(null);
                            $('#total_discount').val(null);
                            $('.coupon-box').show();
                            $('.coupon-box').html("{{ __('hms::lang.something_went_wrong') }}").css({'font-weight': 'bold','font-size': '16px', 'color': 'red'});
                        }
                    }else{
                        $('#coupon_id').val(null);
                        $('.coupon-box').hide();

                        if($('#discount_percent').val() == ''){
                            $('#discount_type').val(null);
                            $('#total_discount').val(null);
                            $('.total_discount_show').text(__currency_trans_from_en(total_discount.toFixed(2) , true));
                        }
                    }


                    $('.total').text(__currency_trans_from_en(total.toFixed(2) , true));
                    $('.extra_price').text(__currency_trans_from_en(extra_amount.toFixed(2) , true));
                    $('.room_price').text(__currency_trans_from_en(room_price.toFixed(2) , true));

                    calculate_balance_due();
            }

           

            function calculateDays() {
    
                // Assuming you have the start and end date strings in 'D-MM-YYYY' format
                const startDateString = $('#arrival_date').val();
                const endDateString = $('#departure_date').val();

                // Convert the date strings to moment objects using the input format
                const startDate = moment(startDateString, moment_date_format, true);
                const endDate = moment(endDateString, moment_date_format, true);

                // Check if the date strings are valid
                if (startDate.isValid() && endDate.isValid()) {
                // Calculate the difference in days
                var daysDifference = endDate.diff(startDate, 'days'); // Adding 1 to include both start and end days
                    if(daysDifference <= 0){
                        ++daysDifference;
                    }

                    $('.days_count').text(daysDifference + ' Days')

                    return daysDifference;
                }
            }
            //get customer
            $('select#customer_id').select2({
                ajax: {
                    url: '/contacts/customers',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term, // search term
                            page: params.page,
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data,
                        };
                    },
                },
                templateResult: function (data) { 
                    var template = '';
                    if (data.supplier_business_name) {
                        template += data.supplier_business_name + "<br>";
                    }
                    template += data.text + "<br>" + LANG.mobile + ": " + data.mobile;

                    if (typeof(data.total_rp) != "undefined") {
                        var rp = data.total_rp ? data.total_rp : 0;
                        template += "<br><i class='fa fa-gift text-success'></i> " + rp;
                    }

                    return  template;
                },
                minimumInputLength: 1,
                language: {
                    noResults: function() {
                        var name = $('#customer_id')
                            .data('select2')
                            .dropdown.$search.val();
                        return (
                            '<button type="button" data-name="' +
                            name +
                            '" class="btn btn-link add_new_customer"><i class="fa fa-plus-circle fa-lg" aria-hidden="true"></i>&nbsp; ' +
                            __translate('add_name_as_new_customer', { name: name }) +
                            '</button>'
                        );
                    },
                },
                escapeMarkup: function(markup) {
                    return markup;
                },
            });
            $('#customer_id').on('select2:select', function(e) {
                var data = e.params.data;
                if (data.pay_term_number) {
                    $('input#pay_term_number').val(data.pay_term_number);
                } else {
                    $('input#pay_term_number').val('');
                }

                if (data.pay_term_type) {
                    $('#add_sell_form select[name="pay_term_type"]').val(data.pay_term_type);
                    $('#edit_sell_form select[name="pay_term_type"]').val(data.pay_term_type);
                } else {
                    $('#add_sell_form select[name="pay_term_type"]').val('');
                    $('#edit_sell_form select[name="pay_term_type"]').val('');
                }
                
                update_shipping_address(data);
                $('#advance_balance_text').text(__currency_trans_from_en(data.balance), true);
                $('#advance_balance').val(data.balance);

                if (data.price_calculation_type == 'selling_price_group') {
                    $('#price_group').val(data.selling_price_group_id);
                    $('#price_group').change();
                } else {
                    $('#price_group').val('');
                    $('#price_group').change();
                }
                if ($('.contact_due_text').length) {
                    get_contact_due(data.id);
                }
            });

            function update_shipping_address(data) {
               
                if ($('#billing_address_div').length) {
                    var address = [];
                    if (data.supplier_business_name) {
                        address.push(data.supplier_business_name);
                    }
                    if (data.name) {
                        address.push('<br>' + data.name);
                    }
                    if (data.text) {
                        address.push('<br>' + data.text);
                    }
                    if (data.address_line_1) {
                        address.push('<br>' + data.address_line_1);
                    }
                    if (data.address_line_2) {
                        address.push('<br>' + data.address_line_2);
                    }
                    if (data.city) {
                        address.push('<br>' + data.city);
                    }
                    if (data.state) {
                        address.push(data.state);
                    }
                    if (data.country) {
                        address.push(data.country);
                    }
                    if (data.zip_code) {
                        address.push('<br>' + data.zip_code);
                    }
                    var billing_address = address.join(', ');
                    $('#billing_address_div').html(billing_address);
                }

            }

            function get_contact_due(id) {
                $.ajax({
                    method: 'get',
                    url: /get-contact-due/ + id,
                    dataType: 'text',
                    success: function(result) {
                        if (result != '') {
                            $('.contact_due_text').find('span').text(result);
                            $('.contact_due_text').removeClass('hide');
                        } else {
                            $('.contact_due_text').find('span').text('');
                            $('.contact_due_text').addClass('hide');
                        }
                    },
                });
            }

            set_default_customer();

            function set_default_customer() {
                var default_customer_id = $('#default_customer_id').val();
                var default_customer_name = $('#default_customer_name').val();
                var default_customer_balance = $('#default_customer_balance').val();
                var default_customer_address = $('#default_customer_address').val();
                var exists = default_customer_id ? $('select#customer_id option[value=' + default_customer_id + ']').length : 0;
                if (exists == 0 && default_customer_id) {
                    $('select#customer_id').append(
                        $('<option>', { value: default_customer_id, text: default_customer_name })
                    );
                }
                $('#advance_balance_text').text(__currency_trans_from_en(default_customer_balance), true);
                $('#advance_balance').val(default_customer_balance);
           
                if (default_customer_address) {
                    $('#shipping_address').val(default_customer_address);
                }
                $('select#customer_id')
                    .val(default_customer_id)
                    .trigger('change');

                if ($('#default_selling_price_group').length) {
                    $('#price_group').val($('#default_selling_price_group').val());
                    $('#price_group').change();
                }

                //initialize tags input (tagify)
                if ($("textarea#repair_defects").length > 0 && !customer_set) {
                    let suggestions = [];
                    if ($("input#pos_repair_defects_suggestion").length > 0 && $("input#pos_repair_defects_suggestion").val().length > 2) {
                        suggestions = JSON.parse($("input#pos_repair_defects_suggestion").val());    
                    }
                    let repair_defects = document.querySelector('textarea#repair_defects');
                    tagify_repair_defects = new Tagify(repair_defects, {
                            whitelist: suggestions,
                            maxTags: 100,
                            dropdown: {
                                maxItems: 100,           // <- mixumum allowed rendered suggestions
                                classname: "tags-look", // <- custom classname for this dropdown, so it could be targeted
                                enabled: 0,             // <- show suggestions on focus
                                closeOnSelect: false    // <- do not hide the suggestions dropdown once an item has been selected
                            }
                            });
                }

                customer_set = true;
            }

            $(document).on('click', '.add_new_customer', function() {
                $('#customer_id').select2('close');
                var name = $(this).data('name');
                $('.contact_modal')
                    .find('input#name')
                    .val(name);
                $('.contact_modal')
                    .find('select#contact_type')
                    .val('customer')
                    .closest('div.contact_type_div')
                    .addClass('hide');
                $('.contact_modal').modal('show');
            });
            $('form#quick_add_contact')
                .submit(function(e) {
                    e.preventDefault();
                })
                .validate({
                    rules: {
                        contact_id: {
                            remote: {
                                url: '/contacts/check-contacts-id',
                                type: 'post',
                                data: {
                                    contact_id: function() {
                                        return $('#contact_id').val();
                                    },
                                    hidden_id: function() {
                                        if ($('#hidden_id').length) {
                                            return $('#hidden_id').val();
                                        } else {
                                            return '';
                                        }
                                    },
                                },
                            },
                        },
                    },
                    messages: {
                        contact_id: {
                            remote: LANG.contact_id_already_exists,
                        },
                    },
                    submitHandler: function(form) {
                        $.ajax({
                            method: 'POST',
                            url: base_path + '/check-mobile',
                            dataType: 'json',
                            data: {
                                contact_id: function() {
                                    return $('#hidden_id').val();
                                },
                                mobile_number: function() {
                                    return $('#mobile').val();
                                },
                            },
                            beforeSend: function(xhr) {
                                __disable_submit_button($(form).find('button[type="submit"]'));
                            },
                            success: function(result) {
                                if (result.is_mobile_exists == true) {
                                    swal({
                                        title: LANG.sure,
                                        text: result.msg,
                                        icon: 'warning',
                                        buttons: true,
                                        dangerMode: true,
                                    }).then(willContinue => {
                                        if (willContinue) {
                                            submitQuickContactForm(form);
                                        } else {
                                            $('#mobile').select();
                                        }
                                    });
                                    
                                } else {
                                    submitQuickContactForm(form);
                                }
                            },
                        });
                    },
                });
            $('.contact_modal').on('hidden.bs.modal', function() {
                $('form#quick_add_contact')
                    .find('button[type="submit"]')
                    .removeAttr('disabled');
                $('form#quick_add_contact')[0].reset();
            });
            function submitQuickContactForm(form) {
                var data = $(form).serialize();
                $.ajax({
                    method: 'POST',
                    url: $(form).attr('action'),
                    dataType: 'json',
                    data: data,
                    beforeSend: function(xhr) {
                        __disable_submit_button($(form).find('button[type="submit"]'));
                    },
                    success: function(result) {
                        if (result.success == true) {
                            var name = result.data.name;

                            if (result.data.supplier_business_name) {
                                name += result.data.supplier_business_name;
                            }
                            
                            $('select#customer_id').append(
                                $('<option>', { value: result.data.id, text: name })
                            );
                            $('select#customer_id')
                                .val(result.data.id)
                                .trigger('change');
                            $('div.contact_modal').modal('hide');
                            update_shipping_address(result.data)
                            toastr.success(result.msg);
                        } else {
                            toastr.error(result.msg);
                        }
                    },
                }); 
            }

            $("#discount_percent").keyup(function(){
                
                if($(this).val() > 0){
                    $('#coupon_code').prop('disabled', true);
                    calculateAllPrice();
                }else{
                    calculateAllPrice();
                    $('#coupon_code').prop('disabled', false);
                    
                }

            });

            $("#coupon_code").keyup(function(){
                
                if($(this).val() != ''){
                    $('#discount_percent').prop('disabled', true);
                }else{
                    $('#discount_percent').prop('disabled', false);
                    calculateAllPrice();
                }

            });
            
            // {{-- payment code  --}}

            $(document).on('change', '.payment_types_dropdown', function(e) {
                var default_accounts = $('select#select_location_id').length ? 
                            $('select#select_location_id')
                            .find(':selected')
                            .data('default_payment_accounts') : $('#location_id').data('default_payment_accounts');
                var payment_type = $(this).val();
                var payment_row = $(this).closest('.payment_row');
                if (payment_type && payment_type != 'advance') {
                    var default_account = default_accounts && default_accounts[payment_type]['account'] ? 
                        default_accounts[payment_type]['account'] : '';
                    var row_index = payment_row.find('.payment_row_index').val();

                    var account_dropdown = payment_row.find('select#account_' + row_index);
                    if (account_dropdown.length && default_accounts) {
                        account_dropdown.val(default_account);
                        account_dropdown.change();
                    }
                }

                //Validate max amount and disable account if advance 
                amount_element = payment_row.find('.payment-amount');
                account_dropdown = payment_row.find('.account-dropdown');
                if (payment_type == 'advance') {
                    max_value = $('#advance_balance').val();
                    msg = $('#advance_balance').data('error-msg');
                    amount_element.rules('add', {
                        'max-value': max_value,
                        messages: {
                            'max-value': msg,
                        },
                    });
                    if (account_dropdown) {
                        account_dropdown.prop('disabled', true);
                        account_dropdown.closest('.form-group').addClass('hide');
                    }
                } else {
                    amount_element.rules("remove", "max-value");
                    if (account_dropdown) {
                        account_dropdown.prop('disabled', false); 
                        account_dropdown.closest('.form-group').removeClass('hide');
                    }    
                }
            });


            $(document).on('change', '.payment-amount', function() {
                    calculate_balance_due();
            });

            function calculate_balance_due() {
                var total_payable = __read_number($('#final_total_input'));
                var total_paying = 0;
                    $('.payment-amount')
                    .each(function() {
                        if (parseFloat($(this).val())) {
                            total_paying += __read_number($(this));
                        }
                    });
                var bal_due = total_payable - total_paying;
                var change_return = 0;

                //change_return
                if (bal_due < 0 || Math.abs(bal_due) < 0.05) {
                    __write_number($('input#change_return'), bal_due * -1);
                    $('span.change_return_span').text(__currency_trans_from_en(bal_due * -1, true));
                    change_return = bal_due * -1;
                    bal_due = 0;
                } else {
                    __write_number($('input#change_return'), 0);
                    $('span.change_return_span').text(__currency_trans_from_en(0, true));
                    change_return = 0;
                    
                }

                if (change_return !== 0) {
                    $('#change_return_payment_data').removeClass('hide');
                } else {
                    $('#change_return_payment_data').addClass('hide');
                }

                __write_number($('input#total_paying_input'), total_paying);
                $('span.total_paying').text(__currency_trans_from_en(total_paying, true));

                __write_number($('input#in_balance_due'), bal_due);
                $('span.balance_due').text(__currency_trans_from_en(bal_due, true));

                __highlight(bal_due * -1, $('span.balance_due'));
                __highlight(change_return * -1, $('span.change_return_span'));
            }

            //  commom code end
        });

        $(document).on('click', 'button#submit_action', function(e){
    		if($('.booking_add_room table tbody tr').length == 0 && $('input[type="checkbox"][name^="extras["][name$="][id]"]:checked').length == 0){
                toastr.warning("{{ __('hms::lang.no_rooms_or_extras') }}");
                return false;
            }
    	});
    </script>
@endsection