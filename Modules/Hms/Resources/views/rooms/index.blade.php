@extends('layouts.app')
@section('title', __('hms::lang.rooms'))
@section('content')
@include('hms::layouts.nav')
    <section class="content-header">
        <h1> @lang('hms::lang.rooms')
        </h1>
        <p><i class="fa fa-info-circle"></i> @lang('hms::lang.rooms_help_text') </p>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="box box-solid">
            <div class="box-header">
                <h3 class="box-title">&nbsp;</h3>
                <div class="box-tools">
                    <a href="{{ action([\Modules\Hms\Http\Controllers\RoomController::class, 'create']) }}"
                        class="btn btn-block btn-primary">
                        <i class="fa fa-plus"></i> @lang('messages.add')</a>
                </div>
            </div>
            <div class="box-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="rooms_table">
                            <thead>
                                <tr>
                                    <th>
                                        @lang('hms::lang.type')
                                    </th>
                                    <th>
                                        @lang('hms::lang.max_no_of_adult')
                                    </th>
                                    <th>
                                        @lang('hms::lang.max_no_of_child')
                                    </th>
                                    <th>
                                        @lang('hms::lang.max_occupancy')
                                    </th>
                                    <th>
                                        @lang('hms::lang.description')
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

    </section>
    <!-- /.content -->

@endsection

@section('javascript')

    <script type="text/javascript">
        $(document).ready(function() {
            superadmin_business_table = $('#rooms_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ action([\Modules\Hms\Http\Controllers\RoomController::class, 'index']) }}",
                },
                aaSorting: [
                    [5, 'desc']
                ],
                columns: [
                    {
                        data: 'type',
                        name: 'hms_room_types.type'
                    },
                    {
                        data: 'no_of_adult',
                        name: 'hms_room_types.no_of_adult'
                    },
                    {
                        data: 'no_of_child',
                        name: 'hms_room_types.no_of_child'
                    },
                    {
                        data: 'max_occupancy',
                        name: 'hms_room_types.max_occupancy'
                    },
                    {
                        data: 'description',
                        name: 'hms_room_types.description'
                    },
                    {
                        data: 'created_at',
                        name: 'hms_room_types.created_at'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        sorting:false,
                    },
                ]
            });

            $(document).on('click', 'a.delete_room_confirmation', function(e) {
                e.preventDefault();
                swal({
                    title: LANG.sure,
                    text: "Once deleted, you will not be able to recover this Room !",
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


