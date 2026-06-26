# BillX GPS — Production deployment

## Before you go live

1. Copy `.env.production.example` → `.env` on the server
2. Set `APP_DEBUG=false`, `APP_ENV=production`, HTTPS `APP_URL`
3. Generate `APP_KEY`: `php artisan key:generate`
4. Use MySQL (not sqlite), strong `ADMIN_PASSWORD` on first migrate
5. Configure SMTP, reCAPTCHA, Google Maps, Pusher, Firebase
6. Place Firebase service account JSON in `storage/app/firebase/` (never commit)
7. Set `TRACKING_INGEST_AUTO_CREATE_DEVICES=false` (devices must exist in admin)
8. Do **not** run `DemoSeeder` on production

## Deploy

```bash
git pull
bash scripts/deploy-production.sh
```

This runs: `composer install --no-dev`, `npm ci && npm run build`, `storage:link`, `php artisan app:deploy`.

## Server processes (required)

**Cron** (scheduler — live map broadcast, connectivity checks):

```cron
* * * * * cd /path/to/billxiot_gps && php artisan schedule:run >> /dev/null 2>&1
```

**Queue worker** (`QUEUE_CONNECTION=database`):

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

Use Supervisor or systemd for the queue worker.

## Health check

```bash
curl -fsS https://your-domain.com/up
```

Returns `200` when DB is reachable.

## Cache commands (production)

Use safe deploy commands only:

```bash
php artisan app:deploy          # migrate + safe optimize
php artisan app:recover --rebuild   # if cache issues after deploy
```

Avoid bare `php artisan optimize:clear` on live traffic (can log users out).

## Mobile app

See `gps_tracker_pro_mobile_billxiot/README.md` for Android release build.

API base URL: `https://billxiotgps.com/api`

Add Android **release SHA-1** to Firebase and Google Maps API key restrictions.

## Nginx (recommended)

- Force HTTPS
- `client_max_body_size` for avatar uploads
- Trust proxy headers if behind Cloudflare/load balancer
