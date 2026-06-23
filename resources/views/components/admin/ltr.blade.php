@props(['tag' => 'span'])
<{{ $tag }} {{ $attributes->merge(['class' => trim('admin-ltr ' . ($attributes->get('class') ?? ''))]) }} dir="ltr">{{ $slot }}</{{ $tag }}>
