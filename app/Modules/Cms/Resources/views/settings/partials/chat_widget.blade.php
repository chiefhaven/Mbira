<div class="pos-tab-content">
    <div class="row">
        <div class="col-xs-12">
            <div class="form-group">
                {!! Form::label('chat_widget_option', __('cms::lang.choose_chat_widget') . ':') !!}
                {!! Form::select('chat[enable]', ['in_app_chat' => __('cms::lang.in_app_chat'), 'other' => __('cms::lang.other_chat')], $details['chat']['enable'] ?? 'in_app', ['class' => 'form-control', 'id' => 'chat_widget_option']); !!}
            </div>
        </div>
        <div class="col-xs-12 in_app_chat_div">
            <h4>
                {{__('cms::lang.contact_us')}}
            </h4>
        </div>
        @for($i = 1; $i <=2 ; $i++)
            <div class="col-xs-6 in_app_chat_div mb-5">
                <div class="form-group mb-0">
                    {!! Form::label("mobile_number_{$i}", __('cms::lang.mobile_number', ['count' => $i]) . ':' )!!}
                    {!! Form::text("chat[mobile][{$i}]", (isset($details['chat']) && isset($details['chat']['mobile']) && isset($details['chat']['mobile'][$i])) ? $details['chat']['mobile'][$i] : '', ['class' => 'form-control input_number', 'id' => "mobile_number_{$i}", "minlength" => "10", "maxlength" => "12"]) !!}
                </div>
                <small class="help-text">
                    @lang('cms::lang.contact_no_help_text')
                </small>
            </div>
        @endfor
        <div class="col-xs-12 in_app_chat_div">
            <h4>
                {{__('cms::lang.mail_us')}}
            </h4>
        </div>
        @for($i = 1; $i <=2 ; $i++)
            <div class="col-xs-6 in_app_chat_div mb-5">
                <div class="form-group mb-0">
                    {!! Form::label("email_count_{$i}", __('cms::lang.email_count', ['count' => $i]) . ':' )!!}
                    {!! Form::email("chat[mail][{$i}]", (isset($details['chat']) && isset($details['chat']['mail']) && isset($details['chat']['mail'][$i])) ? $details['chat']['mail'][$i] : '', ['class' => 'form-control', 'id' => "email_count_{$i}"]) !!}
                </div>
            </div>
        @endfor
        <div class="col-xs-12 in_app_chat_div">
            <h4>
                {{__('cms::lang.chat_with_us')}}
            </h4>
        </div>
        @for($i = 1; $i <=2 ; $i++)
            <div class="col-xs-6 in_app_chat_div mb-5">
                <div class="form-group mb-0">
                    {!! Form::label("whatsapp_number_{$i}", __('cms::lang.whatsapp_number', ['count' => $i]) . ':' )!!}
                    {!! Form::text("chat[whatsapp][{$i}]", (isset($details['chat']) && isset($details['chat']['whatsapp']) && isset($details['chat']['whatsapp'][$i])) ? $details['chat']['whatsapp'][$i] : '', ['class' => 'form-control input_number', 'id' => "whatsapp_number_{$i}", "minlength" => "10", "maxlength" => "12"]) !!}
                </div>
                <small class="help-text">
                    @lang('cms::lang.contact_no_help_text')
                </small>
            </div>
        @endfor
        <div class="col-xs-6 in_app_chat_div mb-5">
            <div class="form-group">
                    {!! Form::label('label', __('cms::lang.fb_messanger_link') . ':' )!!}
                    {!! Form::text('chat[messanger_link]', !empty($details['chat']['messanger_link']) ? $details['chat']['messanger_link'] : '', ['class' => 'form-control', 'id' => 'label', 'placeholder' => 'http://m.me/yourusername']) !!}
            </div>
        </div>
        <div class="col-xs-6 in_app_chat_div mb-5">
            <div class="form-group">
                    {!! Form::label('label', __('cms::lang.telegram') . ':' )!!}
                    {!! Form::text('chat[telegram]', !empty($details['chat']['telegram']) ? $details['chat']['telegram'] : '', ['class' => 'form-control', 'id' => 'label', 'placeholder' => 'https://t.me/yourusername']) !!}
            </div>
        </div>
        <div class="col-xs-12 third_party_chat_div mb-5">
            <div class="form-group mb-0">
                {!! Form::label('chat_widget', __('cms::lang.chat_widget') . ':') !!}
                {!! Form::textarea('chat_widget', !empty($details['chat_widget']) ? $details['chat_widget'] : '', ['class' => 'form-control','placeholder' => __('cms::lang.chat_widget')]); !!}
            </div>
            <small class="help-text">
                @lang('cms::lang.chat_widget_instructions')
            </small>
        </div>
    </div>
</div>