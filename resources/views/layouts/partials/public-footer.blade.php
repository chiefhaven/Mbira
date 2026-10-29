<footer class="brand-footer">
    <div class="brand-footer-inner">
        <div class="brand-footer-identity">
            <a href="{{ url('/') }}" class="brand-footer-name">{{ config('app.name') }}</a>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
        <nav class="brand-footer-links" aria-label="Footer navigation">
            <a href="{{ url('/') }}">Home</a>
            @if(Route::has('pricing') && config('app.env') != 'demo')
                <a href="{{ route('pricing') }}">@lang('superadmin::lang.pricing')</a>
            @endif
            @if(Route::has('cms.contact.us'))
                <a href="{{ route('cms.contact.us') }}">Contact us</a>
            @endif
            @if(Auth::check())
                <a href="{{ route('home') }}">@lang('cms::lang.dashboard')</a>
            @else
                <a href="{{ route('login') }}">@lang('business.sign_in')</a>
            @endif
        </nav>
        @if(!empty($__site_details['follow_us']))
            <nav class="brand-footer-social" aria-label="Social media">
                @foreach(['facebook' => 'Facebook', 'instagram' => 'Instagram', 'twitter' => 'Twitter', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube'] as $network => $label)
                    @if(!empty($__site_details['follow_us'][$network]))
                        <a href="{{ $__site_details['follow_us'][$network] }}" target="_blank" rel="noopener noreferrer">{{ $label }}</a>
                    @endif
                @endforeach
            </nav>
        @endif
        @if(config('branding.footer'))
            <p class="brand-footer-custom">{{ config('branding.footer') }}</p>
        @endif
    </div>
</footer>
