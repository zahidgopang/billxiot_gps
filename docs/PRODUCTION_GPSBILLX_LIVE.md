# Production live setup — https://gpsbillx.com

Realtime map flow:

```
GPS device → Traccar → POST https://gpsbillx.com/api/traccar/forward → Laravel → Reverb → browser / mobile
```

## 1. Deploy Laravel (Lightsail)

```bash
cd /path/to/billxiot_gps
git pull
bash scripts/deploy-production.sh
```

## 2. Generate forward secret (on server)

```bash
openssl rand -hex 32
```

Copy the output — use **the same string** in Laravel `.env` and Traccar `traccar.xml`.

## 3. Production `.env` (server)

Copy from `.env.production.example` if needed, then set at minimum:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://gpsbillx.com
PUBLIC_URL=https://gpsbillx.com

TRACCAR_BROADCAST_POSITIONS=true
TRACCAR_BROADCAST_MODE=forward
TRACCAR_FORWARD_SECRET=<paste openssl output>
TRACCAR_FORWARD_PROCESS_EVENTS=true
TRACCAR_FORWARD_EVENTS_DEBOUNCE_SECONDS=30
TRACCAR_FORWARD_BROADCAST_MIN_INTERVAL_SECONDS=2

BROADCAST_DRIVER=reverb
REVERB_CLIENT_ENABLED=true
REVERB_HOST=gpsbillx.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

SANCTUM_STATEFUL_DOMAINS=gpsbillx.com,www.gpsbillx.com
QUEUE_CONNECTION=database
```

Apply:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan traccar:forward-status
```

## 4. Traccar `traccar.xml`

Open `/opt/traccar/conf/traccar.xml` (or `/etc/traccar/traccar.xml`).

**Correct forward URL (no space):**

```xml
<entry key='forward.enable'>true</entry>
<entry key='forward.type'>json</entry>
<entry key='forward.json'>true</entry>
<entry key='forward.url'>https://gpsbillx.com/api/traccar/forward</entry>
<entry key='forward.header'>X-Traccar-Token: <same secret as TRACCAR_FORWARD_SECRET></entry>
```

**HTTP 405 in Traccar logs?** Traccar 6 defaults to `forward.type=url` (GET). Laravel expects POST JSON — add `forward.type=json` and restart Traccar.

See also: [gpsbillx-traccar-forward.xml.snippet](./production/gpsbillx-traccar-forward.xml.snippet)

Restart Traccar:

```bash
sudo systemctl restart traccar
sudo systemctl status traccar
```

## 5. Reverb (Supervisor / systemd)

Reverb must run continuously:

```bash
sudo systemctl status reverb
# or: php artisan reverb:start  (dev only — use Supervisor in production)
```

Nginx must proxy WebSocket to `REVERB_SERVER_PORT` (default 8080).

## 6. Cron

```cron
* * * * * cd /path/to/billxiot_gps && php artisan schedule:run >> /dev/null 2>&1
```

With `TRACCAR_BROADCAST_MODE=forward`, the heavy `traccar:broadcast-positions` job does **not** run.

## 7. Test

```bash
curl -sS -X POST https://gpsbillx.com/api/traccar/forward \
  -H "Content-Type: application/json" \
  -H "X-Traccar-Token: YOUR_SECRET" \
  -d '{"position":{"deviceId":1,"latitude":24.7,"longitude":46.6,"speed":0,"course":0,"fixTime":"2026-07-05T12:00:00.000+0000","attributes":{}},"device":{"id":1,"uniqueId":"DEVICE_IMEI"}}'
```

Expected: `{"ok":true,"broadcast":true}`

Or on server:

```bash
php artisan traccar:forward-status --test --device=1
```

## 8. Verify live map

1. Open https://gpsbillx.com/user/tracking (or admin tracking)
2. DevTools → Network: no repeated `live-json` when Reverb connected
3. Move a vehicle — marker updates within ~1–2 s

## Wrong URL (do not use)

```xml
<!-- WRONG — space breaks the URL -->
<entry key='forward.url'>https://gpsbillx.com/ /api/traccar/forward</entry>
```

Correct: `https://gpsbillx.com/api/traccar/forward`
