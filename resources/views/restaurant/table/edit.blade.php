<div class="modal-dialog" role="document">
  <div class="modal-content">

    {!! Form::open(['url' => action([\App\Http\Controllers\Restaurant\TableController::class, 'update'], [$table->id]), 'method' => 'PUT', 'id' => 'table_edit_form' ]) !!}

    <div class="modal-header">
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      <h4 class="modal-title">@lang( 'restaurant.edit_table' )</h4>
    </div>

    <div class="modal-body">
      <div class="form-group">
        {!! Form::hidden('is_default', 0) !!}
        <label for="table_is_default">
          {!! Form::checkbox('is_default', 1, $table->is_default, ['class' => 'input-icheck', 'id' => 'table_is_default', 'aria-describedby' => 'table_default_help']) !!}
          @lang('restaurant.default_table')
        </label>
        <p class="help-block" id="table_default_help">@lang('restaurant.default_table_help')</p>
      </div>
      <div class="form-group">
        {!! Form::hidden('allow_multiple_orders', 0) !!}
        <label for="table_allow_multiple_orders">
          {!! Form::checkbox('allow_multiple_orders', 1, $table->allow_multiple_orders, ['class' => 'input-icheck', 'id' => 'table_allow_multiple_orders', 'aria-describedby' => 'table_multiple_orders_help']) !!}
          @lang('restaurant.allow_multiple_orders')
        </label>
        <p class="help-block" id="table_multiple_orders_help">@lang('restaurant.allow_multiple_orders_help')</p>
      </div>
      <div class="form-group">
        {!! Form::label('name', __( 'restaurant.table_name' ) . ':*') !!}
          {!! Form::text('name', $table->name, ['class' => 'form-control', 'required', 'placeholder' => __( 'brand.brand_name' )]); !!}
      </div>

      <div class="form-group">
        {!! Form::label('description', __( 'restaurant.short_description' ) . ':') !!}
          {!! Form::text('description', $table->description, ['class' => 'form-control','placeholder' => __( 'brand.short_description' )]); !!}
      </div>
    </div>

    <div class="modal-footer">
      <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang( 'messages.update' )</button>
      <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang( 'messages.close' )</button>
    </div>

    {!! Form::close() !!}

  </div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->