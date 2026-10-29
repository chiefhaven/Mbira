@extends('accounting::layouts.app')
@section('title', 'Transfers')

@section('content')
    @include('accounting::layouts.nav')
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>Transfer
            <small>Manage your Transfers</small>
        </h1>
    </section>

    <!-- Main content -->
    <section class="content">

        @can('account.access')
            <div class="row">
                <div class="col-sm-12">
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active">
                                <a href="#transfers" data-toggle="tab">
                                    <i class="fa fa-book"></i> <strong>Transfer</strong>
                                </a>
                            </li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="transfers">
                                <div class="row">
                                    <div class="col-md-12">
                                        @component('accounting::components.widget')
                                            <div class="col-md-8 col-md-offset-4">
                                                <button data-href="{{ url('accounting/transfers/create') }}"
                                                    class="btn btn-primary btn-modal pull-right" data-container=".account_model">
                                                    <i class="fa fa-plus"></i> @lang('messages.add')</button>
                                            </div>
                                        @endcomponent
                                    </div>
                                    <div class="col-sm-12">
                                        <br>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped" id="transfers_table">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Journal Entry</th>
                                                        <th>Transfer from</th>
                                                        <th></th>
                                                        <th>Transfer To</th>
                                                        <th>Transfer By</th>
                                                        <th>Amount</th>
                                                        <th>Date</th>
                                                    </tr>
                                                </thead>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endcan

        <div class="modal fade account_model" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
        </div>

        <div class="modal fade" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel" id="account_type_modal">
        </div>
    </section>
    <!-- /.content -->

@endsection

@section('javascript')
    <script>
        $(document).ready(function() {

            const transfers_table = $('#transfers_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '/accounting/transfers',
                columns: [{
                        data: 'id',
                        name: 'id'
                    },
                    {
                        data: 'journal_entry',
                        name: 'journal_entry'
                    },
                    {
                        data: 'transfer_from',
                        name: 'transfer_from'
                    },
                    {
                        data: 'arrow_right',
                        name: 'arrow_right',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'transfer_to',
                        name: 'transfer_to'
                    },
                    {
                        data: 'transfer_by',
                        name: 'transfer_by'
                    },
                    {
                        data: 'amount',
                        name: 'amount'
                    },
                    {
                        data: 'created_at',
                        name: 'created_at'
                    },
                ],
            });

            $(document).on('submit', 'form#fund_transfer_form', function(e) {
                e.preventDefault();
                var data = $(this).serialize();
                $.ajax({
                    method: "post",
                    url: $(this).attr("action"),
                    dataType: "json",
                    data: data,
                    success: function(result) {
                        if (result.success == true) {
                            $('div.account_model').modal('hide');
                            toastr.success(result.msg);
                            transfers_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    }
                });
            });

        });
    </script>
@endsection
