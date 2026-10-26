@extends('cms::frontend.layouts.app')
@section('title', __('cms::lang.blog'))
@section('css')
<style type="text/css">
    .blog-img{
        height: 232px !important;
        object-fit: cover !important;
        max-width: 100% !important;
    }
</style>
@endsection
@section('content')
@includeIf('cms::frontend.layouts.header')
<div class="article-block space-between-blocks">
    <div class="container col-xxl-10 px-xxl-0">
        <div class="article col-xl-10 mx-auto">
            <div class="px-4 mb-4 text-center">
                <h1 class="article-block__title">
                    Latest Blogs
                </h1>
            </div>
        </div>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
            @forelse($blogs as $key => $blog)
                <div class="col">
                    <div class="card h-100 shadow-sm">
                        <img src="{{$blog->feature_image_url ?? asset('modules/cms/img/default.png')}}"
                            class="blog-img" 
                            loading="lazy">
                        <div class="card-body">
                            <a href="{{action([\Modules\Cms\Http\Controllers\CmsController::class, 'viewBlog'], ['id' => $blog->id, 'slug' => $blog->slug])}}" class="text-decoration-none text-dark">
                                <h4>
                                    {{$blog->title}}
                                </h4>
                            </a>
                            @if(!empty($blog->meta_description))
                                <p class="card-text"
                                    title="{{$blog->meta_description}}">
                                    {{substr($blog->meta_description,0,160)}}...
                                </p>
                            @endif
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="btn-group">
                                    <a type="button" class="hero__btn btn btn-secondary mb-2 mb-lg-0 mx-1 mx-lg-2"
                                        href="{{action([\Modules\Cms\Http\Controllers\CmsController::class, 'viewBlog'], ['id' => $blog->id, 'slug' => $blog->slug])}}">
                                        Read more
                                    </a>
                                </div>
                                <small class="text-muted" title="last updated">
                                    {{\Carbon\Carbon::parse($blog->created_at)->diffForHumans()}}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col text-center">
                    <h1>
                        No blogs written!
                    </h1>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection