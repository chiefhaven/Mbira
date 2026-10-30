<?php

namespace Modules\Cms\Http\Controllers;

use Illuminate\Routing\Controller;

class CmsAssetController extends Controller
{
    public const ASSETS = [
        'css/cms.css' => 'text/css',
        'css/homepage.css' => 'text/css',
        'js/cms.js' => 'application/javascript',
        'img/contact.jpg' => 'image/jpeg',
        'img/default.png' => 'image/png',
        'img/home.png' => 'image/png',
    ];

    public function show(string $asset)
    {
        abort_unless(isset(self::ASSETS[$asset]), 404);
        $path = dirname(__DIR__, 2).'/Resources/assets/'.$asset;
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => self::ASSETS[$asset],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=0, must-revalidate',
        ]);
    }
}
