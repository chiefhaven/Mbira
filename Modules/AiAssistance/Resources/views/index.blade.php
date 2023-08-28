@extends('layouts.app')

@section('title', __('aiassistance::lang.aiassistance'))

@section('content')

@include('aiassistance::layouts.nav')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang( 'aiassistance::lang.aiassistance' )</h1>
    @if($token_remaining_display)
        <p class="text-info">{{$token_remaining_display}}</p>
    @endif
</section>

<section class="content no-print">
    <div class="row">

        @foreach($tools as $k => $tool)
        <div class="col-md-4">
            <div class="box box-success hvr-grow-shadow">

                <div class="box-body text-center">
                    <i class="{{$tool['icon']}} font-30"></i>
                    <h3 class="text-center">{{$tool['label']}}</h3>
                    <p class="text-center">{{$tool['description']}}</p>
                    <a href="{{action([\Modules\AiAssistance\Http\Controllers\AiAssistanceController::class, 'create'], ['tool' => $k])}}" class="btn btn-success">@lang( 'aiassistance::lang.create' )</a>
                </div>

            </div>

        </div>
        @endforeach


    </div>
</section>

@stop

@section('javascript')

@endsection