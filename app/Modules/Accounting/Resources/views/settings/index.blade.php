@extends('layouts.app')

@section('title', __('messages.settings'))

@section('content')

@include('accounting::layouts.nav')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang( 'messages.settings' )</h1>
</section>
<section class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#account_setting" data-toggle="tab" aria-expanded="true">
                            @lang('accounting::lang.account_setting')
                        </a>
                    </li>
                    <li>
                        <a href="#sub_type_tab" data-toggle="tab" aria-expanded="true">
                            @lang('accounting::lang.account_sub_type')
                        </a>
                    </li>
                    <li>
                        <a href="#detail_type_tab" data-toggle="tab" aria-expanded="true">
                            @lang('accounting::lang.detail_type')
                        </a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active" id="account_setting"> 
                        <div class="row mb-12">
                            <div class="col-md-4">
                                <button class="btn btn-danger accounting_reset_data" 
                                    data-href="{{action('\Modules\Accounting\Http\Controllers\SettingsController@resetData')}}">
                                    @lang('accounting::lang.reset_data')
                                </button>
                            </div>
                        </div>
                        <br>
                        {!! Form::open(['action' => '\Modules\Accounting\Http\Controllers\SettingsController@saveSettings',
                            'method' => 'post']) !!}
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        {!! Form::label('journal_entry_prefix', __('accounting::lang.journal_entry_prefix') . ':') !!}
                                        {!! Form::text('journal_entry_prefix',!empty($accounting_settings['journal_entry_prefix'])? 
                                            $accounting_settings['journal_entry_prefix'] : '', 
                                            ['class' => 'form-control ', 'id' => 'journal_entry_prefix']); !!}
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        {!! Form::label('transfer_prefix', __('accounting::lang.transfer_prefix') . ':') !!}
                                        {!! Form::text('transfer_prefix',!empty($accounting_settings['transfer_prefix'])? 
                                            $accounting_settings['transfer_prefix'] : '', 
                                            ['class' => 'form-control ', 'id' => 'transfer_prefix']); !!}
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group pull-right">
                                    {{Form::submit('update', ['class'=>"btn btn-danger"])}}
                                    </div>
                                </div>
                            </div>
                        {!! Form::close() !!}
                    </div>
                    
                    <div class="tab-pane" id="sub_type_tab"> 
                        <div class="row">
                            <div class="col-md-12">
                                <button class="btn btn-primary pull-right" id="add_account_sub_type">
                                    <i class="fas fa-plus"></i> @lang('messages.add')
                                </button> 
                            </div>
                            <div class="col-md-12">
                                <br>
                                <table class="table table-bordered table-striped" id="account_sub_type_table">
                                    <thead>
                                        <tr>
                                            <th>
                                               @lang('accounting::lang.account_sub_type')
                                            </th>
                                            <th>
                                               @lang('accounting::lang.account_type')
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
                    <div class="tab-pane" id="detail_type_tab">
                        <div class="row">
                            <div class="col-md-12">
                                <button class="btn btn-primary pull-right" id="add_detail_type">
                                    <i class="fas fa-plus"></i> @lang('messages.add')
                                </button> 
                            </div>
                            <div class="col-md-12">
                                <br>
                                <table class="table table-striped" id="detail_type_table" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>
                                               @lang('accounting::lang.detail_type')
                                            </th>
                                            <th>
                                               @lang('accounting::lang.parent_type')
                                            </th>
                                            <th>
                                               @lang('lang_v1.description')
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
                </div>
            </div>
        </div>
    </div>
