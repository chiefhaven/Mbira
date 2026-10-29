<!-- Modal -->
<div class="modal fade" id="mapTransactionsModal" tabindex="-1" role="dialog" aria-labelledby="mapTransactionsModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mapTransactionsModalLabel">
                    {{ trans_choice('accounting::general.map_to', 1) }}
                    {{ trans_choice('accounting::general.chart_of_account', 1) }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form action="{{ url('accounting/transactions/map_to_chart_of_account') }}" method="post">
                @csrf

                <div class="modal-body">
                    <input type="hidden" name="map_type" value="{{ $map_type }}">

                    <input type="hidden" name="mapping_for" value="{{ $mapping_for }}">

                    <input type="hidden" id="transaction_id" name="transaction_id">

                    <div class="form-group">
                        <label for="chart_of_account_id" class="control-label">{{ trans_choice('accounting::general.map_to', 1) }}</label>
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

                    <div class="form-group">
                        <label for="notes">{{ trans_choice('accounting::lang.note', 1) }}</label>
                        <textarea name="notes" id="notes" cols="30" rows="3" class="form-control"></textarea>
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
