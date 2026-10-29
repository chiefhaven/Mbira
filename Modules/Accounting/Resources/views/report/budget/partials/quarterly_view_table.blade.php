<thead>
    <tr>
        <th>{{ trans('account.account') }}</th>
        @for ($i = 1; $i <= 4; $i++)
            <th>Q{{ $i }} {{ Request::get('year') }}</td>
        @endfor
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
            @for ($i = 0; $i < 5; $i++)
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
                    $yearly_total = $chart_of_account->budget->yearly;
                @endphp
                @foreach ($chart_of_account->budget->quarterly as $index => $quartely_budget)
                    @php
                        $quarter_number = $index + 1;
                        $quarter = 'quarter_' . $quarter_number;
                    @endphp
                    <td class="{{ $quarter }}">{{ number_format($quartely_budget, 2) }}</td>
                @endforeach
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
            @for ($i = 1; $i <= 4; $i++)
                <td>{{ number_format($chart_of_accounts_for_type->pluck('budget.quarterly')->pluck($i)->sum(),2) }}</td>
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

        @for ($i = 1; $i <= 4; $i++)
            <td>{{ number_format($chart_of_accounts->pluck('budget.quarterly')->pluck($i)->sum(),2) }}</td>
        @endfor
        <td>
            {{ number_format($chart_of_accounts->yearly_total, 2) }}
        </td>
    </tr>

</tbody>
