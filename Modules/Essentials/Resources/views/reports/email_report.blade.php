
<div style="width: 100%;">
    <div style="border: 5px solid #1406f2; border-radius: 5px; overflow: hidden; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); margin-bottom: 20px;">
        <h3 style="color: #1406f2; margin-bottom: 0; background-color: #1406f2; padding: 10px; color: #fff; margin-top:0px;">
            <i class="fa fa-exclamation-triangle" style="color: #fff;" aria-hidden="true"></i>
            {{ __('essentials::lang.attendace_report_for') }}  {{ $transactionUtil->format_date($date, false, $business) }}
        </h3>
        <div style="overflow-x:auto;">
        <table align="center" style="width: 80%; border: 1px solid #ddd; border-collapse: collapse;">
                <thead style="background-color: #f9fafb;">
                <tr>
                    <th style="border: 1px solid #ddd; padding: 8px;">@lang('essentials::lang.employee')</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">@lang('essentials::lang.is_present')</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">@lang('essentials::lang.clock_in')</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">@lang('essentials::lang.clock_out')</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">@lang('essentials::lang.work_duration')</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">@lang('essentials::lang.shift')</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    <tr style="border: 1px solid #ddd;">
                        <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">{{ $user->user }}</td>
                        <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">@if ($user->status == 'Present')
                                <span style="color: green;">&#10004;</span> <!-- Tick icon -->
                            @elseif ($user->status == 'Absent')
                                <span style="color: red;">&#10008;</span> <!-- Cancel icon -->
                            @endif</td>
                        <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">{{ $transactionUtil->format_date($user->clock_in_time, true, $business) }}</td>
                        <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">{{ $transactionUtil->format_date($user->clock_out_time, true, $business) }}</td>
                        <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">@if(!empty($user->clock_in_time) && !empty($user->clock_out_time)) {{ \Carbon::parse($user->clock_in_time)->diffForHumans(\Carbon::parse($user->clock_out_time), true, true, 2)  }} @endif</td>
                        <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">{{ $user->shift_name }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>