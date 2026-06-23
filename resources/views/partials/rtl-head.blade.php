@if(($isRtl ?? false) || ($htmlDir ?? 'ltr') === 'rtl')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/rtl.css') }}?v={{ @filemtime(public_path('css/rtl.css')) }}">
@endif
