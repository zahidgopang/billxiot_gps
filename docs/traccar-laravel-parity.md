# Laravel ↔ Traccar parity (final integration)

## Single source of truth

| Concern | Table(s) |
|---------|----------|
| Users (Laravel login + Traccar UI) | `tc_users` |
| Devices | `tc_devices` (`uniqueid` = IMEI) |
| User ↔ device | `tc_user_device` only |
| Geofences | `tc_geofences` + `tc_device_geofence` + `tc_user_geofence` |
| GPS / history | `tc_positions` |
| Events / alerts | `tc_events` |
| Subscriptions (business) | `subscriptions` → `tc_users.id`, `tc_devices.id` |

## Admin CRUD (same as Traccar)

Laravel admin writes **directly** to `tc_*` via Eloquent models (`User`, `Device`, `Geofence`). No duplicate `devices` / `users` tables.

- **User**: `name`, `email`, `login`, `disabled`, `administrator`, `attributes` (Laravel password, role, phone, preferences)
- **Laravel role `admin`**: `administrator=1`, `userLimit=-1`, `deviceLimit=-1`, `readonly=0` (full Traccar UI access via `TraccarUserPermissions`)
- **Laravel role `user`**: `administrator=0`, limits `0` (devices assigned via `tc_user_device` only)
- **Device**: `name`, `uniqueid`, `category`, `contact`, `disabled`, `attributes`
- **Assignment**: `TraccarUserDeviceLinker` upserts `tc_user_device` only (never geofence pivots)
- **Geofence**: `TraccarGeofenceManager` writes `area` WKT + junction rows

Traccar UI password (PBKDF2): `TraccarUserPasswordSync` on admin user create/update.

## What must NOT happen

- Geofence delete → must **not** delete `tc_user_device`
- Device–geofence unlink → must **not** remove device ownership
- No `sync()` on unrelated pivots — use `TraccarUserDeviceLinker::upsert()` / scoped deletes

## Tracking visibility (`TraccarTrackingGate`)

Maps, live JSON, dashboards, and customer APIs check:

1. Row exists in `tc_users` and `disabled = 0`
2. `tc_user_device` link
3. `tc_devices.disabled = 0` and app status `active`
4. Active **subscription** (maps); ingest API may allow inactive devices when `TRACCAR_ALLOW_INACTIVE_INGEST=true`

## Commands

```bash
php artisan traccar:sync          # backfill only if needed
php artisan traccar:repair-links  # rebuild tc_user_device from assignments
php artisan config:clear
```

## `.env`

```env
TRACCAR_ENABLED=true
TRACCAR_MODE=full
TRACCAR_UNIFIED_IDS=true
TRACCAR_SYNC_ON_CHANGE=false
```
