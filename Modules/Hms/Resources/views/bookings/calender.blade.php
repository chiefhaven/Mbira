@extends('layouts.app')
@section('title', __('lang_v1.calendar'))

@section('content')
@include('hms::layouts.nav')
<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang( 'lang_v1.calendar' )</h1>
    <p><i class="fa fa-info-circle"></i> @lang('hms::lang.calender_help_text') </p>
</section>

<!-- Main content -->
<section class="content">
    <div class="box box-solid">
        <div class="box-header">
            <div class="box-tools pull-left">
                <h3>@lang('hms::lang.jump_to')</h3>
                <input type="text" value="{{ request()->input('date') ? request()->input('date') : ''  }}" class="form-control date_picker">
            </div>
            <div class="box-tools pull-left" style="margin-left: 25px; margin-top:45px;">
                <a class="btn btn-primary  mt-5" id="week_prev" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
                <a class="btn btn-primary  mt-5" id="day_prev" aria-label="Previous">
                    <span aria-hidden="true">&lsaquo;</span>
                </a>
            </div>

            <div class="box-tools pull-right" style="margin-top:45px;">
                <a class="btn btn-primary mt-5" id="day_next" aria-label="next">
                    <span aria-hidden="true">&rsaquo;</span>
                </a>
                <a class="btn btn-primary mt-5" id="week_next" aria-label="next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </div>
        </div>
        
        <div class="box-body">
            <div class="table-responsive">
                <table class="table table-bordered " id="bookings_calender">
                    <thead>
                        <tr>
                            <th style="width: 150px;">
                                {!! Form::select('type_id', $types,  request()->input('type_id') ? request()->input('type_id') : null  , ['class' => 'form-control', 'id' => 'type_id', 'placeholder' => __('hms::lang.type')]); !!}
                            </th>
                            {!! $date_html !!}
                        </tr>
                        {!! $html !!}
                    </thead>
                </table>
            </div>
        </div>
    </div>
</section>
<!-- /.content -->

@endsection

@section('javascript')
   <script>
    $(document).ready(function() {
        var currentDate = new Date();
        var currentDateTime = moment(currentDate);

        $('.date_picker').datetimepicker({
            format: moment_date_format,
            ignoreReadonly: true,
            defaultDate: currentDateTime
        });

        $('.date_picker').on('dp.change', function (e) {
              
            window.location.href = "{{ route('booking_calendar') }}?type_id=" + $('#type_id').val()+ "&date=" + $('.date_picker').val();

        });

        $('#type_id').on('change', function () {
            window.location.href = "{{ route('booking_calendar') }}?type_id=" + $('#type_id').val()+ "&date=" + $('.date_picker').val();
        })


        $('#week_next').on('click', function () {
            var weekNext = "{{ request()->input('week_next') }}";
            if(weekNext == ''){
                weekNext =1;
            }else{
                weekNext++;
            }
            window.location.href = "{{ route('booking_calendar') }}?type_id=" + $('#type_id').val()+ "&week_next=" + weekNext;
        })

        $('#week_prev').on('click', function () {

            var weekNext = "{{ request()->input('week_next') }}";
            if(weekNext == ''){
                weekNext = -1;
            }else{
                weekNext--;
            }
            window.location.href = "{{ route('booking_calendar') }}?type_id=" + $('#type_id').val()+ "&week_next=" + weekNext;
        })

        
        $('#day_next').on('click', function () {

                var daynext = "{{ request()->input('day_next') }}";
                if(daynext == ''){
                    daynext = 1;
                }else{
                    daynext ++;
                }
                window.location.href = "{{ route('booking_calendar') }}?type_id=" + $('#type_id').val()+ "&day_next=" + daynext;
        })

        $('#day_prev').on('click', function () {

                var daynext = "{{ request()->input('day_next') }}";
                if(daynext == ''){
                    daynext = -1;
                }else{
                    daynext --;
                }
                window.location.href = "{{ route('booking_calendar') }}?type_id=" + $('#type_id').val()+ "&day_next=" + daynext;
        })

        $(".add_booking").hover(
            function() {
                $(this).find(".add_booking_div").fadeIn();
            },
            function() {
                $(this).find(".add_booking_div").fadeOut();
            }
        );


    });  

   </script>
@endsection

<style>
.hotel-reservation-outer:last-child {
    padding-bottom: 5px;
    height: 30px;
}
.hotel-reservation-outer {
    height: 25px;
    width: 100%;
    position: relative;
}

.hotel-reservation-inner {
    height: 20px;
    width: 100%;
    border-radius: 2px;
    padding: 0 5px;
    color: #fff;
}
.bg-confirmed {
    background-color: #5ac5b6;
    border-color: #5ac5b6;
    color: #fff;
}
.add_booking_div{
    display: none;
    padding-top: 15px;
}
</style>

