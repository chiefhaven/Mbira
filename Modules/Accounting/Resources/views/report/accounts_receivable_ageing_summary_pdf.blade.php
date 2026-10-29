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
<h3 class="text-center">{{ trans_choice('accounting::report.accounts_receivable_ageing_summary', 1) }}</h3>
<table class="table table-striped" style="font-size: 12px">
    <thead>
        <tr>
            <th>{{ trans_choice('accounting::lang.account', 1) }}</th>
            <th style="text-align: right">{{ trans('accounting::lang.current') }}</th>
            <th style="text-align: right">{{ trans('accounting::general.one_to_thirty') }}</th>
            <th style="text-align: right">{{ trans('accounting::general.thirty_one_to_sixty') }}</th>
            <th style="text-align: right">{{ trans('accounting::general.sixty_one_to_ninety') }}</th>
            <th style="text-align: right">{{ trans('accounting::general.ninety_one_and_over') }}</th>
            <th style="text-align: right">{{ trans_choice('accounting::lang.amount', 1) }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($chart_of_accounts as $chart_of_account_name => $chart_of_account_id)
            @php
                $chart_of_account = $data->where('chart_of_account_id', $chart_of_account_id);
            @endphp
            <tr>
                <td>{{ $chart_of_account_name }}</td>
                <td style="text-align: right">
                    {{ $chart_of_account->where('transaction_date', $days_past->today)->sum('amount_due') }}</td>
                <td style="text-align: right">
                    {{ $chart_of_account->whereBetween('transaction_date', [$days_past->thirty_days_ago, $days_past->yesterday])->sum('amount_due') }}
                </td>
                <td style="text-align: right">
                    {{ $chart_of_account->whereBetween('transaction_date', [$days_past->sixty_days_ago, $days_past->thirty_one_days_ago])->sum('amount_due') }}
                </td>
                <td style="text-align: right">
                    {{ $chart_of_account->whereBetween('transaction_date', [$days_past->ninety_days_ago, $days_past->sixty_one_days_ago])->sum('amount_due') }}
                </td>
                <td style="text-align: right">
                    {{ $chart_of_account->where('transaction_date', '<=', $days_past->ninety_one_days_ago)->sum('amount_due') }}
                </td>
                <td style="text-align: right; font-weight: bold">{{ $chart_of_account->sum('amount_due') }}</td>
            </tr>
        @endforeach
        <tr class="bg-grand-total" style="font-weight: bold">
            <td>{{ trans('accounting::lang.total') }}</td>
            <td style="text-align: right">{{ $data->where('transaction_date', $days_past->today)->sum('amount_due') }}</td>
            <td style="text-align: right">
                {{ $data->whereBetween('transaction_date', [$days_past->thirty_days_ago, $days_past->yesterday])->sum('amount_due') }}
            </td>
            <td style="text-align: right">
                {{ $data->whereBetween('transaction_date', [$days_past->sixty_days_ago, $days_past->thirty_one_days_ago])->sum('amount_due') }}
            </td>
            <td style="text-align: right">
                {{ $data->whereBetween('transaction_date', [$days_past->ninety_days_ago, $days_past->sixty_one_days_ago])->sum('amount_due') }}
            </td>
            <td style="text-align: right">
                {{ $data->where('transaction_date', '<=', $days_past->ninety_one_days_ago)->sum('amount_due') }}</td>
            <td style="text-align: right">{{ $data->sum('amount_due') }}</td>
        </tr>
    </tbody>
</table>
