# Production health check (`/up`)

## Expected responses

| Code | Meaning |
|------|---------|
| **200** | App booted and database reachable |
| **503** | App booted but database unreachable (degraded) |
| **500** | Laravel failed to boot — see logs below |

```bash
curl -sS https://falconeyegps.com/up
# {"status":"ok","checks":{"app":true,"database":true}}
```

Use a normal browser user-agent or add one; `RestrictScrapers` does **not** apply to `/up`.

## If you see HTTP 500

SSH to the server and run:

```bash
cd /var/www/gps-traccar

# 1. Latest error
tail -80 storage/logs/laravel.log

# 2. Can PHP run the app?
php artisan about

# 3. Hit PHP-FPM directly (bypass nginx SSL)
curl -sS -o /tmp/up.json -w "%{http_code}\n" http://127.0.0.1/up
cat /tmp/up.json

# 4. Permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache

# 5. Config cache after .env changes
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

## Common causes

1. **Missing or invalid `APP_KEY`** in `.env` → run `php artisan key:generate`
2. **Wrong DB credentials** (`DB_HOST`, `DB_DATABASE`, `DB_PASSWORD`)
3. **MySQL not running** → `sudo systemctl status mysql`
4. **PHP version** — Laravel 12 requires PHP **8.2+** (project lock may require **8.4**)
5. **`vendor/` missing** after deploy → `composer install --no-dev --optimize-autoloader`
6. **Maintenance mode** stuck → `php artisan up`

## Nginx

Ensure `location /` passes to `public/index.php` and does not block `/up`.
