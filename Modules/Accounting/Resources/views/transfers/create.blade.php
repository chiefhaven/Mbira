<div class="modal-dialog" role="document">
    <div class="modal-content">

        <form method="post" id="fund_transfer_form" action="{{ url('accounting/transfers/store') }}" enctype="multipart/form-data">
            {{ csrf_field() }}

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">@lang('account.fund_transfer')</h4>
            </div>

            <div class="modal-body">

                <div class="col-md-12">
                    <div class="form-group">
                        <label for="location_id" class="control-label">{{ trans_choice('accounting::lang.business_location', 1) }}</label>
                        <select class="form-control @error('location_id') is-invalid @enderror" name="location_id" id="location_id"
                            v-model="location_id" required>
                            <option value="" disabled selected>{{ trans_choice('accounting::lang.select', 1) }}</option>
                            @foreach ($business_locations as $key)
                                <option value="{{ $key->id }}">{{ $key->name }}</option>
                            @endforeach
                        </select>
                        @error('location_id')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label for="currency_id" class="control-label">{{ trans_choice('accounting::lang.currency', 1) }}</label>
                        <v-select label="currency" :options="currencies" :reduce="currency => currency.id" v-model="currency_id">
                            <template #search="{attributes, events}">
                                <input autocomplete="off" class="vs__search @error('currency_id') is-invalid @enderror"
                                    :required="!currency_id" v-bind="attributes" v-on="events" />
                            </template>
                        </v-select>
                        <input type="hidden" name="currency_id" v-model="currency_id">
                        @error('currency_id')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label for="amount" class="control-label">{{ trans_choice('accounting::lang.amount', 1) }}</label>
                        <input type="text" name="amount" v-model="amount" id="amount"
                            class="form-control numeric @error('amount') is-invalid @enderror" required>
                        @error('amount')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label for="debit" class="control-label">{{ trans_choice('accounting::general.transfer_from', 1) }}</label>
                        <select class="form-control @error('debit') is-invalid @enderror" name="debit" id="debit" v-model="debit"
                            required>
                            <option value="" disabled selected>{{ trans_choice('accounting::lang.select', 1) }}</option>
                            @foreach ($chart_of_accounts as $key)
                                <option value="{{ $key->id }}">{{ $key->name }}</option>
                            @endforeach
                        </select>
                        @error('debit')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label for="credit" class="control-label">{{ trans_choice('accounting::general.transfer_to', 1) }}</label>
                        <select class="form-control @error('credit') is-invalid @enderror" name="credit" id="credit" v-model="credit"
                            required>
                            <option value="" disabled selected>{{ trans_choice('accounting::lang.select', 1) }}</option>
                            @foreach ($chart_of_accounts as $key)
                                <option value="{{ $key->id }}">{{ $key->name }}</option>
                            @endforeach
                        </select>
                        <div class="text-danger" v-show="credit_equal_debit">{{ __('accounting::general.transfer_accounts_same') }}
                        </div>
                        @error('credit')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        {!! Form::label('date', __('messages.date') . ':*') !!}
                        <div class="input-group date" id='od_datetimepicker'>
                            {!! Form::text('date', null, [
                                'class' => 'form-control datepicker',
                                'required',
                                'placeholder' => __('messages.date'),
                                'v-model' => 'date',
                            ]) !!}
                            <span class="input-group-addon" id="date_addon">
                                <span class="glyphicon glyphicon-calendar"></span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label for="reference" class="control-label">{{ trans_choice('accounting::general.reference', 1) }}</label>
                        <input type="text" name="reference" v-model="reference" id="reference"
                            class="form-control @error('reference') is-invalid @enderror">
                        @error('reference')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label for="payment_type_id" class="control-label">{{ trans_choice('accounting::lang.payment', 1) }}
                            {{ trans_choice('accounting::lang.type', 1) }}</label>
                        <select class="form-control" name="payment_type_id" id="payment_type_id">
                            @foreach ($payment_types as $payment_type)
                                <option value="{{ $payment_type->id }}">{{ $payment_type->name }}</option>
                            @endforeach
                        </select>
                        @error('payment_type_id')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label for="receipt" class="control-label">{{ trans_choice('accounting::lang.transaction', 1) }}</label>
                        <input type="text" name="receipt" v-model="receipt" id="receipt"
                            class="form-control @error('receipt') is-invalid @enderror">
                        @error('receipt')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label for="notes" class="control-label">{{ trans_choice('accounting::lang.note', 2) }}</label>
                        <textarea type="text" name="notes" v-model="notes" id="notes" class="form-control @error('notes') is-invalid @enderror"></textarea>
                        @error('notes')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>

                {{-- <div class="form-group">
                    {!! Form::label('document', __('purchase.attach_document') . ':') !!}
                    {!! Form::file('document', ['id' => 'upload_document', 'accept' => implode(',', array_keys(config('constants.document_upload_mimes_types')))]) !!}
                    <p class="help-block">
                        @lang('purchase.max_file_size', ['size' => (config('constants.document_size_limit') / 1000000)])
                        @includeIf('accounting::components.document_help_text')
                    </p>
                </div> --}}
            </div>

            <div class="modal-footer">
                <div class="text-danger" v-show="credit_equal_debit">{{ __('accounting::general.transfer_accounts_same') }}</div>
                <button type="submit" class="btn btn-primary float-right"
                    :disabled="!is_btn_enabled">{{ trans_choice('accounting::lang.save', 1) }}</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
            </div>

        </form>

    </div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->

<script>
    new Vue({
        el: '#fund_transfer_form',
        data() {
            return {
                currencies: {!! json_encode($currencies) !!},
                location_id: "{{ old('location_id') }}",
                currency_id: "{{ old('currency_id') }}",
                debit: "{{ old('debit') }}",
                credit: "{{ old('credit') }}",
                amount: "{{ old('amount') }}",
                reference: "{{ old('reference') }}",
                date: "{{ old('date', date('Y-m-d')) }}",
                payment_type_id: "{{ old('payment_type_id') }}",
                receipt: "{{ old('receipt') }}",
                notes: "{{ old('notes') }}",
            }
        },

        computed: {
            is_btn_enabled() {
                return !this.credit_equal_debit;
            },

            credit_equal_debit() {
                return (this.debit.trim() && this.credit.trim()) &&
                    this.debit == this.credit
            }

        }
    });

    $(document).ready(function() {
        $('.datepicker')
            .datepicker({
                format: 'yyyy-mm-dd',
                defaultDate: new Date()
            })
            .attr('readonly', '');

        $('#date_addon').on('click', function() {
            $('#date').trigger('click');
        });
    });
</script>
