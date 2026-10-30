<nav class="brand-navigation" id="public-navigation" aria-label="Main navigation">
    <a class="brand-wordmark" href="{{ request()->getBaseUrl() ?: '/' }}" aria-label="{{ config('app.name') }} home">
        <img class="brand-header-logo" src="{{ config('branding.logo') ?: asset('img/logo-small.png') }}" alt="{{ config('app.name') }}">
    </a>
    <button class="brand-menu-toggle" type="button" aria-controls="public-navigation-links" aria-expanded="false">Menu <span aria-hidden="true">&#9776;</span></button>
    <div class="brand-navigation-links" id="public-navigation-links">
        <a href="{{ request()->getBaseUrl() ?: '/' }}" @if(request()->is('/')) aria-current="page" @endif>Home</a>
        @if(Route::has('pricing') && config('app.env') != 'demo')
            <a href="{{ route('pricing', [], false) }}" @if(request()->routeIs('pricing')) aria-current="page" @endif>@lang('superadmin::lang.pricing')</a>
        @endif
        @if(Route::has('cms.contact.us'))
            <a href="{{ route('cms.contact.us', [], false) }}" @if(request()->routeIs('cms.contact.us')) aria-current="page" @endif>Contact us</a>
        @endif
        @if(!empty($__blog_count))
            <a href="{{ action([\Modules\Cms\Http\Controllers\CmsController::class, 'getBlogList'], [], false) }}">@lang('cms::lang.blogs')</a>
        @endif
        @if(isset($__pages) && count($__pages) > 0)
            <details class="brand-pages">
                <summary>Pages</summary>
                <ul>
                    @foreach($__pages as $nav_page)
                        <li><a href="{{ action([\Modules\Cms\Http\Controllers\CmsPageController::class, 'showPage'], ['page' => $nav_page->slug], false) }}">{{ $nav_page->title }}</a></li>
                    @endforeach
                </ul>
            </details>
        @endif
        @if(Auth::check())
            <a href="{{ route('home', [], false) }}">@lang('cms::lang.dashboard')</a>
        @else
            <a href="{{ route('login', [], false) }}" @if(request()->is('login')) aria-current="page" @endif>@lang('business.sign_in')</a>
        @endif
        @if(config('constants.SHOW_REPAIR_STATUS_LOGIN_SCREEN') && Route::has('repair-status'))
            <a href="{{ route('repair-status', [], false) }}">@lang('repair::lang.repair_status')</a>
        @endif
        @if(Route::has('member_scanner'))
            <a href="{{ action([\Modules\Gym\Http\Controllers\MemberController::class, 'member_scanner'], [], false) }}">@lang('gym::lang.gym_member_profile')</a>
        @endif
        @if(!empty($__site_details['btns']['navbar']['text']))
            <a class="brand-register" href="{{ $__site_details['btns']['navbar']['link'] ?? route('business.getRegister', [], false) }}">{{ $__site_details['btns']['navbar']['text'] }}</a>
        @elseif(config('constants.allow_registration'))
            <a class="brand-register" href="{{ route('business.getRegister', [], false) }}">@lang('business.register')</a>
        @endif
    </div>
</nav>
<script>
    (function() {
        var navigation = document.getElementById('public-navigation');
        var toggle = navigation.querySelector('.brand-menu-toggle');
        var desktop = window.matchMedia('(min-width: 992px)');
        function closeMenu() {
            navigation.classList.remove('brand-menu-open');
            toggle.setAttribute('aria-expanded', 'false');
        }
        toggle.addEventListener('click', function() {
            var open = navigation.classList.toggle('brand-menu-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        navigation.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                navigation.querySelectorAll('details[open]').forEach(function(details) {
                    details.open = false;
                });
                if (!desktop.matches && navigation.classList.contains('brand-menu-open')) {
                    closeMenu();
                    toggle.focus();
                }
            }
        });
        desktop.addEventListener('change', closeMenu);
    })();
</script>
