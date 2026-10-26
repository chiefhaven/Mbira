@extends('layouts.app')
@section('title', __('hms::lang.unavailable'))
@section('content')
@include('hms::layouts.nav')
    <section class="content-header">
        <h1> @lang('hms::lang.unavailable')
        </h1>
        <p><i class="fa fa-info-circle"></i> @lang('hms::lang.unavailable_help_text') </p>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="box box-solid">
            <div class="box-header">
                <h3 class="box-title">&nbsp;</h3>
                <div class="box-tools">
                    <a href="{{ action([\Modules\Hms\Http\Controllers\UnavailableController::class, 'create']) }}"
                        class="btn btn-block btn-primary btn-modal-unavailable" data-container=".view_modal">
                        <i class="fa fa-plus"></i> @lang('messages.add')</a>
                </div>
            </div>
            <div class="box-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="unavailable_table">
                            <thead>
                                <tr>
                                    <th>
                                        @lang('hms::lang.room_no')
                                    </th>
                                    <th>
                                        @lang('hms::lang.date_from')
                                    </th>
                                    <th>
                                        @lang('hms::lang.date_to')
                                    </th>
                                    <th>
                                        @lang('hms::lang.unavailable_type')
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
        <div class="modal fade view_modal_unavailable" tabindex="-1" role="dialog" 
        aria-labelledby="gridSystemModalLabel"></div>
        </div>

    </section>
    <!-- /.content -->

@endsection

@section('javascript')

    <script type="text/javascript">
        $(document).ready(function() {
            superadmin_business_table = $('#unavailable_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ action([\Modules\Hms\Http\Controllers\UnavailableController::class, 'index']) }}",
                },
                columns: [
                    {
                        data: 'room_number',
                        name: 'room.room_number'
                    },
                    {
                        data: 'date_from',
                        name: 'hms_room_unavailables.date_from',
                    },
                    {
                        data: 'date_to',
                        name: 'hms_room_unavailables.date_to',
                    },
                    {
                        data: 'unavailable_type',
                        name: 'hms_room_unavailables.unavailable_type',
                    },
                    {
                        data:'created_at',
                        name: 'hms_room_unavailables.created_at'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        sorting:false, 
                    }
                ],
            });

            $(document).on('click', '.btn-modal-unavailable', function(e) {
                e.preventDefault();
                $.ajax({
                    url: $(this).attr('href'),
                    dataType: 'html',
                    success: function(result) {
                        $('.view_modal_unavailable')
                            .html(result)
                            .modal('show');
                    },
                });
            });


            $(".view_modal_unavailable").on("show.bs.modal", function() {
                    $('.select2').select2();
                    $('.select2-container').css('width', '100%');
                    $('#date_from').datepicker({
                        autoclose: true,
                    });
                    $('#date_to').datepicker({
                        autoclose: true,
                    });
            });

            $(document).on('click', 'a.delete_unavailable_confirmation', function(e) {
                e.preventDefault();
                swal({
                    title: LANG.sure,
                    text: "Once deleted, you will not be able to recover this Unavailable !",
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

