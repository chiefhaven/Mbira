@extends('accounting::layouts.app')
@section('title')
    {{ trans_choice('accounting::lang.edit', 1) }} {{ trans_choice('accounting::lang.account', 1) }}
@endsection

@section('content')

    @include('accounting::layouts.nav')
    <!-- Content Header (Page header) -->
    @component('accounting::components.section_header')
        @slot('title')
            {{ trans_choice('accounting::general.accounting', 1) }}
        @endslot
        @slot('subtitle')
            {{ trans_choice('accounting::lang.edit', 1) }} {{ trans_choice('accounting::lang.account', 1) }}
        @endslot
    @endcomponent

    <!-- Main content -->
    <section class="content no-print" id="vue-app">
        @can('product.view')
            <div class="row">

                @component('accounting::components.box')
                    @slot('body')
                        <section class="content">
                            <form method="post" action="{{ url('accounting/chart_of_account/' . $chart_of_account->id . '/update') }}">
                                {{ csrf_field() }}
                                <div class="card card-bordered card-preview">
                                    <div class="card-body">

                                        <div class="row gy-4">
                                            <div class="col-md-12">
                                                <input type="hidden" name="date" value="{{ date('Y-m-d') }}">

                                                <div class="form-group">
                                                    <label for="account_type" class="control-label">{{ trans_choice('accounting::lang.account', 1) }}
                                                        {{ trans_choice('accounting::lang.type', 1) }}</label>
                                                    <select class="form-control @error('account_type') is-invalid @enderror" name="account_type"
                                                        id="account_type" v-model="account_type" v-on:change="resetAccountSubtypeAndDetailTypeId">
                                                        <option value="" disabled></option>
                                                        <option v-for="account_type in account_types" :value="account_type.id">
                                                            @{{ account_type.name }}
                                                        </option>
                                                    </select>
                                                    @error('account_type')
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $message }}</strong>
                                                        </span>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="account_subtype_id"
                                                        class="control-label">{{ trans_choice('accounting::general.account_subtype', 1) }}</label>
                                                    <v-select label="name" :options="filtered_account_subtypes" :reduce="account => account.id"
                                                        v-model="account_subtype_id" v-on:input="resetDetailAndParentId">
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
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="detail_type_id" class="control-label">{{ trans_choice('accounting::general.account', 1) }}
                                                        {{ trans_choice('accounting::general.detail_type', 1) }}</label>
                                                    <v-select label="name" :options="filtered_account_detail_types" :reduce="account => account.id"
                                                        v-model="detail_type_id">
                                                        <template #search="{attributes, events}">
                                                            <input autocomplete="off" class="vs__search @error('detail_type_id') is-invalid @enderror"
                                                                v-bind="attributes" v-bind:required="!detail_type_id" v-on="events" />
                                                        </template>
                                                    </v-select>
                                                    <input type="hidden" name="detail_type_id" v-model="detail_type_id">

                                                    <div v-if="account_detail_type.description">
                                                        <div class="alert alert-info">
                                                            @{{ account_detail_type.description }}
                                                        </div>
                                                    </div>

                                                    @error('detail_type_id')
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $message }}</strong>
                                                        </span>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="parent_id" class="control-label">{{ trans_choice('accounting::lang.parent', 1) }}</label>
                                                    <select class="form-control @error('parent_id') is-invalid @enderror" name="parent_id" id="parent_id"
                                                        v-model="parent_id">
                                                        <option value=""></option>
                                                        <option v-for="account in filtered_chart_of_accounts" :value="account.id">@{{ account.name }}
                                                        </option>
                                                    </select>
                                                    @error('parent_id')
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $message }}</strong>
                                                        </span>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="name" class="control-label">{{ trans_choice('accounting::lang.name', 1) }}</label>
                                                    <input type="text" name="name" v-model="name" id="name"
                                                        class="form-control @error('name') is-invalid @enderror" required>
                                                    @error('name')
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $message }}</strong>
                                                        </span>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="gl_code"
                                                        class="control-label">{{ trans_choice('accounting::general.gl_code', 1) }}</label>
                                                    <input type="number" name="gl_code" v-model="gl_code" id="gl_code"
                                                        class="form-control @error('gl_code') is-invalid @enderror" required>
                                                    @error('gl_code')
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $message }}</strong>
                                                        </span>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="allow_manual"
                                                        class="control-label">{{ trans_choice('accounting::general.manual_entries_allowed', 1) }}</label>
                                                    <select class="form-control @error('allow_manual') is-invalid @enderror" name="allow_manual"
                                                        id="allow_manual" v-model="allow_manual">
                                                        <option value="0">{{ trans_choice('accounting::lang.no', 1) }}</option>
                                                        <option value="1">{{ trans_choice('accounting::lang.yes', 1) }}</option>
                                                    </select>
                                                    @error('allow_manual')
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $message }}</strong>
                                                        </span>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="currency_id"
                                                        class="control-label">{{ trans_choice('accounting::lang.currency', 1) }}</label>
                                                    <v-select label="currency" :options="currencies" :reduce="currency => currency.id"
                                                        v-model="currency_id">
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
                                        </div>

                                        <div class="form-group">
                                            <label for="active" class="control-label">{{ trans_choice('accounting::lang.active', 1) }}</label>
                                            <select class="form-control @error('active') is-invalid @enderror" name="active" id="active"
                                                v-model="active">
                                                <option value="0">{{ trans_choice('accounting::lang.no', 1) }}</option>
                                                <option value="1">{{ trans_choice('accounting::lang.yes', 1) }}</option>
                                            </select>
                                            @error('active')
                                                <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                            @enderror
                                        </div>

                                        <div class="form-group">
                                            <label for="notes" class="control-label">{{ trans_choice('accounting::lang.note', 2) }}</label>
                                            <textarea type="text" name="notes" v-model="notes" id="notes" class="form-control @error('notes') is-invalid @enderror"></textarea>
                                            @error('notes')
                                                <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                            @enderror
                                        </div>

                                        <div class="form-group">
                                            <div class="checkbox">
                                                <label>
                                                    <input name="has_opening_balance" type="checkbox" v-model="has_opening_balance">
                                                    {{ trans('accounting::general.has_opening_balance') }}
                                                </label>
                                            </div>
                                        </div>

                                        <div v-if="has_opening_balance">

                                            <div class="form-group">
                                                <label for="opening_balance"
                                                    class="control-label">{{ trans_choice('account.opening_balance', 1) }}</label>
                                                <input type="number" name="opening_balance" v-model="opening_balance" id="opening_balance"
                                                    class="form-control @error('name') is-invalid @enderror" required>
                                                @error('name')
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                    </span>
                                                @enderror
                                            </div>

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

                                            {{-- <div class="form-group">
                                                <label for="receipt" class="control-label">{{ trans_choice('accounting::lang.transaction', 1) }}</label>
                                                <input type="text" name="receipt" v-model="receipt" id="receipt"
                                                    class="form-control @error('receipt') is-invalid @enderror">
                                                @error('receipt')
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                    </span>
                                                @enderror
                                            </div> --}}

                                        </div>

                                    </div>
                                    <div class="card-footer border-top">
                                        <button type="submit"
                                            class="btn btn-primary float-right">{{ trans_choice('accounting::lang.save', 1) }}</button>
                                    </div>
                                </div><!-- .card-preview -->
                            </form>
                        </section>
                    @endslot
                @endcomponent

            </div>
        @endcan
    </section>

