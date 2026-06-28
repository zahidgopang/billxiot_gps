<?php

namespace App\Services\Tracking;

use App\Models\User;
use App\Models\VehicleEvent;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Support\Facades\DB;

class NotificationPreferenceService
{
    /**
     * Canonical notification types users can toggle (web feed + mobile push).
     *
     * @return list<string>
     */
    public static function controllableTypes(): array
    {
        return array_values(array_unique(array_merge(
            VehicleEvent::dashboardAlertTypes(),
            ['geofence_enter', 'geofence_exit', 'stopped', 'running', 'slow_speed'],
        )));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function preferencesForUser(User $user): array
    {
        $stored = $this->loadStored($user);

        return collect(self::controllableTypes())->map(function (string $type) use ($stored) {
            $pref = $this->resolvePref($stored, $type);

            return [
                'type' => $type,
                'label' => $this->labelForType($type),
                'web' => (bool) ($pref['web'] ?? true),
                'push' => (bool) ($pref['push'] ?? true),
            ];
        })->values()->all();
    }

    /**
     * @param  list<array{type: string, web?: bool, push?: bool}>  $items
     */
    public function update(User $user, array $items): void
    {
        if (! TraccarSchema::hasTable('tc_notifications')) {
            $this->storeInUserAttributes($user, $items);

            return;
        }

        foreach ($items as $item) {
            $type = (string) ($item['type'] ?? '');
            if ($type === '') {
                continue;
            }

            $web = (bool) ($item['web'] ?? true);
            $push = (bool) ($item['push'] ?? true);

            // Channels are stored in the attributes JSON for portability across
            // Traccar schemas (the real tc_notifications has no web/mail/sms columns;
            // channels live in `notificators`/`attributes`).
            $existing = DB::table('tc_notifications')->where('type', $type)->first();
            $attrs = $existing ? (json_decode((string) ($existing->attributes ?? '{}'), true) ?: []) : [];
            $attrs['web'] = $web;
            $attrs['push'] = $push;

            $columns = TraccarSchema::filterColumns('tc_notifications', [
                'type' => $type,
                'always' => false,
                'web' => $web,
                'mail' => false,
                'sms' => false,
                'notificators' => $this->notificatorsString($web),
                'attributes' => json_encode($attrs),
            ]);

            if (! $existing) {
                $notificationId = DB::table('tc_notifications')->insertGetId($columns);
            } else {
                $notificationId = (int) $existing->id;
                unset($columns['type']);
                DB::table('tc_notifications')->where('id', $notificationId)->update($columns);
            }

            if (TraccarSchema::hasTable('tc_user_notification')) {
                DB::table('tc_user_notification')->updateOrInsert(
                    ['userid' => $user->id, 'notificationid' => $notificationId],
                    ['userid' => $user->id, 'notificationid' => $notificationId]
                );
            }
        }
    }

    /**
     * Traccar's native channel list. Keep `web` so alerts surface in the Traccar UI
     * when enabled; always include the push notificator so mobile delivery is gated
     * by our own attributes flag rather than Traccar's.
     */
    private function notificatorsString(bool $web): string
    {
        $channels = [];
        if ($web) {
            $channels[] = 'web';
        }
        $channels[] = 'firebase';

        return implode(',', $channels);
    }

    public function allowsPush(User $user, string $eventType): bool
    {
        return $this->channelEnabled($user, $eventType, 'push');
    }

    public function allowsWeb(User $user, string $eventType): bool
    {
        return $this->channelEnabled($user, $eventType, 'web');
    }

    public function isWebSuppressed(User $user, string $eventType): bool
    {
        return ! $this->channelEnabled($user, $eventType, 'web');
    }

    /**
     * Event types the user explicitly disabled for the web/in-app feed.
     * Unstored types default to enabled, so only opt-outs are returned.
     *
     * @return list<string>
     */
    public function suppressedWebTypes(User $user): array
    {
        $suppressed = [];

        foreach (self::controllableTypes() as $type) {
            if (! $this->channelEnabled($user, $type, 'web')) {
                $suppressed[] = $type;
            }
        }

        return $suppressed;
    }

    /**
     * @return array<string, array{web: bool, push: bool}>
     */
    private function loadStored(User $user): array
    {
        if (TraccarSchema::hasTable('tc_notifications') && TraccarSchema::hasTable('tc_user_notification')) {
            // The `web` column only exists on some schemas; select it conditionally
            // and otherwise derive the channel from the attributes JSON.
            $hasWebColumn = TraccarSchema::hasColumn('tc_notifications', 'web');
            $select = ['n.type', 'n.attributes'];
            if ($hasWebColumn) {
                $select[] = 'n.web';
            }

            $rows = DB::table('tc_notifications as n')
                ->join('tc_user_notification as un', 'un.notificationid', '=', 'n.id')
                ->where('un.userid', $user->id)
                ->select($select)
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $attrs = json_decode((string) ($row->attributes ?? '{}'), true) ?: [];
                $web = array_key_exists('web', $attrs)
                    ? (bool) $attrs['web']
                    : ($hasWebColumn ? (bool) ($row->web ?? true) : true);

                $out[(string) $row->type] = [
                    'web' => $web,
                    'push' => (bool) ($attrs['push'] ?? true),
                ];
            }

            return $out;
        }

        $attrs = $user->attributes ?? [];
        if (is_string($attrs)) {
            $attrs = json_decode($attrs, true) ?: [];
        }

        return (array) ($attrs['notification_preferences'] ?? []);
    }

    /**
     * @param  list<array{type: string, web?: bool, push?: bool}>  $items
     */
    private function storeInUserAttributes(User $user, array $items): void
    {
        $prefs = [];
        foreach ($items as $item) {
            $type = (string) ($item['type'] ?? '');
            if ($type === '') {
                continue;
            }
            $prefs[$type] = [
                'web' => (bool) ($item['web'] ?? true),
                'push' => (bool) ($item['push'] ?? true),
            ];
        }

        $attrs = is_array($user->attributes) ? $user->attributes : [];
        $attrs['notification_preferences'] = $prefs;
        $user->attributes = $attrs;
        $user->save();
    }

    private function labelForType(string $type): string
    {
        $key = 'app.tracking.notif_type_' . $type;
        $label = __($key);

        return $label !== $key ? $label : ucwords(str_replace('_', ' ', $type));
    }

    /**
     * @param  array<string, array{web?: bool, push?: bool}>  $stored
     * @return array{web?: bool, push?: bool}
     */
    private function resolvePref(array $stored, string $type): array
    {
        foreach ($this->aliasKeys($type) as $key) {
            if (isset($stored[$key])) {
                return $stored[$key];
            }
        }

        return ['web' => true, 'push' => true];
    }

    private function channelEnabled(User $user, string $eventType, string $channel): bool
    {
        $stored = $this->loadStored($user);

        foreach ($this->aliasKeys($eventType) as $key) {
            if (array_key_exists($channel, $stored[$key] ?? [])) {
                return (bool) $stored[$key][$channel];
            }
        }

        return true;
    }

    /**
     * Map push/event aliases to the canonical preference key.
     *
     * @return list<string>
     */
    private function aliasKeys(string $type): array
    {
        $canonical = match ($type) {
            'offline', 'device_offline' => 'device_offline',
            'delayed', 'delayed_data' => 'delayed_data',
            'maintenance', 'maintenance_due' => VehicleEvent::TYPE_MAINTENANCE,
            'vehicle_started', 'vehicle_moving', 'vehicle_parked' => 'running',
            'vehicle_stopped' => 'stopped',
            default => $type,
        };

        return array_values(array_unique([$canonical, $type]));
    }
}
