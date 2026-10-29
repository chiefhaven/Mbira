
<div style="width: 100%;">
    <div style="border: 5px solid #1406f2; border-radius: 5px; overflow: hidden; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); margin-bottom: 20px;">
        <h3 style="color: #1406f2; margin-bottom: 0; background-color: #1406f2; padding: 10px; color: #fff; margin-top:0px;">
            <i class="fa fa-exclamation-triangle" style="color: #fff;" aria-hidden="true"></i>
            {{ __('essentials::lang.attendace_report_for') }}  {{ $transactionUtil->format_date($startDate, false, $business) }} @lang('lang_v1.to') {{ $transactionUtil->format_date($endDate, false, $business) }}
        </h3>
        <div style="overflow-x:auto;">
            <table align="center" style="width: 80%; border: 1px solid #ddd; border-collapse: collapse;">
                <thead style="background-color: #f9fafb;">
                    <tr>
                        <th style="border: 1px solid #ddd; padding: 8px;">@lang('essentials::lang.employee')</th>
                        <th style="border: 1px solid #ddd; padding: 8px;">@lang('essentials::lang.days_present')</th>
                        <th style="border: 1px solid #ddd; padding: 8px;">@lang('essentials::lang.days_absent')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr style="border: 1px solid #ddd;">
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">{{ $user->user }}</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">{{ $user->present_count }}</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">{{ $user->absent_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>