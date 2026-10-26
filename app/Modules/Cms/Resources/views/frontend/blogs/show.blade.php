@extends('cms::frontend.layouts.app')
@section('title', $blog->title)
@section('meta')
    <meta name="description" content="{{$blog->meta_description}}">
@endsection
@section('content')
@includeIf('cms::frontend.layouts.header')
    <div class="article-block space-between-blocks">
        <div class="container col-xxl-10 px-xxl-0">
            <div class="article col-xl-10 mx-auto">
                <div class="px-4 mb-4 text-center">
                    <p class="article-block__info">
                        <span class="article-block__author">
                            {{$blog->createdBy->user_full_name ?? ''}}
                        </span>
                        <span class="article-block__time">{{\Carbon\Carbon::parse($blog->created_at)->diffForHumans()}}</span>
                    </p>
                    <h1 class="article-block__title">
                        {{$blog->title}}
                    </h1>
                </div>
                <div class="article-block__header mb-5 py-4 px-xxl-5">
                    <img src="{{$blog->feature_image_url ?? asset('modules/cms/img/default.png')}}" 
                    class="article-block__header-img w-100" loading="lazy">
                </div>
                {!!$blog->content!!}
            </div>
        </div>
    </div>
@endsection