<!-- Modal -->
<div class="modal fade" id="editQuarterlyModal" tabindex="-1" role="dialog" aria-labelledby="editQuarterlyModalTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editQuarterlyModalTitle">
                    @{{ chart_of_account.name }} - {{ ucfirst(Request::get('view')) }}
                    {{ trans_choice('accounting::general.budget', 1) }} {{ $financial_year }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form action="{{ url('accounting/budget/update_quarterly_budget') }}" method="post">
                @csrf
                <div class="modal-body">

                    <input type="hidden" name="business_id" v-model="business_id">

                    <input type="hidden" name="chart_of_account_id" v-model="chart_of_account_id">

                    <input type="hidden" name="financial_year" v-model="financial_year">

                    @for ($i = 0; $i < 4; $i++)
                        @php
                            $quarter_number = $i + 1;
                            $id = 'quarter_' . $quarter_number; // Added the first quarter January starts at 1 but array indexes are zero based (start at zero)
                        @endphp

                        <div class="form-group">
                            <label for="{{ $id }}">{{ trans_choice('accounting::lang.quarter', 1) }} {{ $quarter_number }}
                                {{ $financial_year }}</label>
                            <input class="form-control" id="{{ $id }}" name="{{ $id }}"
                                v-model="quarters[{{ $quarter_number }}]">
                            <small>
                                <a href="#" input_id="{{ $id }}" class="apply_for_all">
                                    {{ trans('accounting::general.apply_for_all') }}
                                    {{ trans_choice('accounting::lang.quarter', 2) }}
                                </a>
                            </small>
                        </div>
                    @endfor

                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                <input class="input-icheck" name="eliminate_decimals" type="checkbox" v-model="eliminate_decimals">
                                Eliminate decimals (Recommended if you wish to avoid rounding off loss)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>

            </form>

        </div>
    </div>
</div>
