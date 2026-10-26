<div class="modal-dialog" role="document">
    <div class="modal-content">
  
      {!! Form::open(['url' => action([\Modules\Hms\Http\Controllers\UnavailableController::class, 'update'], [$unavailable->id]), 'method' => 'put', 'id' => 'edit_unavailable' ]) !!}
  
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">@lang( 'hms::lang.edit_unavailable' )</h4>
      </div>
  
      <div class="modal-body">
        <div class="form-group">
          {!! Form::label('room_id', __('hms::lang.rooms'). '*') !!} <br>
          {!! Form::select('room_id', $rooms, $unavailable->hms_rooms_id, [
              'class' => 'form-control select2',
              'required',
          ]) !!}
        </div>

        <div class="form-group">
            {!! Form::label('date_from', __('hms::lang.date_from') . '*') !!}
            {!! Form::text('date_from', !empty($unavailable->date_from) ? @format_date($unavailable->date_from) : null, [
                'class' => 'form-control',
                'required',
                'readonly',
            ]) !!}
        </div>
        <div class="form-group">
            {!! Form::label('date_to', __('hms::lang.date_to') . '*') !!}
            {!! Form::text('date_to', !empty($unavailable->date_to) ? @format_date($unavailable->date_to) : null, [
                'class' => 'form-control datepicker',
                'required',
                'readonly',
            ]) !!}
        </div>
        <div class="form-group">
          {!! Form::label('unavailable_type', __('hms::lang.unavailable_type'). '*') !!} <br>
          {!! Form::select('unavailable_type', $types, $unavailable->unavailable_type, [
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