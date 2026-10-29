<div class="col-sm-12">
    <br>
    <div class="table-responsive">
        <table class="table table-bordered table-striped datatable" id="transfers_table">
            <thead>
                <tr>
                    <th></th>
                    <th>{{ trans('account.account') }}</th>
                    @foreach ($months as $month)
                        <th>{{ $month }}</td>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($chart_of_accounts as $chart_of_account)
                    <tr>
                        <td>
                            <div class="btn-group">
                                <button href="#" class="btn btn-info dropdown-toggle btn-xs" data-toggle="dropdown" aria-expanded="false">
                                    {{ trans_choice('accounting::lang.action', 1) }}
                                    <span class="caret"></span><span class="sr-only"></span>
                                </button>

                                <div class="dropdown-menu dropdown-menu-left">
                                    <a href="#" class="dropdown-item" data-toggle="modal" data-target="#editMonthlyModal"
                                        chart_of_account_id="{{ $chart_of_account->id }}" @click="onClickEditBudget">
                                        <i class="fas fa-edit"></i>{{ trans_choice('accounting::lang.edit', 1) }}
                                    </a>
                                </div>
                            </div>
                        </td>

                        <td>{{ $chart_of_account->name }}</td>

                        @php
                            $budget = $chart_of_account->budget;
                        @endphp
                        @for ($month_number = 1; $month_number <= 12; $month_number++)
                            @php
                                $month = 'month_' . $month_number;
                            @endphp
                            <td class="{{ $month }}">{{ number_format($budget[$month], 2) }}</td>
                        @endfor

                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@include('accounting::budget.modals.edit_monthly_modal')
