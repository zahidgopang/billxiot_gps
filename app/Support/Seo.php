<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

class Seo
{
    public const LOCALES = ['en', 'ar'];

    public static function routeKey(): string
    {
        $name = Route::currentRouteName();

        return $name ?: 'home';
    }

    public static function pageMeta(?string $key = null): array
    {
        $key = $key ?? self::routeKey();
        $meta = trans('seo.pages.'.$key);

        return is_array($meta) ? $meta : [];
    }

    public static function title(?string $key = null): string
    {
        $meta = self::pageMeta($key);

        return $meta['title'] ?? config('branding.seo.default_title');
    }

    public static function description(?string $key = null): string
    {
        $meta = self::pageMeta($key);

        return $meta['description'] ?? config('branding.seo.default_description');
    }

    public static function keywords(?string $key = null): string
    {
        $meta = self::pageMeta($key);

        return $meta['keywords'] ?? config('branding.seo.keywords');
    }

    public static function path(?string $key = null): string
    {
        $meta = self::pageMeta($key);

        return $meta['path'] ?? '/';
    }

    public static function localizedUrl(string $path, string $locale): string
    {
        $base = rtrim(config('app.url'), '/');
        $path = $path === '/' ? '' : '/'.ltrim($path, '/');
        $url = $base.$path;

        return $url.(str_contains($url, '?') ? '&' : '?').'lang='.$locale;
    }

    public static function hreflangUrls(?string $key = null): array
    {
        $path = self::path($key);
        $urls = [];
        foreach (self::LOCALES as $locale) {
            $urls[$locale] = self::localizedUrl($path, $locale);
        }

        return $urls;
    }

    public static function breadcrumbSchema(array $items): array
    {
        $elements = [];
        foreach ($items as $index => $item) {
            $entry = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['label'],
            ];
            if (! empty($item['url'])) {
                $entry['item'] = $item['url'];
            }
            $elements[] = $entry;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    public static function faqSchema(array $faqs): array
    {
        $entities = [];
        foreach ($faqs as $faq) {
            if (empty($faq['q']) || empty($faq['a'])) {
                continue;
            }
            $entities[] = [
                '@type' => 'Question',
                'name' => $faq['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['a'],
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    public static function organizationSchema(): array
    {
        $siteUrl = rtrim(config('app.url'), '/');

        return [
            '@type' => 'Organization',
            '@id' => $siteUrl.'/#organization',
            'name' => config('branding.name'),
            'url' => $siteUrl.'/',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => url(config('branding.app_icon')),
            ],
            'description' => config('branding.seo.default_description'),
            'email' => config('contact.email'),
            'telephone' => config('contact.whatsapp'),
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'email' => config('contact.email'),
                'telephone' => config('contact.whatsapp'),
                'availableLanguage' => ['English', 'Arabic'],
            ],
            'sameAs' => config('seo.social_profiles', []),
        ];
    }
}