@stop
@section('javascript')
    <script>
        var app = new Vue({
            el: "#vue-app",
            data: {
                name: "{{ old('name', $chart_of_account->name) }}",
                account_type: "{{ old('account_type', $chart_of_account->account_type) }}",
                parent_id: "{{ old('parent_id', $chart_of_account->parent_id) }}",
                gl_code: "{{ old('gl_code', $chart_of_account->gl_code) }}",
                allow_manual: "{{ old('allow_manual', $chart_of_account->allow_manual) }}",
                active: "{{ old('active', $chart_of_account->active) }}",
                notes: "{{ old('notes', $chart_of_account->notes) }}",
                currencies: {!! json_encode($currencies) !!},
                opening_balance: "{{ old('opening_balance', $chart_of_account->opening_balance) }}",
                has_opening_balance: {{ old('has_opening_balance', $chart_of_account->opening_balance) ? 1 : 0 }},
                payment_type_id: "{{ old('payment_type_id', $chart_of_account->payment_type_id) }}",
                chart_of_accounts: {!! json_encode($chart_of_accounts) !!},
                account_types: {!! json_encode($account_types) !!},
                account_subtypes: {!! json_encode($account_subtypes) !!},
                account_detail_types: {!! json_encode($account_detail_types) !!},
                currency_id: parseInt("{{ old('currency_id', $chart_of_account->currency_id) }}"),
                account_subtype_id: parseInt("{{ old('account_subtype_id', $chart_of_account->account_subtype_id) }}"),
                detail_type_id: parseInt("{{ old('detail_type_id', $chart_of_account->detail_type_id) }}"),
                // receipt: parseInt("{{ old('receipt', $chart_of_account->receipt) }}"),
            },

            computed: {
                filtered_account_subtypes() {
                    return this.account_subtypes.filter(account => account.account_type == this.account_type);
                },

                filtered_account_detail_types() {
                    return this.account_detail_types.filter(account => account.account_subtype_id == this.account_subtype_id);
                },

                filtered_chart_of_accounts() {
                    return this.account_subtype_id ?
                        this.chart_of_accounts.filter(account => account.account_subtype_id == this.account_subtype_id) :
                        null;
                },

                account_detail_type() {
                    return this.detail_type_id ?
                        this.account_detail_types.find(account => account.id == this.detail_type_id) : {};
                }
            },

            methods: {
                resetAccountSubtypeAndDetailTypeId() {
                    // Reset the account_subtype_id when the account_type is changed to avoid choosing an account_type_id that
                    // does not belong to that account_type
                    this.account_subtype_id = null;
                    this.detail_type_id = null;
                },

                resetDetailAndParentId() {
                    // Reset the detail_type_id when the account_subtype is changed to avoid choosing an detail_type_id that
                    // does not belong to that account_subtype
                    this.detail_type_id = null;
                    this.parent_id = null;
                },
            }
        })
    </script>
@endsection
