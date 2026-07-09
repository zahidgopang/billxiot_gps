@php
    $htmlLang = app()->getLocale();
    $htmlDir = $htmlLang === 'ar' ? 'rtl' : 'ltr';
    $brand = config('branding.name', config('app.name'));
    $contactEmail = config('contact.email', config('mail.from.address'));
    $theme = $theme ?? 'slate';
    $themes = [
        'red' => ['bg' => '#FEF2F2', 'accent' => '#DC2626', 'soft' => '#FEE2E2', 'text' => '#991B1B'],
        'amber' => ['bg' => '#FFFBEB', 'accent' => '#D97706', 'soft' => '#FEF3C7', 'text' => '#92400E'],
        'blue' => ['bg' => '#EFF6FF', 'accent' => '#2563EB', 'soft' => '#DBEAFE', 'text' => '#1E40AF'],
        'purple' => ['bg' => '#F5F3FF', 'accent' => '#7C3AED', 'soft' => '#EDE9FE', 'text' => '#5B21B6'],
        'slate' => ['bg' => '#F8FAFC', 'accent' => '#475569', 'soft' => '#E2E8F0', 'text' => '#334155'],
        'teal' => ['bg' => '#F0FDFA', 'accent' => '#0D9488', 'soft' => '#CCFBF1', 'text' => '#115E59'],
        'indigo' => ['bg' => '#EEF2FF', 'accent' => '#4F46E5', 'soft' => '#E0E7FF', 'text' => '#3730A3'],
        'orange' => ['bg' => '#FFF7ED', 'accent' => '#EA580C', 'soft' => '#FFEDD5', 'text' => '#9A3412'],
    ];
    $palette = $themes[$theme] ?? $themes['slate'];
    $homeUrl = auth()->check() && Route::has('dashboard') ? route('dashboard') : url('/');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $htmlLang) }}" dir="{{ $htmlDir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('app.errors.generic_title')) — {{ $brand }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    @if($htmlDir === 'rtl')
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@500;600;700&display=swap" rel="stylesheet">
    @endif
    <style>
        :root {
            --err-bg: {{ $palette['bg'] }};
            --err-accent: {{ $palette['accent'] }};
            --err-soft: {{ $palette['soft'] }};
            --err-text: {{ $palette['text'] }};
            --err-card: #FFFFFF;
            --err-muted: #64748B;
            --err-border: #E2E8F0;
            --err-ink: #0F172A;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            background:
                radial-gradient(circle at top left, rgba(255,255,255,0.85), transparent 40%),
                radial-gradient(circle at bottom right, var(--err-soft), transparent 45%),
                var(--err-bg);
            color: var(--err-ink);
            font-family: {{ $htmlDir === 'rtl' ? "'Cairo', sans-serif" : "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" }};
        }
        .err-card {
            width: 100%;
            max-width: 34rem;
            background: var(--err-card);
            border: 1px solid var(--err-border);
            border-radius: 1.25rem;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
            padding: 2rem 1.75rem;
            text-align: center;
        }
        .err-icon {
            width: 5rem;
            height: 5rem;
            margin: 0 auto 1.25rem;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--err-soft);
            color: var(--err-accent);
            font-size: 2.25rem;
        }
        .err-code {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.75rem;
            border-radius: 999px;
            background: var(--err-soft);
            color: var(--err-text);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            margin-bottom: 0.85rem;
        }
        .err-title {
            margin: 0 0 0.65rem;
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--err-ink);
        }
        .err-message {
            margin: 0 0 1.25rem;
            color: var(--err-muted);
            font-size: 1rem;
            line-height: 1.55;
        }
        .err-detail {
            text-align: start;
            background: var(--err-bg);
            border: 1px solid var(--err-border);
            border-radius: 0.9rem;
            padding: 0.95rem 1rem;
            margin-bottom: 1.5rem;
            color: var(--err-text);
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .err-detail strong {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            margin-bottom: 0.35rem;
            color: var(--err-ink);
        }
        .err-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            justify-content: center;
        }
        .err-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            min-height: 2.6rem;
            padding: 0.65rem 1.1rem;
            border-radius: 0.75rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.92rem;
            border: 1px solid transparent;
            transition: background 0.15s ease, transform 0.15s ease;
            cursor: pointer;
        }
        .err-btn:hover { transform: translateY(-1px); }
        .err-btn--primary {
            background: var(--err-accent);
            color: #fff;
            flex: 1 1 12rem;
        }
        .err-btn--primary:hover { filter: brightness(0.95); color: #fff; }
        .err-btn--secondary {
            background: #fff;
            color: var(--err-ink);
            border-color: var(--err-border);
            flex: 1 1 8rem;
        }
        .err-btn--secondary:hover { background: #F8FAFC; color: var(--err-ink); }
        .err-footer {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--err-border);
            color: #94A3B8;
            font-size: 0.78rem;
        }
        @media (max-width: 480px) {
            .err-card { padding: 1.5rem 1.15rem; }
            .err-title { font-size: 1.45rem; }
            .err-btn { flex: 1 1 100%; }
        }
    </style>
    @yield('styles')
</head>
<body>
    <main class="err-card" role="alert">
        @yield('content')

        <div class="err-actions">
            @hasSection('actions')
                @yield('actions')
            @else
                <a href="{{ $homeUrl }}" class="err-btn err-btn--primary">
                    <i class="fas fa-home"></i>
                    {{ auth()->check() ? __('app.errors.go_dashboard') : __('app.errors.go_home') }}
                </a>
                @if(Route::has('contact'))
                    <a href="{{ route('contact') }}" class="err-btn err-btn--secondary">
                        <i class="fas fa-envelope"></i>
                        {{ __('app.errors.contact_support') }}
                    </a>
                @elseif(! empty($contactEmail))
                    <a href="mailto:{{ $contactEmail }}" class="err-btn err-btn--secondary">
                        <i class="fas fa-envelope"></i>
                        {{ __('app.errors.contact_support') }}
                    </a>
                @endif
            @endif
        </div>

        <div class="err-footer">
            &copy; {{ date('Y') }} {{ $brand }}
        </div>
    </main>
    @yield('scripts')
</body>
</html>
