<div class="modal-dialog" role="document">
    <div class="modal-content">
  
      {!! Form::open(['url' => action([\Modules\Hms\Http\Controllers\HmsCouponController::class, 'store']), 'method' => 'post', 'id' => 'add_coupon' ]) !!}
  
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">@lang( 'hms::lang.add_coupon' )</h4>
      </div>
  
      <div class="modal-body">
        <div class="form-group">
            {!! Form::label('hms_room_type_id', __('hms::lang.type') . '*') !!}
            {!! Form::select('hms_room_type_id', $types, '', [
                'class' => 'form-control',
                'required',
                'placeholder' => __('hms::lang.type'),
            ]) !!}
        </div>
        <div class="form-group">
            {!! Form::label('date_from', __('hms::lang.date_from') . '*') !!}
            {!! Form::text('start_date', null, [
                'class' => 'form-control date_picker',
                'required',
                'readonly',
            ]) !!}
        </div>
        <div class="form-group">
            {!! Form::label('date_to', __('hms::lang.date_to') . '*') !!}
            {!! Form::text('end_date', null, [
                'class' => 'form-control date_picker',
                'required',
                'readonly',
            ]) !!}
        </div>
        <div class="form-group">
            {!! Form::label('coupon_code', __('hms::lang.coupon_code') . '*') !!}
            {!! Form::text('coupon_code', null, [
                'class' => 'form-control',
                'required',
            ]) !!}
        </div>
        <div class="form-group">
            {!! Form::label('discount', __('hms::lang.discount') . '*') !!}
            {!! Form::number('discount', null, [
                'class' => 'form-control',
                'required',
                'step' => '0.01',
            ]) !!}
        </div>
        <div class="form-group">
            {!! Form::label('discount_type', __('hms::lang.discount_type'). '*') !!}
            {!! Form::select('discount_type', $discount_type, '', [
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