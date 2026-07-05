# Traccar → Laravel position forward

Realtime maps (web `/tracking` + mobile) use **Reverb** (`DeviceLocationUpdated` on channel `device.{id}`).

The recommended path is **not** polling `tc_positions` every 30s — Traccar pushes each GPS report to Laravel, Laravel broadcasts to Reverb immediately.

## 1. Laravel `.env`

```env
TRACCAR_BROADCAST_POSITIONS=true
TRACCAR_BROADCAST_MODE=forward
TRACCAR_FORWARD_SECRET=your-long-random-secret-min-32-chars
BROADCAST_DRIVER=reverb
REVERB_CLIENT_ENABLED=true
```

Then:

```bash
php artisan config:clear
php artisan traccar:forward-status
```

Generate a secret: `php -r "echo bin2hex(random_bytes(32));"`

## 2. Traccar `conf/traccar.xml`

Inside `<properties>` add (use the **same** secret as `.env`):

```xml
<entry key='forward.enable'>true</entry>
<entry key='forward.json'>true</entry>
<entry key='forward.url'>https://YOUR-DOMAIN/api/traccar/forward</entry>
<entry key='forward.header'>X-Traccar-Token: your-long-random-secret-min-32-chars</entry>
```

**Local Laragon example** (this project):

```xml
<entry key='forward.url'>https://zahid.billxiot_gps/api/traccar/forward</entry>
```

Restart Traccar after saving (`traccar.service` or Windows Traccar service).

## 3. Verify

```bash
php artisan traccar:forward-status --test
```

Expected: `HTTP 200` and `"broadcast":true`.

From Traccar server (optional):

```bash
curl -X POST "https://YOUR-DOMAIN/api/traccar/forward" \
  -H "Content-Type: application/json" \
  -H "X-Traccar-Token: YOUR_SECRET" \
  -d '{"device":{"id":1,"uniqueId":"IMEI"},"position":{"deviceId":1,"latitude":24.71,"longitude":46.67,"speed":10,"fixTime":"2026-01-01T12:00:00Z"}}'
```

## 4. Common problems

| Symptom | Cause | Fix |
|---------|--------|-----|
| Laravel log shows nothing | Traccar `forward.enable` false or wrong URL | Edit `traccar.xml`, restart Traccar |
| HTTP 401 | Secret mismatch | Same value in `.env` and `forward.header` |
| HTTP 200 `skipped: forward mode disabled` | `.env` not `forward` mode | `TRACCAR_BROADCAST_MODE=forward`, `config:clear` |
| HTTP 200 `broadcast:false` | Device id / IMEI not in Laravel | Device must exist; with `TRACCAR_UNIFIED_IDS=true`, Traccar `deviceId` = Laravel `devices.id` |
| Traccar cannot reach URL | Firewall / SSL / wrong host | Traccar must HTTP POST to Laravel; test curl from Traccar server |
| Map connected but frozen | Reverb not running | `php artisan reverb:start` (Supervisor in production) |

## 5. Fallback (no traccar.xml access)

```env
TRACCAR_BROADCAST_POSITIONS=false
TRACCAR_BROADCAST_MODE=off
TRACKING_LIVE_POLL_INTERVAL_MS=6000
```

Maps update via HTTP polling only (~6s). Higher server load with many vehicles.

## 6. Production

See [PRODUCTION_DEPLOY.md](./PRODUCTION_DEPLOY.md). Use `https://gpsbillx.com/api/traccar/forward` and run Reverb under Supervisor.
