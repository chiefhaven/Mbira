<?php

namespace Modules\Cms\Utils;

use Illuminate\Support\Str;
use Modules\Cms\Entities\CmsPage;

class CmsSeo
{
    public static function pageUrl(CmsPage $page): string
    {
        if ($page->type === 'blog') {
            return url('c/blog/'.$page->slug.'-'.$page->id);
        }

        if ($page->layout === 'home') {
            return url('/');
        }

        if ($page->layout === 'contact') {
            return url('c/contact-us');
        }

        return url('c/page/'.$page->slug);
    }

    public static function metadata(?CmsPage $page, string $title): array
    {
        $siteName = config('app.name');
        $description = self::plainText($page->meta_description ?? '');
        if ($description === '') {
            $description = self::plainText($page->content ?? '');
        }
        if ($description === '') {
            $description = $title.' - '.$siteName.'. Explore our business management software, services and updates.';
        }

        return [
            'title' => self::plainText($title).' | '.$siteName,
            'description' => mb_strlen($description) > 160 ? Str::limit($description, 157, '...') : $description,
            'url' => $page ? self::pageUrl($page) : url()->current(),
            'type' => $page && $page->type === 'blog' ? 'article' : 'website',
            'image' => $page && $page->feature_image
                ? $page->feature_image_url
                : url(config('branding.logo') ?: 'img/logo-small.png'),
            'site_name' => $siteName,
        ];
    }

    private static function plainText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
    }
}
