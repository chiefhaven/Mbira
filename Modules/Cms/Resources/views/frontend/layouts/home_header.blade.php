<header class="hero container-fluid">
    <a class="cms-skip-link" href="#main-content">Skip to content</a>
    <div class="hero__content container mx-auto">
        <!-- I'm putting the nav inside this "fixed-nav-container" to fix some issues happens on scroll -->
        @includeIf('cms::frontend.layouts.navbar', ['is_homepage' => true])
           <!------------------------------>
    <!--Hero---------------->
    <!------------------------------>
        <div class="cms-hero-layout">
        <div class="hero__body px-0">
            <h1 class="hero__title mb-4">
                {{$page->title}}
            </h1>
            <div class="hero__paragraph mx-0 mb-4">
                {!!$page->content!!}
            </div>
            <div class="hero__btns-container">
                <a class="hero__btn btn btn-primary mb-2 mb-lg-0 mx-1 mx-lg-2" href="{{ $hero_btn['link'] }}">
                    {{$hero_btn['text']}}
                    <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i>
                </a>
                <a class="btn btn-secondary" href="{{ route('cms.contact.us') }}">Talk to our team</a>
            </div>
            <div class="cms-hero-topics" aria-label="Business tools">
                <span><i class="fas fa-check" aria-hidden="true"></i> Sales &amp; invoicing</span>
                <span><i class="fas fa-check" aria-hidden="true"></i> Inventory</span>
                <span><i class="fas fa-check" aria-hidden="true"></i> Business insights</span>
            </div>
        </div>
        @php
            $bg_img_url = asset('modules/cms/img/home.png');
            if(!empty($page->feature_image_url)) {
                $bg_img_url = $page->feature_image_url;
            }
        @endphp
        <div class="cms-hero-visual">
            <div class="cms-preview-bar">
                <span class="cms-preview-dots" aria-hidden="true"><span></span><span></span><span></span></span>
                <span>{{ config('app.name') }} / Your workspace</span>
                <i class="fas fa-lock" aria-hidden="true"></i>
            </div>
            <img class="cms-hero-image" src="{{ $bg_img_url }}" alt="{{ config('app.name') }} business management" fetchpriority="high">
            <div class="cms-preview-caption">
                <span class="cms-preview-icon"><i class="fas fa-layer-group" aria-hidden="true"></i></span>
                <div><strong>One workspace. More possibilities.</strong><span>Keep your everyday business moving.</span></div>
            </div>
        </div>
        </div>
    </div>
</header>