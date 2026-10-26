@extends('layouts.app')
@section('title', __('cms::lang.cms'))
@section('content')
@section('css')
<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-iconpicker/1.10.0/css/bootstrap-iconpicker.min.css">
<style type="text/css">
    .input-group-addon{
        padding: 0px;
        margin: 0px;
        border: 0.1px solid #d2d6de;
        background-color: #d2d6de !important;
    }
    .input-group-addon > button.iconpicker{
        padding-bottom: 0px;
        background-color: #d2d6de !important;
    }
</style>
@endsection
@include('cms::layouts.nav')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>
        @if($post_type == 'page')
            @lang('cms::lang.edit_page')
        @elseif($post_type == 'testimonial')
            @lang('cms::lang.edit_testimonial')
        @elseif($post_type == 'blog')
            @lang('cms::lang.edit_blog')
        @endif
    </h1>
</section>
<!-- input label text based on post type -->
@php
    if (in_array($post_type, ['blog', 'page'])) {
        $title_label = __('cms::lang.title');
        $content_label = __('cms::lang.content');
        $feature_image_label = __('cms::lang.feature_image');
    } elseif (in_array($post_type, ['testimonial'])) {
        $title_label = __('user.name');
        $content_label = __('cms::lang.testimonial');
        $feature_image_label = __('cms::lang.user_image');
    }

    if(in_array($post_type, ['page']) && !empty($page->layout) && in_array($page->layout, ['contact'])) {
        $content_label = __('cms::lang.description');
    } elseif(in_array($post_type, ['page']) && !empty($page->layout) && in_array($page->layout, ['home'])) {
        $content_label = __('cms::lang.description');
    }
@endphp
<!-- Main content -->
<section class="content">
    {!! Form::open(['url' => action([\Modules\Cms\Http\Controllers\CmsPageController::class, 'update'], [$page->id]), 'id' => 'edit_page_form', 'method' => 'put', 'files' => true]) !!}
        <input type="hidden" name="type" value="{{$post_type}}">
        <div class="row">
            <div class="col-md-9">
                @component('components.widget', ['class' => 'box-solid'])
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                {!! Form::label('title', $title_label . ':*' )!!}
                                {!! Form::text('title', $page->title, ['class' => 'form-control', 'required' ]) !!}
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                {!! Form::label('content', $content_label . ':*' )!!}
                                {!! Form::textarea('content', $page->content, ['class' => 'form-control' ]) !!}
                            </div>
                        </div>
                    </div>
                @endcomponent
                @if(in_array($post_type, ['page']) && !empty($page->layout) && in_array($page->layout, ['home']))
                    @includeIf('cms::page.partials.features', ['page_meta' => $page_meta])
                    @includeIf('cms::page.partials.industries', ['page_meta' => $page_meta])
                @endif
            </div>
            <div class="col-md-3">
                @component('components.widget', ['class' => 'box-solid'])
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="form-group">
                                {!! Form::label('feature_image', $feature_image_label . ':') !!}
                                {!! Form::file('feature_image', ['id' => 'feature_image', 'accept' => 'image/*']); !!}
                                <small><p class="help-block">@lang('purchase.max_file_size', ['size' => (config('constants.document_size_limit') / 1000000)])</p>
                                    <p class="help-block">@lang('lang_v1.previous_image_will_be_replaced')</p>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                {!! Form::label('priority', __('cms::lang.priority') . ':' )!!}
                                {!! Form::number('priority', $page->priority, ['class' => 'form-control' ]) !!}
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <br>
                                <label>
                                  {!! Form::checkbox('is_enabled', 1, $page->is_enabled, ['class' => 'input-icheck']); !!} <strong>@lang('cms::lang.is_enabled')</strong>
                                </label> 
                            </div>
                        </div>
                    </div>
                @endcomponent
            </div>
        </div>
        <!-- TODO:include edit SEO -->
        <div class="row">
            <div class="col-md-12 text-center">
                <button type="submit" class="btn btn-primary submit-btn btn-lg">@lang('messages.update')</button>
            </div>
        </div>
    {!! Form::close() !!}
</section>

@stop

@section('javascript')
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-iconpicker/1.10.0/js/bootstrap-iconpicker.bundle.min.js"></script>
    <script type="text/javascript">
        $(document).ready(function () {
            init_tinymce('content');
            var img_fileinput_setting = {
                showUpload: false,
                showPreview: true,
                browseLabel: LANG.file_browse_label,
                removeLabel: LANG.remove,
                previewSettings: {
                    image: { width: 'auto', height: 'auto', 'max-width': '100%', 'max-height': '100%' },
                },
            };
            $('#feature_image').fileinput(img_fileinput_setting);

            $("form#edit_page_form").validate({
                submitHandler: function(form, e) {
                    if ($('form#edit_page_form').valid()) {
                        window.onbeforeunload = null;
                        //if meta des length is 0;extract from content and use it as meta description
                        if (
                            $("textarea#meta_description") &&
                            (
                                $("textarea#meta_description").val().length == 0
                            )
                        ) {
                            let meta_description = tinyMCE.get('content').getContent({format : 'text'});
                            $("textarea#meta_description").val(meta_description.substring(0, 160));
                        }
                        let post_submit_btn = Ladda.create(document.querySelector('.submit-btn'));
                        form.submit();
                        post_submit_btn.start();
                    }
                }
            });
            //display notification before if any data is changed
            __page_leave_confirmation('#edit_page_form');

            @if(in_array($post_type, ['page']) && !empty($page->layout) && in_array($page->layout, ['home']))
                setTimeout(() => {
                    init_tinymce('industry_content');
                }, 3000);

                setTimeout(() => {
                    init_tinymce('feature_content');
                }, 4000);

                for(let i=0;i<8;i++) {
                    $(`#industry_icon_${i}`)
                        .iconpicker()
                        .on('change', function(e) {
                            $(`#input_industry_icon_${i}`).val(e.icon);
                        });
                }

                for(let i=0;i<10;i++){
                    $(`#feature_icon_${i}`)
                        .iconpicker()
                        .on('change', function(e) {
                            $(`#input_feature_icon_${i}`).val(e.icon);
                        });
                }
            @endif
        })
    </script>
@endsection