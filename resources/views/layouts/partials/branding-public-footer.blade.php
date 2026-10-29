@if(!empty($public_footer))
    @include('layouts.partials.public-footer')
@elseif (config('branding.footer'))
    <footer class="text-center" style="padding: 16px;">{{ config('branding.footer') }}</footer>
@endif
