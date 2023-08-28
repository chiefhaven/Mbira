<div class="pos-tab-content">
	<div class="row">
		<div class="col-md-12">
			<div class="form-group">
                {!! Form::label('statistics_tagline', __('cms::lang.tagline') . ':' )!!}
                {!! Form::text('statistics[tagline]', !empty($details['statistics']['tagline']) ? $details['statistics']['tagline'] : null, ['class' => 'form-control', 'id' => 'statistics_tagline']) !!}
           </div>
		</div>
		<div class="col-md-12">
			<div class="form-group">
                {!! Form::label('statistics_description', __('cms::lang.description') . ':' )!!}
                {!! Form::textarea('statistics[description]', !empty($details['statistics']['description']) ? $details['statistics']['description'] : null, ['class' => 'form-control', 'id' => 'statistics_description', 'rows' => 2]) !!}
           </div>
		</div>
	</div>
	@for($i=0;$i<4;$i++)
		<div class="row">
			<div class="col-md-6">
				<div class="form-group">
                    {!! Form::label('statistics_num'.$i, __('cms::lang.stats') . ':' )!!}
                    {!! Form::text('statistics[content]['.$i.'][stats]', !empty($details['statistics']['content'][$i]['stats']) ? $details['statistics']['content'][$i]['stats'] : null, ['class' => 'form-control', 'id' => 'statistics_num'.$i, 'placeholder' => __('cms::lang.stats')]) !!}
               </div>
			</div>
			<div class="col-md-6">
				<div class="form-group">
                    {!! Form::label('statistics_title_'.$i, __('cms::lang.title') . ':' )!!}
                    {!! Form::text('statistics[content]['.$i.'][title]', !empty($details['statistics']['content'][$i]['title']) ? $details['statistics']['content'][$i]['title'] : null, ['class' => 'form-control', 'id' => 'statistics_title_'.$i, 'placeholder' => __('cms::lang.title')]) !!}
               </div>
			</div>
		</div>
	@endfor
</div>