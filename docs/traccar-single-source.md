# Traccar single source of truth (final)

## Production `.env`

```env
TRACCAR_ENABLED=true
TRACCAR_MODE=full
TRACCAR_SYNC_ON_CHANGE=false
TRACCAR_WRITE_LEGACY_TABLES=false
TRACCAR_SYNC_DEVICES_ON_CHANGE=true
TRACCAR_SYNC_USERS_ON_CHANGE=true
```

## Data flow

```
GPS device → Traccar TCP → tc_positions / tc_events
Laravel API  → tc_positions (ingest)
Map/UI       → reads tc_positions, tc_geofences, tc_events
```

## Table ownership

| Concern | Table(s) | Notes |
|---------|----------|--------|
| GPS history | `tc_positions` | Only |
| Alerts / motion | `tc_events` | Only |
| Tracker | `tc_devices` | IMEI, disabled, last position |
| Geofence geometry | `tc_geofences` | WKT `area` |
| Device ↔ geofence | `tc_device_geofence` | Junction only |
| User ↔ geofence | `tc_user_geofence` | Junction only |
| User ↔ device access | `tc_user_device` | Never touched by geofence delete |
| Device lists / maps (UI) | `tc_user_device` + `tc_devices` | `User::trackerDevicesQuery()` — not `devices.user_id` alone |
| Tracker login / dashboard | `tc_users` | `TraccarUserAccessService::hasTrackerAccount()` — Laravel `users` is auth only |
| Traccar login | `tc_users` | PBKDF2 (`TRACCAR_DEFAULT_USER_PASSWORD`) |
| Laravel login | `users` | Bcrypt — separate |
| Subscriptions | `subscriptions` + `devices` | Business layer |

## Laravel services

| Action | Service |
|--------|---------|
| User create/update | `TraccarEntityProvisioner::provisionUser()` → `tc_users` |
| Device create/update | `provisionDevice()` → `tc_devices` + `tc_user_device` |
| Geofence CRUD | `TraccarGeofenceManager` → `tc_geofences` + junctions |
| Geofence delete | `removeGeofence()` — junctions only, preserves `tc_user_device` |
| Positions | `PositionReader` / `PositionWriter` |
| Events | `EventReader` / `EventWriter` |
| Map geofences JSON | `GeofenceStore` → `tc_geofences` |

## Deprecated (no writes in `full`)

- `device_locations`
- `vehicle_events`
- `geofence_events`

Check: `php artisan traccar:legacy-status`

## Cutover commands

```bash
php artisan traccar:sync
php artisan traccar:repair-links
php artisan traccar:legacy-status
```

## Google Maps / frontend

No UI changes required. APIs unchanged:

- Live/history → `PositionReader` → `tc_positions`
- Geofences → `GeofenceStore` → `tc_geofences`
- Alerts → `EventReader` → `tc_events`
