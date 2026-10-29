<div class="col-md-4 col-sm-6 col-xs-12 col-custom">
    @component('components.widget', [
        'class' => '',
        'title' => __('essentials::lang.birthdays'),
        'icon' => '<i class="fas fa-birthday-cake"></i>',
    ])
        <div class="">
            <table class="table no-margin">
                <tbody>
                    <tr>
                        <th class="bg-light-gray" colspan="3">@lang('home.today')</th>
                    </tr>
                    @forelse($today_births as $birthday)
                        <tr>
                            <td>{{ $birthday->surname }} {{ $birthday->first_name }} {{ $birthday->last_name }}</td>
                            <td>{{ @format_date(\Carbon::parse($birthday->dob)->setYear(date('Y'))) }} </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center">@lang('lang_v1.no_data')</td>
                        </tr>
                    @endforelse
                    <tr>
                        <td colspan="3">&nbsp;</td>
                    </tr>
                    <tr>
                        <th class="bg-light-gray" colspan="3">@lang('lang_v1.upcoming')</th>
                    </tr>
                    @forelse($up_comming_births as $birthday)
                        <tr>
                            <td>{{ $birthday->surname }} {{ $birthday->first_name }} {{ $birthday->last_name }}</td>
                            @if (date('m') == '12' && Carbon::parse($birthday->dob)->format('m') == '1')
                                <td>{{ @format_date(\Carbon::parse($birthday->dob)->setYear(date('Y') + 1)) }} </td>
                            @else
                                <td>{{ @format_date(\Carbon::parse($birthday->dob)->setYear(date('Y'))) }} </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center">@lang('lang_v1.no_data')</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endcomponent
</div>
