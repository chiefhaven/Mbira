@extends('accounting::layouts.transactions_layout')
@section('tab-title')
    {{ trans_choice('accounting::general.purchase', 2) }}
@endsection

@section('tab-content')
    <!-- Main content -->
    @can('product.view')
        <div class="row">

            @component('accounting::components.box')
                @slot('title')
                    {{ trans_choice('accounting::general.purchase', 2) }}
                @endslot

                @slot('header')
                    <div class="box-tools">
                        @can('client.clients.client_types.create')
                            <a href="{{ url('client/client_type/create') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> {{ trans_choice('accounting::lang.add', 1) }}
                            </a>
                        @endcan
                    </div>
                @endslot

                @slot('body')
                    <!-- Main content -->
                    <section class="content">
                        <div class="card">

                            <div class="card-body table-responsive p-0">
                                <table class="table  table-striped table-hover table-condensed" id="data-table">
                                    <thead>
                                        <tr>
                                            <th>
                                                {{ trans_choice('accounting::lang.name', 1) }}
                                            </th>
                                            <th>{{ trans_choice('accounting::lang.action', 1) }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if (!empty($data))
                                            @foreach ($data as $key)
                                                <tr>
                                                    <td>
                                                        <span>{{ $key->name }}</span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <button href="#" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                                                                <i class="fas fa-ellipsis-h"></i>
                                                            </button>
                                                            <div class="dropdown-menu dropdown-menu-right">
                                                                @can('client.clients.client_types.edit')
                                                                    <a href="{{ url('client/client_type/' . $key->id . '/edit') }}" class="dropdown-item">
                                                                        <i class="fas fa-edit"></i>
                                                                        <span>{{ trans_choice('accounting::lang.edit', 1) }}</span>
                                                                    </a>
                                                                @endcan
                                                                <div class="divider"></div>
                                                                @can('client.clients.client_types.destroy')
                                                                    <a href="{{ url('client/client_type/' . $key->id . '/destroy') }}"
                                                                        class="dropdown-item confirm">
                                                                        <i class="fas fa-trash"></i>
                                                                        <span>{{ trans_choice('accounting::lang.delete', 1) }}</span>
                                                                    </a>
                                                                @endcan
                                                            </div>
                                                        </div>
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
    @endcan
@endsection

@section('tab-javascript')
@endsection
