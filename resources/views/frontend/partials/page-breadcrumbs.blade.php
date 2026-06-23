@php
    use App\Support\Seo;

    $key = Seo::routeKey();
    if ($key === 'home') {
        return;
    }
    $locale = app()->getLocale();
    $crumbKey = 'seo.breadcrumbs.'.$key;
    $label = __($crumbKey);
    if ($label === $crumbKey) {
        $label = Seo::pageMeta($key)['h1'] ?? ucfirst($key);
    }
@endphp

@include('frontend.partials.breadcrumbs', [
    'items' => [
        ['label' => __('seo.breadcrumbs.home'), 'url' => Seo::localizedUrl('/', $locale)],
        ['label' => $label, 'url' => null],
    ],
])
