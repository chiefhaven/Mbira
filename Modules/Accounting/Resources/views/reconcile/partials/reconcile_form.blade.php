<form action="{{ url('accounting/reconcile/start') }}" method="post">
    @csrf
    <input type="hidden" name="chart_of_account_name" v-model="chart_of_account.name">

    <div class="row">
        <div class="form-group col-4 pl-1">
            <label>{{ trans('accounting::lang.which_account_to_reconcile') }}</label>
        </div>
    </div>

    <div class="row">
        <div class="form-group col-md-12">
            <label for="chart_of_account_id" class="control-label">{{ trans_choice('accounting::general.account', 1) }}</label>
            <v-select label="name_with_subtype" :options="chart_of_accounts" :reduce="account => account.id"
                v-model="chart_of_account_id">
                <template #search="{attributes, events}">
                    <input autocomplete="off" class="vs__search @error('chart_of_account_id') is-invalid @enderror"
                        v-bind="attributes" v-bind:required="!chart_of_account_id" v-on="events" />
                </template>
            </v-select>
            <input type="hidden" name="chart_of_account_id" v-model="chart_of_account_id">
            @error('chart_of_account_id')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>
    </div>

    <div class="row">
        <div class="form-group col-4 pl-1">
            <label>{{ trans('accounting::lang.add_the_following_information') }}</label>
        </div>
    </div>

    <div class="row">
        <div class="form-group col-md-4">
            <label for="opening_balance"
                class="control-label">{{ trans_choice('account.opening_balance', 1) }}</label>
            <input class="form-control" v-model="chart_of_account.last_opening_balance" type="text" name="opening_balance"
                id="opening_balance" readonly>
            @error('opening_balance')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="form-group col-md-4">
            <label for="ending_balance"
                class="control-label">{{ trans_choice('accounting::lang.ending_balance', 1) }}</label>
            <input class="form-control" v-model="ending_balance" type="text" name="ending_balance" id="ending_balance">
            @error('ending_balance')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="form-group col-md-4">
            <label for="ending_date" class="control-label">{{ trans_choice('accounting::lang.ending_date', 1) }}</label>
            <input class="form-control datepicker" v-model="ending_date" type="text" name="ending_date" id="ending_date">
            @error('ending_date')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>
    </div>

    <div class="form-group">
        <div class="btn-group float-right">
            <button type="button" class="btn btn-danger" @click="storeUndoReconciliation" 
                :disabled="chart_of_account.last_reconcile_transaction_id == null">
                {{ trans('accounting::lang.undo_last_reconciliation') }}
            </button>
            <button type="submit" class="btn btn-primary">
                {{ trans('accounting::lang.reconcile') }}
            </button>
        </div>
    </div>
</form>