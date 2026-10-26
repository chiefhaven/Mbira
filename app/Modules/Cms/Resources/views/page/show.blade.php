@extends('cms::layouts.frontend')
@section('title', $page->title)
@section('meta')
    <meta name="description" content="{{$page->meta_description}}">
@endsection
@section('content')
    <section class="py-2 text-center container">
        <div class="row py-lg-5">
            <div class="col-lg-6 col-md-8 mx-auto">
                <h1 class="fw-light">
                    {{$page->title}}
                </h1>
            </div>
        </div>
    </section>
    <section>
        <div class="container">
            @if(!empty($page->feature_image))
                <div class="row">
                    <div class="col text-center">
                        <img src="{{$page->feature_image_url ?? 'https://picsum.photos/1200/800/'}}"
                            style="max-width: 100%;" loading="lazy">
                    </div>
                </div>
            @endif
            <div class="row mt-4">
                <div class="col">
                    {!!$page->content!!}
                </div>
            </div>
        </div>
    </section>
@endsection