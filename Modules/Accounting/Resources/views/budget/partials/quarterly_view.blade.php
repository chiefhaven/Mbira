<div class="col-sm-12">
    <br>
    <div class="table-responsive">
        <table class="table table-bordered table-striped datatable" id="transfers_table">
            <thead>
                <tr>
                    <th></th>
                    <th>{{ trans('account.account') }}</th>
                    @for ($i = 1; $i <= 4; $i++)
                        <th>Q{{ $i }} {{ Request::get('year') }}</td>
                    @endfor
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
                                    <a href="#" class="dropdown-item" data-toggle="modal" data-target="#editQuarterlyModal"
                                        chart_of_account_id="{{ $chart_of_account->id }}" @click="onClickEditBudget">
                                        <i class="fas fa-edit"></i>{{ trans_choice('accounting::lang.edit', 1) }}
                                    </a>
                                </div>
                            </div>
                        </td>

                        <td>{{ $chart_of_account->name }}</td>

                        @foreach ($chart_of_account->budget->quarterly as $index => $quartely_budget)
                            @php
                                $quarter_number = $index + 1;
                                $quarter = 'quarter_' . $quarter_number;
                            @endphp
                            <td class="{{ $quarter }}">{{ number_format($quartely_budget, 2) }}</td>
                        @endforeach

                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@include('accounting::budget.modals.edit_quarterly_modal')
