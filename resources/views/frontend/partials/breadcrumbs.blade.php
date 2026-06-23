@php
    use App\Support\Seo;

    $items = $items ?? [];
    if (count($items) < 2) {
        return;
    }
    $schemaItems = [];
@endphp

<nav aria-label="{{ __('seo.breadcrumbs.home') }}" class="mb-6">
    <ol class="flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
        @foreach($items as $index => $item)
            @php
                $schemaItems[] = [
                    'label' => $item['label'],
                    'url' => $item['url'] ?? null,
                ];
            @endphp
            <li class="flex items-center gap-2">
                @if($index > 0)
                    <span aria-hidden="true" class="text-slate-400">/</span>
                @endif
                @if(!empty($item['url']) && $index < count($items) - 1)
                    <a href="{{ $item['url'] }}" class="hover:text-sky-600 dark:hover:text-sky-400 transition-colors">{{ $item['label'] }}</a>
                @else
                    <span class="text-slate-700 dark:text-slate-200 font-medium" aria-current="page">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>

@push('json-ld')
<script type="application/ld+json">{!! json_encode(Seo::breadcrumbSchema($schemaItems), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush
