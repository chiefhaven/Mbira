<div>
    @lang('hms::lang.id'): {{ $info->ref_no }} <br>
    {{ $info->contact->name }} <br>
    {{ $info->contact->mobile }} <br>
    @if($info->status == 'confirmed')
        <h6 class="bg-green badge">{{ucfirst($info->status) }}</h6>
    @elseif($info->status == 'pending')
        <h6 class="bg-yellow badge">{{ucfirst($info->status) }}</h6>
    @elseif($info->status == 'cancelled')
        <h6 class="bg-red badge">{{ucfirst($info->status) }}</h6>
    @endif
    <br>
    @lang('hms::lang.stay') : {{@format_datetime($info->hms_booking_arrival_date_time)}} - {{ @format_datetime($info->hms_booking_departure_date_time) }}
    <hr>
    <br>
</div>