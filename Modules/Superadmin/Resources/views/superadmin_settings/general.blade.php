@extends('layouts.app')
@section('title', __('superadmin::lang.general_branding'))

@section('content')
@include('superadmin::layouts.nav')
<section class="content-header">
    <h1>@lang('superadmin::lang.general_branding')</h1>
</section>
<section class="content">
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    {!! Form::open(['url' => route('superadmin.settings.general.update'), 'method' => 'put', 'files' => true]) !!}
    <div class="box box-primary">
        <div class="box-body">
            <p class="help-block">@lang('superadmin::lang.branding_help')</p>
            <div class="row">
                <div class="col-md-6 form-group">
                    {!! Form::label('name', __('superadmin::lang.app_name')) !!}
                    {!! Form::text('name', old('name', $branding['name'] ?? config('app.name')), ['class' => 'form-control', 'required', 'maxlength' => 191]) !!}
                </div>
                <div class="col-md-6 form-group">
                    {!! Form::label('title', __('superadmin::lang.branding_title')) !!}
                    {!! Form::text('title', old('title', $branding['title'] ?? config('app.title')), ['class' => 'form-control', 'maxlength' => 191]) !!}
                </div>
                <div class="col-md-12 form-group">
                    {!! Form::label('footer', __('superadmin::lang.branding_footer')) !!}
                    {!! Form::textarea('footer', old('footer', $branding['footer'] ?? ''), ['class' => 'form-control', 'rows' => 2, 'maxlength' => 500]) !!}
                    <p class="help-block">@lang('superadmin::lang.branding_footer_help')</p>
                </div>
            </div>
            <div class="row">
                @foreach (['logo', 'compact_logo', 'favicon'] as $asset)
                    <div class="col-md-4 form-group">
                        {!! Form::label($asset, __('superadmin::lang.branding_'.$asset)) !!}
                        @if (!empty($branding[$asset]))
                            <div style="margin-bottom: 10px;">
                                <img src="{{ asset($branding[$asset]) }}" alt="{{ __('superadmin::lang.branding_'.$asset) }}" style="max-width: 180px; max-height: 90px;">
                            </div>
                            <label>
                                {!! Form::checkbox('remove_'.$asset, 1, old('remove_'.$asset, false)) !!}
                                @lang('superadmin::lang.branding_reset_image')
                            </label>
                        @endif
                        {!! Form::file($asset, ['accept' => $asset === 'favicon' ? '.png,.ico' : '.png,.jpg,.jpeg,.webp']) !!}
                        <p class="help-block">{{ $asset === 'favicon' ? __('superadmin::lang.branding_icon_help') : __('superadmin::lang.branding_image_help') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="box-footer">
            <button type="submit" class="btn btn-primary">@lang('messages.update')</button>
        </div>
    </div>
    {!! Form::close() !!}
</section>
@endsection
