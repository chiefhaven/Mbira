<!-- app css -->
@if(!empty($for_pdf))
<link rel="stylesheet" href="{{ asset('css/app.css?v='.$asset_v) }}">
@endif

<!-- Header Section -->
<div class="col-md-12 col-sm-12" style="margin-top: 20px; @if(!empty($for_pdf)) width:100%; @endif">
    <div class="col-md-4 col-sm-4 text-left" style="@if(!empty($for_pdf)) width:33.33%; float:left; @endif">
        <p><strong>{{$contact->business->name}}</strong><br>
            @if(!empty($location))
            {!! $location->location_address !!}
            @else
            {!! $contact->business->business_address !!}
            @endif
        </p>
    </div>
    <div class="col-md-4 col-sm-4 text-center" style="@if(!empty($for_pdf)) width:33.33%; float:left; text-align:center; @endif">
        <h2 class="text-uppercase" style="margin: 0;"><strong>@lang('lang_v1.partner_ledger')</strong></h2>
    </div>
    <div class="col-md-4 col-sm-4 text-right" style="@if(!empty($for_pdf)) width:33.33%; float:left; text-align:right; @endif">
        <p>{{$contact->contact_id ?? 'N/A'}}<br>
        <p>{{$contact->name}}
            <br>@lang('contact.mobile'): {{$contact->mobile}}
        </p>
    </div>
</div>

<div class="col-md-6 @if(!empty($for_pdf)) width-50 @endif">
</div>

<div class="col-md-6 @if(!empty($for_pdf)) width-50 @endif" style="@if(!empty($for_pdf)) float: right; @endif">
    <h3 class="mb-0 blue-heading p-4">@lang('lang_v1.account_summary')</h3>
    <!-- <small>{{$ledger_details['start_date']}} @lang('lang_v1.to') {{$ledger_details['end_date']}}</small>
	<hr> -->
    <table class="table table-condensed text-left align-left no-border @if(!empty($for_pdf)) table-pdf @endif">
        <tr>
            <td>@lang('lang_v1.opening_balance')</td>
            <td class="align-right">@format_currency($ledger_details['beginning_balance'])</td>
        </tr>
        @if( $contact->type == 'supplier' || $contact->type == 'both')
        <tr>
            <td>@lang('report.total_purchase')</td>
            <td class="align-right">@format_currency($ledger_details['total_purchase'])</td>
        </tr>
        @endif
        @if( $contact->type == 'customer' || $contact->type == 'both')
        <tr>
            <td>@lang('lang_v1.total_invoice')</td>
            <td class="align-right">@format_currency($ledger_details['total_invoice'])</td>
        </tr>
        @endif
        <tr>
            <td>@lang('sale.total_paid')</td>
            <td class="align-right">@format_currency($ledger_details['total_paid'])</td>
        </tr>
        <tr>
            <td>@lang('lang_v1.advance_balance')</td>
            <td class="align-right">@format_currency($contact->balance - $ledger_details['total_reverse_payment'])</td>
        </tr>
        @if($ledger_details['ledger_discount'] > 0)
        <tr>
            <td>@lang('lang_v1.ledger_discount')</td>
            <td class="align-right">@format_currency($ledger_details['ledger_discount'])</td>
        </tr>
        @endif
        <tr>
            <td><strong>@lang('lang_v1.balance_due')</strong></td>
            <td class="align-right">@format_currency($ledger_details['balance_due'] - $ledger_details['ledger_discount'])</td>
        </tr>
    </table>
</div>
</div>

