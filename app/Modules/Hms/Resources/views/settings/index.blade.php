@extends('layouts.app')
@section('title', __('messages.settings'))

@section('content')
    @include('hms::layouts.nav')
    <!-- Main content -->
    <section class="content">
        <!-- Custom Tabs -->
        @component('components.widget', ['class' => 'box-primary', 'title' => __('messages.settings') . ':'])
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#cn_1" data-toggle="tab" aria-expanded="true">
                            @lang('hms::lang.booking_prefix')
                        </a>
                    </li>
                    <li class="">
                        <a href="#cn_2" data-toggle="tab" aria-expanded="true">
                            @lang('lang_v1.customer_notifications')
                        </a>
                    </li>
                    <li class="">
                        <a href="#cn_3" data-toggle="tab" aria-expanded="true">
                            @lang('hms::lang.print_pdf')
                        </a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active" id="cn_1">
                        <div class="row">
                            <div class="box-body">
                                {!! Form::open([
                                    'url' => action([\Modules\Hms\Http\Controllers\HmsSettingController::class, 'store']),
                                    'method' => 'post',
                                    'id' => 'hms_setting',
                                    'files' => true,
                                ]) !!}
                                @php
                                    $settings = json_decode($busines->hms_settings);
                                @endphp
                                <div class="col-md-4">
                                    <div class="form-group">
                                        {!! Form::label('booking_prefix', __('hms::lang.booking_prefix') . '*') !!}
                                        {!! Form::text('booking_prefix', $settings->prefix ?? null, [
                                            'class' => 'form-control',
                                            'required',
                                            'placeholder' => __('hms::lang.booking_prefix'),
                                        ]) !!}
                                    </div>
                                </div>
                                <div class="col-md-12 text-center">
                                    {!! Form::submit(__('messages.submit'), ['class' => 'btn btn-success btn-big']) !!}
                                </div>
                
                                {!! Form::close() !!}
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane" id="cn_2">
                        <h3>@lang('hms::lang.new_booking')</h3>
                        <div class="row">
                            {!! Form::open([
                                'url' => action([\Modules\Hms\Http\Controllers\HmsSettingController::class, 'store_email_template']),
                                'method' => 'post',
                            ]) !!}
                            <div class="col-md-12">
                                <strong>@lang('lang_v1.available_tags'):</strong>
                                <p class="help-block">
                                    {{ implode(', ', $tags) }}
                                </p>
                            </div>
                            <div class="col-md-12 mt-10">
                                <div class="form-group">
                                    {!! Form::label('subject', __('lang_v1.email_subject') . ':') !!}
                                    {!! Form::text('subject', empty($template->subject) ? null : $template->subject, [
                                        'class' => 'form-control',
                                        'placeholder' => __('lang_v1.email_subject'),
                                        'id' => 'subject',
                                    ]) !!}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    {!! Form::label('cc', 'CC:') !!}
                                    {!! Form::email('cc', empty($template->cc) ? null : $template->cc, [
                                        'class' => 'form-control',
                                        'placeholder' => 'CC',
                                        'id' => 'cc',
                                    ]) !!}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    {!! Form::label('bcc', 'BCC:') !!}
                                    {!! Form::email('bcc', empty($template->bcc) ? null : $template->bcc, [
                                        'class' => 'form-control',
                                        'placeholder' => 'BCC',
                                        'id' => 'bcc',
                                    ]) !!}
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    {!! Form::label('email_body', __('lang_v1.email_body') . ':') !!}
                                    {!! Form::textarea('email_body', empty($template->email_body) ? null : $template->email_body, [
                                        'class' => 'form-control ckeditor',
                                        'placeholder' => __('lang_v1.email_body'),
                                        'id' => 'email_body',
                                        'rows' => 6,
                                    ]) !!}
                                </div>
                            </div>
                            <div class="col-md-12 mt-15">
                                <label class="checkbox-inline">
                                    {!! Form::checkbox('auto_send', 1, empty($template->auto_send) ? null : $template->auto_send, [
                                        'class' => 'input-icheck',
                                    ]) !!} @lang('lang_v1.autosend_email')
                                </label>
                            </div>
                            <div class="row">
                                <div class="col-md-12 text-center">
                                    <button type="submit" class="btn btn-danger btn-big">@lang('messages.save')</button>
                                </div>
                            </div>
                            {!! Form::close() !!}
                        </div>
                    </div>
                    <div class="tab-pane" id="cn_3">
                            <div class="row">
                                <div class="box-body">
                                    {!! Form::open([
                                        'url' => action([\Modules\Hms\Http\Controllers\HmsSettingController::class, 'post_pdf']),
                                        'method' => 'post',
                                        'id' => 'post_pdf',
                                        'files' => true,
                                    ]) !!}
                                    @php
                                        $settings = json_decode($busines->hms_settings);
                                    @endphp
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            {!! Form::label('footer_text', __('hms::lang.footer_text')) !!}
                                            {!! Form::textarea('footer_text', $settings->booking_pdf->footer_text ?? null, [
                                                'class' => 'form-control',
                                                'placeholder' => __('hms::lang.footer_text'),
                                            ]) !!}
                                        </div>
                                    </div>
                                    <div class="col-md-12 text-center">
                                        {!! Form::submit(__('messages.submit'), ['class' => 'btn btn-success btn-big']) !!}
                                    </div>
                    
                                    {!! Form::close() !!}
                                </div>
                            </div>
                    </div>
                </div>
            </div>
        @endcomponent
    </section>
    <!-- /.content -->
@endsection

@section('javascript')
    <script type="text/javascript">
        tinymce.init({
            selector: 'textarea#email_body',
        });
        tinymce.init({
            selector: 'textarea#footer_text',
        });
    </script>
@endsection
