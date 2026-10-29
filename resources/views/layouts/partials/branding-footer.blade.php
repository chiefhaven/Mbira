@if (config('branding.footer'))
    {{ config('branding.footer') }}
@else
    {{ config('app.name', 'ultimatePOS') }} - V{{ config('author.app_version') }} | Copyright &copy; {{ date('Y') }} All rights reserved.
@endif
