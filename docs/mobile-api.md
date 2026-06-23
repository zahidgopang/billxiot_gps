# End-user mobile API

Base URL: `/api` (Laravel Sanctum bearer token).

**Audience:** end-user accounts only (`role = user`). Admin and client accounts receive `403`.

## Authentication

### POST `/api/login`

Body: `email`, `password`, optional `device_name`.

Checks: valid credentials, active account, at least one device with active subscription and valid payment (`paid` or `partial` on client invoice).

Error messages:

| Code | HTTP | Message |
|------|------|---------|
| `invalid_credentials` | 401 | Invalid credentials |
| `account_inactive` | 403 | Account inactive |
| `subscription_expired` | 402 | Subscription expired |
| `payment_due` | 402 | Payment due |

Success `data`: `token`, `token_type`, `user`, `permissions`, `accessible_devices`, `subscription`.

### POST `/api/logout`

Header: `Authorization: Bearer {token}`

## Protected routes

All routes below require:

```
Authorization: Bearer {token}
```

Middleware re-validates account, subscription, and payment on **every** request. Failure:

```json
{ "success": false, "message": "Subscription expired", "code": "subscription_expired" }
```

### Profile

| Method | Path |
|--------|------|
| GET | `/api/profile` |
| POST | `/api/profile/update` |
| POST | `/api/change-password` |

### Dashboard

| Method | Path |
|--------|------|
| GET | `/api/dashboard` |
| GET | `/api/dashboard/activity` |
| GET | `/api/dashboard/recent-vehicles` |

### Devices

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/devices` | List |
| GET | `/api/devices/{id}` | Detail |
| GET | `/api/devices/{id}/live` | Live position |
| GET | `/api/devices/{id}/history` | `from`, `to` query (Y-m-d) |
| GET | `/api/devices/{id}/route-summary` | Same date filters |
| GET | `/api/devices/{id}/events` | Trip / alert events |
| GET | `/api/devices/{id}/live-stream` | Pusher/Echo channel info |

### Geofences

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/geofences` | Read-only list for mobile |

### Push notifications (FCM)

Register the device FCM token after login (Sanctum required):

| Method | Path | Body |
|--------|------|------|
| POST | `/api/push-token` | `fcm_token` (required), `platform` (`android`/`ios`), `device_name` (optional) |
| DELETE | `/api/push-token` | `fcm_token` (required) — call on logout |

FCM payloads include `data.type` (e.g. `vehicle_moving`, `geofence_enter`, `device_offline`) plus `device_id`, `device_name`, `title`, `body`.

| Method | Path | Body | Notes |
|--------|------|------|-------|
| POST | `/api/push-test` | optional `title`, `body`, `fcm_token` | Sends test push to authenticated user |

Laravel sends pushes via Firebase HTTP v1 using a service account JSON file (see `.env`: `FIREBASE_CREDENTIALS`, `PUSH_NOTIFICATIONS_ENABLED`, `PUSH_EVENT_NOTIFICATIONS_ENABLED`).

Test from CLI: `php artisan push:test {user_id}`

### Alerts

| Method | Path |
|--------|------|
| GET | `/api/alerts` |
| GET | `/api/alerts/unread` |
| POST | `/api/alerts/read` | `alert_ids[]` |

### Map & export

| Method | Path |
|--------|------|
| GET | `/api/address?lat=&lng=` | Google reverse geocoding |
| GET | `/api/export/csv?device_id=&from=&to=` | CSV download |
| GET | `/api/export/gpx?device_id=&from=&to=` | GPX download |

## Map rendering (`map_rendering`)

Device list, detail, and live responses include `map_rendering` (see `App\Services\Mobile\MapRenderingSpec`). Native apps should use these values for vehicle size, pulse diameter, status colors, and GPS anchor so maps match the web tracker.

Full spec: [map-rendering-spec.md](./map-rendering-spec.md)

## Real-time tracking

1. Call `GET /api/devices/{id}/live-stream` for channel name `device.{id}` and event `DeviceLocationUpdated`.
2. Use Laravel Echo + Pusher (or compatible broadcaster) with Sanctum cookie/token via `POST /broadcasting/auth`.
3. Positions are also pushed when devices report via `traccar:broadcast-positions`.

## Response envelope

```json
{ "success": true, "data": { ... } }
{ "success": false, "message": "...", "code": "optional_code" }
```

## Flutter mobile app

Separate project (not inside this Laravel repo):

`../gps_tracker_pro_mobile` — see `ARCHITECTURE.md` and `README.md` there.

## Legacy API

Existing routes remain unchanged:

- `GET /api/my/devices`
- `GET /api/device/{imei}/latest`
- `GET /api/device/{imei}/history`
