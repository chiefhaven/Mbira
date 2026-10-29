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
<h3 class="text-center">{{ trans_choice('accounting::general.ledger', 1) }}</h3>
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
            <th>{{ trans_choice('accounting::lang.start_date', 1) }}: {{ readable_date($start_date) }}</th>
            <th>{{ trans_choice('accounting::lang.end_date', 1) }}: {{ readable_date($end_date) }}</th>
        </tr>
        <tr>
            <th>{{ trans_choice('accounting::lang.account', 1) }}</th>
            <th>{{ trans_choice('accounting::general.gl_code', 1) }}</th>
            <th style="text-align:right">{{ trans_choice('accounting::general.debit', 1) }}</th>
            <th style="text-align:right">{{ trans_choice('accounting::general.credit', 1) }}</th>
        </tr>
    </thead>

    <tbody>
        @php
            $row_counter = 1;
        @endphp

        @foreach ($account_types as $account_type)
            @php
                $parent1_index = $row_counter;
            @endphp
            <tr class="treegrid-{{ $row_counter }}">
                <td>{{ ucfirst($account_type) }}</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            @php
                $row_counter++;
                $filtered_account_subtypes = $account_subtypes->where('account_type', $account_type);
                $chart_of_account_type = $data->where('account_type', $account_type);
            @endphp

            @foreach ($filtered_account_subtypes as $account_subtype)
                @php
                    $parent2_index = $row_counter;
                @endphp
                <tr class="treegrid-{{ $row_counter }} treegrid-parent-{{ $parent1_index }}">
                    <td>
                        {{ ucfirst($account_subtype->name) }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                @php
                    $row_counter++;
                    $chart_of_account_subtype = $chart_of_account_type->where('account_subtype_id', $account_subtype->id);
                @endphp

                @foreach ($chart_of_account_subtype as $chart_of_account)
                    <tr class="treegrid-{{ $row_counter }} treegrid-parent-{{ $parent2_index }}">
                        <td style="min-width: 200px">{{ $chart_of_account->name }}</td>
                        <td>{{ $chart_of_account->gl_code }}</td>
                        <td style="text-align:right">{{ number_format($chart_of_account->debit, 2) }}</td>
                        <td style="text-align:right">{{ number_format($chart_of_account->credit, 2) }}</td>
                    </tr>
                    @php
                        $row_counter++;
                    @endphp
                @endforeach
            @endforeach

            <tr class="font-weight-bold bg-subtotal">
                <td>
                    {{ trans('accounting::lang.total_for') }} {{ $account_type }}
                </td>
                <td></td>
                <td style="text-align:right">{{ number_format($chart_of_account_type->sum('debit'), 2) }}</td>
                <td style="text-align:right">{{ number_format($chart_of_account_type->sum('credit'), 2) }}</td>
            </tr>
        @endforeach

        <tr class="font-weight-bold bg-grand-total">
            <td>
                {{ trans_choice('accounting::general.net_income', 1) }}
            </td>
            <td></td>
            <td style="text-align:right">{{ number_format($data->sum('debit'), 2) }}</td>
            <td style="text-align:right">{{ number_format($data->sum('credit'), 2) }}</td>
        </tr>
    </tbody>
</table>
