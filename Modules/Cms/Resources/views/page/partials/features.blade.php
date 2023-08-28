@component('components.widget', ['class' => 'box-primary', 'title' => __('cms::lang.features')])
	@php
        $feature = [];
        if(
            isset($page_meta['feature']) && 
            isset($page_meta['feature']['meta_value']) &&
            !empty($page_meta['feature']['meta_value'])
        ) {
            $feature = json_decode($page_meta['feature']['meta_value'], true);
        }
    @endphp
    <input type="hidden" name="meta[feature][id]" value="@if(isset($page_meta['feature']) && isset($page_meta['feature']['id'])){{$page_meta['feature']['id']}} @endif">
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('feature_title', __('cms::lang.title') . ':*' )!!}
                {!! Form::text('meta[feature][title]', (isset($feature['title']) && !empty($feature['title'])) ? $feature['title'] : null, ['class' => 'form-control', 'id' => 'feature_title']) !!}
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('feature_content', __('cms::lang.description') . ':*' )!!}
                {!! Form::textarea('meta[feature][description]', (isset($feature['description']) && !empty($feature['description'])) ? $feature['description'] : null, ['class' => 'form-control', 'id'=>'feature_content']) !!}
            </div>
        </div>
    </div>
    @for($i=0; $i<10; $i++)
    	@php
            $feature_icon = '';
            $feature_title = '';
            $feature_description = '';
            if(isset($feature['content']) && !empty($feature['content'])) {
                $feature_icon = (isset($feature['content'][$i]) && isset($feature['content'][$i]['icon'])) ? $feature['content'][$i]['icon'] : '';
                $feature_title = (isset($feature['content'][$i]) && isset($feature['content'][$i]['title'])) ? $feature['content'][$i]['title'] : '';
                $feature_description = (isset($feature['content'][$i]) && isset($feature['content'][$i]['description'])) ? $feature['content'][$i]['description'] : '';
            }
        @endphp
		<div class="row">
			<div class="col-md-4">
		       {!! Form::label('input_feature_icon_'.$i, __('cms::lang.icon') . ':' )!!}
		       <div class="form-group">
	               <div class="input-group">
	                    {!! Form::text('meta[feature][content]['.$i.'][icon]', $feature_icon, ['class' => 'form-control', 'id' => 'input_feature_icon_'.$i, 'placeholder' => __('cms::lang.icon'), 'readonly']) !!}
	                    <span class="input-group-addon">
	                        <button class="btn btn-outline-secondary" 
	                        	data-icon="{{$feature_icon ?? 'fas fa-home'}}" role="iconpicker"
	                        	id="feature_icon_{{$i}}">
	                        </button>
	                    </span>
	                </div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="form-group">
		            {!! Form::label('meta_feature_title_'.$i, __('cms::lang.title') . ':' )!!}
		            {!! Form::text('meta[feature][content]['.$i.'][title]', $feature_title, ['class' => 'form-control', 'id' => 'meta_feature_title_'.$i, 'placeholder' => __('cms::lang.title')]) !!}
		       </div>
			</div>
			<div class="col-md-4">
				<div class="form-group">
		            {!! Form::label('meta_feature_description_'.$i, __('cms::lang.description') . ':' )!!}
		            {!! Form::textarea('meta[feature][content]['.$i.'][description]', $feature_description, ['class' => 'form-control', 'rows' => 2, 'id' => 'meta_feature_description_'.$i, 'placeholder' => __('cms::lang.description')]) !!}
		       </div>
			</div>
		</div>
	@endfor
@endcomponent