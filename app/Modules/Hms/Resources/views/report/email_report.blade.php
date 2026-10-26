
<div style="width: 100%;">
    <div style="border: 5px solid #05bfe7; border-radius: 5px; overflow: hidden; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); margin-bottom: 20px;">
        <h3 style="color: #05bfe7; margin-bottom: 0; background-color: #05bfe7; padding: 10px; color: #fff; margin-top:0px;">
            <i class="fa fa-exclamation-triangle" style="color: #fff;" aria-hidden="true"></i>
            @if(empty($dates['date_from']))
                {{ __('hms::lang.hms_report') }} {{ $transactionUtil->format_date($dates['date_to'], false, $business) }}
            @else
                {{ __('hms::lang.hms_report') }} {{ $transactionUtil->format_date($dates['date_to'], false, $business) }} @lang('lang_v1.to') {{ $transactionUtil->format_date($dates['date_from'], false, $business) }}
            @endif
        </h3>
        <div style="overflow-x:auto;">
            <table align="center" style="width: 80%; border: 1px solid #ddd; border-collapse: collapse;">
                <thead style="background-color: #f9fafb;">
                        <tr class="bg-light-green">
                            <th rowspan="2" style="border: 1px solid #ddd; padding: 8px;">@lang('hms::lang.rooms')</th>
                            <th colspan="4" style="border: 1px solid #ddd; padding: 8px;">@lang('hms::lang.booking_received')</th>
                            <th colspan="4" style="border: 1px solid #ddd; padding: 8px;">@lang('hms::lang.booking_confirmed')</th>
                            <th colspan="4" style="border: 1px solid #ddd; padding: 8px;">@lang('hms::lang.booking_cancelled')</th>
                            <th colspan="4" style="border: 1px solid #ddd; padding: 8px;">@lang('hms::lang.booking_pending')</th>
                        </tr>
                        <tr>
                            <th  style="border: 1px solid #ddd; padding: 8px; background-color: #d1ecf1;">@lang('hms::lang.booked')</th>
                            <th  style="border: 1px solid #ddd; padding: 8px; background-color: #d1ecf1;">@lang('hms::lang.guests')</th>
                            <th  style="border: 1px solid #ddd; padding: 8px; background-color: #d1ecf1;">@lang('hms::lang.nights')</th>
                            <th  style="border: 1px solid #ddd; padding: 8px; background-color: #d1ecf1;">@lang('hms::lang.amount')</th>
                            <th  style="border: 1px solid #ddd; padding: 8px; background-color: #28a745;">@lang('hms::lang.booked')</th>
                            <th  style="border: 1px solid #ddd; padding: 8px; background-color: #28a745;">@lang('hms::lang.guests')</th>
                            <th  style="border: 1px solid #ddd; padding: 8px; background-color: #28a745;">@lang('hms::lang.nights')</th>
                            <th  style="border: 1px solid #ddd; padding: 8px; background-color: #28a745;">@lang('hms::lang.amount')</th>
                            <th  style="border: 1px solid #ddd; padding: 8px; background-color: red;">@lang('hms::lang.booked')</th>
                            <th  style="border: 1px solid #ddd; padding: 8px; background-color: red;">@lang('hms::lang.guests')</th>
                            <th  style="border: 1px solid #ddd; padding: 8px; background-color: red;">@lang('hms::lang.nights')</th>
                            <th  style="border: 1px solid #ddd; padding: 8px; background-color: red;">@lang('hms::lang.amount')</th>
                            <th  style="border: 1px solid #ddd; padding: 8px; background-color: yellow;">@lang('hms::lang.booked')</th>
                            <th  style="border: 1px solid #ddd; padding: 8px; background-color: yellow;">@lang('hms::lang.guests')</th>
                            <th  style="border: 1px solid #ddd; padding: 8px; background-color: yellow;">@lang('hms::lang.nights')</th>
                            <th  style="border: 1px solid #ddd; padding: 8px; background-color: yellow;">@lang('hms::lang.amount')</th>
                        </tr>
                    </thead>
                    <tboady>
                        @foreach ($all_room_types as $index => $all_room_type)
                            <tr>
                                <td style="border: 1px solid #ddd; padding: 8px;">{{ $all_room_type->type }}</td>
                                <td style="border: 1px solid #ddd; padding: 8px;">{{ $all_room_type->transactions_count }}</td>
                                <td style="border: 1px solid #ddd; padding: 8px;">{{ $all_room_type->no_of_guest ?? 0 }}</td>
                                
                                    @if($all_room_type->transactions_count != 0)
                                        <td style="border: 1px solid #ddd; padding: 8px;">
                                            {{ $all_room_type->total_days == 0 ? 1 : $all_room_type->total_days }}
                                        </td>
                                    @else
                                        <td style="border: 1px solid #ddd; padding: 8px;">0</td>
                                    @endif
                                
                                <td style="border: 1px solid #ddd; padding: 8px;"> {{ $transactionUtil->num_f($all_room_type->total_price, true, $business) }}</td>

                                @if(isset($confirmed_room_types[$index]))
                                    <td style="border: 1px solid #ddd; padding: 8px;">{{ $confirmed_room_types[$index]->transactions_count  }}</td>
                                @endif
                                @if(isset($confirmed_room_types[$index]))
                                    <td style="border: 1px solid #ddd; padding: 8px;">{{ $confirmed_room_types[$index]->no_of_guest ?? 0  }}</td>
                                @endif
                                @if(isset($confirmed_room_types[$index]))
                                    @if($confirmed_room_types[$index]->transactions_count != 0)
                                        <td style="border: 1px solid #ddd; padding: 8px;">
                                            {{ $confirmed_room_types[$index]->total_days == 0 ? 1 : $confirmed_room_types[$index]->total_days }}
                                        </td>
                                    @else
                                        <td style="border: 1px solid #ddd; padding: 8px;">0</td>
                                    @endif
                                @endif
                                @if(isset($confirmed_room_types[$index]))
                                    <td style="border: 1px solid #ddd; padding: 8px;">
                                        {{ $transactionUtil->num_f($confirmed_room_types[$index]->total_price, true, $business) }}
                                    </td>
                                @endif

                                @if(isset($cancelled_room_types[$index]))
                                    <td style="border: 1px solid #ddd; padding: 8px;">{{ $cancelled_room_types[$index]->transactions_count ?? 0  }}</td>
                                @endif
                                @if(isset($cancelled_room_types[$index]))
                                    <td style="border: 1px solid #ddd; padding: 8px;">{{ $cancelled_room_types[$index]->no_of_guest ?? 0  }}</td>
                                @endif
                                @if(isset($cancelled_room_types[$index]))
                                    @if($cancelled_room_types[$index]->transactions_count != 0)
                                        <td style="border: 1px solid #ddd; padding: 8px;">
                                            {{ $cancelled_room_types[$index]->total_days == 0 ? 1 : $cancelled_room_types[$index]->total_days }}
                                        </td>
                                    @else
                                        <td style="border: 1px solid #ddd; padding: 8px;">0</td>
                                    @endif

                                @endif
                                @if(isset($cancelled_room_types[$index]))
                                    <td style="border: 1px solid #ddd; padding: 8px;">
                                            {{ $transactionUtil->num_f($cancelled_room_types[$index]->total_price, true, $business) }}
                                    </td>
                                @endif

                                @if(isset($pending_room_types[$index]))
                                    <td style="border: 1px solid #ddd; padding: 8px;">{{ $pending_room_types[$index]->transactions_count ?? 0  }}</td>
                                @endif
                                @if(isset($pending_room_types[$index]))
                                    <td style="border: 1px solid #ddd; padding: 8px;">{{ $pending_room_types[$index]->no_of_guest ?? 0  }}</td>
                                @endif
                                @if(isset($pending_room_types[$index]))

                                @if($pending_room_types[$index]->transactions_count != 0)
                                    <td style="border: 1px solid #ddd; padding: 8px;">
                                        {{ $pending_room_types[$index]->total_days == 0 ? 1 : $pending_room_types[$index]->total_days }}
                                    </td>
                                @else
                                    <td style="border: 1px solid #ddd; padding: 8px;">0</td>
                                @endif

                                @endif
                                @if(isset($pending_room_types[$index]))
                                    <td style="border: 1px solid #ddd; padding: 8px;">   
                                        {{ 
                                            $transactionUtil->num_f($pending_room_types[$index]->total_price, true, $business)
                                        }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>