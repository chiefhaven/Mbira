@extends('layouts.app')
@section('title', __('hms::lang.hms'))
@section('content')
    @include('hms::layouts.nav')
<section class="content no-print">
    <div class="row">   
        <div class="col-md-4">
            <div class="box box-solid">
                <div class="box-body p-10">
                    <table class="table no-margin">
                        <tr>
                            <th>@lang('hms::lang.rooms_booked_today')</th>
                            <td>{{ $room_count->booked_rooms ?? 0 }}</td>
                        </tr>
                        <tr>
                            <th>@lang('hms::lang.pending_rooms_today')</th>
                            <td>{{ $room_count->pending_rooms ?? 0 }}</td>
                        </tr>
                        <tr>
                            <th>@lang('hms::lang.available_rooms_today')</th>
                            <td>{{ $room_count->unbooked_rooms ?? 0 }}</td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang('hms::lang.available_rooms_by_type')</h3>
                </div>
                <div class="box-body p-10">
                    <table class="table no-margin">
                        @foreach ($unbooked_rooms_by_type as $types)
                            <tr>
                                <th>{{ $types->room_type }}</th>
                                <td>{{ $types->unbooked_count ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang('hms::lang.guests')</h3>
                </div>
                <div class="box-body p-10">
                    <table class="table no-margin">
                        <tr>
                            <th>@lang('hms::lang.staying_tonight')</th>
                            <td>{{ $guest_count_tonight->sum('adult_guests') + $guest_count_tonight->sum('child_guests') }}</td>
                        </tr>
                        <tr>
                            <td>@lang('hms::lang.adults')</td>
                            <td>{{ $guest_count_tonight->sum('adult_guests') ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td>@lang('hms::lang.childrens')</td>
                            <td>{{ $guest_count_tonight->sum('child_guests') ?? 0 }}</td>
                        </tr>
                    </table>
                </div>
                <div class="box-body p-10">
                    <table class="table no-margin">
                        <tr>
                            <th>@lang('hms::lang.arriving_today')</th>
                            <td>{{ $arrive_today->sum('adult_guests') + $arrive_today->sum('child_guests') }}</td>
                        </tr>
                        <tr>
                            <td>@lang('hms::lang.adults')</td>
                            <td>{{ $arrive_today->sum('adult_guests') ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td>@lang('hms::lang.childrens')</td>
                            <td>{{ $arrive_today->sum('child_guests') ?? 0 }}</td>
                        </tr>
                    </table>
                </div>
                <div class="box-body p-10">
                    <table class="table no-margin">
                        <tr>
                            <th>@lang('hms::lang.leaving_today')</th>
                            <td>{{ $leave_today->sum('adult_guests') + $leave_today->sum('child_guests') }}</td>
                        </tr>
                        <tr>
                            <td>@lang('hms::lang.adults')</td>
                            <td>{{ $leave_today->sum('adult_guests') ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td>@lang('hms::lang.childrens')</td>
                            <td>{{ $leave_today->sum('child_guests') ?? 0 }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#cn_1" data-toggle="tab" aria-expanded="true">
                            @lang('hms::lang.arrivals')
                        </a>
                    </li>
                    <li>
                        <a href="#cn_2" data-toggle="tab" aria-expanded="true">
                            @lang('hms::lang.departures')
                        </a>
                    </li>
                    <li>
                        <a href="#cn_3" data-toggle="tab" aria-expanded="true">
                            @lang('hms::lang.latest')
                        </a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active" id="cn_1">
                        @forelse ($today_arrivales as $info)
                            @include('hms::dashboard.partial.booking_info')
                        @empty
                            @lang('hms::lang.no_arrivals_today')
                        @endforelse
                    </div>
                    <div class="tab-pane" id="cn_2">
                        @forelse ($today_departure as $info)
                            @include('hms::dashboard.partial.booking_info')
                        @empty
                            @lang('hms::lang.no_departures_today')
                        @endforelse
                    </div>
                    <div class="tab-pane" id="cn_3">
                        @forelse ($latest_bookig as $info)
                            @include('hms::dashboard.partial.booking_info')
                        @empty
                            @lang('hms::lang.no_latest')
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#chat_1" data-toggle="tab" aria-expanded="true">
                            @lang('hms::lang.upcoming_bookings')
                        </a>
                    </li>
                    <li>
                        <a href="#chat_2" data-toggle="tab" aria-expanded="true">
                            @lang('hms::lang.past_bookings')
                        </a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active" id="chat_1">
                        {!! $booking_chart->container() !!}
                    </div>
                    <div class="tab-pane" id="chat_2">
                        {!! $past_booking_chart->container() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('javascript')
{!! $booking_chart->script() !!}
{!! $past_booking_chart->script() !!}
@endsection
