<div class="modal fade" id="createAccountDetailModal" tabindex="-1" role="dialog" aria-labelledby="createAccountDetailModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createAccountDetailModalLabel">
                    {{ trans('accounting::lang.create') }} {{ trans('account.account') }}
                    {{ trans_choice('accounting::general.detail_type', 1) }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form action="{{ url('accounting/settings/detail_types/store') }}" method="post">
                @csrf
                <div class="modal-body">

                    <div class="form-group">
                        <label for="account_subtype_id"
                            class="control-label">{{ trans_choice('accounting::general.account_subtype', 1) }}</label>
                        <v-select label="name" :options="account_subtypes" :reduce="account => account.id" v-model="account_subtype_id">
                            <template #search="{attributes, events}">
                                <input autocomplete="off" class="vs__search @error('account_subtype_id') is-invalid @enderror"
                                    v-bind="attributes" v-bind:required="!account_subtype_id" v-on="events" />
                            </template>
                        </v-select>
                        <input type="hidden" name="account_subtype_id" v-model="account_subtype_id">
                        @error('account_subtype_id')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="name">{{ trans('accounting::lang.name') }}</label>
                        <input class="form-control" type="text" name="name" id="name" v-model="name">
                    </div>

                    <div class="form-group">
                        <label for="description">{{ trans('accounting::lang.description') }}</label>
                        <textarea class="form-control" type="text" name="description" id="description" v-model="description"></textarea>
                    </div>

                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                <input class="input-icheck" name="active" type="checkbox" v-model="active">
                                {{ trans('accounting::lang.active') }}
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
