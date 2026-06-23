<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $lastmod = now()->toAtomString();
        $entries = [];

        foreach (config('seo.sitemap', []) as $item) {
            $path = $item['path'] ?? '/';
            $alternates = [];
            foreach (Seo::LOCALES as $locale) {
                $alternates[] = [
                    'lang' => $locale,
                    'href' => Seo::localizedUrl($path, $locale),
                ];
            }

            $entries[] = [
                'loc' => Seo::localizedUrl($path, 'en'),
                'lastmod' => $lastmod,
                'changefreq' => $item['changefreq'] ?? 'monthly',
                'priority' => $item['priority'] ?? '0.5',
                'alternates' => $alternates,
            ];
        }

        return response()
            ->view('sitemap', ['entries' => $entries])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
