@extends('layouts.app')
@section('title', 'Superadmin Subscription')

@section('content')
@include('superadmin::layouts.nav')
<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang( 'superadmin::lang.subscription' )
        <small>@lang( 'superadmin::lang.view_subscription' )</small>
    </h1>
</section>

<!-- Main content -->
<section class="content">

    @include('superadmin::layouts.partials.currency')
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('package_id',  __('superadmin::lang.packages') . ':') !!}
                {!! Form::select('package_id', $packages, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('subscription_status',  __('superadmin::lang.status') . ':') !!}
                {!! Form::select('subscription_status', $subscription_statuses, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('created_at', __('lang_v1.created_at') . ':') !!}
                {!! Form::text('created_at', null, ['placeholder' => __('lang_v1.select_a_date_range'), 
                    'class' => 'form-control', 'readonly']); !!}
            </div>
        </div>
    @endcomponent
    <div class="box box-solid">  
        <div class="box-body">
            @can('superadmin')
                <div class="table-responsive">
            	<table class="table table-bordered table-striped" id="superadmin_subscription_table">
            		<thead>
            			<tr>
                            <th>@lang( 'superadmin::lang.business_name' )</th>
            				<th>@lang( 'superadmin::lang.package_name' )</th>
                            <th>@lang( 'superadmin::lang.status' )</th>
                            <th>@lang( 'lang_v1.created_at' )</th>
                            <th>@lang( 'superadmin::lang.start_date' )</th>
            				<th>@lang( 'superadmin::lang.trial_end_date' )</th>
                            <th>@lang( 'superadmin::lang.end_date' )</th>
            				<th>@lang( 'superadmin::lang.price' )</th>
                            <th>@lang( 'superadmin::lang.paid_via' )</th>
            				<th>@lang( 'superadmin::lang.payment_transaction_id' )</th>
                            <th>@lang( 'superadmin::lang.action' )</th>
            			</tr>
            		</thead>
            	</table>
                </div>
            @endcan
        </div>

    </div>
    <div class="modal fade" id="statusModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"></div>

</section>
<!-- /.content -->

@endsection

@section('javascript')
<script>
    $(document).ready(function(){

        $('#created_at').daterangepicker(
        dateRangeSettings,
            function (start, end) {
                $('#created_at').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
                superadmin_subscription_table.ajax.reload();
            }
        );
        $('#created_at').on('cancel.daterangepicker', function(ev, picker) {
            $('#created_at').val('');
            superadmin_subscription_table.ajax.reload();
        });

        $('#package_id, #subscription_status').change( function(){
            superadmin_subscription_table.ajax.reload();
        });

        // superadmin_subscription_table
        var superadmin_subscription_table = $('#superadmin_subscription_table').DataTable({
                        processing: true,
                        serverSide: true,
                        ajax: {
                            url: '/superadmin/superadmin-subscription',
                            data: function(d) {
                                if ($('#package_id').length) {
                                    d.package_id = $('#package_id').val();
                                }
                                if ($('#subscription_status').length) {
                                    d.status = $('#subscription_status').val();
                                }

                                var start = '';
                                var end = '';
                                if ($('#created_at').val()) {
                                    start = $('input#created_at')
                                        .data('daterangepicker')
                                        .startDate.format('YYYY-MM-DD');
                                    end = $('input#created_at')
                                        .data('daterangepicker')
                                        .endDate.format('YYYY-MM-DD');
                                }
                                d.start_date = start;
                                d.end_date = end;

                                d = __datatable_ajax_callback(d);
                            },
                        },
                        columnDefs:[{
                                "targets": 9,
                                "orderable": false,
                                "searchable": false
                            }],
                        "fnDrawCallback": function (oSettings) {
                         __currency_convert_recursively($('#superadmin_subscription_table'), true);
                        }
                    });


        // change_status button
        $(document).on('click', 'button.change_status', function(){
            $("div#statusModal").load($(this).data('href'), function(){
                $(this).modal('show');
                $("form#status_change_form").submit(function(e){
                    e.preventDefault();
                    var url = $(this).attr("action");
                    var data = $(this).serialize();
                    $.ajax({
                        method: "POST",
                        dataType: "json",
                        data: data,
                        url: url,
                        success:function(result){
                            if( result.success == true){
                                $("div#statusModal").modal('hide');
                                toastr.success(result.msg);
                                superadmin_subscription_table.ajax.reload();
                            }else{
                                toastr.error(result.msg);
                            }
                        }
                    });
                });
            });
        });

        $(document).on('shown.bs.modal', '.view_modal', function(){
            $('.edit-subscription-modal .datepicker').datepicker({
                autoclose: true,
                format:datepicker_date_format
            });
            $("form#edit_subscription_form").submit(function(e){
              e.preventDefault();
              var url = $(this).attr("action");
              var data = $(this).serialize();
              $.ajax({
                  method: "POST",
                  dataType: "json",
                  data: data,
                  url: url,
                  success:function(result){
                      if( result.success == true){
                          $("div.view_modal").modal('hide');
                          toastr.success(result.msg);
                          superadmin_subscription_table.ajax.reload();
                      }else{
                          toastr.error(result.msg);
                      }
                  }
              });
            });
        });

    });
</script>
@endsection