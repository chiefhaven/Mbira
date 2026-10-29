<div class="row check_group">
    <div class="col-md-3"><h4>@lang('restaurant.restaurant')</h4></div>
    <div class="col-md-9">
        @foreach(['restaurant.kitchen' => 'role.restaurant_kitchen', 'restaurant.bar' => 'role.restaurant_bar',
            'restaurant.approve_void' => 'role.restaurant_approve_void'] as $permission => $label)
            <div class="col-md-12"><div class="checkbox"><label>
                {!! Form::checkbox('permissions[]', $permission, in_array($permission, $role_permissions ?? []), ['class' => 'input-icheck']) !!}
                @lang($label)
            </label></div></div>
        @endforeach
    </div>
</div>
