# BillXiot GPS branding assets

Web logos live in `web/`:

| File | Use |
|------|-----|
| `logo_en.png` | Light backgrounds — BILLX wordmark + blue **GPS** suffix |
| `logo.png` | Dark backgrounds — white wordmark + blue **GPS** suffix |
| `logo_billx_only.png` | Original BILLX wordmark (source for regeneration) |

Favicons are generated from `logo_en.png` into `favicon/`.

## Regenerate logos (add / refresh GPS suffix)

```bash
python scripts/compose-billxiot-gps-logo.py
```

This updates web favicons and syncs copies to `gps_tracker_pro_mobile_billxiot/assets/branding/`.
Then in the mobile project: `dart run flutter_launcher_icons`.

## Laravel config

Paths are set in `config/branding.php` and used by `partials/brand-logo.blade.php` and `partials/seo-meta.blade.php`.

Brand display name comes from `APP_BRAND_NAME` (falls back to `APP_NAME`).
