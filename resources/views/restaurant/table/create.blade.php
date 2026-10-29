<div class="modal-dialog" role="document">
  <div class="modal-content">

    {!! Form::open(['url' => action([\App\Http\Controllers\Restaurant\TableController::class, 'store']), 'method' => 'post', 'id' => 'table_add_form' ]) !!}

    <div class="modal-header">
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      <h4 class="modal-title">@lang( 'restaurant.add_table' )</h4>
    </div>

    <div class="modal-body">
      <div class="form-group">
        {!! Form::hidden('is_default', 0) !!}
        <label for="table_is_default">
          {!! Form::checkbox('is_default', 1, false, ['class' => 'input-icheck', 'id' => 'table_is_default', 'aria-describedby' => 'table_default_help']) !!}
          @lang('restaurant.default_table')
        </label>
        <p class="help-block" id="table_default_help">@lang('restaurant.default_table_help')</p>
      </div>
      <div class="form-group">
        {!! Form::hidden('allow_multiple_orders', 0) !!}
        <label for="table_allow_multiple_orders">
          {!! Form::checkbox('allow_multiple_orders', 1, false, ['class' => 'input-icheck', 'id' => 'table_allow_multiple_orders', 'aria-describedby' => 'table_multiple_orders_help']) !!}
          @lang('restaurant.allow_multiple_orders')
        </label>
        <p class="help-block" id="table_multiple_orders_help">@lang('restaurant.allow_multiple_orders_help')</p>
      </div>

      @if(count($business_locations) == 1)
        @php 
            $default_location = current(array_keys($business_locations->toArray())) 
        @endphp
      @else
        @php $default_location = null; @endphp
      @endif
      <div class="form-group">
        {!! Form::label('location_id', __('purchase.business_location').':*') !!}
        {!! Form::select('location_id', $business_locations, $default_location, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select'), 'required']); !!}
      </div>
      
      <div class="form-group">
        {!! Form::label('name', __( 'restaurant.table_name' ) . ':*') !!}
          {!! Form::text('name', null, ['class' => 'form-control', 'required', 'placeholder' => __( 'restaurant.table_name' ) ]); !!}
      </div>

      <div class="form-group">
        {!! Form::label('description', __( 'restaurant.short_description' ) . ':') !!}
          {!! Form::text('description', null, ['class' => 'form-control','placeholder' => __( 'restaurant.short_description' )]); !!}
      </div>
    </div>

    <div class="modal-footer">
      <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang( 'messages.save' )</button>
      <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang( 'messages.close' )</button>
    </div>

    {!! Form::close() !!}

  </div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->