@extends('accounting::settings.layout')
@section('tab-title')
    {{ trans_choice('accounting::general.account_subtype', 2) }}
@endsection

@section('tab-content')
    <!-- Main content -->
    <section class="content no-print">
        <div class="row">

            <h3 style="padding: 0px 24px;">{{ trans_choice('accounting::general.account_subtype', 2) }}</h3>

            <!-- Main content -->
            <section class="content">

                @foreach ($account_types as $account_type)
                    @component('accounting::components.box')
                        @slot('title')
                            {{ ucfirst($account_type->plural_name) }}
                        @endslot

                        @slot('header')
                            <div class="box-tools">
                                <a href="#" class="btn btn-primary" data-toggle="modal" data-target="#createAccountSubtypeModal"
                                    @click="setAccountType('{{ $account_type->id }}')">
                                    <i class="fas fa-plus"></i> {{ trans_choice('accounting::lang.add', 1) }}
                                </a>
                            </div>
                        @endslot

                        @slot('body')
                            <div class="table-responsive">
                                <table class="table table-striped table-condensed table-hover datatable">
                                    <thead>
                                        <tr>
                                            <th>
                                                {{ trans_choice('accounting::lang.name', 1) }}
                                            </th>
                                            <th>
                                                {{ trans_choice('accounting::lang.active', 1) }}
                                            </th>
                                            <th>
                                                {{ trans_choice('accounting::lang.action', 1) }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $filtered_account_subtypes = $account_subtypes->where('account_type', $account_type->id);
                                        @endphp
                                        @if (!empty($filtered_account_subtypes))
                                            @foreach ($filtered_account_subtypes as $key)
                                                <tr>
                                                    <td>
                                                        <span>{{ $key->name }}</span>
                                                    </td>
                                                    <td>
                                                        @if ($key->active)
                                                            <span class="label label-success">
                                                                {{ trans('accounting::lang.yes') }}
                                                            </span>
                                                        @else
                                                            <span class="label label-danger">
                                                                {{ trans('accounting::lang.no') }}
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if (!$key->is_default_type)
                                                            <div class="btn-group">
                                                                <button href="#" class="btn btn-info dropdown-toggle btn-xs"
                                                                    data-toggle="dropdown"
                                                                    aria-expanded="false">{{ trans_choice('accounting::lang.action', 1) }}
                                                                    <span class="caret"></span><span class="sr-only"></span>
                                                                </button>

                                                                <div class="dropdown-menu dropdown-menu-right">
                                                                    <a href="{{ url('accounting/settings/account_subtypes/' . $key->id . '/edit') }}"
                                                                        class="dropdown-item">
                                                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                                                        <span>{{ trans_choice('accounting::lang.edit', 1) }}</span>
                                                                    </a>
                                                                    <div class="dropdown-divider"></div>
                                                                    <a href="#" class="dropdown-item confirm-delete"
                                                                        form_id="delete-account-subtype-{{ $key->id }}-form"
                                                                        action="{{ url('accounting/settings/account_subtypes/' . $key->id . '/destroy') }}">
                                                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                                                        <span>{{ trans_choice('accounting::lang.delete', 1) }}</span>
                                                                    </a>
                                                                    <form id="delete-account-subtype-{{ $key->id }}-form" method="post">
                                                                        @csrf
                                                                        @method('delete')
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        @endslot
                    @endcomponent
                @endforeach

            </section>

        </div>
    </section>
@endsection

@section('tab-modal-content')
    @include('accounting::settings.account_subtypes.create')
@endsection

@section('tab-javascript')
    <script>
        const app = new Vue({
            el: '#vue-app-with-modal',
            data: {
                account_types: {!! json_encode($account_types) !!},
                account_type: "{{ old('account_subtype_id') }}",
                name: "{{ old('name') }}",
                description: "{{ old('description') }}",
                active: {{ old('active', 1) }},
            },

            methods: {
                setAccountType(account_type) {
                    this.account_type = account_type;
                }
            }
        });
    </script>
@endsection
