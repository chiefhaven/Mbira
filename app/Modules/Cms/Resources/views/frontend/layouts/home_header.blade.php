<header class="hero container-fluid">
    <div class="hero__content container mx-auto">
        <!-- I'm putting the nav inside this "fixed-nav-container" to fix some issues happens on scroll -->
        @includeIf('cms::frontend.layouts.navbar')
           <!------------------------------>
    <!--Hero---------------->
    <!------------------------------>
        <div class="hero__body col-lg-7 px-0">
            <h1 class="hero__title mb-4">
                {{$page->title}}
            </h1>
            <div class="hero__paragraph mx-0 mb-4">
                {!!$page->content!!}
            </div>
            <div class="hero__btns-container">
                <a class="hero__btn btn btn-primary mb-2 mb-lg-0 mx-1 mx-lg-2" href="{{ $hero_btn['link'] }}">
                    {{$hero_btn['text']}}
                </a>
                <!-- <a class="hero__btn btn btn-secondary mb-2 mb-lg-0 mx-1 mx-lg-2" href="{{ route('business.getRegister') }}">
                    Get access for $4.9
                </a> -->
            </div>
        </div>
    </div>
    <div class="hero__row d-block d-lg-flex row">
        <div class="hero__empty-column col-lg-7"></div>
        @php
            $bg_img_url = asset('modules/cms/img/home.png');
            if(!empty($page->feature_image_url)) {
                $bg_img_url = $page->feature_image_url;
            }
        @endphp
        <div class="hero__image-column col-lg-5" 
            style="background-image: url({{$bg_img_url}});">
        </div>
    </div>
</header>