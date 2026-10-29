@extends('layouts.app')
@section('title', __('product.edit_product'))
@section('content')
<section class="content-header">
    <h1>@lang('product.edit_product'): {{ $product->name }}</h1>
</section>
<section class="content">
    <div class="alert alert-info">@lang('product.eis_local_details')</div>
    @if ($errors->any())
        <div class="alert alert-danger"><ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul></div>
    @endif
    {!! Form::open(['url' => action([\App\Http\Controllers\ProductController::class, 'update'], [$product->id]), 'method' => 'PUT', 'files' => true]) !!}
    @component('components.widget', ['class' => 'box-primary'])
        <p><strong>@lang('product.sku'):</strong> {{ $product->sku }}</p>
        <div class="row">
            <div class="col-sm-4 form-group">
                {!! Form::label('brand_id', __('product.brand')) !!}
                {!! Form::select('brand_id', $brands, old('brand_id', $product->brand_id), ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]) !!}
            </div>
            <div class="col-sm-4 form-group">
                {!! Form::label('category_id', __('product.category')) !!}
                {!! Form::select('category_id', $categories, old('category_id', $product->category_id), ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]) !!}
            </div>
            <div class="col-sm-4 form-group">
                {!! Form::label('sub_category_id', __('product.sub_category')) !!}
                {!! Form::select('sub_category_id', $sub_categories, old('sub_category_id', $product->sub_category_id), ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]) !!}
            </div>
            <div class="col-sm-4 form-group">
                {!! Form::label('alert_quantity', __('product.alert_quantity')) !!}
                {!! Form::text('alert_quantity', old('alert_quantity', $alert_quantity), ['class' => 'form-control input_number']) !!}
            </div>
            <div class="col-sm-4 form-group">
                {!! Form::label('image', __('lang_v1.product_image')) !!}
                {!! Form::file('image', ['accept' => '.jpg,.jpeg,.png,.webp']) !!}
                @if ($product->image)
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" style="max-width: 120px; max-height: 120px;">
                @endif
            </div>
            <div class="col-sm-4 form-group">
                {!! Form::label('product_locations', __('business.business_locations')) !!}
                {!! Form::hidden('product_locations', '') !!}
                {!! Form::select('product_locations[]', $business_locations, old('product_locations', $product->product_locations->pluck('id')), ['class' => 'form-control select2', 'multiple']) !!}
            </div>
        </div>
        <h4>@lang('lang_v1.rack_details')</h4>
        <div class="row">
            @foreach ($business_locations as $location_id => $location)
                <div class="col-sm-4 form-group">
                    <strong>{{ $location }}</strong>
                    @foreach (['rack', 'row', 'position'] as $field)
                        {!! Form::label('rack_'.$location_id.'_'.$field, __('lang_v1.'.$field)) !!}
                        {!! Form::text('product_racks['.$location_id.']['.$field.']', old('product_racks.'.$location_id.'.'.$field, $rack_details[$location_id][$field] ?? null), ['id' => 'rack_'.$location_id.'_'.$field, 'class' => 'form-control']) !!}
                    @endforeach
                </div>
            @endforeach
        </div>
        @php
            $labels = json_decode(session('business.custom_labels'), true) ?? [];
        @endphp
        <div class="row">
            @foreach (range(1, 20) as $number)
                @if (!empty($labels['product']['custom_field_'.$number]))
                    @php
                        $field = 'product_custom_field'.$number;
                        $details = $labels['product_cf_details'][$number] ?? [];
                    @endphp
                    <div class="col-sm-4 form-group">
                        {!! Form::label($field, $labels['product']['custom_field_'.$number]) !!}
                        @if (($details['type'] ?? '') === 'dropdown')
                            {!! Form::select($field, preg_split('/\r\n|\r|\n/', $details['dropdown_options'] ?? ''), old($field, $product->$field), ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]) !!}
                        @else
                            {!! Form::input(($details['type'] ?? '') === 'date' ? 'date' : 'text', $field, old($field, $product->$field), ['class' => 'form-control']) !!}
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
        <button type="submit" class="btn btn-primary">@lang('messages.update')</button>
    @endcomponent
    {!! Form::close() !!}
</section>
@endsection
@section('javascript')
    <script src="{{ asset('js/product.js?v='.$asset_v) }}"></script>
@endsection
