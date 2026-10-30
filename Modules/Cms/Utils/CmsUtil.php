<?php

namespace Modules\Cms\Utils;

use App\Utils\Util;
use Modules\Cms\Entities\CmsPage;

class CmsUtil extends Util
{
    public static function assetUrl(string $asset): string
    {
        if (!isset(\Modules\Cms\Http\Controllers\CmsAssetController::ASSETS[$asset])) {
            throw new \InvalidArgumentException('Unknown CMS asset.');
        }
        $path = dirname(__DIR__).'/Resources/assets/'.$asset;

        return asset('modules/cms/'.$asset).'?v='.substr(hash_file('sha256', $path), 0, 12);
    }

    public static function relativeButtonLink(string $link): string
    {
        $link = trim($link);
        if ($link === '' || $link[0] === '#' || $link[0] === '?' || preg_match('~^(mailto:|tel:)~i', $link)) {
            return $link;
        }

        $parts = parse_url($link);
        if ($parts === false) {
            throw new \InvalidArgumentException('Invalid CMS button link.');
        }
        $path = '/'.ltrim($parts['path'] ?? '', '/');
        $base = request()->getBaseUrl();
        if ($base !== '' && ($path === $base || str_starts_with($path, $base.'/'))) {
            $path = substr($path, strlen($base)) ?: '/';
        } elseif ($path === '/public' || str_starts_with($path, '/public/')) {
            // Older CMS settings included the server's public directory in URLs.
            $path = substr($path, 7) ?: '/';
        }

        return $base.$path
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }

    public static function relativeButtons(array $buttons): array
    {
        foreach ($buttons as &$button) {
            if (isset($button['link'])) {
                $button['link'] = self::relativeButtonLink($button['link']);
            }
        }

        return $buttons;
    }

    public function findIdFromGivenUrl($url)
    {
        return last(explode('-', $url));
    }

    public function getPageByType($type = 'page')
    {
        $posts = CmsPage::where('type', $type)
                ->where('is_enabled', 1)
                ->orderBy('priority', 'desc')
                ->get();

        return $posts;
    }

    public function getPageByLayout($layout)
    {
        $page = CmsPage::with(['pageMeta'])
                ->where('type', 'page')
                ->where('layout', $layout)
                ->first();

        return $page;
    }
}
