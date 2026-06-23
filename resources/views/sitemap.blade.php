<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach($entries as $entry)
    <url>
        <loc>{{ $entry['loc'] }}</loc>
        <lastmod>{{ $entry['lastmod'] }}</lastmod>
        <changefreq>{{ $entry['changefreq'] }}</changefreq>
        <priority>{{ $entry['priority'] }}</priority>
        @foreach($entry['alternates'] as $alt)
        <xhtml:link rel="alternate" hreflang="{{ $alt['lang'] }}" href="{{ $alt['href'] }}"/>
        @endforeach
    </url>
@endforeach
</urlset>
