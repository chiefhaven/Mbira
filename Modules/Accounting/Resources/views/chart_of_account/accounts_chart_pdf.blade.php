<style>
    body {
        font-size: 9px;
    }

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

    a {
        text-decoration: none;
    }

</style>
<h3 class="text-center">{{ Session::get('business.name') }}</h3>
<h3 class="text-center"> {{ trans_choice('accounting::general.chart_of_accounts_sheet', 1) }}</h3>
<table id="data-table" class="table table-striped table-condensed table-hover">
    <thead>
        <tr>
            <th>
                {{ trans_choice('accounting::lang.name', 1) }}
            </th>
            <th>
                {{ trans_choice('accounting::general.gl_code', 2) }}
            </th>
            <th>
                {{ trans_choice('accounting::lang.account', 1) }} {{ trans_choice('accounting::lang.type', 1) }}
            </th>
            <th>
                {{ trans_choice('accounting::lang.active', 1) }}
            </th>
            <th>
                {{ trans_choice('accounting::general.manual_entries_allowed', 1) }}
            </th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $key)
            <tr>
                <td>
                    <a href="{{ url('accounting/chart_of_account/' . $key->id . '/show') }}">
                        <span>{{ $key->name }}</span>
                    </a>
                </td>
                <td>
                    <span>{{ $key->gl_code }}</span>
                </td>
                <td>
                    @if ($key->account_type == 'asset')
                        <span>{{ trans_choice('accounting::general.asset', 1) }}</span>
                    @elseif ($key->account_type == 'expense')
                        <span>{{ trans_choice('accounting::general.expense', 1) }}</span>
                    @elseif ($key->account_type == 'equity')
                        <span>{{ trans_choice('accounting::general.equity', 1) }}</span>
                    @elseif ($key->account_type == 'liability')
                        <span>{{ trans_choice('accounting::general.liability', 1) }}</span>
                    @elseif ($key->account_type == 'income')
                        <span>{{ trans_choice('accounting::general.income', 1) }}</span>
                    @endif
                </td>
                <td>
                    @if ($key->active == 1)
                        <span class="label label-success">{{ trans_choice('accounting::lang.yes', 1) }}</span>
                    @else
                        <span class="label label-danger">{{ trans_choice('accounting::lang.no', 1) }}</span>
                    @endif
                </td>
                <td>
                    @if ($key->allow_manual == 1)
                        <span class="label label-success">{{ trans_choice('accounting::lang.yes', 1) }}</span>
                    @else
                        <span class="label label-danger">{{ trans_choice('accounting::lang.no', 1) }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
