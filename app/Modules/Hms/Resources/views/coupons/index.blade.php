@extends('layouts.app')
@section('title', __('hms::lang.coupons'))
@section('content')
@include('hms::layouts.nav')
    <section class="content-header">
        <h1> @lang('hms::lang.coupons')
        </h1>
        <p><i class="fa fa-info-circle"></i> @lang('hms::lang.coupon_help_text') </p>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="box box-solid">
            <div class="box-header">
                <h3 class="box-title">&nbsp;</h3>
                <div class="box-tools">
                    <a href="{{ action([\Modules\Hms\Http\Controllers\HmsCouponController::class, 'create']) }}"
                        class="btn btn-block btn-primary btn-modal-extra" data-container=".view_modal">
                        <i class="fa fa-plus"></i> @lang('messages.add')</a>
                </div>
            </div>
            <div class="box-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="extras_table">
                            <thead>
                                <tr>
                                    <th>
                                        @lang('hms::lang.type')
                                    </th>
                                    <th>
                                        @lang('hms::lang.coupon_code')
                                    </th>
                                    <th>
                                        @lang('hms::lang.date_from')
                                    </th>
                                    <th>
                                        @lang('hms::lang.date_to')
                                    </th>
                                    <th>
                                        @lang('hms::lang.discount')
                                    </th>
                                    <th>
                                        @lang('hms::lang.discount_type')
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
        <div class="modal fade view_modal_coupon" tabindex="-1" role="dialog" 
        aria-labelledby="gridSystemModalLabel"></div>
        </div>

    </section>
    <!-- /.content -->

@endsection

@section('javascript')

    <script type="text/javascript">
        $(document).ready(function() {
            superadmin_business_table = $('#extras_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ action([\Modules\Hms\Http\Controllers\HmsCouponController::class, 'index']) }}",
                },
                aaSorting: [
                    [6, 'desc']
                ],
                columns: [
                    {
                        data: 'type',
                        name: 'type.type'
                    },
                    {
                        data: 'coupon_code',
                        name: 'coupon_code'
                    },
                    {
                        data: 'start_date',
                        name: 'start_date'
                    },
                    {
                        data: 'end_date',
                        name: 'end_date'
                    },
                    {
                        data: 'discount',
                        name: 'discount'
                    },
                    {
                        data: 'discount_type',
                        name: 'discount_type'
                    },
                    {
                        data: 'created_at',
                        name: 'created_at'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        sorting:false,
                    }
                ],
            });

            $(document).on('click', '.btn-modal-extra', function(e) {
                e.preventDefault();
                $.ajax({
                    url: $(this).attr('href'),
                    dataType: 'html',
                    success: function(result) {
                        $('.view_modal_coupon')
                            .html(result)
                            .modal('show');
                    },
                });
            });

            $(".view_modal_coupon").on("show.bs.modal", function() {
            var currentDate = new Date();
            var currentDateTime = moment(currentDate);

                $('.date_picker').datetimepicker({
                    format: moment_date_format,
                    ignoreReadonly: true,
                    defaultDate: currentDateTime
                });
            });

            $(document).on('click', 'a.delete_coupon_confirmation', function(e) {
                e.preventDefault();
                swal({
                    title: LANG.sure,
                    text: "Once deleted, you will not be able to recover this Coupon !",
                    icon: "warning",
                    buttons: true,
                    dangerMode: true,
                }).then((confirmed) => {
                    if (confirmed) {
                        window.location.href = $(this).attr('href');
                    }
                });
            });
        });
    </script>
@endsection

