<link rel="stylesheet" href="{{ asset('css/app.css?v=' . $asset_v) }}">
<style type="text/css">
    .box {
        border: 1px solid;
    }

    .table-pdf {
        width: 100%;
    }

    .table-pdf td,
    .table-pdf th {
        padding: 6px;
        text-align: left;
    }

    .w-20 {
        width: 20%;
        float: left;
    }

    .checklist {
        padding: 5px 15px;
        width: 100%;
    }

    .checkbox {
        width: 20%;
        float: left;
    }

    .checkbox-text {
        width: 80%;
        float: left;
    }

    .content-div {
        padding: 6px;
    }

    .table-slim {
        width: 100%;
    }

    .table-slim td,
    .table-slim th {
        padding: 1px !important;
        font-size: 12px;
    }

    .font-14 {
        font-size: 14px;
    }

    .font-12 {
        font-size: 12px;
    }

    body {
        font-size: 11px;
    }
</style>

<div class="width-100 box mb-10">
    <div class="width-50 f-left" align="center">
        @if (!empty(Session::get('business.logo')))
            <img src="{{ asset('uploads/business_logos/' . Session::get('business.logo')) }}" alt="Logo"
                style="width: auto; max-height: 90px; margin: auto;">
        @endif
    </div>
    <div class="width-50 f-left" align="center">
        <p style="text-align: center;">
            <strong class="font-14">
                {{ $business->name }}
            </strong>
            <br>
            <span class="font-12">
                {!! $business->name !!}
            </span>
        </p>
    </div>
</div>
<div class="width-100 box mb-10">
    <table class="no-border table-pdf">
        <tr>
            <th>@lang('hms::lang.booking_Id'):</th>
            <th>@lang('hms::lang.arrival_date_time'):</th>
            <th>@lang('hms::lang.departure_date_time'):</th>

            @if(!empty($transaction->check_in))
                <th>@lang('hms::lang.check_in'):</th>
            @endif

            @if(!empty($transaction->check_out))
                <th>@lang('hms::lang.check_out'):</th>
            @endif

            <th>@lang('hms::lang.status'):</th>
        </tr>
        <tr>
            <td style="padding-top: 8">
                {{ $transaction->ref_no }}
            </td>
            <td style="padding-top: 8">
                {{ @format_datetime($transaction->hms_booking_arrival_date_time) }}
            </td>
            <td style="padding-top: 8">
                {{ @format_datetime($transaction->hms_booking_departure_date_time) }}
            </td>
            @if(!empty($transaction->check_in))
                <td style="padding-top: 8">
                    {{ @format_datetime($transaction->check_in) }}
                </td>
            @endif
            @if(!empty($transaction->check_out))
            <td style="padding-top: 8">
                {{ @format_datetime($transaction->check_out) }}
            </td>
            @endif
            <td style="padding-top: 8">
                {{ ucfirst($transaction->status) }}
            </td>
        </tr>
    </table>
