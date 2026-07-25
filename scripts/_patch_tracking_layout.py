from pathlib import Path
import re

p = Path(r"d:\laragon\www\billxiot_gps\resources\views\tracking\layouts\app.blade.php")
text = p.read_text(encoding="utf-8")

old_start = (
    '    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">\n'
    '    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">'
)
new_start = (
    '    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">\n'
    '    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.31.0/tabler-icons.min.css">\n'
    '    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">'
)
if old_start not in text:
    raise SystemExit("start block missing")
text = text.replace(old_start, new_start, 1)

pat = (
    r"    @include\('tracking\.partials\.design-tokens'\)\n"
    r"    <style>.*?body\.tracking-embed \.gt-module-page > h1:first-child \{ display: none; \}\n"
    r"    </style>"
)
repl = """    @include('tracking.partials.design-tokens')
    <link rel="stylesheet" href="{{ asset('css/tracking-chrome.css') }}?v={{ @filemtime(public_path('css/tracking-chrome.css')) }}">
    <style>
        body.tracking-shell {
            margin: 0;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .content-wrap {
            padding: 0;
            margin-top: var(--tracking-topbar-height);
            min-height: calc(100vh - var(--tracking-topbar-height));
        }

        body.tracking-shell:has(.tc-workspace-nav) {
            --tracking-nav-height: 48px;
        }

        body.tracking-shell.tc-module-nav-open {
            --tracking-nav-height: 52px;
        }

        body.tracking-shell.tc-module-nav-collapsed:has(.tc-workspace-nav__reveal) {
            --tracking-nav-height: 30px;
        }

        /* Embedded (inside a popup iframe): hide chrome so only the module content shows. */
        body.tracking-embed .tracking-topbar { display: none !important; }
        body.tracking-embed .content-wrap { margin-top: 0; padding: 0.85rem; }
        body.tracking-embed .gt-hub-nav { display: none !important; }
        body.tracking-embed .tc-workspace-nav { display: none !important; }
        body.tracking-embed .gt-module-page > h1:first-child { display: none; }
    </style>"""

new_text, n = re.subn(pat, repl, text, count=1, flags=re.S)
if n != 1:
    raise SystemExit(f"style block replace failed: {n}")
p.write_text(new_text, encoding="utf-8")
print("layout updated")