</section>
@include('accounting::account_type.create')
<div class="modal fade" id="edit_account_type_modal" tabindex="-1" role="dialog">
</div>
@stop
@section('javascript')
<script type="text/javascript">
	$(document).ready( function(){
        account_sub_type_table = $('#account_sub_type_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{action('\Modules\Accounting\Http\Controllers\AccountTypeController@index')}}?account_type=sub_type",
            columnDefs: [
                {
                    targets: [2],
                    orderable: false,
                    searchable: false,
                },
            ],
            columns: [
               { data: 'name', name: 'name' },
               { data: 'account_primary_type', name: 'account_primary_type' },
               { data: 'action', name: 'action' },
            ],
        });

        detail_type_table = $('#detail_type_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{action('\Modules\Accounting\Http\Controllers\AccountTypeController@index')}}?account_type=detail_type",
            columnDefs: [
                {
                    targets: 3,
                    orderable: false,
                    searchable: false,
                },
            ],
            columns: [
               { data: 'name', name: 'name' },
               { data: 'parent_type', name: 'parent_type' },
               { data: 'description', name: 'description' },
               { data: 'action', name: 'action' },
            ],
        });

        $('#add_account_sub_type').click( function(){
            $('#account_type').val('sub_type')
            $('#account_type_title').text("{{__('accounting::lang.add_account_sub_type')}}");
            $('#description_div').addClass('hide');
            $('#parent_id_div').addClass('hide');
            $('#account_type_div').removeClass('hide');
            $('#create_account_type_modal').modal('show');
        });

        $('#add_detail_type').click( function(){
            $('#account_type').val('detail_type')
            $('#account_type_title').text("{{__('accounting::lang.add_detail_type')}}");
            $('#description_div').removeClass('hide');
            $('#parent_id_div').removeClass('hide');
            $('#account_type_div').addClass('hide');
            $('#create_account_type_modal').modal('show');
        })
	});
    $(document).on('hidden.bs.modal', '#create_account_type_modal', function (e) {
       $('#create_account_type_form')[0].reset();
    })
    $(document).on('submit', 'form#create_account_type_form', function(e) {
        e.preventDefault();
        var form = $(this);
        var data = form.serialize();

        $.ajax({
            method: 'POST',
            url: $(this).attr('action'),
            dataType: 'json',
            data: data,
            success: function(result) {
                if (result.success == true) {
                    $('#create_account_type_modal').modal('hide');
                    toastr.success(result.msg);
                    if(result.data.account_type=='sub_type') {
                        account_sub_type_table.ajax.reload();
                    } else {
                        detail_type_table.ajax.reload();
                    }
                    $('#create_account_type_form').find('button[type="submit"]').attr('disabled', false);
                } else {
                    toastr.error(result.msg);
                }
            },
        });
    });

    $(document).on('submit', 'form#edit_account_type_form', function(e) {
        e.preventDefault();
        var form = $(this);
        var data = form.serialize();

        $.ajax({
            method: 'PUT',
            url: $(this).attr('action'),
            dataType: 'json',
            data: data,
            success: function(result) {
                if (result.success == true) {
                    $('#edit_account_type_modal').modal('hide');
                    toastr.success(result.msg);
                    if(result.data.account_type=='sub_type') {
                        account_sub_type_table.ajax.reload();
                    } else {
                        detail_type_table.ajax.reload();
                    }
                    
                } else {
                    toastr.error(result.msg);
                }
            },
        });
    });

    $(document).on('click', 'button.delete_account_type_button', function() {
        swal({
            title: LANG.sure,
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(willDelete => {
            if (willDelete) {
                var href = $(this).data('href');
                var data = $(this).serialize();

                $.ajax({
                    method: 'DELETE',
                    url: href,
                    dataType: 'json',
                    data: data,
                    success: function(result) {
                        if (result.success == true) {
                            toastr.success(result.msg);
                            account_sub_type_table.ajax.reload();
                            detail_type_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    },
                });
            }
        });
    });

    $(document).on('click', 'button.accounting_reset_data', function() {
        swal({
            title: LANG.sure,
            icon: 'warning',
            text: "@lang('accounting::lang.reset_help_txt')",
            buttons: true,
            dangerMode: true,
        }).then(willDelete => {
            if (willDelete) {
                var href = $(this).data('href');
                window.location.href = href;
            }
        });
    });
</script>
@endsection