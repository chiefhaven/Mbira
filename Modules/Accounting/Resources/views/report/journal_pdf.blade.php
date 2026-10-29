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
<h3 class="text-center">{{ trans_choice('accounting::general.journal', 1) }}</h3>
<table class="table table-striped" style="font-size: 12px">
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
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th>{{ trans_choice('accounting::lang.start_date', 1) }}: {{ readable_date($start_date) }}</th>
            <th>{{ trans_choice('accounting::lang.end_date', 1) }}: {{ readable_date($end_date) }}</th>
        </tr>
        <tr>
            <th>
                {{ trans_choice('accounting::lang.transaction', 1) }} {{ trans_choice('accounting::lang.date', 1) }}
            </th>
            <th>
                {{ trans_choice('accounting::lang.transaction', 1) }}#
            </th>
            <th>
                {{ trans_choice('accounting::lang.type', 1) }}
            </th>
            <th>
                {{ trans_choice('accounting::general.account_subtype', 1) }}
            </th>
            <th>
                {{ trans_choice('accounting::lang.account', 1) }}
                {{ trans_choice('accounting::general.detail_type', 1) }}
            </th>
            <th>
                {{ trans_choice('accounting::lang.account', 1) }}
            </th>
            <th>
                {{ trans_choice('accounting::lang.created_by', 1) }}
            </th>
            <th style="text-align: right">
                {{ trans_choice('accounting::general.debit', 1) }}
            </th>
            <th style="text-align: right">
                {{ trans_choice('accounting::general.credit', 1) }}
            </th>
        </tr>
    </thead>

    <tbody>
        @php
            $parent2_index = 1;
        @endphp

        @foreach ($account_types as $account_type)
            @php
                $parent1_index = $parent2_index;
            @endphp
            <tr class="treegrid-{{ $parent2_index }}">
                <td>{{ ucfirst($account_type) }}</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            @php
                $parent2_index++;
                $chart_of_account_type = $data->where('account_type', $account_type);
            @endphp

            @foreach ($chart_of_account_type as $key)
                <tr class="treegrid-{{ $parent2_index }} treegrid-parent-{{ $parent1_index }}">
                    <td style="min-width: 200px">{{ $key->date }}</td>
                    <td class="___class_+?41___">
                        <span>{{ $key->transaction_number }}</span>
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
                    <td>{{ $key->account_subtype }}</td>
                    <td>{{ $key->account_detail_type }}</td>
                    <td class="___class_+?44___">
                        <span>{{ $key->account_name }}</span>
                    </td>
                    <td class="___class_+?42___">
                        <span>{{ $key->created_by }}</span>
                    </td>
                    <td class="___class_+?45___" style="text-align: right">
                        <span>{{ number_format($key->debit) }}</span>
                    </td>
                    <td class="___class_+?46___" style="text-align: right">
                        <span>{{ number_format($key->credit) }}</span>
                    </td>
                    @php
                        $parent2_index++;
                    @endphp
            @endforeach

            <tr class="font-weight-bold bg-subtotal">
                <td>
                    {{ trans('accounting::lang.total_for') }} {{ $account_type }}
                </td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td class="text-right">
                    {{ number_format($chart_of_account_type->sum('debit'), 2) }}
                </td>
                <td class="text-right">
                    {{ number_format($chart_of_account_type->sum('credit'), 2) }}
                </td>
            </tr>
        @endforeach

        <tr class="font-weight-bold bg-grand-total">
            <td>
                {{ trans('accounting::lang.grand_total') }}
            </td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td class="text-right">
                {{ number_format($data->sum('debit'), 2) }}
            </td>
            <td class="text-right">
                {{ number_format($data->sum('credit'), 2) }}
            </td>
        </tr>
    </tbody>
</table>
