<div class="pos-tab-content">
    <!-- button options -->
    @php
        $navbar_btn['text'] = 'Try For Free';
        $navbar_btn['link'] = route('business.getRegister');
        if(isset($details['btns']) && isset($details['btns']['navbar']) && !empty($details['btns']['navbar']['text'])) {
            $navbar_btn['text'] = $details['btns']['navbar']['text'] ?? 'Try For Free';
        }
        if(isset($details['btns']) && isset($details['btns']['navbar']) && !empty($details['btns']['navbar']['link'])) {
            $navbar_btn['link'] = $details['btns']['navbar']['link'] ?? route('business.getRegister');
        }

        $hero_btn['text'] = 'Start your Free Trial';
        $hero_btn['link'] = route('business.getRegister');
        if(isset($details['btns']) && isset($details['btns']['hero']) && !empty($details['btns']['hero']['text'])) {
            $hero_btn['text'] = $details['btns']['hero']['text'] ?? 'Start your Free Trial';
        }
        if(isset($details['btns']) && isset($details['btns']['hero']) && !empty($details['btns']['hero']['link'])) {
            $hero_btn['link'] = $details['btns']['hero']['link'] ?? route('business.getRegister');
        }

        $industry_btn['text'] = 'Get Started';
        $industry_btn['link'] = route('business.getRegister');
        if(isset($details['btns']) && isset($details['btns']['industry']) && !empty($details['btns']['industry']['text'])) {
            $industry_btn['text'] = $details['btns']['industry']['text'] ?? 'Get Started';
        }
        if(isset($details['btns']) && isset($details['btns']['industry']) && !empty($details['btns']['industry']['link'])) {
            $industry_btn['link'] = $details['btns']['industry']['link'] ?? route('business.getRegister');
        }

        $cta_btn['text'] = 'Try Now';
        $cta_btn['link'] = route('business.getRegister');
        if(isset($details['btns']) && isset($details['btns']['cta']) && !empty($details['btns']['cta']['text'])) {
            $cta_btn['text'] = $details['btns']['cta']['text'] ?? 'Try Now';
        }
        if(isset($details['btns']) && isset($details['btns']['cta']) && !empty($details['btns']['cta']['link'])) {
            $cta_btn['link'] = $details['btns']['cta']['link'] ?? route('business.getRegister');
        }
    @endphp
    <div class="row">
        <!-- navbar -->
        <div class="col-xs-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        {{__('cms::lang.navbar_btn')}}
                    </h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="form-group">
                                {!! Form::label('navbar_btn', __('cms::lang.button_text') . ':*' )!!}
                                {!! Form::text('btns[navbar][text]', $navbar_btn['text'], ['class' => 'form-control', 'id' => 'navbar_btn']) !!}
                            </div>
                        </div>
                        <div class="col-xs-12">
                            <div class="form-group">
                                {!! Form::label('navbar_btn_link', __('cms::lang.button_link') . ':*' )!!}
                                {!! Form::text('btns[navbar][link]', $navbar_btn['link'], ['class' => 'form-control', 'id' => 'navbar_btn_link']) !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /navbar -->
        <!-- hero section btn -->
        <div class="col-xs-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        {{__('cms::lang.hero_btn')}}
                    </h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="form-group">
                                {!! Form::label('hero_btn', __('cms::lang.button_text') . ':*' )!!}
                                {!! Form::text('btns[hero][text]', $hero_btn['text'], ['class' => 'form-control', 'id' => 'hero_btn']) !!}
                            </div>
                        </div>
                        <div class="col-xs-12">
                            <div class="form-group">
                                {!! Form::label('hero_btn_link', __('cms::lang.button_link') . ':*' )!!}
                                {!! Form::text('btns[hero][link]', $hero_btn['link'], ['class' => 'form-control', 'id' => 'hero_btn_link']) !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /hero section btn -->
        <!-- industry btn -->
        <div class="col-xs-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        {{__('cms::lang.industry_btn')}}
                    </h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="form-group">
                                {!! Form::label('industry_btn', __('cms::lang.button_text') . ':*' )!!}
                                {!! Form::text('btns[industry][text]', $industry_btn['text'], ['class' => 'form-control', 'id' => 'industry_btn']) !!}
                            </div>
                        </div>
                        <div class="col-xs-12">
                            <div class="form-group">
                                {!! Form::label('industry_btn_link', __('cms::lang.button_link') . ':*' )!!}
                                {!! Form::text('btns[industry][link]', $industry_btn['link'], ['class' => 'form-control', 'id' => 'industry_btn_link']) !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /industry btn -->
        <!-- cta btn -->
        <div class="col-xs-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        {{__('cms::lang.call_to_action_btn')}}
                    </h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="form-group">
                                {!! Form::label('call_to_action_btn', __('cms::lang.button_text') . ':*' )!!}
                                {!! Form::text('btns[cta][text]', $cta_btn['text'], ['class' => 'form-control', 'id' => 'call_to_action_btn']) !!}
                            </div>
                        </div>
                        <div class="col-xs-12">
                            <div class="form-group">
                                {!! Form::label('call_to_action_btn_link', __('cms::lang.button_link') . ':*' )!!}
                                {!! Form::text('btns[cta][link]', $cta_btn['link'], ['class' => 'form-control', 'id' => 'call_to_action_btn_link']) !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /cta btn -->
    </div>
    <!-- /button options -->
</div>