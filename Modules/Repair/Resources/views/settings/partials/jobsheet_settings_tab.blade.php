{!! Form::open(['action' => '\Modules\Repair\Http\Controllers\RepairSettingsController@updateJobsheetSettings', 'method' => 'post']) !!}
@php
$custom_labels = json_decode(session('business.custom_labels'), true);
$contact_custom_fields = !empty($jobsheet_pdf_settings['contact_custom_fields']) ? $jobsheet_pdf_settings['contact_custom_fields'] : [];
@endphp
<div class="row">
    <div class="col-sm-12">
        <h3>@lang('repair::lang.job_sheet_pdf'):</h3>
    </div>

    <div class="col-sm-12">
        <h4>@lang('lang_v1.fields_for_customer_details'):</h4>
    </div>
    <div class="col-sm-3">
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('show_customer', 1, !empty($jobsheet_pdf_settings['show_customer']), ['class' => 'input-icheck']); !!} @lang('invoice.show_customer')</label>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="form-group">
            {!! Form::label('customer_label', __('invoice.customer_label') . ':' ) !!}
            {!! Form::text('customer_label', $jobsheet_pdf_settings['customer_label'] ?? null, ['class' => 'form-control',
            'placeholder' => __('invoice.customer_label') ]); !!}
        </div>
    </div>
    <div class="col-sm-3">
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('show_client_id', 1, !empty($jobsheet_pdf_settings['show_client_id']), ['class' => 'input-icheck']); !!} @lang('lang_v1.show_client_id')</label>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="form-group">
            {!! Form::label('client_id_label', __('lang_v1.client_id_label') . ':' ) !!}
            {!! Form::text('client_id_label', $jobsheet_pdf_settings['client_id_label'] ?? null, ['class' => 'form-control',
            'placeholder' => __('lang_v1.client_id_label') ]); !!}
        </div>
    </div>
    <div class="col-sm-3">
        <div class="form-group">
            {!! Form::label('client_tax_label', __('lang_v1.client_tax_label') . ':' ) !!}
            {!! Form::text('client_tax_label', $jobsheet_pdf_settings['client_tax_label'] ?? null, ['class' => 'form-control',
            'placeholder' => __('lang_v1.client_tax_label') ]); !!}
        </div>
    </div>
    <div class="clearfix"></div>
    <div class="col-sm-3">
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('contact_custom_fields[]', 'custom_field1', in_array('custom_field1', $contact_custom_fields), ['class' => 'input-icheck']); !!} {{ $custom_labels['contact']['custom_field_1'] ?? __('lang_v1.contact_custom_field1') }}</label>
            </div>
        </div>
    </div>

    <div class="col-sm-3">
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('contact_custom_fields[]', 'custom_field2', in_array('custom_field2', $contact_custom_fields), ['class' => 'input-icheck']); !!} {{ $custom_labels['contact']['custom_field_2'] ?? __('lang_v1.contact_custom_field2') }}</label>
            </div>
        </div>
    </div>

    <div class="col-sm-3">
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('contact_custom_fields[]', 'custom_field3', in_array('custom_field3', $contact_custom_fields), ['class' => 'input-icheck']); !!} {{ $custom_labels['contact']['custom_field_3'] ?? __('lang_v1.contact_custom_field3') }}</label>
            </div>
        </div>
    </div>

    <div class="col-sm-3">
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('contact_custom_fields[]', 'custom_field4', in_array('custom_field4', $contact_custom_fields), ['class' => 'input-icheck']); !!} {{ $custom_labels['contact']['custom_field_4'] ?? __('lang_v1.contact_custom_field4') }}</label>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <hr>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <h3>@lang('repair::lang.job_sheet_label'):</h3>
    </div>

    <div class="col-sm-3">
        <div class="form-group">
            {!! Form::label('repair::lang.label_width', __('repair::lang.label_width') . '(MM):' ) !!}
            {!! Form::text('label_width', $jobsheet_pdf_settings['label_width'] ?? 75, ['class' => 'form-control',
            'placeholder' => __('repair::lang.label_width') ]); !!}
        </div>
    </div>
    <div class="col-sm-3">
        <div class="form-group">
            {!! Form::label('repair::lang.label_height', __('repair::lang.label_height') . '(MM):' ) !!}
            {!! Form::text('label_height', $jobsheet_pdf_settings['label_height'] ?? 50, ['class' => 'form-control',
            'placeholder' => __('repair::lang.label_height') ]); !!}
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-3">
        <h4>@lang('repair::lang.customer_information'):</h4>
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('show_customer_name_in_label', 1, 
                        !empty($jobsheet_pdf_settings['show_customer_name_in_label']), 
                        ['class' => 'input-icheck']); !!} @lang('sale.customer_name')</label>
            </div>
        </div>
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('show_customer_address_in_label', 1, 
                        !empty($jobsheet_pdf_settings['show_customer_address_in_label']), 
                        ['class' => 'input-icheck']); !!} @lang('repair::lang.customer_address')</label>
            </div>
        </div>
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('show_customer_phone_in_label', 1, 
                        !empty($jobsheet_pdf_settings['show_customer_phone_in_label']), 
                        ['class' => 'input-icheck']); !!} @lang('repair::lang.customer_phone')</label>
            </div>
        </div>
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('show_customer_alt_phone_in_label', 1, 
                        !empty($jobsheet_pdf_settings['show_customer_alt_phone_in_label']), 
                        ['class' => 'input-icheck']); !!} @lang('repair::lang.alt_phone')</label>
            </div>
        </div>
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('show_customer_email_in_label', 1, 
                        !empty($jobsheet_pdf_settings['show_customer_email_in_label']), 
                        ['class' => 'input-icheck']); !!} @lang('lang_v1.customer_email')</label>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <h4>@lang('repair::lang.label_details'):</h4>
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('show_sales_person_in_label', 1, 
                        !empty($jobsheet_pdf_settings['show_sales_person_in_label']), 
                        ['class' => 'input-icheck']); !!} @lang('repair::lang.sales_person')</label>
            </div>
        </div>
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('show_barcode_in_label', 1, 
                        !empty($jobsheet_pdf_settings['show_barcode_in_label']), 
                        ['class' => 'input-icheck']); !!} @lang('repair::lang.barcode')</label>
            </div>
        </div>
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('show_status_in_label', 1, 
                        !empty($jobsheet_pdf_settings['show_status_in_label']), 
                        ['class' => 'input-icheck']); !!} @lang('sale.status')</label>
            </div>
        </div>
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('show_due_date_in_label', 1, 
                        !empty($jobsheet_pdf_settings['show_due_date_in_label']), 
                        ['class' => 'input-icheck']); !!} @lang('lang_v1.due_date')</label>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <h4>@lang('repair::lang.label_information'):</h4>
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('show_technician_in_label', 1, 
                        !empty($jobsheet_pdf_settings['show_technician_in_label']), 
                        ['class' => 'input-icheck']); !!} @lang('repair::lang.technician')</label>
            </div>
        </div>
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('show_problem_in_label', 1, 
                        !empty($jobsheet_pdf_settings['show_problem_in_label']), 
                        ['class' => 'input-icheck']); !!} @lang('repair::lang.problem')</label>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <h4>@lang('repair::lang.device_info'):</h4>
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('show_sr_no_in_label', 1, 
                        !empty($jobsheet_pdf_settings['show_sr_no_in_label']), 
                        ['class' => 'input-icheck']); !!} @lang('repair::lang.imei_sr_no')</label>
            </div>
        </div>
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('show_brand_in_label', 1, 
                        !empty($jobsheet_pdf_settings['show_brand_in_label']), 
                        ['class' => 'input-icheck']); !!} @lang('repair::lang.brand_model')</label>
            </div>
        </div>
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('show_location_in_label', 1, 
                        !empty($jobsheet_pdf_settings['show_location_in_label']), 
                        ['class' => 'input-icheck']); !!} @lang('sale.location')</label>
            </div>
        </div>
        <div class="form-group">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('show_password_in_label', 1, 
                        !empty($jobsheet_pdf_settings['show_password_in_label']), 
                        ['class' => 'input-icheck']); !!} @lang('lang_v1.password')</label>
            </div>
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