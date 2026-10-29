<div class="modal-dialog" role="document">
    <div class="modal-content">
  
      {!! Form::open(['id' => 'edit_booking_room' ]) !!}
  
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"> @lang('hms::lang.edit_room')</h4>
      </div>
  
      <div class="modal-body">
        <div class="form-group">
            {!! Form::label('name', __('hms::lang.type') . '*') !!}
            {!! Form::select('type', $types, $type->id, [
                'class' => 'form-control',
                'required',
                'id' => 'type',
                'disabled' => true,
                'placeholder' => __('hms::lang.type'),
            ]) !!}
        </div>
       <div class="modify_field"> 
        <div class="form-group">
            {!! Form::label('no_of_adult', __('hms::lang.no_of_adult') . '*') !!}
            <select class="form-control" id="no_of_adult" required name="no_of_child">
                @for ($i = 1; $i <= $type->no_of_adult; $i++)
                    <option {{ $i == $no_of_adult ? 'selected' : ''}} value="{{ $i }}">{{ $i }}</option>
                @endfor
            </select>
        </div>
        <div class="form-group">
            {!! Form::label('no_of_child', __('hms::lang.no_of_child') . '*') !!}
            <select class="form-control" id="no_of_child" required name="no_of_child">
                @for ($i = 0; $i <= $type->no_of_child; $i++)
                    <option {{ $i == $no_of_child ? 'selected' : ''}} value="{{ $i }}">{{ $i }}</option>
                @endfor
            </select>
        </div>
          <div class="form-group">
            {!! Form::label('room_no', __('hms::lang.room_no') . '*') !!}
            {!! Form::select('room_no', $rooms , $room_id , [
                'class' => 'form-control',
                'required',
                'id' => 'room_no',
                'placeholder' => __('hms::lang.room_no'),
            ]) !!}
          </div>
       </div>
      </div>
  
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">@lang( 'messages.save' )</button>
        <button type="button" class="btn btn-default" data-dismiss="modal">@lang( 'messages.close' )</button>
      </div>
  
      {!! Form::close() !!}
  
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->