<link rel="stylesheet" href="{{ asset('css/brand-logo.css') }}?v={{ filemtime(public_path('css/brand-logo.css')) }}">
@if(($htmlDir ?? 'ltr') === 'rtl')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
@else
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
@endif
<script>
    document.documentElement.lang = @json($htmlLang ?? 'en');
    document.documentElement.dir = @json($htmlDir ?? 'ltr');
</script>