<div class="col-md-12 col-sm-12 @if(!empty($for_pdf)) width-100 @endif">
    @php
    $amount_due = 0;
    $current_due = 0;
    $due_1_30_days = 0;
    $due_30_60_days = 0;
    $due_60_90_days = 0;
    $due_over_90_days = 0;
    @endphp
    @if(!empty($for_pdf))
    <br>
    @endif
    <p class="text-center" style="text-align: center;"><strong>@lang('lang_v1.ledger_table_heading', ['start_date' => $ledger_details['start_date'], 'end_date' => $ledger_details['end_date']])</strong></p>
    <div class="table-responsive">
        <table class="table @if(!empty($for_pdf)) table-pdf td-border @endif" id="ledger_table">
            <thead>
                <tr class="row-border blue-heading">
                    <th width="18%" class="text-center">@lang('lang_v1.date')</th>
                    <th width="8%" class="text-center">@lang('lang_v1.type')</th>
                    <th width="15%" class="text-center">@lang('purchase.ref_no')</th>
                    <th width="15%" class="text-center">@lang('lang_v1.payment_method')</th>
                    <th width="10%" class="text-center">@lang('account.debit')</th>
                    <th width="10%" class="text-center">@lang('account.credit')</th>
                    <th width="10%" class="text-center">@lang('lang_v1.balance')</th>
                    <th width="15%" class="text-center">@lang('report.others')</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ledger_details['ledger'] as $data)

                <tr>
                    <td class="row-border">{{@format_datetime($data['date'])}}</td>
                    <td>{{$data['type']}}</td>
                    <td>{{$data['ref_no']}}</td>
                    <td>{{$data['payment_method']}}</td>
                    <!-- <td>{{$data['location']}}</td> -->
                    {{--<td class="ws-nowrap align-right">@if($data['total'] !== '') @format_currency($data['total']) @endif</td>--}}
                    <td class="ws-nowrap align-right">@if($data['debit'] != '') @format_currency($data['debit']) @endif</td>
                    <td class="ws-nowrap align-right">@if($data['credit'] != '') @format_currency($data['credit']) @endif</td>
                    <td class="ws-nowrap align-right">{{$data['balance']}}</td>

                    <td>
                        {!! $data['others'] !!}
                    </td>
                </tr>

                @php
                if(empty($data['total_due'])) {
                continue;
                }
                if($data['payment_status'] != 'paid' && !empty($data['due_date'])) {
                if (!empty($data['transaction_type']) && $data['transaction_type'] == 'ledger_discount') {
                $data['total_due'] = -1 * $data['total_due'];
                }
                $amount_due += $data['total_due'];

                $days_diff = $data['due_date']->diffInDays();
                if($days_diff == 0){
                $current_due += $data['total_due'];
                } elseif ($days_diff > 0 && $days_diff <= 30) {
                    $due_1_30_days +=$data['total_due'];
                    } elseif ($days_diff> 30 && $days_diff <= 60) {
                        $due_30_60_days +=$data['total_due'];
                        } elseif ($days_diff> 60 && $days_diff <= 90) {
                            $due_60_90_days +=$data['total_due'];
                            } elseif ($days_diff> 90) {
                            $due_over_90_days += $data['total_due'];
                            }
                            }
                            @endphp
                            @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Aging Report Section -->
<div class="col-md-12 col-sm-12 @if(!empty($for_pdf)) width-100 @endif">
    <h4 style="margin-top: 20px; margin-bottom: 10px;"><strong>@lang('lang_v1.aging_report')</strong></h4>
    <table class="table" style="margin-top: 0; width: 100%;">
        <tr>
            <th style="font-size: 12px;">@lang('lang_v1.current')</th>
            <th style="color: #2dce89 !important;font-size: 12px;">{{strtoupper(__('lang_v1.1_30_days_past_due'))}}</th>
            <th style="color: #ffd026 !important;font-size: 12px;">{{strtoupper(__('lang_v1.30_60_days_past_due'))}}</th>
            <th style="color: #ffa100 !important;font-size: 12px;">{{strtoupper(__('lang_v1.60_90_days_past_due'))}}</th>
            <th style="color: #f5365c !important;font-size: 12px;">{{strtoupper(__('lang_v1.over_90_days_past_due'))}}</th>
            <th style="font-size: 12px;">{{strtoupper(__('lang_v1.amount_due'))}}</th>
        </tr>
        <tr>
            <td style="text-align: center;">
                @format_currency($current_due)
            </td>
            <td style="color: #2dce89 !important; text-align: center;">
                @format_currency($due_1_30_days)
            </td>
            <td style="color: #ffd026 !important; text-align: center;">
                @format_currency($due_30_60_days)
            </td>
            <td style="color: #ffa100 !important;text-align: center;">
                @format_currency($due_60_90_days)
            </td>
            <td style="color: #f5365c !important; text-align: center;">
                @format_currency($due_over_90_days)
            </td>
            <td style="text-align: center;">
                @format_currency($amount_due)
            </td>
        </tr>
    </table>
</div>
