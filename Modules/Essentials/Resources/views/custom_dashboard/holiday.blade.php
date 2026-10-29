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
                                    <th>@lang( 'lang_v1.name' )</th>
                                    <th>@lang( 'lang_v1.date' )</th>
                                    <th>@lang( 'business.business_location' )</th>
                                    <th>@lang( 'brand.note' )</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($holidays as $holiday)
                                    <tr>
                                        <td>{{ $holiday->name }}</td>
                                        @php
                                            $start_date = \Carbon::parse($holiday->start_date);
                                            $end_date = \Carbon::parse($holiday->end_date);

                                            $diff = $start_date->diffInDays($end_date);
                                            $diff += 1;
                                            
                                        @endphp
                                        <td>{{ @format_date($start_date) .' - '.@format_date($end_date).' ('.$diff.\Str::plural(__('lang_v1.day'), $diff).')' }}</td>
                                        <td>{{$holiday->location ?? __("lang_v1.all")}}</td>
                                        <td>{{$holiday->note}}</td>
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