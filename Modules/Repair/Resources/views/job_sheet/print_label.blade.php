<link rel="stylesheet" href="{{ asset('css/app.css?v='.$asset_v) }}">
<style type="text/css">
    .box {
        /* border: 1px solid; */
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
@php
$custom_labels = json_decode(session('business.custom_labels'), true);
$contact_custom_fields = !empty($jobsheet_settings['contact_custom_fields']) ?
$jobsheet_settings['contact_custom_fields'] : [];
@endphp
<div class="width-100 box mb-10">

    <div class="width-100" align="center">
        <p style="text-align: center;">
            <strong class="font-14">
                {{$job_sheet->customer->business->name}}<br>
            </strong>

            @if(!empty($jobsheet_settings['show_location_in_label']) && 
            ($job_sheet->customer->business->name != $job_sheet->businessLocation->name))
            <span class="font-12">
                {!!$job_sheet->businessLocation->name!!} <br>
            </span>
            @endif

            @if(!empty($jobsheet_settings['show_barcode_in_label']))
            <span align="center">
                <img src="data:image/png;base64,{{DNS1D::getBarcodePNG($job_sheet->job_sheet_no, 'C128', 1,30,array(39, 48, 10), true)}}"><br>
            </span>
            @endif

            <span class="font-12">
                <b>@lang('repair::lang.job_sheet_no'):</b> {{$job_sheet->job_sheet_no}}<br>
            </span>
            @if(!empty($jobsheet_settings['show_brand_in_label']))
                <span class="font-12">
                    {{$job_sheet->brand?->name}} {{$job_sheet->deviceModel?->name}}<br>
                </span>
            @endif

            @if(!empty($jobsheet_settings['show_password_in_label']) && !empty($job_sheet->security_pwd))
            <span class="font-12">
                <b>@lang('lang_v1.password'):</b> {{$job_sheet->security_pwd}}<br>
            </span>
            @endif

            @if(!empty($job_sheet->security_pattern))
            <span class="font-12">
                <b>@lang('repair::lang.security_pattern_code'):</b> {{$job_sheet->security_pattern}}<br>
            </span>
            @endif
            @if(!empty($jobsheet_settings['show_problem_in_label']))
                <span class="font-12">
                    <b>@lang('repair::lang.problem'):</b>
                    @php
                    $defects = json_decode($job_sheet->defects, true);
                    @endphp
                    @if(!empty($defects))
                    @foreach($defects as $product_defect)
                    {{$product_defect['value']}}
                    @if(!$loop->last)
                    {{','}}
                    @endif
                    @endforeach
                    @endif
                    <br>
                </span>
            @endif
            @if(!empty($jobsheet_settings['show_sales_person_in_label']))
                <span class="font-12">
                    <b>@lang('repair::lang.sales_person'):</b> {{$job_sheet->createdBy->user_full_name}}<br>
                </span>
            @endif
            @if(!empty($jobsheet_settings['show_status_in_label']))
                <span class="font-12">
                    <b>@lang('sale.status'):</b> {{$job_sheet->status->name ?? ''}}<br>
                </span>
            @endif
            @if(!empty($jobsheet_settings['show_due_date_in_label']))
                <span class="font-12">
                    <b>@lang('lang_v1.due_date'):</b> @if(!empty($job_sheet->delivery_date))
                    {{@format_datetime($job_sheet->delivery_date)}}<br>@endif
                </span>
            @endif
            @if(!empty($jobsheet_settings['show_technician_in_label']))
                <span class="font-12">
                    <b>@lang('repair::lang.technician'):</b> {{$job_sheet->technician->user_full_name ?? ''}}<br>
                </span>
            @endif
            @if(!empty($jobsheet_settings['show_sr_no_in_label']))
                <span class="font-12">
                    <b>@lang('repair::lang.imei_sr_no'):</b> {{$job_sheet->serial_no ?? ''}}<br>
                </span>
            @endif

            @if(!empty($jobsheet_settings['show_customer_name_in_label']))
                <span class="font-12">
                    <b>@lang('contact.customer'):</b>
                    {{$job_sheet->customer->name}}<br>
                </span>
            @endif

            @if(!empty($jobsheet_settings['show_customer_address_in_label']))
                <span class="font-12">
                    {!!implode(', ', $job_sheet->customer->contact_address_array) !!}<br>
                </span>
            @endif
            @if(!empty($jobsheet_settings['show_customer_phone_in_label']))
                <span class="font-12">
                    {{$job_sheet->customer->mobile}},
                </span>
            @endif
            @if(!empty($jobsheet_settings['show_customer_alt_phone_in_label']))
                <span class="font-12">
                {{$job_sheet->customer->alternate_number}}
                </span>
            @endif
            @if(!empty($jobsheet_settings['show_customer_email_in_label']))
                <span class="font-12">
                    {{$job_sheet->customer->email}}
                </span>
            @endif
        </p>
    </div>
</div>