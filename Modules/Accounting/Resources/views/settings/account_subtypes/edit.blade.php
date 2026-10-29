@extends('accounting::settings.layout')
@section('tab-title')
    {{ trans_choice('accounting::general.account_subtype', 2) }}
@endsection

@section('tab-content')
    <!-- Main content -->
    <section class="content no-print" id="vue-app">
        <div class="row">

            @component('accounting::components.box')
                @slot('title')
                    {{ trans_choice('accounting::general.account_subtype', 1) }}
                    #{{ $account_subtype->id }}
                @endslot

                @slot('body')
                    <!-- Main content -->
                    <section class="content" id="vue-app">
                        <div class="card">

                            <form action="{{ url('accounting/settings/account_subtypes/' . $account_subtype->id . '/update') }}" method="post">
                                <div class="card-body p-0">
                                    @csrf
                                    <div class="form-group">
                                        <label for="account_type" class="control-label">{{ trans_choice('accounting::general.account', 1) }}
                                            {{ trans_choice('accounting::lang.type', 1) }}</label>
                                        <v-select label="name" :options="account_types" :reduce="account => account.id" v-model="account_subtype.id">
                                            <template #search="{attributes, events}">
                                                <input autocomplete="off" class="vs__search @error('account_type') is-invalid @enderror"
                                                    v-bind="attributes" v-bind:required="!account_subtype.id" v-on="events" />
                                            </template>
                                        </v-select>
                                        <input type="hidden" name="account_type" v-model="account_subtype.id">
                                        @error('account_type')
                                            <span class="invalid-feedback" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="name">{{ trans('accounting::lang.name') }}</label>
                                        <input class="form-control" type="text" name="name" v-model="name">
                                    </div>

                                    <div class="form-group">
                                        <label for="description">{{ trans('accounting::lang.description') }}</label>
                                        <textarea class="form-control" type="text" name="description" v-model="description"></textarea>
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

                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary float-right">{{ trans_choice('accounting::lang.save', 1) }}</button>
                                </div>

                            </form>

                        </div>
                    </section>
                @endslot
            @endcomponent
        </div>
    </section>

@endsection

@section('tab-javascript')
    <script>
        const app = new Vue({
            el: '#vue-app',
            data: {
                account_types: {!! json_encode($account_types) !!},
                account_subtype: {
                    id: "{{ old('account_type', $account_subtype->account_type) }}",
                    name: "{{ old('account_type', $account_subtype->account_type) }}"
                },
                name: "{{ old('name', $account_subtype->name) }}",
                description: "{{ old('description', $account_subtype->description) }}",
                active: {{ old('active', $account_subtype->active) ? 1 : 0 }},
            },
        });
    </script>
@endsection
