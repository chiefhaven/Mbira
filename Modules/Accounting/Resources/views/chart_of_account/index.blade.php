@extends('accounting::layouts.app')
@section('title')
    {{ trans_choice('accounting::general.chart_of_account', 2) }}
@endsection

@section('content')

    @include('accounting::layouts.nav')
    <!-- Content Header (Page header) -->
    @component('accounting::components.section_header')
        @slot('title')
            {{ trans_choice('accounting::general.accounting', 1) }}
        @endslot
        @slot('subtitle')
            @lang('accounting::lang.view_charts_of_accounts')
        @endslot
    @endcomponent

    <!-- Main content -->
    <section class="content no-print" id="vue-app">

        @can('product.view')
            <div class="row">

                @component('accounting::components.box')
                    @slot('header')
                        <div class="box-tools">
                            @can('accounting.chart_of_accounts.create')
                                <a href="{{ url('accounting/chart_of_account/create') }}" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> {{ trans_choice('accounting::lang.add', 1) }}
                                </a>
                            @endcan
                        </div>
                    @endslot

                    @slot('body')
                        <!-- Main content -->
                        <section class="content">
                            <div class="card">
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table id="data-table" class="table table-striped table-condensed table-hover">
                                            <thead>
                                                <tr>
                                                    <th>{{ trans_choice('accounting::lang.action', 1) }}</th>
                                                    <th>
                                                        {{ trans_choice('accounting::lang.name', 1) }}
                                                    </th>
                                                    <th>
                                                        {{ trans_choice('accounting::general.gl_code', 2) }}
                                                    </th>
                                                    <th>
                                                        {{ trans_choice('accounting::lang.parent', 2) }}
                                                        {{ trans_choice('accounting::lang.account', 1) }}
                                                    </th>
                                                    <th>
                                                        {{ trans_choice('accounting::lang.account', 1) }}
                                                        {{ trans_choice('accounting::lang.type', 1) }}
                                                    </th>
                                                    <th>
                                                        {{ trans_choice('accounting::general.account_subtype', 1) }}
                                                    </th>
                                                    <th>
                                                        {{ trans_choice('accounting::lang.account', 1) }}
                                                        {{ trans_choice('accounting::general.detail_type', 1) }}
                                                    </th>
                                                    <th>
                                                        {{ trans_choice('accounting::lang.active', 1) }}
                                                    </th>
                                                    <th>
                                                        {{ trans_choice('accounting::general.manual_entries_allowed', 1) }}
                                                    </th>
                                                    <th>
                                                        {{ trans_choice('accounting::lang.currency', 1) }}
                                                    </th>
                                                    <th>
                                                        {{ trans_choice('account.opening_balance', 1) }}
                                                    </th>
                                                    <th>
                                                        {{ trans('accounting::lang.current') }} {{ trans_choice('accounting::lang.balance', 1) }}
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($data as $key)
                                                    <tr>
                                                        <td>
                                                            <div class="btn-group">
                                                                <button href="#" class="btn btn-info dropdown-toggle btn-xs" data-toggle="dropdown"
                                                                    aria-expanded="false">{{ trans_choice('accounting::lang.action', 1) }}
                                                                    <span class="caret"></span><span class="sr-only"></span>
                                                                </button>
                                                                <div class="dropdown-menu dropdown-menu-left">
                                                                    <a href="{{ url('accounting/chart_of_account/' . $key->id . '/show') }}"
                                                                        class="dropdown-item">
                                                                        <i class="fas fa-eye"></i>
                                                                        <span>{{ trans_choice('accounting::lang.detail', 2) }}</span>
                                                                    </a>
                                                                    @can('accounting.chart_of_accounts.edit')
                                                                        <a href="{{ url('accounting/chart_of_account/' . $key->id . '/edit') }}"
                                                                            class="dropdown-item">
                                                                            <i class="fas fa-edit"></i>
                                                                            <span>{{ trans_choice('accounting::lang.edit', 1) }}</span>
                                                                        </a>
                                                                    @endcan
                                                                    <div class="dropdown-divider"></div>
                                                                    @can('accounting.chart_of_accounts.destroy')
                                                                        <a href="{{ url('accounting/chart_of_account/' . $key->id . '/destroy') }}"
                                                                            class="dropdown-item confirm">
                                                                            <i class="fas fa-trash"></i>
                                                                            <span>{{ trans_choice('accounting::lang.delete', 1) }}</span>
                                                                        </a>
                                                                    @endcan
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <a href="{{ url('accounting/chart_of_account/' . $key->id . '/show') }}">
                                                                <span>{{ $key->name }}</span> <i class="fas fa-external-link-alt"></i>
                                                            </a>
                                                        </td>

                                                        <td>
                                                            <span>{{ $key->gl_code }}</span>
                                                        </td>

                                                        <td>
                                                            <span>{{ $key->parent->name }}</span>
                                                        </td>

                                                        <td>
                                                            @if ($key->account_type == 'asset')
                                                                <span>{{ trans_choice('accounting::general.asset', 1) }}</span>
                                                            @elseif ($key->account_type == 'expense')
                                                                <span>{{ trans_choice('accounting::general.expense', 1) }}</span>
                                                            @elseif ($key->account_type == 'equity')
                                                                <span>{{ trans_choice('accounting::general.equity', 1) }}</span>
                                                            @elseif ($key->account_type == 'liability')
                                                                <span>{{ trans_choice('accounting::general.liability', 1) }}</span>
                                                            @elseif ($key->account_type == 'income')
                                                                <span>{{ trans_choice('accounting::general.income', 1) }}</span>
                                                            @endif
                                                        </td>

                                                        <td>
                                                            <span>{{ $key->account_subtype->name }}</span>
                                                        </td>

                                                        <td>
                                                            <span>{{ $key->account_detail_type->name }}</span>
                                                        </td>

                                                        <td>
                                                            @if ($key->active == 1)
                                                                <span
                                                                    class="label label-success btn-xs">{{ trans_choice('accounting::lang.yes', 1) }}</span>
                                                            @else
                                                                <span class="label label-danger btn-xs">{{ trans_choice('accounting::lang.no', 1) }}</span>
                                                            @endif
                                                        </td>

                                                        <td>
                                                            @if ($key->allow_manual == 1)
                                                                <span
                                                                    class="label label-success btn-xs">{{ trans_choice('accounting::lang.yes', 1) }}</span>
                                                            @else
                                                                <span class="label label-danger btn-xs">{{ trans_choice('accounting::lang.no', 1) }}</span>
                                                            @endif
                                                        </td>

                                                        <td>
                                                            <span>{{ $key->currency->code }}</span>
                                                        </td>

                                                        <td>
                                                            <span>{{ $key->opening_balance }}</span>
                                                        </td>

                                                        <td>
                                                            <span>{{ $key->current_balance }}</span>
                                                        </td>

                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </section>
                    @endslot
                @endcomponent

            </div>
        @endcan
    </section>

@stop
@section('javascript')
    <script>
        $(document).ready(function() {
            $('#data-table').DataTable();
        });
    </script>

    <script>
        var app = new Vue({
            el: "#vue-app",
            data: {
                records: {!! json_encode($data) !!},
                selectAll: false,
                selectedRecords: []
            },
            methods: {
                // selectAllRecords() {
                //     this.selectedRecords = [];
                //     if (this.selectAll) {
                //         this.records.data.forEach(item => {
                //             this.selectedRecords.push(item.id);
                //         });
                //     }
                // },
            },
        })
    </script>
@endsection
