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

**Cron** (connectivity checks, maintenance — **not** required for realtime map when using forward mode):

```cron
* * * * * cd /path/to/billxiot_gps && php artisan schedule:run >> /dev/null 2>&1
```

### Realtime map (Reverb) without CPU-heavy DB polling

**Recommended:** Traccar pushes each GPS report to Laravel → Laravel broadcasts to Reverb. No `traccar:broadcast-positions` scheduler.

**.env:**

```env
TRACCAR_BROADCAST_POSITIONS=true
TRACCAR_BROADCAST_MODE=forward
TRACCAR_FORWARD_SECRET=your-long-random-secret
REVERB_CLIENT_ENABLED=true
BROADCAST_DRIVER=reverb
```

**Traccar** (`conf/traccar.xml` on the same server):

```xml
<entry key='forward.enable'>true</entry>
<entry key='forward.json'>true</entry>
<entry key='forward.url'>https://gpsbillx.com/api/traccar/forward</entry>
<entry key='forward.header'>X-Traccar-Token: your-long-random-secret</entry>
```

Restart Traccar after editing. Map clients (web + mobile) subscribe to Reverb `device.{id}` and receive `DeviceLocationUpdated` within ~1–2 seconds of each GPS report.

**Fallback** (if you cannot edit Traccar `traccar.xml`): keep broadcast **off** and use HTTP polling (maps still update every ~6s; ~16% CPU on Lightsail):

```env
TRACCAR_BROADCAST_POSITIONS=false
TRACCAR_BROADCAST_MODE=off
TRACCAR_SCHEDULE_POSITION_EVENTS=true
TRACCAR_POSITION_EVENTS_INTERVAL_MINUTES=5
TRACKING_LIVE_POLL_INTERVAL_MS=6000
TRACKING_LIVE_JSON_CACHE_SECONDS=3
```

Cron still runs a **light alert job every 5 minutes** (geofence/push) — not the heavy position broadcast.

**Optional:** `TRACCAR_BROADCAST_MODE=light` + `TRACCAR_BROADCAST_INTERVAL_SECONDS=60` if you want occasional WebSocket updates and accept higher CPU than `off`.

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
