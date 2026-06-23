# FalconEyeGPS branding assets

Generated from `scripts/extract-brand-assets.py` (source mockup in `source-mockup.png`).

## Folders

| Folder | Contents |
|--------|----------|
| `web/` | Horizontal logo (light + dark), falcon pin icon |
| `mobile/` | Splash logos, app icon copy, mobile-oriented exports |
| `favicon/` | `favicon.ico`, PNG sizes 16–256, `apple-touch-icon.png` |
| `app-icon/` | Square launcher masters `icon-1024.png`, `icon-512.png` |

## Regenerate

```bash
python scripts/extract-brand-assets.py
```

Place an updated combined mockup at the path in the script (`SRC`) or update `SRC` in the script.

## Laravel config

Paths are set in `config/branding.php` and used by `partials/brand-logo.blade.php` and `partials/seo-meta.blade.php`.

Legacy copies are also written to `public/images/` for backward compatibility.
