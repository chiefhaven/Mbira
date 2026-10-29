<div class="col-md-{{ round(($dashboard_detail->size / 100) * 12)  }}">
    <div
        class="tw-mb-4 tw-transition-all lg:tw-col-span-2 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md  tw-ring-gray-200">
        <div class="tw-p-2 sm:tw-p-3">
            <div class="box-header text-center">
                <h3 class="tw-font-bold tw-text-base lg:tw-text-xl pull-left">{{ $dashboard_detail->heading  }}</h3>
                <h6 class="text-uppercase"
                    style="font-family: Arial, sans-serif; color: #333; letter-spacing: 2px; margin-top: 5px;">
                    {{ @format_date($startDate) }} - {{ @format_date($endDate) }}
                </h6>
            </div>
            <div class="tw-flow-root tw-border-gray-200">
                <div class="tw-overflow-x-auto">
                    <div class="tw-inline-block tw-min-w-full tw-py-2 tw-align-middle sm:tw-px-5">
                        <table class="table table-bordered table-striped">
                            <thead style="background-color: #f9fafb;">
                                <tr>
                                    <th>@lang( 'essentials::lang.event_name' )</th>
                                    <th>@lang( 'lang_v1.date' )</th>
                                    <th>@lang( 'restaurant.start_time' )</th>
                                    <th>@lang( 'restaurant.end_time' )</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reminders as $remainder)
                                    <tr>
                                        <td>{{ $remainder->name }}</td>
                                        <td>{{ @format_date($remainder->date) }}</td>
                                        <td>{{ @format_time($remainder->time) }}</td>
                                        <td>{{ @format_time($remainder->end_time) }}</td>
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