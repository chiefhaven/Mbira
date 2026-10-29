<div class="col-md-{{ round(($dashboard_detail->size / 100) * 12) }}">
    <div
        class="tw-mb-4 tw-transition-all lg:tw-col-span-2 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md  tw-ring-gray-200">
        <div class="tw-p-2 sm:tw-p-3">
            <div class="box-header text-center">
                <h3 class="box-title pull-left">{{ $dashboard_detail->heading }}</h3>
                <h6 class="text-uppercase"
                    style="font-family: Arial, sans-serif; color: #333; letter-spacing: 2px; margin-top: 5px;">
                    {{ @format_date($date) }}
                </h6>
            </div>
            <div class="tw-flow-root tw-mt-5  tw-border-gray-200">
                <div class="tw-overflow-x-auto">
                    <div class="tw-inline-block tw-min-w-full tw-py-2 tw-align-middle sm:tw-px-5">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>@lang('essentials::lang.employee')</th>
                                    <th>@lang('essentials::lang.is_present')</th>
                                    <th>@lang('essentials::lang.clock_in')</th>
                                    <th>@lang('essentials::lang.clock_out')</th>
                                    <th>@lang('essentials::lang.work_duration')
                                    </th>
                                    <th>@lang('essentials::lang.shift')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                    <tr>
                                        <td>
                                            {{ $user->user }}</td>
                                        <td>
                                            @if ($user->status == 'Present')
                                                <span style="color: green;">&#10004;</span> <!-- Tick icon -->
                                            @elseif($user->status == 'Absent')
                                                <span style="color: red;">&#10008;</span> <!-- Cancel icon -->
                                            @endif
                                        </td>
                                        <td>
                                            {{ $transactionUtil->format_date($user->clock_in_time, true, $business) }}
                                        </td>
                                        <td>
                                            {{ $transactionUtil->format_date($user->clock_out_time, true, $business) }}
                                        </td>
                                        <td>
                                            @if (!empty($user->clock_in_time) && !empty($user->clock_out_time))
                                                {{ \Carbon::parse($user->clock_in_time)->diffForHumans(\Carbon::parse($user->clock_out_time), true, true, 2) }}
                                            @endif
                                        </td>
                                        <td>{{ $user->shift_name }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
