@extends('layouts.app')
@section('title', __('cms::lang.cms'))
@section('css')
<style type="text/css">
    .app-logo{
        max-width: 100%;
        width: 100px;
        object-fit: contain;
    }
</style>
@endsection
@section('content')

@include('cms::layouts.nav')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>
        @lang('cms::lang.site_details')
    </h1>
</section>
<!-- Main content -->
<section class="content">
    {!! Form::open(['action' => '\Modules\Cms\Http\Controllers\SettingsController@store', 'id' => 'site_details_form', 'method' => 'post', 'files' => true, 'enctype' => "multipart/form-data"]) !!}
        <div class="row">
            <div class="col-md-12">
                @component('components.widget', ['class' => 'box-solid'])
                    <div class="row">
                        <div class="col-xs-12 pos-tab-container">
                            <div class="col-lg-2 col-md-2 col-sm-2 col-xs-2 pos-tab-menu">
                                <div class="list-group">
                                    <a href="#" class="list-group-item text-center active">
                                        @lang('cms::lang.application')
                                    </a>
                                    <a href="#" class="list-group-item text-center">
                                        @lang('cms::lang.contact_us')
                                    </a>
                                    <a href="#" class="list-group-item text-center">
                                        @lang('cms::lang.follow_us_on_social_media')
                                    </a>
                                    <a href="#" class="list-group-item text-center">
                                        @lang('cms::lang.statistics')
                                    </a>
                                    <a href="#" class="list-group-item text-center">
                                        @lang('cms::lang.faq')
                                    </a>
                                    <a href="#" class="list-group-item text-center">
                                        @lang('cms::lang.chat_widget')
                                    </a>
                                    <a href="#" class="list-group-item text-center">
                                        @lang('cms::lang.integration')
                                    </a>
                                    <a href="#" class="list-group-item text-center">
                                        @lang('cms::lang.buttons')
                                    </a>
                                </div>
                            </div>
                            <div class="col-lg-10 col-md-10 col-sm-10 col-xs-10 pos-tab">
                                @includeIf('cms::settings.partials.application')
                                @includeIf('cms::settings.partials.contact_us')
                                @includeIf('cms::settings.partials.follow_us_on_social_media')
                                @includeIf('cms::settings.partials.statistics')
                                @includeIf('cms::settings.partials.faqs')
                                @includeIf('cms::settings.partials.chat_widget')
                                @includeIf('cms::settings.partials.integration')
                                @includeIf('cms::settings.partials.buttons')
                            </div>
                        </div>
                        <!--  </pos-tab-container> -->
                    </div>
                @endcomponent
            </div>            
        </div>
        <div class="row">
            <div class="col-md-12 text-right">
                <button type="submit" class="btn btn-primary submit-btn btn-lg">@lang('messages.submit')</button>
            </div>
        </div>
    {!! Form::close() !!}
</section>

@stop

@section('javascript')
    <script type="text/javascript">

        function toggleChatDiv(option) {
            if(option == 'in_app_chat'){
                $('div.in_app_chat_div').removeClass('hide');
                $('div.third_party_chat_div').addClass('hide');
            } else {
                $('div.in_app_chat_div').addClass('hide');
                $('div.third_party_chat_div').removeClass('hide');
            }
        }
        
        $(document).on('change', '#chat_widget_option', function() {
            toggleChatDiv($(this).val());
        });

        toggleChatDiv($('#chat_widget_option').val());

        $(document).ready(function () {
            __page_leave_confirmation('#site_details_form');
            $("form#site_details_form").validate({
                submitHandler: function(form, e) {
                    if ($('form#site_details_form').valid()) {
                        window.onbeforeunload = null;
                        let post_submit_btn = Ladda.create(document.querySelector('.submit-btn'));
                        form.submit();
                        post_submit_btn.start();
                    }
                }
            });
        })
    </script>
@endsection