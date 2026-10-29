@extends('layouts.app')
@section('title', __('hms::lang.bookings'))
@section('content')
    @include('hms::layouts.nav')
    <section class="content-header">
        <h1> @lang('hms::lang.bookings')
        </h1>
        <p><i class="fa fa-info-circle"></i> @lang('hms::lang.bookings_help_text') </p>
    </section>

    <!-- Main content -->
    <section class="content">
        @component('components.filters', ['title' => __('report.filters')])
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('customer_id', __('contact.customer') . ':') !!}
                    {!! Form::select('customer_id', $customers, null, [
                        'class' => 'form-control select2',
                        'style' => 'width:100%',
                        'placeholder' => __('lang_v1.all'),
                    ]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('status', __('hms::lang.status') . ':') !!}
                    {!! Form::select('status', $status, null, [
                        'class' => 'form-control',
                        'style' => 'width:100%',
                        'placeholder' => __('lang_v1.all'),
                    ]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('filter_payment_status',  __('hms::lang.payment_status') . ':') !!}
                    {!! Form::select('filter_payment_status', ['paid' => __('lang_v1.paid'), 'due' => __('lang_v1.due'), 'partial' => __('lang_v1.partial'), 'overdue' => __('lang_v1.overdue')], null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
                </div>
            </div>
        @endcomponent
        <div class="box box-solid">
            <div class="box-header">
                <h3 class="box-title">&nbsp;</h3>
                <div class="box-tools">
                    @can('hms.add_booking')
                        <a href="{{ action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'create']) }}"
                            class="btn btn-block btn-primary btn-modal-extra" data-container=".view_modal">
                            <i class="fa fa-plus"></i> @lang('messages.add')</a>
                    @endcan
                </div>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="bookings_table">
                        <thead>
                            <tr>
                                <th>
                                    @lang('hms::lang.booking_Id')
                                </th>

                                <th>
                                    @lang('hms::lang.stay')
                                </th>
                                <th>
                                    @lang('hms::lang.customer')
                                </th>
                                <th>
                                    @lang('hms::lang.status')
                                </th>
                                <th>
                                    @lang('hms::lang.payment_status')
                                </th>
                                <th>
                                    @lang('lang_v1.payment_method')
                                </th>
                                <th>
                                    @lang('hms::lang.total_amount')
                                </th>
                                <th>
                                    @lang('hms::lang.total_paid')
                                </th>
                                <th>
                                    @lang('hms::lang.due')
                                </th>
                                <th>
                                    @lang('lang_v1.created_at')
                                </th>
                                <th>
                                    @lang('messages.action')
                                </th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
        <!-- Add HMS Extra Modal -->
        <div class="modal fade check_in_out" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
        </div>
    </section>
    <div class="modal fade payment_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    </div>
    <div class="modal fade edit_payment_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
        <!-- /.content -->
    @endsection

    @section('javascript')
        <script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>

        <script type="text/javascript">
            $(document).ready(function() {
                bookings_table = $('#bookings_table').DataTable({
                    processing: true,
                    serverSide: true,

                    ajax: {
                        url: "{{ action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'index']) }}",
                        "data": function(d) {
                            d.customer_id = $('#customer_id').val();
                            d.status = $('#status').val();
                            d.payment_status = $('#filter_payment_status').val();
                        },
                    },
                    aaSorting: [
                        [9, 'desc']
                    ],
                    columns: [{
                            data: 'ref_no',
                            name: 'ref_no'
                        },
                        {
                            data: 'stay',
                            name: 'stay',
                            orderable: false,
                            "searchable": false
                        },
                        {
                            data: 'c_name',
                            name: 'c.name',
                        },
                        {
                            data: 'status',
                            name: 'status'
                        },
                        {
                            data: 'payment_status',
                            name: 'payment_status',
                        },
                        { 
                            data: 'payment_methods', 
                            orderable: false,
                            "searchable": false
                        },
                        { data: 'final_total',
                         name: 'final_total'
                        },
                        { data: 'total_paid',
                         name: 'total_paid', 
                         "searchable": false
                        },
                        { data: 'total_remaining',
                         name: 'total_remaining'
                        },
                        {
                            data: 'created_at',
                            name: 'created_at'
                        },
                        {
                            data: 'action',
                            name: 'action',
                            sorting: false,
                        }
                    ],
                });

                $(document).on('change', '#customer_id, #status, #filter_payment_status', function() {
                    bookings_table.ajax.reload();
                });

                $(document).on('click', '.btn-modal-checkIn', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: $(this).attr('href'),
                        dataType: 'html',
                        success: function(result) {
                            $('.check_in_out')
                                .html(result)
                                .modal('show');
                        },
                    });
                });
                $(".check_in_out").on("show.bs.modal", function() {
                    var currentDate = new Date();
                    var currentDateTime = moment(currentDate);

                    $('.date_picker').datetimepicker({
                        format: moment_date_format + ' ' + moment_time_format,
                        ignoreReadonly: true,
                        defaultDate: currentDateTime
                    });
                });
                
            });
        </script>
    @endsection
