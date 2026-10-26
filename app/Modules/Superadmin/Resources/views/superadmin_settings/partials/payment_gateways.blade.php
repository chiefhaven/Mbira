<div class="pos-tab-content">
    <div class="row">
        <div class="col-xs-6">
            <div class="form-group">
                <label>
                {!! Form::checkbox('enable_offline_payment', 1,!empty($settings["enable_offline_payment"]), 
                [ 'class' => 'input-icheck']); !!}
                @lang('superadmin::lang.enable_offline_payment')
                </label>
            </div>
        </div>
        <div class="col-xs-6">
            <div class="form-group">
                {!! Form::label('offline_payment_details', __('superadmin::lang.offline_payment_details') . ':') !!}
                @show_tooltip(__('superadmin::lang.offline_payment_details_tooltip'))
                {!! Form::textarea('offline_payment_details', !empty($settings["offline_payment_details"]) ? $settings["offline_payment_details"] : null, ['class' => 'form-control','placeholder' => __('superadmin::lang.offline_payment_details'), 'rows' => 3]); !!}
            </div>
        </div>
    </div>
    <div class="row">
    	<h4>Stripe:</h4>
    	<div class="col-xs-4">
            <div class="form-group">
            	{!! Form::label('STRIPE_PUB_KEY', __('superadmin::lang.stripe_pub_key') . ':') !!}
            	{!! Form::text('STRIPE_PUB_KEY', $default_values['STRIPE_PUB_KEY'], ['class' => 'form-control','placeholder' => __('superadmin::lang.stripe_pub_key')]); !!}
            </div>
        </div>
        <div class="col-xs-4">
            <div class="form-group">
            	{!! Form::label('STRIPE_SECRET_KEY', __('superadmin::lang.stripe_secret_key') . ':') !!}
            	{!! Form::text('STRIPE_SECRET_KEY', $default_values['STRIPE_SECRET_KEY'], ['class' => 'form-control','placeholder' => __('superadmin::lang.stripe_secret_key')]); !!}
            </div>
        </div>

        <div class="clearfix"></div>
        
        <h4>Paypal:</h4>
        <div class="col-xs-6">
            <div class="form-group">
            	{!! Form::label('PAYPAL_MODE', __('superadmin::lang.paypal_mode') . ':') !!}
            	{!! Form::select('PAYPAL_MODE',['live' => 'Live', 'sandbox' => 'Sandbox'],  $default_values['PAYPAL_MODE'], ['class' => 'form-control','placeholder' => __('messages.please_select')]); !!}
            </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-xs-4">
            <div class="form-group">
            	{!! Form::label('PAYPAL_SANDBOX_API_USERNAME', __('superadmin::lang.paypal_sandbox_api_username') . ':') !!}
            	{!! Form::text('PAYPAL_SANDBOX_API_USERNAME', $default_values['PAYPAL_SANDBOX_API_USERNAME'], ['class' => 'form-control','placeholder' => __('superadmin::lang.paypal_sandbox_api_username')]); !!}
            </div>
        </div>
        <div class="col-xs-4">
            <div class="form-group">
            	{!! Form::label('PAYPAL_SANDBOX_API_PASSWORD', __('superadmin::lang.paypal_sandbox_api_password') . ':') !!}
            	{!! Form::text('PAYPAL_SANDBOX_API_PASSWORD', $default_values['PAYPAL_SANDBOX_API_PASSWORD'], ['class' => 'form-control','placeholder' => __('superadmin::lang.paypal_sandbox_api_password')]); !!}
            </div>
        </div>
        <div class="col-xs-4">
            <div class="form-group">
            	{!! Form::label('PAYPAL_SANDBOX_API_SECRET', __('superadmin::lang.paypal_sandbox_api_secret') . ':') !!}
            	{!! Form::text('PAYPAL_SANDBOX_API_SECRET', $default_values['PAYPAL_SANDBOX_API_SECRET'], ['class' => 'form-control','placeholder' => __('superadmin::lang.paypal_sandbox_api_secret')]); !!}
            </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-xs-4">
            <div class="form-group">
            	{!! Form::label('PAYPAL_LIVE_API_USERNAME', __('superadmin::lang.paypal_live_api_username') . ':') !!}
            	{!! Form::text('PAYPAL_LIVE_API_USERNAME', $default_values['PAYPAL_LIVE_API_USERNAME'], ['class' => 'form-control','placeholder' => __('superadmin::lang.paypal_live_api_username')]); !!}
            </div>
        </div>
        <div class="col-xs-4">
            <div class="form-group">
            	{!! Form::label('PAYPAL_LIVE_API_PASSWORD', __('superadmin::lang.paypal_live_api_password') . ':') !!}
            	{!! Form::text('PAYPAL_LIVE_API_PASSWORD', $default_values['PAYPAL_LIVE_API_PASSWORD'], ['class' => 'form-control','placeholder' => __('superadmin::lang.paypal_live_api_password')]); !!}
            </div>
        </div>
        <div class="col-xs-4">
            <div class="form-group">
            	{!! Form::label('PAYPAL_LIVE_API_SECRET', __('superadmin::lang.paypal_live_api_secret') . ':') !!}
            	{!! Form::text('PAYPAL_LIVE_API_SECRET', $default_values['PAYPAL_LIVE_API_SECRET'], ['class' => 'form-control','placeholder' => __('superadmin::lang.paypal_live_api_secret')]); !!}
            </div>
        </div>

        
        <div class="clearfix"></div>
        
        <h4>ONEKHUSA:</h4>
        <div class="col-xs-4">
            <div class="form-group">
                {!! Form::label('ONEKHUSA_BASEURL', 'Public key:') !!}
                {!! Form::text('ONEKHUSA_BASEURL', $default_values['ONEKHUSA_BASEURL'], ['class' => 'form-control']); !!}
            </div>
        </div>
        <div class="col-xs-4">
            <div class="form-group">
                {!! Form::label('ONEKHUSA_MERCHANT_ACCOUNT_NUMBER', 'Merchant Account Number:') !!}
                {!! Form::text('ONEKHUSA_MERCHANT_ACCOUNT_NUMBER', $default_values['ONEKHUSA_MERCHANT_ACCOUNT_NUMBER'], ['class' => 'form-control']); !!}
            </div>
        </div>
        <div class="col-xs-4">
            <div class="form-group">
                {!! Form::label('ONEKHUSA_API_SECRET', 'API Secret:') !!}
                {!! Form::text('ONEKHUSA_API_SECRET', $default_values['ONEKHUSA_API_SECRET'], ['class' => 'form-control']); !!}
            </div>
        </div>
        <div class="col-xs-4">
            <div class="form-group">
                {!! Form::label('ONEKHUSA_API_KEY', 'API Key:') !!}
                {!! Form::text('ONEKHUSA_API_KEY', $default_values['ONEKHUSA_API_KEY'], ['class' => 'form-control']); !!}
            </div>
        </div>
        <div class="col-xs-4">
            <div class="form-group">
                {!! Form::label('ONEKHUSA_ORGANISATION_ID', 'Organisation ID:') !!}
                {!! Form::text('ONEKHUSA_ORGANISATION_ID', $default_values['ONEKHUSA_ORGANISATION_ID'], ['class' => 'form-control']); !!}
            </div>
        </div>
        <div class="col-xs-12">
            <br/>
            <p class="help-block"><i>@lang('superadmin::lang.payment_gateway_help')</i></p>
        </div>
    </div>
</div>