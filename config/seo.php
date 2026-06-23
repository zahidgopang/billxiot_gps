<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Search engine verification (set in .env when you register the site)
    |--------------------------------------------------------------------------
    */
    'google_site_verification' => env('GOOGLE_SITE_VERIFICATION'),
    'bing_site_verification' => env('BING_SITE_VERIFICATION'),
    'yandex_site_verification' => env('YANDEX_SITE_VERIFICATION'),

    'google_analytics_id' => env('GOOGLE_ANALYTICS_ID'),
    'google_tag_manager_id' => env('GOOGLE_TAG_MANAGER_ID'),
    'meta_pixel_id' => env('META_PIXEL_ID'),

    /** Default Open Graph / Twitter share image (absolute path under public/). */
    'og_image' => env('SEO_OG_IMAGE', 'branding/app-icon/icon-1024.png'),

    'twitter_handle' => env('SEO_TWITTER_HANDLE'),

    'social_profiles' => array_values(array_filter([
        env('SEO_FACEBOOK_URL'),
        env('SEO_TWITTER_URL'),
        env('SEO_LINKEDIN_URL'),
        env('SEO_YOUTUBE_URL'),
    ])),

    /** Public marketing URLs included in sitemap.xml */
    'sitemap' => [
        ['path' => '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
        ['path' => '/pricing', 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['path' => '/contact', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['path' => '/android-app', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['path' => '/about', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['path' => '/company', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['path' => '/help', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['path' => '/docs', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['path' => '/blog', 'priority' => '0.6', 'changefreq' => 'weekly'],
        ['path' => '/careers', 'priority' => '0.5', 'changefreq' => 'monthly'],
        ['path' => '/press', 'priority' => '0.5', 'changefreq' => 'monthly'],
        ['path' => '/api', 'priority' => '0.5', 'changefreq' => 'monthly'],
        ['path' => '/status', 'priority' => '0.4', 'changefreq' => 'daily'],
        ['path' => '/privacy', 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['path' => '/terms', 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['path' => '/security', 'priority' => '0.4', 'changefreq' => 'yearly'],
        ['path' => '/cookies', 'priority' => '0.3', 'changefreq' => 'yearly'],
    ],

    /** Path prefixes that must not be indexed */
    'noindex_prefixes' => [
        '/admin',
        '/client',
        '/user',
        '/dashboard',
        '/profile',
        '/login',
        '/register',
        '/demo',
        '/locale',
        '/api/',
        '/device/',
    ],

];
