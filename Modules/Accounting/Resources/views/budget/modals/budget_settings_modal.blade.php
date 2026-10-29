<!-- Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1" role="dialog" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="settingsModalLabel">
                    {{ trans_choice('accounting::general.budget', 1) }}
                    {{ trans('accounting::lang.settings') }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form method="post" action="{{ url('accounting/budget/store_financial_year_start') }}">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="financial_year_start">{{ trans('accounting::general.financial_year_start') }}</label>
                        <select class="form-control" name="financial_year_start" id="financial_year_start" v-model="financial_year_start">
                            @foreach ($calendar_year as $number => $month)
                                <option value="{{ $number }}">{{ $month }}</option>
                            @endforeach
                        </select>
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
