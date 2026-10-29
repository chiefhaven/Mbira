@extends('accounting::settings.layout')
@section('tab-title')
    {{ trans_choice('account.account', 1) }}
    {{ trans_choice('accounting::general.detail_type', 2) }}
@endsection

@section('tab-content')
    <!-- Main content -->
    <section class="content no-print" id="vue-app">
        <div class="row">

            @component('accounting::components.box')
                @slot('title')
                    {{ trans_choice('account.account', 1) }}
                    {{ trans_choice('accounting::general.detail_type', 2) }}
                @endslot

                @slot('header')
                    <div class="box-tools">
                        <a href="#" class="btn btn-primary" data-toggle="modal" data-target="#createAccountDetailModal">
                            <i class="fas fa-plus"></i> {{ trans_choice('accounting::lang.add', 1) }}
                        </a>
                    </div>
                @endslot

                @slot('body')
                    <!-- Main content -->
                    <section class="content">
                        <div class="card">

                            <div class="card-body p-0 table-responsive">
                                <table class="table table-striped table-condensed table-hover datatable">
                                    <thead>
                                        <tr>
                                            <th>
                                                {{ trans_choice('accounting::general.account_subtype', 1) }}
                                            </th>
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
                                        @if (!empty($account_detail_types))
                                            @foreach ($account_detail_types as $key)
                                                <tr>
                                                    <td>
                                                        <span>
                                                            {{ $key->account_subtype->name }}
                                                        </span>
                                                    </td>
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
                                                                    <a href="{{ url('accounting/settings/detail_types/' . $key->id . '/edit') }}"
                                                                        class="dropdown-item">
                                                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                                                        <span>{{ trans_choice('accounting::lang.edit', 1) }}</span>
                                                                    </a>
                                                                    <div class="dropdown-divider"></div>
                                                                    <a href="#" class="dropdown-item confirm-delete"
                                                                        form_id="delete-account-detail-type-{{ $key->id }}-form"
                                                                        action="{{ url('accounting/settings/detail_types/' . $key->id . '/destroy') }}">
                                                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                                                        <span>{{ trans_choice('accounting::lang.delete', 1) }}</span>
                                                                    </a>
                                                                    <form id="delete-account-detail-type-{{ $key->id }}-form" method="post">
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
                        </div>
                    </section>
                @endslot
            @endcomponent
        </div>
    </section>

@endsection

@section('tab-modal-content')
    @include('accounting::settings.detail_types.create')
@endsection

@section('tab-javascript')
    <script>
        const app = new Vue({
            el: '#createAccountDetailModal',
            data: {
                account_subtypes: {!! json_encode($account_subtypes) !!},
                account_subtype_id: "{{ old('account_subtype_id') }}",
                name: "{{ old('name') }}",
                description: "{{ old('description') }}",
                active: {{ old('active', 1) }},
            }
        });
    </script>
@endsection
