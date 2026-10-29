<thead>
    <tr>
        <th>{{ trans('account.account') }}</th>
        @foreach ($months as $month)
            <th>{{ $month }}</td>
        @endforeach
        <th>{{ trans('sale.total') }}</th>
    </tr>
</thead>

<tbody>
    @php
        $treegrid_index = 1;
    @endphp

    @foreach ($account_types as $type)
        @php
            $parent_index = $treegrid_index;
        @endphp
        <tr class="treegrid-{{ $treegrid_index }}">
            <td>{{ ucfirst($type) }}</td>
            @for ($i = 0; $i < 13; $i++)
                <td></td>
            @endfor
        </tr>
        @php
            $treegrid_index++;
            $chart_of_accounts_for_type = $chart_of_accounts_by_type->get($type);
        @endphp

        @foreach ($chart_of_accounts_for_type as $chart_of_account)
            <tr class="treegrid-{{ $treegrid_index }} treegrid-parent-{{ $parent_index }}">
                <td style="min-width: 200px">{{ $chart_of_account->name }}</td>
                @php
                    $budget = $chart_of_account->budget;
                    $yearly_total = $chart_of_account->budget->yearly;
                @endphp
                @for ($month_number = 1; $month_number <= 12; $month_number++)
                    @php
                        $month = 'month_' . $month_number;
                    @endphp
                    <td class="{{ $month }}">{{ number_format($budget[$month], 2) }}</td>
                @endfor
                <td class="font-weight-bold">{{ number_format($yearly_total, 2) }}</td>
            </tr>
            @php
                $treegrid_index++;
            @endphp
        @endforeach

        <tr class="font-weight-bold bg-subtotal">
            <td>
                {{ trans('accounting::lang.total_for') }} {{ $type }}
            </td>

            @for ($i = 1; $i <= 12; $i++)
                <td>{{ number_format($chart_of_accounts_for_type->pluck("budget.month_$i")->sum(), 2) }}</td>
            @endfor
            <td>
                {{ number_format($chart_of_accounts_for_type->yearly_total, 2) }}
            </td>
        </tr>
    @endforeach

    <tr class="font-weight-bold bg-grand-total">
        <td>
            {{ trans('accounting::lang.grand_total') }}
        </td>

        @for ($i = 1; $i <= 12; $i++)
            <td>{{ number_format($chart_of_accounts->pluck("budget.month_$i")->sum(), 2) }}</td>
        @endfor
        <td>
            {{ number_format($chart_of_accounts->yearly_total, 2) }}
        </td>
    </tr>
</tbody>
