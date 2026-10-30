<?php

namespace Modules\Cms\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Cms\Entities\CmsPage;
use Modules\Cms\Utils\CmsSeo;

class SeoController extends Controller
{
    public function sitemap()
    {
        $urls = [
            url('/') => null,
            url('c/contact-us') => null,
            url('c/blogs') => null,
        ];

        $pages = CmsPage::where('is_enabled', 1)
            ->where(function ($query) {
                $query->where('type', 'blog')
                    ->orWhere(function ($query) {
                        $query->where('type', 'page')
                            ->where(function ($query) {
                                $query->whereNull('layout')->orWhereIn('layout', ['home', 'contact']);
                            });
                    });
            })
            ->orderBy('id')
            ->get();

        foreach ($pages as $page) {
            $urls[CmsSeo::pageUrl($page)] = $page->updated_at ? $page->updated_at->toAtomString() : null;
        }

        return response()->view('cms::frontend.seo.sitemap', compact('urls'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots()
    {
        $rules = ['User-agent: *'];
        foreach (['cms', 'superadmin', 'home', 'login', 'register', 'business/register', 'repair-status'] as $path) {
            $rules[] = 'Disallow: '.parse_url(url($path), PHP_URL_PATH);
        }
        $rules[] = '';
        $rules[] = 'Sitemap: '.url('sitemap.xml');

        return response(implode("\n", $rules)."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
