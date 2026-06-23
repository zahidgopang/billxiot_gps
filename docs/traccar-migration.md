# Traccar database migration

Laravel and Traccar share one MySQL database. Tracking data can be mirrored into `tc_*` tables without changing API routes or the frontend.

## Setup

1. Point Laravel `.env` at your MySQL database (same DB Traccar will use).
2. Start the Traccar server once so Liquibase creates `tc_devices`, `tc_positions`, etc.
3. Run Laravel migrations: `php artisan migrate` (creates `traccar_entity_map`).
4. Enable integration:

```env
TRACCAR_ENABLED=true
TRACCAR_MODE=dual_write
```

5. Backfill existing devices/users/geofences:

```bash
php artisan traccar:sync
```

## Modes

| Mode | Writes | Reads |
|------|--------|-------|
| `off` | `device_locations` only | Laravel tables |
| `dual_write` | Laravel + Traccar | Laravel (safe rollout) |
| `read_traccar` | Both | Traccar first, fallback Laravel |
| `full` | Traccar only | Traccar |

Rollout: `dual_write` → validate → `read_traccar` → `full`.

## Commands

- `php artisan traccar:sync` — backfill all users, devices, geofences
- `php artisan traccar:sync --devices` — devices only

## Notes

- **Laravel** (`users.password`, bcrypt) — customer app login; never written to `tc_users`.
- **Traccar** (`tc_users.hashedPassword` + `salt`, PBKDF2 hex) — internal/debug Traccar UI only; see `App\Support\Traccar\TraccarPassword`.
- On user create/sync, Traccar password is set to `TRACCAR_DEFAULT_USER_PASSWORD` (default `12345678`) using Traccar-compatible hashing.
- Existing valid Traccar hex passwords are left unchanged unless `TRACCAR_RESET_USER_PASSWORD_ON_SYNC=true` or the stored hash is invalid (e.g. old bcrypt mistake).
- Custom Traccar password: `php artisan traccar:set-user-password user@example.com SecretPass`
- Column names are matched case-insensitively (`hashedPassword` → `hashedpassword` on MySQL).
- Debug sync: `TRACCAR_SYNC_LOG=true` then `php artisan traccar:sync --users` and inspect `storage/logs/laravel.log`.

## Trackers not connecting after sync

Traccar **rejects device protocol logins** when `tc_devices.disabled = 1`. By default Laravel only sets that for `blocked` devices (`TRACCAR_DEVICE_DISABLE_WHEN=blocked`). If devices were previously synced as disabled, run:

```bash
php artisan traccar:enable-devices
# or full re-sync:
php artisan traccar:enable-devices --sync
```

After `traccar:sync --users`, device links are refreshed via `relinkUserDevices()` so the Traccar UI shows the user’s fleet again.

Geofence sync writes `tc_geofences` plus junction rows:

- `tc_device_geofence` (device ↔ geofence)
- `tc_user_geofence` (owner user ↔ geofence — required for Traccar UI)

Backfill geofence links: `php artisan traccar:sync --geofences` or `php artisan traccar:sync --users` (also relinks geofences for that user).

**Geofence removal** (Laravel map delete or `GeofenceObserver::deleted`):

1. Scoped `DELETE` on `tc_device_geofence` (`deviceid` + `geofenceid`) and `tc_user_geofence` (`userid` + `geofenceid`)
2. Optional `DELETE` on `tc_geofences` (`TRACCAR_DELETE_GEOFENCE_ROW_ON_REMOVE`, default `true`)

Never deletes `tc_devices`, `tc_user_device`, Laravel `devices`, or position history. `unlinkDevice()` runs only when a Laravel **device** is deleted, not from geofence flows.

Geofence observers use **junction-only** sync (`TRACCAR_ENSURE_ENTITIES_ON_GEOFENCE_SYNC=false` by default): no `syncDevice()` / `syncUser()` during create/update/delete. After removal, `TRACCAR_PRESERVE_DEVICE_ACCESS_ON_GEOFENCE_CHANGE=true` re-upserts `tc_user_device` so Traccar UI access is restored.

If devices disappear in Traccar after geofence edits (including edits made in Traccar UI): `php artisan traccar:repair-links`

## Relationship tables (do not mix)

| Table | Purpose | Laravel sync entry points |
|-------|---------|----------------------------|
| `tc_devices` | Tracker record | `DeviceObserver`, `syncDevice()`, ingest writer |
| `tc_user_device` | User can view device | `DeviceObserver`, `syncUser()`, `relinkUserDevices()`, `traccar:repair-links` |
| `tc_device_geofence` | Geofence on device | `GeofenceObserver`, `syncGeofence()` junction only |
| `tc_user_geofence` | User can see geofence | `GeofenceObserver`, `syncGeofence()` junction only |

No Eloquent `belongsToMany` / `sync()` / `detach()` is used for Traccar tables.

## Sync direction (current)

| Data | Laravel → Traccar | Traccar → Laravel |
|------|-------------------|-------------------|
| Positions | `dual_write`: `device_locations` + `tc_positions` via ingest API | `read_traccar` / `full` modes read `tc_positions` |
| Devices | Observers + `traccar:sync --devices` | Not automatic (map by IMEI on first sync) |
| Users | Observers + `traccar:sync --users` | Not automatic |
| User↔device access | `syncDevice` / `relinkUserDevices` / `traccar:repair-links` | Not automatic — run repair after Traccar UI permission loss |
| Geofences | Observers + `traccar:sync --geofences` | Not automatic |
| Events | `VehicleEventObserver` | Not automatic |

Recommended full backfill order: `php artisan traccar:sync` (users → devices → geofences → repair links).
- Speed in Traccar is stored in **knots**; the integration converts to/from km/h for APIs and the map.
- Do not run device protocols on both Laravel ingest and Traccar ports for the same IMEI without a dedupe strategy.
