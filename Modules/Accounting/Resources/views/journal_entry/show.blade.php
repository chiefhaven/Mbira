@extends('accounting::layouts.app')
@section('title')
    {{ trans_choice('accounting::lang.view', 1) }} {{ trans_choice('accounting::lang.transaction', 1) }}
@endsection

@section('content')

    @include('accounting::layouts.nav')
    <!-- Content Header (Page header) -->
    @component('accounting::components.section_header')
        @slot('title')
            {{ trans_choice('accounting::general.accounting', 1) }}
        @endslot
        @slot('subtitle')
            {{ trans_choice('accounting::lang.view', 1) }} {{ trans_choice('accounting::lang.transaction', 1) }}
        @endslot
    @endcomponent

    <!-- Main content -->
    <section class="content no-print" id="vue-app">
        @can('product.view')
            <div class="row">

                @component('accounting::components.box')
                    @slot('title')
                        {{ trans_choice('accounting::lang.transaction', 1) }} # {{ $data->first()->transaction_number }}
                    @endslot

                    @slot('header')
                        <div class="box-tools">
                            @if ($data->first()->reversed == 0 && $data->first()->reversible == 1)
                                @can('accounting.journal_entries.reverse')
                                    <a href="{{ url('accounting/journal_entry/' . $data->first()->transaction_number . '/reverse') }}"
                                        class="btn btn-danger confirm">
                                        <i class="fa fa-undo"></i> {{ trans_choice('accounting::general.reverse', 1) }}
                                    </a>
                                @endcan
                            @else
                                <span class="text-danger">
                                    {{ trans_choice('accounting::lang.transaction', 1) }} {{ trans_choice('accounting::general.reversed', 1) }}
                                </span>
                            @endif
                        </div>
                    @endslot

                    @slot('body')
                        <section class="content">
                            <div class="card">
                                <div class="card-header with-border">

                                    <div class="card-tools">

                                    </div>
                                </div>
                                <div class="card-body">
                                    <table class="table">
                                        <tr>
                                            <td>{{ trans_choice('accounting::lang.business_location', 1) }}</td>
                                            <td>
                                                @if (!empty($data->first()->business_location))
                                                    {{ $data->first()->business_location->name }}
                                                @endif
                                            </td>
                                            <td>{{ trans_choice('accounting::lang.transaction', 1) }} {{ trans_choice('accounting::lang.date', 1) }}
                                            </td>
                                            <td>
                                                {{ $data->first()->date }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>{{ trans_choice('accounting::lang.created_by', 1) }}</td>
                                            <td>
                                                @if (!empty($data->first()->created_by))
                                                    {{ $data->first()->created_by->first_name }} {{ $data->first()->created_by->last_name }}
                                                @else
                                                    {{ trans_choice('accounting::lang.system', 1) }}
                                                @endif
                                            </td>
                                            <td>{{ trans_choice('accounting::lang.created_on', 1) }}</td>
                                            <td>
                                                {{ $data->first()->created_at }}
                                            </td>
                                        </tr>
                                    </table>
                                    <table class="table table-bordered table-striped" id="data-table">
                                        <thead>
                                            <tr>
                                                <th> {{ trans_choice('accounting::lang.id', 1) }}</th>
                                                <th> {{ trans_choice('accounting::lang.type', 1) }}</th>
                                                <th> {{ trans_choice('accounting::lang.account', 1) }}</th>
                                                <th> {{ trans_choice('accounting::general.debit', 1) }}</th>
                                                <th> {{ trans_choice('accounting::general.credit', 1) }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($data as $key)
                                                <tr>
                                                    <td>{{ $key->id }}</td>
                                                    <td>
                                                        @if (!empty($key->chart_of_account))
                                                            @if ($key->chart_of_account->account_type == 'asset')
                                                                {{ trans_choice('accounting::general.asset', 1) }}
                                                            @elseif ($key->chart_of_account->account_type == 'expense')
                                                                {{ trans_choice('accounting::general.expense', 1) }}
                                                            @elseif ($key->chart_of_account->account_type == 'equity')
                                                                {{ trans_choice('accounting::general.equity', 1) }}
                                                            @elseif ($key->chart_of_account->account_type == 'liability')
                                                                {{ trans_choice('accounting::general.liability', 1) }}
                                                            @elseif ($key->chart_of_account->account_type == 'income')
                                                                {{ trans_choice('accounting::general.income', 1) }}
                                                            @endif
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if (!empty($key->chart_of_account))
                                                            {{ $key->chart_of_account->name }}
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if (!empty($key->debit))
                                                            {{ number_format($key->debit, 2) }}
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if (!empty($key->credit))
                                                            {{ number_format($key->credit, 2) }}
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>

                                    </table>

                                </div>
                                <!-- /.box-body -->
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
@endsection
