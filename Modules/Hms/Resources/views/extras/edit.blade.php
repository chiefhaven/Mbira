<div class="modal-dialog" role="document">
    <div class="modal-content">
  
      {!! Form::open(['url' => action([\Modules\Hms\Http\Controllers\ExtraController::class, 'update'], ['extra'=> $extra->id]), 'method' => 'put', 'id' => 'add_extra' ]) !!}
  
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">@lang( 'hms::lang.edit_extra' )</h4>
      </div>
  
      <div class="modal-body">
        <div class="form-group">
            {!! Form::label('name', __('hms::lang.name') . '*') !!}
            {!! Form::text('name', $extra->name, [
                'class' => 'form-control',
                'required',
                'placeholder' => __('hms::lang.name'),
            ]) !!}
        </div>
        <div class="form-group">
            {!! Form::label('price', __('hms::lang.price') . '*') !!}
            {!! Form::number('price', $extra->price, [
                'class' => 'form-control',
                'required',
                'step' => '0.01',
                'placeholder' => __('hms::lang.price'),
            ]) !!}
        </div>
        <div class="form-group">
            {!! Form::label('price_per', __('hms::lang.per') . '*') !!}
            {!! Form::select('price_per', $price_per,$extra->price_per , [
                'class' => 'form-control',
                'required',
            ]) !!}
        </div>
      </div>
  
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">@lang( 'messages.save' )</button>
        <button type="button" class="btn btn-default" data-dismiss="modal">@lang( 'messages.close' )</button>
      </div>
  
      {!! Form::close() !!}
  
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->