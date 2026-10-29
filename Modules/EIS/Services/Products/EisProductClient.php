<?php

namespace Modules\EIS\Services\Products;

use Illuminate\Support\Facades\Http;
use Modules\EIS\Services\EisSubscriptionAccess;

class EisProductClient
{
    /** Shared with sync scoping: this is the exact value sent, with null-only fallback. */
    public static function requestSiteId($settings)
    {
        return \Modules\EIS\Services\Terminal\TerminalContext::site($settings);
    }

    public function fetch($settings, int $page = 1)
    {
        app(EisSubscriptionAccess::class)->assertAllowed((int) ($settings->business_id ?? 0));

        return Http::baseUrl(config('eis.base_url'))
            ->withToken($settings->jwt_token)
            ->post('/utilities/get-terminal-site-products', [
                'tin'    => $settings->tpin,
                'siteId' => self::requestSiteId($settings),
                'page'   => $page,
            ])
            ->throw()
            ->json();
    }
}