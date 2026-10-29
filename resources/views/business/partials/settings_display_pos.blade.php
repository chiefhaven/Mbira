<div class="pos-tab-content">
    <div class="row">
        <div class="col-sm-12">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[customer_display_screen]', 1,  
                        !empty($pos_settings['customer_display_screen']) , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.enable_customer_display_screen' ) }}
                  </label>
                  <p class="help-block"><i> @lang('lang_v1.customer_display_instraction')</i></p>
                </div>
            </div>
        </div>
        <div class="col-sm-12">
            <div class="form-group">
                {!! Form::label('display_screen_heading', __('lang_v1.display_screen_heading') . ':') !!}
                 {!! Form::textarea('pos_settings[display_screen_heading]', isset($pos_settings['display_screen_heading']) ? $pos_settings['display_screen_heading'] : null, ['class' => 'form-control', 'id' => 'display_screen_heading']); !!}
            </div>
        </div>
        @for ($i = 1; $i <= 10; $i++)
            <div class="col-sm-4">
                <div class="form-group">
                    {!! Form::label("carousel_image_$i", __('lang_v1.carousel_image', ['number' => $i]) . ':') !!}
                    {!! Form::file("carousel_image_$i", ['accept' => 'image/*', 'class' => 'carousel_image']) !!}
                    <p class="help-block"><i> @lang('lang_v1.image_help')</i></p>
                </div>
            </div>
        @endfor
    </div>
</div>