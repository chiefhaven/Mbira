@extends('layouts.app')

@section('title', __('crm::lang.contacts_login'))

@section('content')
@include('crm::layouts.nav')
<!-- Content Header (Page header) -->
<section class="content-header no-print">
   <h1>@lang('crm::lang.commissions')</h1>
</section>
<section class="content no-print">
	@component('components.filters', ['title' => __('report.filters')])
        <div class="row">
        	<div class="col-md-4">
                <div class="form-group">
                    {!! Form::label('contact_id_filter', __('contact.contact') . ':') !!}
                    {!! Form::select('contact_id_filter', $contacts, null, ['class' => 'form-control select2', 'id' => 'contact_id_filter', 'placeholder' => __('messages.all')]); !!}
                </div>    
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label('crm_contact_id', __('crm::lang.contact_person') . ':') !!}
                    {!! Form::select('crm_contact_id', $crm_contact_persons, null, ['class' => 'form-control select2', 'id' => 'crm_contact_id', 'placeholder' => __('messages.all')]); !!}
                </div>    
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label('location_id', __('sale.location') . ':') !!}
                    {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control select2', 'id' => 'location_id', 'placeholder' => __('messages.all')]); !!}
                </div>    
            </div>
            <div class="col-md-4">
            	<div class="form-group">
            		{!! Form::label('commission_date_range', __('report.date_range') . ':') !!}
            		{!! Form::text('commission_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']); !!}
            	</div>
            </div>
        </div>
    @endcomponent

	@component('components.widget', ['class' => 'box-primary'])
		<div class="table-responsive">
			<table class="table table-bordered table-striped" id="commission_table" style="width: 100%;">
				<thead>
					<tr>
						<th>@lang('lang_v1.date')</th>
						<th>@lang('contact.contact')</th>
						<th>@lang('lang_v1.name')</th>
						<th>@lang( 'lang_v1.mobile_number' )</th>
						<th>@lang('sale.invoice_no')</th>
						<th>@lang('sale.location')</th>
						<th>@lang('crm::lang.total_commission')</th>
					</tr>
				</thead>
				<tbody></tbody>
		        <tfoot>
                    <tr class="bg-gray font-17 footer-total text-center">
                        <td colspan="6">
                            <strong>@lang('sale.total'):</strong>
                        </td>
                        <td class="footer_total_commission"></td>
                    </tr>
                </tfoot>
			</table>
		</div>
	@endcomponent
	<div class="modal fade contact_login_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
</section>
@endsection
@section('javascript')
<script type="text/javascript">
	$(document).ready( function(){
		commission_table = $("#commission_table").DataTable({
            processing: true,
            serverSide: true,
            'ajax': {
                url: "/crm/commissions",
                data: function (d) {
                    if ($("#crm_contact_id").length > 0) {
                    	d.crm_contact_id = $("#crm_contact_id").val();
                    }

                    if ($("#contact_id_filter").length > 0) {
                    	d.contact_id = $("#contact_id_filter").val();
                    }

                    if ($("#location_id").length > 0) {
                    	d.location_id = $("#location_id").val();
                    }

                    if ($('#commission_date_range').val()) {
	            		d.start_date_time = $('#commission_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
	            		d.end_date_time = $('#commission_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
	            	}
                }
            },

            columns: [
	        	{ data: 'transaction_date', name: 't.transaction_date' },
	        	{ data: 'contact', name: 'contact' },
	        	{ data: 'full_name', name: 'full_name' },
	        	{ data: 'contact_no', name: 'u.contact_no' },
	        	{ data: 'invoice_no', name: 't.invoice_no' },
	        	{ data: 'location', name: 'bl.name' },
	            { data: 'commission_amount', name: 'commission_amount' }
	        ],
	        "footerCallback": function ( row, data, start, end, display ) {
	        	var total_commission = 0
	        	for (var r in data){
	                total_commission += parseFloat(data[r].commission_amount_uf);
	            }

	            $('.footer_total_commission').html(__currency_trans_from_en(total_commission));
	        }
		});

		$('#commission_date_range').daterangepicker(
	        dateRangeSettings,
	        function (start, end) {
	            $('#commission_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
	            commission_table.ajax.reload();
	        }
	    );
	    $('#commission_date_range').on('cancel.daterangepicker', function(ev, picker) {
	        $('#commission_date_range').val('');
	        commission_table.ajax.reload();
	    });

		$(document).on('change', '#crm_contact_id, #location_id, #contact_id_filter', function() {
			commission_table.ajax.reload();
		});
	})
</script>
@endsection