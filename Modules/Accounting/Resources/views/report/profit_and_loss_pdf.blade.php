<style>
    .table {
        width: 100%;
        border: 1px solid #ccc;
        border-collapse: collapse;
    }

    .table th,
    td {
        padding: 5px;
        text-align: left;
        border: 1px solid #ccc;
    }

    .light-heading th {
        background-color: #eeeeee
    }

    .green-heading th {
        background-color: #4CAF50;
        color: white;
    }

    .text-center {
        text-align: center;
    }

    .table-striped tr:nth-child(even) {
        background-color: #f2f2f2;
    }

    .text-danger {
        color: #a94442;
    }

    .text-success {
        color: #3c763d;
    }

</style>
<h3 class="text-center">{{ get_business_name() }}</h3>
<h3 class="text-center">{{ trans_choice('accounting::report.profit_and_loss', 1) }}</h3>

<table class="table table-striped">
    <thead>
        <tr>
            <th>
                @if (!empty($location_id) && !empty($data->first()->business_location))
                    {{ trans_choice('accounting::lang.business_location', 1) }}:
                    {{ $data->first()->business_location }}
                @endif
            </th>
            <th></th>
            <th></th>
            <th>{{ trans_choice('accounting::lang.start_date', 1) }}: {{ readable_date($start_date) }}</th>
            <th>{{ trans_choice('accounting::lang.end_date', 1) }}: {{ readable_date($end_date) }}</th>
        </tr>
        <tr>
            <th>{{ trans_choice('accounting::lang.account', 1) }}</th>
            <th>{{ trans_choice('accounting::general.gl_code', 1) }}</th>
            <th style="text-align:right">{{ trans_choice('accounting::general.debit', 1) }}</th>
            <th style="text-align:right">{{ trans_choice('accounting::general.credit', 1) }}</th>
            <th style="text-align:right">{{ trans_choice('accounting::general.balance', 1) }}</th>
        </tr>
    </thead>

    <tbody>
        @php
            $parent2_index = 1;
        @endphp

        @foreach ($account_types as $account_type)
            @php
                $parent1_index = $parent2_index;
                $class_name = $account_type == 'income' ? 'text-success' : 'text-danger';
            @endphp
            <tr class="treegrid-{{ $parent2_index }}">
                <td>
                    {{ ucfirst($account_type) }}</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            @php
                $parent2_index++;
                $chart_of_account_type = $data->where('account_type', $account_type);
            @endphp

            @foreach ($chart_of_account_type as $chart_of_account)
                <tr class="treegrid-{{ $parent2_index }} treegrid-parent-{{ $parent1_index }}">
                    <td style="min-width: 200px">{{ $chart_of_account->name }}</td>
                    <td>{{ $chart_of_account->gl_code }}</td>
                    <td style="text-align:right">{{ number_format($chart_of_account->debit, 2) }}</td>
                    <td style="text-align:right">{{ number_format($chart_of_account->credit, 2) }}</td>
                    <td style="text-align:right">
                        {{ number_format($chart_of_account->credit - $chart_of_account->debit, 2) }}
                    </td>
                </tr>
                @php
                    $parent2_index++;
                @endphp
            @endforeach

            <tr class="font-weight-bold bg-subtotal {{ $class_name }}">
                <td>
                    {{ trans('accounting::lang.total_for') }} {{ $account_type }}
                </td>
                <td></td>
                <td style="text-align:right">{{ number_format($chart_of_account_type->sum('debit'), 2) }}</td>
                <td style="text-align:right">{{ number_format($chart_of_account_type->sum('credit'), 2) }}</td>
                <td style="text-align:right">
                    {{ number_format($chart_of_account_type->sum('credit') - $chart_of_account_type->sum('debit'), 2) }}
                </td>
            </tr>
        @endforeach

        <tr class="font-weight-bold bg-grand-total">
            <td>
                {{ trans_choice('accounting::general.net_income', 1) }}
            </td>
            <td></td>
            <td style="text-align:right">{{ number_format($data->sum('debit'), 2) }}</td>
            <td style="text-align:right">{{ number_format($data->sum('credit'), 2) }}</td>
            <td style="text-align:right">
                {{ number_format($data->sum('credit') - $data->sum('debit'), 2) }}
            </td>
        </tr>
    </tbody>
</table>
