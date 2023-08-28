<div class="fixed-nav-container">
    <nav class="hero-nav position-relative container mx-auto px-0">
        <ul class="nav w-100 list-unstyled align-items-center p-0">
            <li class="hero-nav__item">
                <a href="{{url('/')}}">
                    <img class="hero-nav__logo" src="{{$__logo_url}}" 
                        change-src-onscroll="{{$__logo_url}}" alt="logo" loading="lazy">
                </a>
                <!-- Don't remove this empty span -->
                <span class="mx-2"></span>
            </li>
            <li id="hero-menu" class="flex-grow-1 hero__nav-list hero__nav-list--mobile-menu ft-menu">
                <ul class="hero__menu-content nav flex-column flex-lg-row ft-menu__slider animated list-unstyled p-2 p-lg-0">
                    <li class="flex-grow-1">
                        <ul class="nav nav--lg-side flex-column-reverse flex-lg-row-reverse list-unstyled align-items-center p-0">
                            <li class="flex-grow-1">
                                <ul class="nav nav--lg-side flex-column-reverse flex-lg-row-reverse list-unstyled align-items-center p-0">
                                    <li class="hero-nav__item">
                                        <a href="{{ $navbar_btn['link'] }}" target="_blank" 
                                            class="btn btn-primary">
                                            {{$navbar_btn['text']}}
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            {{-- If already loggedin show dashboard link --}}
                            @if(Auth::check())
                                <li class="hero-nav__item">
                                    <a href="{{ route('home') }}" class="hero-nav__link">
                                        <strong>
                                            @lang('cms::lang.dashboard')
                                        </strong>
                                    </a>
                                </li>
                            @endif

                            @if (Route::has('login'))
                                @if(!Auth::check())
                                    <li class="hero-nav__item">
                                        <a href="{{ route('login') }}" class="hero-nav__link">
                                            <strong>
                                                @lang('lang_v1.login')
                                            </strong>
                                        </a>
                                    </li>
                                @endif
                            @endif

                            @if($__blog_count >= 1)
                                <li class="hero-nav__item">
                                    <a href="{{action([\Modules\Cms\Http\Controllers\CmsController::class, 'getBlogList'])}}" class="hero-nav__link">
                                        {{__('cms::lang.blogs')}}
                                    </a>
                                </li>
                            @endif
                            @if(count($__pages) > 0)
                                <li class="hero-nav__item hero-nav__item--with-dropdown">
                                    <span class="hero-nav__link" tabindex="1" role="button">
                                        <span class="flex-grow-1 me-2">
                                            Pages
                                        </span>
                                        <svg class="hero-nav__item-chevron bi bi-chevron-down" width=".8em" height=".8em" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"></path>
                                        </svg>
                                    </span>
                                    <div class="hero-nav__dropdown dropdown dropdown--of-1-columns">
                                        <div class="row flex-wrap">
                                            <div class="col-lg-12">
                                                @foreach($__pages as $page)
                                                    <a href="{{ action([\Modules\Cms\Http\Controllers\CmsPageController::class, 'showPage'], ['page' => $page->slug]) }}" class="dropdown__link">
                                                        <!-- Don't remove this empty "span" -->
                                                        <span class="mx-2"></span>
                                                        <!-- ------------------------------ -->
                                                        <div class="dropdown__item">
                                                            <span class="dropdown__item-title">
                                                                {{$page->title}}
                                                            </span>
                                                        </div>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endif
                            <li class="hero-nav__item">
                                <a href="{{route('cms.contact.us')}}" class="hero-nav__link">Contact us</a>
                            </li>
                            @if(Route::has('pricing') && config('app.env') != 'demo')
                                <li class="hero-nav__item">
                                    <a href="{{ action([\Modules\Superadmin\Http\Controllers\PricingController::class, 'index']) }}" class="hero-nav__link">
                                        @lang('superadmin::lang.pricing')
                                    </a>
                                </li>
                            @endif
                            <li class="hero-nav__item">
                                <a href="{{ url('/') }}" class="hero-nav__link">
                                   Home
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
                <button close-nav-menu="" class="ft-menu__close-btn animated">
                    <svg class="bi bi-x" width="1em" height="1em" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M11.854 4.146a.5.5 0 010 .708l-7 7a.5.5 0 01-.708-.708l7-7a.5.5 0 01.708 0z" clip-rule="evenodd"></path>
                        <path fill-rule="evenodd" d="M4.146 4.146a.5.5 0 000 .708l7 7a.5.5 0 00.708-.708l-7-7a.5.5 0 00-.708 0z" clip-rule="evenodd"></path>
                    </svg>
                </button>
            </li>
            <li class="hero-nav__item d-lg-none d-flex flex-row-reverse">
                <button open-nav-menu="" class="text-center px-2">
                    <i class="fas fa-bars"></i>
                </button>
            </li>
        </ul>
    </nav>
</div>