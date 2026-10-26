<div class="modal-dialog" role="document">
    <div class="modal-content">
  
      {!! Form::open(['url' => action([\Modules\Hms\Http\Controllers\UnavailableController::class, 'store']), 'method' => 'post', 'id' => 'add_unavailable' ]) !!}
  
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">@lang( 'hms::lang.add_unavailable' )</h4>
      </div>
  
      <div class="modal-body">
        <div class="form-group">
          {!! Form::label('rooms', __('hms::lang.rooms'). '*') !!} <br>
          {!! Form::select('rooms[]', $rooms, '', [
              'class' => 'form-control select2',
              'required',
              'multiple',
          ]) !!}
        </div>

        <div class="form-group">
            {!! Form::label('date_from', __('hms::lang.date_from') . '*') !!}
            {!! Form::text('date_from', null, [
                'class' => 'form-control',
                'readonly',
                'required',
            ]) !!}
        </div>
        <div class="form-group">
            {!! Form::label('date_to', __('hms::lang.date_to') . '*') !!}
            {!! Form::text('date_to', null, [
                'class' => 'form-control datepicker',
                'required',
                'readonly',
            ]) !!}
        </div>
        <div class="form-group">
          {!! Form::label('unavailable_type', __('hms::lang.unavailable_type'). '*') !!} <br>
          {!! Form::select('unavailable_type', $types, '', [
              'class' => 'form-control',
              'required',
              'placeholder' => __('hms::lang.unavailable_type')
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