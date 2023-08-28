<div class="pos-tab-content">
	<h3>
    	@lang('cms::lang.contact_nums')
    </h3>
	<div class="row">
        <div class="col-md-6">
           <div class="form-group">
                {!! Form::label('contact_us_label', __('cms::lang.label') . ':*' )!!}
                {!! Form::text('contact_us[0][label]', !empty($details['contact_us'][0]['label']) ? $details['contact_us'][0]['label'] : 'Mobile', ['class' => 'form-control', 'id' => 'contact_us_label']) !!}
           </div>
        </div>
        <div class="col-md-6">
           <div class="form-group">
                {!! Form::label('contact_us_no', __('cms::lang.contact_no') . ':*' )!!}
                {!! Form::text('contact_us[0][num]', !empty($details['contact_us'][0]['num']) ? $details['contact_us'][0]['num'] : null, ['class' => 'form-control input_number', 'id' => 'contact_us_no', 'minlength' => 10, 'maxlength' => 10]); !!}
                <p>
                	<small>
		                @lang('cms::lang.contact_no_help_text')
		            </small>
                </p>
           </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
           <div class="form-group">
                {!! Form::label('contact_us_label1', __('cms::lang.label') . ':' )!!}
                {!! Form::text('contact_us[1][label]', !empty($details['contact_us'][1]['label']) ? $details['contact_us'][1]['label'] : '', ['class' => 'form-control', 'id' => 'contact_us_label1']) !!}
           </div>
        </div>
        <div class="col-md-6">
           <div class="form-group">
                {!! Form::label('contact_us_no1', __('cms::lang.contact_no') . ':' )!!}
                {!! Form::text('contact_us[1][num]', !empty($details['contact_us'][1]['num']) ? $details['contact_us'][1]['num'] : '', ['class' => 'form-control input_number', 'id' => 'contact_us_no1', 'minlength' => 10, 'maxlength' => 10]); !!}
                <small>
	                @lang('cms::lang.contact_no_help_text')
	            </small>
           </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
           <div class="form-group">
                {!! Form::label('contact_us_label2', __('cms::lang.label') . ':' )!!}
                {!! Form::text('contact_us[2][label]', !empty($details['contact_us'][2]['label']) ? $details['contact_us'][2]['label'] : '', ['class' => 'form-control', 'id' => 'contact_us_label2']) !!}
           </div>
        </div>
        <div class="col-md-6">
           <div class="form-group">
                {!! Form::label('contact_us_no2', __('cms::lang.contact_no') . ':' )!!}
                {!! Form::text('contact_us[2][num]', !empty($details['contact_us'][2]['num']) ? $details['contact_us'][2]['num'] : '', ['class' => 'form-control input_number', 'id' => 'contact_us_no2', 'minlength' => 10, 'maxlength' => 10]); !!}
                <small>
		            @lang('cms::lang.contact_no_help_text')
		        </small>
           </div>
        </div>
    </div>
    <h3>
    	@lang('cms::lang.email_addresses')
    </h3>
    <div class="row">
        <div class="col-md-6">
           <div class="form-group">
                {!! Form::label('mail_us_label', __('cms::lang.label') . ':*' )!!}
                {!! Form::text('mail_us[0][label]', !empty($details['mail_us'][0]['label']) ? $details['mail_us'][0]['label'] : 'Support', ['class' => 'form-control', 'id' => 'mail_us_label']) !!}
           </div>
        </div>
        <div class="col-md-6">
           <div class="form-group">
                {!! Form::label('mail_us_email', __('business.email') . ':*' )!!}
                {!! Form::email('mail_us[0][email]', !empty($details['mail_us'][0]['label']) ? $details['mail_us'][0]['email'] : '', ['class' => 'form-control', 'id' => 'mail_us_email']); !!}
           </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
           <div class="form-group">
                {!! Form::label('mail_us_label1', __('cms::lang.label') . ':' )!!}
                {!! Form::text('mail_us[1][label]', !empty($details['mail_us'][1]['label']) ? $details['mail_us'][1]['label'] : '', ['class' => 'form-control', 'id' => 'mail_us_label1']) !!}
           </div>
        </div>
        <div class="col-md-6">
           <div class="form-group">
                {!! Form::label('mail_us_email1', __('business.email') . ':' )!!}
                {!! Form::email('mail_us[1][email]', !empty($details['mail_us'][1]['email']) ? $details['mail_us'][1]['email'] : '', ['class' => 'form-control', 'id' => 'mail_us_email1']); !!}
           </div>
        </div>
    </div>
</div>