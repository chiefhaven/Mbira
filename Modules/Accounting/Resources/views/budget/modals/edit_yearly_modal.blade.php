<!-- Modal -->
<div class="modal fade" id="editYearlyModal" tabindex="-1" role="dialog" aria-labelledby="editYearlyModalTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editYearlyModalTitle">
                    @{{ chart_of_account.name }}
                    {{ trans_choice('accounting::general.budget', 1) }} {{ $financial_year }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form action="{{ url('accounting/budget/update_yearly_budget') }}" method="post">
                @csrf
                <div class="modal-body">

                    <input type="hidden" name="business_id" v-model="business_id">

                    <input type="hidden" name="chart_of_account_id" v-model="chart_of_account_id">

                    <input type="hidden" name="financial_year" v-model="financial_year">

                    <div class="form-group">
                        <label for="yearly_budget">{{ trans_choice('accounting::general.budget', 1) }} {{ $financial_year }}</label>
                        <input class="form-control" id="yearly_budget" name="yearly_budget" v-model="yearly_budget">
                    </div>

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
