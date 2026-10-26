<div class="row">
    <div class="col-md-9">
        @component('components.widget', ['class' => 'box-primary', 'title' => __('cms::lang.seo')])
            <div class="col-md-12">
                <div class="form-group">
                    {!! Form::label('meta_description', __('cms::lang.meta_description') . ':' )!!}
                    @show_tooltip(__('cms::lang.content_summary_help_text'))
                    {!! Form::textarea('meta_description', $page->meta_description, ['class' => 'form-control', 'rows' => 5, 'maxlength' => '300']) !!}
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    {!! Form::label('tags', __('cms::lang.tags') . ':' )!!}
                    @show_tooltip(__('cms::lang.tags_help_text')) <br>
                    {!! Form::text('tags', $page->tags, ['class' => 'form-control' ]) !!}
                </div>
            </div>
        @endcomponent  
    </div>
</div>