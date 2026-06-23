@php
    use App\Support\Seo;
    use Illuminate\Support\Str;

    $routeKey = Seo::routeKey();
    $pageSeo = Seo::pageMeta($routeKey);
    $brand = config('branding.name');
    $siteUrl = rtrim(config('app.url'), '/');
    $ogImagePath = config('seo.og_image', config('branding.app_icon'));
    $ogImageUrl = url($ogImagePath);
    $faviconIco = asset(config('branding.favicon'));
    $favicon32 = asset(config('branding.favicon_png', 'branding/favicon/favicon-32x32.png'));
    $favicon16 = asset('branding/favicon/favicon-16x16.png');
    $appleTouch = asset(config('branding.apple_touch_icon', 'branding/favicon/apple-touch-icon.png'));
    $themeColor = config('branding.theme_color');

    $sectionTitle = trim($__env->yieldContent('title'));
    $sectionDescription = trim($__env->yieldContent('description'));
    $pageTitle = $seoTitle ?? ($sectionTitle !== '' ? $sectionTitle : Seo::title($routeKey));
    $rawDescription = $seoDescription ?? ($sectionDescription !== '' ? $sectionDescription : Seo::description($routeKey));
    $description = Str::limit(strip_tags($rawDescription), 160, '');
    $keywords = $seoKeywords ?? Seo::keywords($routeKey);

    $hreflang = Seo::hreflangUrls($routeKey);
    $canonical = $seoCanonical ?? $hreflang[app()->getLocale()] ?? url()->current();
    $ogType = $seoOgType ?? 'website';
    $locale = app()->getLocale();
    $ogLocale = $locale === 'ar' ? 'ar_SA' : 'en_US';
    $ogLocaleAlt = $locale === 'ar' ? 'en_US' : 'ar_SA';

    $currentPath = '/'.ltrim(request()->path(), '/');
    $shouldNoindex = $seoRobotsNoindex ?? false;
    if (! $shouldNoindex) {
        foreach (config('seo.noindex_prefixes', []) as $prefix) {
            if ($prefix !== '/' && Str::startsWith($currentPath, $prefix)) {
                $shouldNoindex = true;
                break;
            }
        }
    }
    $robots = $shouldNoindex ? 'noindex, nofollow' : ($seoRobots ?? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1');

    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph' => array_values(array_filter([
            Seo::organizationSchema(),
            [
                '@type' => 'WebSite',
                '@id' => $siteUrl.'/#website',
                'url' => $siteUrl.'/',
                'name' => $brand,
                'description' => config('branding.seo.default_description'),
                'publisher' => ['@id' => $siteUrl.'/#organization'],
                'inLanguage' => ['en', 'ar'],
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => $siteUrl.'/help?lang=en&q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
            [
                '@type' => 'WebPage',
                '@id' => $canonical.'#webpage',
                'url' => $canonical,
                'name' => $pageTitle,
                'description' => $description,
                'isPartOf' => ['@id' => $siteUrl.'/#website'],
                'inLanguage' => $locale,
            ],
            [
                '@type' => 'SoftwareApplication',
                '@id' => $siteUrl.'/#software',
                'name' => $brand,
                'applicationCategory' => 'BusinessApplication',
                'applicationSubCategory' => 'Fleet Management Software',
                'operatingSystem' => 'Android, Web Browser',
                'url' => $siteUrl.'/',
                'image' => $ogImageUrl,
                'description' => config('branding.seo.default_description'),
                'offers' => [
                    '@type' => 'Offer',
                    'url' => $siteUrl.'/pricing?lang='.$locale,
                    'priceCurrency' => 'USD',
                    'availability' => 'https://schema.org/InStock',
                    'description' => 'Fleet GPS tracking plans — contact for pricing',
                ],
                'featureList' => 'Live GPS tracking, fleet map, route history, geofencing, speed alerts, Android mobile app',
            ],
            $routeKey === 'pricing' ? [
                '@type' => 'Product',
                'name' => $brand.' Fleet Tracking Platform',
                'description' => $description,
                'brand' => ['@type' => 'Brand', 'name' => $brand],
                'category' => 'Fleet Management Software',
                'url' => $canonical,
                'offers' => [
                    '@type' => 'AggregateOffer',
                    'url' => $canonical,
                    'priceCurrency' => 'USD',
                    'availability' => 'https://schema.org/InStock',
                    'offerCount' => '3',
                ],
            ] : null,
        ])),
    ];
@endphp

<title>{{ $pageTitle }}</title>

<meta name="description" content="{{ $description }}">
<meta name="keywords" content="{{ $keywords }}">
<meta name="author" content="{{ $brand }}">
<meta name="robots" content="{{ $robots }}">
<meta name="googlebot" content="{{ $robots }}">
<link rel="canonical" href="{{ $canonical }}">

@foreach($hreflang as $lang => $url)
<link rel="alternate" hreflang="{{ $lang }}" href="{{ $url }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $hreflang['en'] ?? $canonical }}">

<link rel="icon" href="{{ $faviconIco }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ $favicon32 }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ $favicon16 }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ $appleTouch }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="{{ $themeColor }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ $brand }}">
<meta name="application-name" content="{{ $brand }}">
<meta name="format-detection" content="telephone=no">

@if(config('seo.google_site_verification'))
<meta name="google-site-verification" content="{{ config('seo.google_site_verification') }}">
@endif
@if(config('seo.bing_site_verification'))
<meta name="msvalidate.01" content="{{ config('seo.bing_site_verification') }}">
@endif
@if(config('seo.yandex_site_verification'))
<meta name="yandex-verification" content="{{ config('seo.yandex_site_verification') }}">
@endif

<meta property="og:type" content="{{ $ogType }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $ogImageUrl }}">
<meta property="og:image:width" content="1024">
<meta property="og:image:height" content="1024">
<meta property="og:image:alt" content="{{ $brand }} — GPS tracking and fleet management">
<meta property="og:site_name" content="{{ $brand }}">
<meta property="og:locale" content="{{ $ogLocale }}">
<meta property="og:locale:alternate" content="{{ $ogLocaleAlt }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $ogImageUrl }}">
<meta name="twitter:image:alt" content="{{ $brand }} GPS fleet tracking">
@if(config('seo.twitter_handle'))
<meta name="twitter:site" content="{{ config('seo.twitter_handle') }}">
@endif

<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