</div>
<div class="box mb-10">
    <table class="table-pdf">
        <tr>
            <td colspan="2" style="vertical-align: top;">
                <table class="width-100">
                    <tr>
                        <th>@lang('sale.customer_name'):</th>
                        <td>{{ $transaction->contact->name }}</td>
                    </tr>
                    <tr>
                        <th>@lang('hms::lang.address'):</th>
                        <td>
                            @if ($transaction->contact->landmark)
                                {{ $transaction->contact->landmark }},
                            @endif

                            {{ $transaction->contact->city }}

                            @if ($transaction->contact->state)
                                {{ ', ' . $transaction->contact->state }}
                            @endif
                            <br>
                            @if ($transaction->contact->country)
                                {{ $transaction->contact->country }}
                            @endif
                        </td>
                    </tr>
                    @if ($transaction->contact->mobile)
                        <tr>
                            <th>@lang('contact.mobile'):</th>
                            <td>{{ $transaction->contact->mobile }}</td>
                        </tr>
                    @endif
                    @if ($transaction->contact->alternate_number)
                        <tr>
                            <th>@lang('contact.alternate_contact_number'):</th>
                            <td>{{ $transaction->contact->alternate_number }}</td>
                        </tr>
                    @endif
                    @if ($transaction->contact->landline)
                        <tr>
                            <th>@lang('contact.landline'):</th>
                            <td>{{ $transaction->contact->alternate_number }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
</div>

<div class="width-100 box mb-10">
    <table class="no-border table-pdf">
        <thead>
            <tr>
                <th>@lang('hms::lang.rooms')</th>
            </tr>
        </thead>
        <tr>
            <th>@lang('hms::lang.type')</th>
            <th>@lang('hms::lang.room_no')</th>
            <th>@lang('hms::lang.no_of_adult')</th>
            <th>@lang('hms::lang.no_of_child')</th>
            <th>@lang('hms::lang.price')</th>
        </tr>
            @foreach ($booking_rooms as $room)
                <tr>
                    <td style="padding-top: 8">
                        {{ $room->type }}
                    
                    </td>
                    <td style="padding-top: 8">
                        {{ $room->room_number }}
                    </td>
                    <td style="padding-top: 8">
                        {{ $room->adults }}
                    </td>
                    <td style="padding-top: 8">
                        {{ $room->childrens }}
                    </td>
                    <td style="padding-top: 8">
                        @format_currency($room->total_price)
                    </td>
                </tr>
            @endforeach
    </table>
</div>
<div class="width-100 box mb-10">
    <table class="no-border table-pdf">
        <thead>
            <tr>
                <th>@lang('hms::lang.extras')</th>
            </tr>
        </thead>
        @foreach ( $extras as $index => $extra)
            @if (in_array($extra->id, $extras_id))
                <tr>
                    <td>
                        {{ $extra->name }} / @format_currency($extra->price) - {{ str_replace("_", " ", $extra->price_per) }} 
                    </td>
                </tr>
            @endif
        @endforeach
    </table>
</div>

            @php
                $discount_percent_disable  = 0;
                if(!empty($transaction->hms_coupon_id)){
                    $discount_percent_disable = 1;
                }
            @endphp

<div class="width-100 box mb-10">
    <table class="no-border table-pdf">
        <tr>
            <th>@lang('hms::lang.room_price'):</th>
            <th>@lang('hms::lang.extra_price'):</th>
            @if($discount_percent_disable == 0 && $transaction->discount_amount > 0)
                <th>@lang('hms::lang.discount'): ({{  number_format($transaction->discount_amount, 2) }} % )</th> 
            @else
                <th>@lang('hms::lang.discount'):</th>
            @endif
            <th>@lang('hms::lang.total'):</th>
            <th>@lang('hms::lang.total_paid'):</th>
            <th>@lang('hms::lang.due'):</th>
        </tr>
        <tr>
            <td style="padding-top: 8">
                @format_currency($transaction->room_price) 
            </td>
            <td style="padding-top: 8">
                 @format_currency($transaction->extra_price) 
            </td>
            @if($discount_percent_disable == 0 && $transaction->discount_amount > 0)
                <td style="padding-top: 8">
                    @format_currency($transaction->discount_amount * ($transaction->room_price + $transaction->extra_price) / 100) 
                </td>
            @else
                <td style="padding-top: 8">
                    @format_currency($transaction->discount_amount) 
                </td>
            @endif
           
            <td style="padding-top: 8">
                @format_currency($transaction->final_total) 
            </td>
            <td style="padding-top: 8">
                @format_currency($transaction->total_paid) 
            </td>
            <td style="padding-top: 8">
                @format_currency($transaction->final_total - $transaction->total_paid) 
            </td>
        </tr>
    </table>
</div>
<footer class="container-fluid text-center" style="position: absolute; bottom: 80; width: 90%;">
    <div class="row">
        <div class="col-xs-12">
            <div class="your-div">
                @php
                    $settings = json_decode($business->hms_settings);
                @endphp
                @if (!empty($settings->booking_pdf->footer_text))
                    <div>
                        {!! $settings->booking_pdf->footer_text !!}
                    </div>
                @endif
            </div>
        </div>
    </div>
</footer>