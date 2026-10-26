<div class="pos-tab-content active">
	<div class="row">
		<div class="col-md-8">
            <div class="form-group">
                {!! Form::label('logo', __('cms::lang.logo') . ':' )!!}
                <div class="input-group">
                    {!! Form::file('logo', ['id' => 'logo', 'accept' => 'image/*']); !!}
                </div>
                <p class="help-block text-muted">
                    @lang('cms::lang.previously_uploaded_will_be_removed')
                    <br>
                    @lang('cms::lang.logo_dimension_help_txt')
                </p>
            </div>
        </div>
        <div class="col-md-4">
            @if(!empty($logo) && !empty($logo->logo_url))
                <img src="{{$logo->logo_url}}" alt="Logo" class="app-logo">
            @endif
        </div>
	</div>
    <h4>
        @lang('cms::lang.notification')
    </h4>
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('notifiable_email', __('cms::lang.email') . ':' )!!}
                @show_tooltip(__('cms::lang.inquiry_notification_help_text'))
                {!! Form::text('notifiable_email', !empty($details['notifiable_email']) ? $details['notifiable_email'] : '', ['class' => 'form-control', 'id' => 'notifiable_email']) !!}
           </div>
           <p>
           		<small>
           			{{__('cms::lang.inquiry_notification_help_text')}}
           		</small>
           </p>
        </div>
    </div>
</div>