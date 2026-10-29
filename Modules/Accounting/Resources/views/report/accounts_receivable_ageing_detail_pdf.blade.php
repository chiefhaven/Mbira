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
<h3 class="text-center">{{ trans_choice('accounting::report.accounts_receivable_ageing_detail', 1) }}</h3>
<table class="table table-striped" style="font-size: 12px">
    <thead>
        <tr>
            <th>{{ trans_choice('accounting::lang.account', 1) }}</th>
            <th>
                {{ trans_choice('accounting::lang.account', 1) }}
                {{ trans_choice('accounting::general.detail_type', 1) }}
            </th>
            <th>{{ trans_choice('accounting::lang.date', 1) }}</th>
            <th>{{ trans_choice('accounting::lang.transaction', 1) }} {{ trans_choice('accounting::lang.type', 1) }}</th>
            <th>{{ trans_choice('accounting::general.invoice', 1) }} {{ trans_choice('accounting::lang.no', 1) }}</th>
            <th style="text-align:right">{{ trans_choice('accounting::lang.amount', 1) }}</th>
        </tr>
    </thead>

    <tbody>
        @php
            $row_counter = 1;
        @endphp

        @foreach ($days_passed_options as $days_passed => $days_passed_label)
            @php
                $parent1_index = $row_counter;
            @endphp
            <tr class="treegrid-{{ $row_counter }}">
                <td>{{ ucfirst($days_passed_label) }}</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            @php
                $row_counter++;
                $chart_of_account_subtype = $data->where('days_passed', $days_passed);
                $filtered_account_subtypes = $chart_of_account_subtype->pluck('account_subtype', 'account_subtype_id');
            @endphp

            @foreach ($filtered_account_subtypes as $account_subtype_id => $account_subtype_name)
                @php
                    $parent2_index = $row_counter;
                @endphp
                <tr class="treegrid-{{ $row_counter }} treegrid-parent-{{ $parent1_index }}">
                    <td>
                        {{ ucfirst($account_subtype_name) }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                @php
                    $row_counter++;
                    $chart_of_account_subtype = $chart_of_account_subtype->where('account_subtype_id', $account_subtype_id);
                @endphp

                @foreach ($chart_of_account_subtype as $chart_of_account)
                    <tr class="treegrid-{{ $row_counter }} treegrid-parent-{{ $parent2_index }}">
                        <td style="min-width: 200px">{{ $chart_of_account->chart_of_account }}</td>
                        <td>{{ $chart_of_account->account_detail_type }}</td>
                        <td>{{ readable_date($chart_of_account->transaction_date) }}</td>
                        <td>{{ $chart_of_account->transaction_type }}</td>
                        <td>{{ $chart_of_account->invoice_no }}</td>
                        <td style="text-align: right">{{ number_format($chart_of_account->amount_due, 2) }}</td>
                    </tr>
                    @php
                        $row_counter++;
                    @endphp
                @endforeach
            @endforeach

            <tr class="font-weight-bold bg-subtotal">
                <td>
                    {{ trans('accounting::lang.total_for') }} {{ $days_passed_label }}
                </td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td style="text-align:right">{{ number_format($chart_of_account_subtype->sum('amount_due'), 2) }}</td>
            </tr>
        @endforeach

        <tr class="font-weight-bold bg-grand-total">
            <td>
                {{ trans_choice('accounting::lang.total', 1) }}
            </td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td style="text-align:right">{{ number_format($data->sum('amount_due'), 2) }}</td>
        </tr>
    </tbody>
</table>
