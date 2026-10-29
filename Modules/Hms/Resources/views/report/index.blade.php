@extends('layouts.app')
@section('title', __('hms::lang.reports'))

@section('content')
@include('hms::layouts.nav')
<section class="content-header">
    <h1> @lang('hms::lang.reports')
    </h1>
    <p><i class="fa fa-info-circle"></i> @lang('hms::lang.report_help_text') </p>
</section>
    <!-- Main content -->
    <section class="content">
        <div class="box box-solid">
            {{-- <div class="box-header">
                <h3 class="box-title"> @lang('hms::lang.reports')</h3>
            </div> --}}
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3">
                        {!! Form::open([
                            'url' => action([\Modules\Hms\Http\Controllers\HmsReportController::class, 'index']),
                            'method' => 'get',
                        ]) !!}
                            <div class="form-group">
                                {!! Form::label('date_to', __('hms::lang.date_to') . ':') !!}
                                {!! Form::text('date_to', request('date_to'), [
                                    'class' => 'form-control date_to',
                                    'placeholder' => __('hms::lang.date_to'),
                                    'readonly',
                                    'required',
                                    'id' => 'date_to',
                                ]) !!}
                            </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('date_from', __('hms::lang.date_from') . ':') !!}
                            {!! Form::text('date_from', request('date_from'), [
                                'class' => 'form-control date_from',
                                'placeholder' => __('hms::lang.date_from'),
                                'readonly',
                                'required',
                                'id' => 'date_from',
                            ]) !!}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary form-control">@lang('hms::lang.generate')</button>
                        </div>
                    </div>
                    {!! Form::close() !!}
                </div>
            </div>
        </div>

            @if (request()->has('date_to') && request()->has('date_from'))
                <div class="box box-solid">
                    <div class="box-body">
                        <div class="row">
                            <hr>  
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.total_bookings_received'):</strong> <br>
                                {{ $total_booking->total_count }}
                            </div> 
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.total_guests'):</strong> <br>
                                {{ $total_booking->total_guest }}
                            </div> 
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.total_nights_booked'):</strong> <br>
                                {{ $total_booking->total_nights }}
                            </div> 
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.total_amount'):</strong> <br>
                                <span class="display_currency" data-currency_symbol="true"> {{ $total_booking->total_amount }} </span>
                            </div>   
                        </div>
                        <div class="row mt-5">
                            <hr>  
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.total_confirmed_bookings'):</strong> <br>
                                {{ $total_confirmed_booking->total_count }}
                            </div> 
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.total_confirmed_guests'):</strong> <br>
                                {{ $total_confirmed_booking->total_guest }}
                            </div> 
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.total_confirmed_nights')</strong> <br>
                                {{ $total_confirmed_booking->total_nights }}
                            </div> 
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.total_amount'):</strong> <br>
                                <span class="display_currency" data-currency_symbol="true"> {{ $total_confirmed_booking->total_amount }} </span>
                            </div>  
                        </div>
                        <div class="row mt-5">
                            <hr>  
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.total_cancelled_bookings'):</strong> <br>
                                {{ $total_cancelled_booking->total_count }}
                            </div> 
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.total_cancelled_guests'):</strong> <br>
                                {{ $total_cancelled_booking->total_guest }}
                            </div> 
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.total_cancelled_nights')</strong> <br>
                                {{ $total_cancelled_booking->total_nights }} 
                            </div> 
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.total_amount'):</strong> <br>
                                <span class="display_currency" data-currency_symbol="true"> {{ $total_cancelled_booking->total_amount }} </span>
                            </div>  
                        </div>
                        <div class="row mt-5">
                            <hr>  
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.total_pending_bookings'):</strong> <br>
                                {{ $total_pending_booking->total_count }}
                            </div> 
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.total_pending_guests'):</strong> <br>
                                {{ $total_pending_booking->total_guest }} 
                            </div> 
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.total_pending_nights')</strong> <br>
                                {{ $total_pending_booking->total_nights }}
                            </div> 
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.total_amount'):</strong> <br>
                                <span class="display_currency" data-currency_symbol="true">  {{ $total_pending_booking->total_amount }} </span>
                            </div>  
                        </div>
                        <div class="row">
                            <hr>  
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.adults_guests'):</strong> <br>
                                {{ $total_confirmed_booking->total_adult_guest }}       

                                @if ($total_confirmed_booking->total_guest != 0)
                                    ({{ $total_confirmed_booking->total_adult_guest / $total_confirmed_booking->total_guest *100 }} %)
                                @endif
                            </div> 
                            <div class="col-md-3">
                                <strong>@lang('hms::lang.children_guests'):</strong> <br>
                                {{ $total_confirmed_booking->total_childs_guest }}

                                @if ($total_confirmed_booking->total_guest != 0)
                                    ({{ $total_confirmed_booking->total_childs_guest / $total_confirmed_booking->total_guest *100 }} %)
                                @endif
                            </div>   
                        </div>
                    </div>
                </div>
                <div class="box box-solid">
                    <div class="box-body">
                        <div class="row">
                            <table class="table table-hover">
                                <thead>
                                    <tr class="bg-light-green">
                                        <th>@lang('hms::lang.rooms_per_booking')</th>
                                        <th>@lang('hms::lang.bookings')</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>@lang('hms::lang.one_room_bookings')</td>
                                        <td>{{ $rooms_by_booking_count->one_line_count }}</td>
                                        <td> 
                                        @if (($rooms_by_booking_count->one_line_count + $rooms_by_booking_count->two_lines_count + $rooms_by_booking_count->more_than_two_lines_count) !=0)

                                            {{ number_format(($rooms_by_booking_count->one_line_count/($rooms_by_booking_count->one_line_count + $rooms_by_booking_count->two_lines_count + $rooms_by_booking_count->more_than_two_lines_count) * 100)) }} %
                                        @else
                                        0 %
                                        @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>@lang('hms::lang.two_room_bookings')</td>
                                        <td>{{ $rooms_by_booking_count->two_lines_count }}</td>
                                        <td>
                                        @if (($rooms_by_booking_count->one_line_count + $rooms_by_booking_count->two_lines_count + $rooms_by_booking_count->more_than_two_lines_count) !=0)

                                            {{ number_format(($rooms_by_booking_count->two_lines_count/($rooms_by_booking_count->one_line_count + $rooms_by_booking_count->two_lines_count + $rooms_by_booking_count->more_than_two_lines_count) * 100)) }} %
                                        @else
                                        0 %
                                        @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>@lang('hms::lang.two_+_room_bookings')</td>
                                        <td>{{ $rooms_by_booking_count->more_than_two_lines_count }}</td>
                                        <td>
                                            @if (($rooms_by_booking_count->one_line_count + $rooms_by_booking_count->two_lines_count + $rooms_by_booking_count->more_than_two_lines_count) !=0)

                                            {{ number_format(($rooms_by_booking_count->more_than_two_lines_count /($rooms_by_booking_count->one_line_count + $rooms_by_booking_count->two_lines_count + $rooms_by_booking_count->more_than_two_lines_count) * 100)) }} %
                                        @else
                                        0 %
                                        @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="box box-solid">
                    <div class="box-body">
                        <div class="row">
                            <table class="table table-hover">
                                <thead>
                                    <tr class="bg-light-green">
                                        <th>@lang('hms::lang.nights_per_booking')</th>
                                        <th>@lang('hms::lang.booking')</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $total_nights = $count_by_night->one_night_count +$count_by_night->two_night_count + $count_by_night->three_night_count+$count_by_night->four_night_count + $count_by_night->five_night_count+$count_by_night->six_night_count + $count_by_night->more_than_six_night_count;
                                    @endphp
                                    <tr>
                                        <td>@lang('hms::lang.1_night_bookings')</td>
                                        <td>{{ $count_by_night->one_night_count }}</td>
                                        <td>
                                            @if (($total_nights !=0))
                                                {{ number_format(($count_by_night->one_night_count/$total_nights *100)) }} %
                                            @else
                                            0%
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>@lang('hms::lang.2_night_bookings')</td>
                                        <td>{{ $count_by_night->two_night_count }}</td>
                                        <td>
                                            @if (($total_nights !=0))
                                                {{ number_format(($count_by_night->two_night_count/$total_nights *100)) }} %
                                            @else
                                            0%
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>@lang('hms::lang.3_night_bookings')</td>
                                        <td>{{ $count_by_night->three_night_count }}</td>
                                        <td>
                                            @if (($total_nights !=0))
                                                {{ number_format(($count_by_night->three_night_count/$total_nights *100)) }} %
                                            @else
                                            0%
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>@lang('hms::lang.4_night_bookings')</td>
                                        <td>{{ $count_by_night->four_night_count }}</td>
                                        <td>
                                            @if (($total_nights !=0))
                                                {{ number_format(($count_by_night->four_night_count/$total_nights *100)) }} %
                                            @else
                                            0%
                                            @endif
                                        </td>
                                    </tr><tr>
                                        <td>@lang('hms::lang.5_night_bookings')</td>
                                        <td>{{ $count_by_night->five_night_count }}</td>
                                        <td>
                                            @if (($total_nights !=0))
                                                {{ number_format(($count_by_night->five_night_count/$total_nights *100)) }} %
                                            @else
                                            0%
                                            @endif
                                        </td>
                                    </tr><tr>
                                        <td>@lang('hms::lang.6_night_bookings')</td>
                                        <td>{{ $count_by_night->six_night_count }}</td>
                                        <td>
                                            @if (($total_nights !=0))
                                                {{ number_format(($count_by_night->six_night_count/$total_nights *100)) }} %
                                            @else
                                            0%
                                            @endif
                                        </td>
                                    </tr><tr>
                                        <td>@lang('hms::lang.6_+_night_bookings')</td>
                                        <td>{{ $count_by_night->more_than_six_night_count }}</td>
                                        <td>
                                            @if (($total_nights !=0))
                                                {{ number_format(($count_by_night->more_than_six_night_count/$total_nights *100)) }} %
                                            @else
                                            0%
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="box box-solid">
                    <div class="box-body">
                        <div class="row">
                            <table class="table table-hover">
                                <thead>
                                    <tr class="bg-light-green">
                                        <th>@lang('hms::lang.guests_per_booking')</th>
                                        <th>@lang('hms::lang.booking')</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @php
                                    $total_adults = $count_by_adults->one_adult_count + $count_by_adults->two_adults_count + $count_by_adults->three_adults_count + $count_by_adults->four_adults_count + $count_by_adults->five_adults_count + $count_by_adults->six_adults_count + $count_by_adults->more_than_six_adults_count
                                @endphp
                                    <tr>
                                        <td>@lang('hms::lang.1_guest_bookings')</td>
                                        <td>{{ $count_by_adults->one_adult_count }}</td>
                                        <td>
                                            @if (($total_adults !=0))
                                                {{ number_format(($count_by_adults->one_adult_count/$total_adults*100)) }} %
                                            @else
                                            0%
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>@lang('hms::lang.2_guest_bookings')</td>
                                        <td>{{ $count_by_adults->two_adults_count }}</td>
                                        <td>
                                            @if (($total_adults !=0))
                                                {{ number_format(($count_by_adults->two_adults_count/$total_adults*100)) }} %
                                            @else
                                            0%
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>@lang('hms::lang.3_guest_bookings')</td>
                                        <td>{{ $count_by_adults->three_adults_count }}</td>
                                        <td>
                                            @if (($total_adults !=0))
                                                {{ number_format(($count_by_adults->three_adults_count/$total_adults*100)) }} %
                                            @else
                                            0%
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>@lang('hms::lang.4_guest_bookings')</td>
                                        <td>{{ $count_by_adults->four_adults_count }}</td>
                                        <td>
                                            @if (($total_adults !=0))
                                                {{ number_format(($count_by_adults->four_adults_count/$total_adults*100)) }} %
                                            @else
                                            0%
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>@lang('hms::lang.5_guest_bookings')</td>
                                        <td>{{ $count_by_adults->five_adults_count }}</td>
                                        <td>
                                            @if (($total_adults !=0))
                                                {{ number_format(($count_by_adults->five_adults_count/$total_adults*100)) }} %
                                            @else
                                            0%
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>@lang('hms::lang.6_guest_bookings')</td>
                                        <td>{{ $count_by_adults->six_adults_count }}</td>
                                        <td>
                                            @if (($total_adults !=0))
                                                {{ number_format(($count_by_adults->six_adults_count/$total_adults*100)) }} %
                                            @else
                                            0%
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>@lang('hms::lang.6_+_guest_bookings')</td>
                                        <td>{{ $count_by_adults->more_than_six_adults_count }}</td>
                                        <td>
                                            @if (($total_adults !=0))
                                                {{ number_format(($count_by_adults->more_than_six_adults_count/$total_adults*100)) }} %
                                            @else
                                            0%
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="box box-solid">
                    <div class="box-body">
                        <div class="row">
                            <table class="table table-hover">
                                <thead>
                                    <tr class="bg-light-green">
                                        <th rowspan="2">@lang('hms::lang.rooms')</th>
                                        <th colspan="4">@lang('hms::lang.booking_received')</th>
                                        <th colspan="4">@lang('hms::lang.booking_confirmed')</th>
                                        <th colspan="4">@lang('hms::lang.booking_cancelled')</th>
                                        <th colspan="4">@lang('hms::lang.booking_pending')</th>
                                    </tr>
                                    <tr>
                                        <th class="bg-info">@lang('hms::lang.booked')</th>
                                        <th class="bg-info">@lang('hms::lang.guests')</th>
                                        <th class="bg-info">@lang('hms::lang.nights')</th>
                                        <th class="bg-info">@lang('hms::lang.amount')</th>
                                        <th class="bg-success">@lang('hms::lang.booked')</th>
                                        <th class="bg-success">@lang('hms::lang.guests')</th>
                                        <th class="bg-success">@lang('hms::lang.nights')</th>
                                        <th class="bg-success">@lang('hms::lang.amount')</th>
                                        <th class="bg-danger">@lang('hms::lang.booked')</th>
                                        <th class="bg-danger">@lang('hms::lang.guests')</th>
                                        <th class="bg-danger">@lang('hms::lang.nights')</th>
                                        <th class="bg-danger">@lang('hms::lang.amount')</th>
                                        <th class="bg-yellow">@lang('hms::lang.booked')</th>
                                        <th class="bg-yellow">@lang('hms::lang.guests')</th>
                                        <th class="bg-yellow">@lang('hms::lang.nights')</th>
                                        <th class="bg-yellow">@lang('hms::lang.amount')</th>
                                    </tr>
                                
                                    @foreach ($all_room_types as $index => $all_room_type)
                                        <tr>
                                            <td>{{ $all_room_type->type }}</td>
                                            <td>{{ $all_room_type->transactions_count }}</td>
                                            <td>{{ $all_room_type->no_of_guest ?? 0 }}</td>
                                            
                                                @if($all_room_type->transactions_count != 0)
                                                    <td>
                                                        {{ $all_room_type->total_days == 0 ? 1 : $all_room_type->total_days }}
                                                    </td>
                                                @else
                                                    <td>0</td>
                                                @endif
                                           
                                            <td class="display_currency" data-currency_symbol="true">{{ $all_room_type->total_price   }}</td>

                                            @if(isset($confirmed_room_types[$index]))
                                                <td>{{ $confirmed_room_types[$index]->transactions_count  }}</td>
                                            @endif
                                            @if(isset($confirmed_room_types[$index]))
                                                <td>{{ $confirmed_room_types[$index]->no_of_guest ?? 0  }}</td>
                                            @endif
                                            @if(isset($confirmed_room_types[$index]))
                                                @if($confirmed_room_types[$index]->transactions_count != 0)
                                                    <td>
                                                        {{ $confirmed_room_types[$index]->total_days == 0 ? 1 : $confirmed_room_types[$index]->total_days }}
                                                    </td>
                                                @else
                                                    <td>0</td>
                                                @endif
                                            @endif
                                            @if(isset($confirmed_room_types[$index]))
                                                <td class="display_currency" data-currency_symbol="true">{{ 
                                                  $confirmed_room_types[$index]->total_price
                                                    }}
                                                </td>
                                            @endif

                                            @if(isset($cancelled_room_types[$index]))
                                                <td>{{ $cancelled_room_types[$index]->transactions_count ?? 0  }}</td>
                                            @endif
                                            @if(isset($cancelled_room_types[$index]))
                                                <td>{{ $cancelled_room_types[$index]->no_of_guest ?? 0  }}</td>
                                            @endif
                                            @if(isset($cancelled_room_types[$index]))
                                                @if($cancelled_room_types[$index]->transactions_count != 0)
                                                    <td>
                                                        {{ $cancelled_room_types[$index]->total_days == 0 ? 1 : $cancelled_room_types[$index]->total_days }}
                                                    </td>
                                                @else
                                                    <td>0</td>
                                                @endif

                                            @endif
                                            @if(isset($cancelled_room_types[$index]))
                                                <td class="display_currency" data-currency_symbol="true">
                                                    {{ 
                                                       $cancelled_room_types[$index]->total_price
                                                        }}
                                                </td>
                                            @endif

                                            @if(isset($pending_room_types[$index]))
                                                <td>{{ $pending_room_types[$index]->transactions_count ?? 0  }}</td>
                                            @endif
                                            @if(isset($pending_room_types[$index]))
                                                <td>{{ $pending_room_types[$index]->no_of_guest ?? 0  }}</td>
                                            @endif
                                            @if(isset($pending_room_types[$index]))

                                            @if($pending_room_types[$index]->transactions_count != 0)
                                                <td>
                                                    {{ $pending_room_types[$index]->total_days == 0 ? 1 : $pending_room_types[$index]->total_days }}
                                                </td>
                                            @else
                                                <td>0</td>
                                            @endif

                                            @endif
                                            @if(isset($pending_room_types[$index]))
                                                <td class="display_currency" data-currency_symbol="true">   
                                                    {{ 
                                                       $pending_room_types[$index]->total_price
                                                    }}
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </thead>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
    </section>
    <!-- /.content -->
@endsection

@section('javascript')
<script>
    $(document).ready(function(){
        var currentDate = new Date();
        var currentYear = currentDate.getFullYear(); // Get the current year
        var startOfYear = new Date(currentYear, 0, 1); // January 1st of the current year
        var formattedStartOfYear = moment(startOfYear)
        var currentDateTime = moment(currentDate)


        $('.date_to').datetimepicker({
            format: moment_date_format,
            ignoreReadonly: true,
            defaultDate: formattedStartOfYear
        });

        $('.date_from').datetimepicker({
            format: moment_date_format,
            ignoreReadonly: true,
            defaultDate: currentDateTime,
            minDate:formattedStartOfYear,
        });

        $('.date_to').on('dp.change', function (e) {
                var selectedDate = e.date;
            // Update the minimum date of the departure datepicker
            $('.date_from').data('DateTimePicker').minDate(selectedDate);
        });
    });
</script>
@endsection