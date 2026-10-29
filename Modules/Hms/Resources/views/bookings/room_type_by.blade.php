<div class="form-group">
    {!! Form::label('no_of_adult', __('hms::lang.no_of_adult') . '*') !!}
    <select class="form-control" id="no_of_adult" required name="no_of_child">
        @for ($i = 1; $i <= $type->no_of_adult; $i++)
            <option value="{{ $i }}">{{ $i }}</option>
        @endfor
    </select>
</div>
<div class="form-group">
    {!! Form::label('no_of_child', __('hms::lang.no_of_child') . '*') !!}
    <select class="form-control" id="no_of_child" required name="no_of_child">
        @for ($i = 0; $i <= $type->no_of_child; $i++)
            <option value="{{ $i }}">{{ $i }}</option>
        @endfor
    </select>
</div>
<div class="form-group">
    {!! Form::label('room_no', __('hms::lang.room_no') . '*') !!}
    {!! Form::select('room_no', $rooms, '', [
        'class' => 'form-control',
        'required',
        'id' => 'room_no',
        'placeholder' => __('hms::lang.room_no'),
    ]) !!}
</div>
