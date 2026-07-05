<?php

namespace App\Services\Tracking;

use App\Models\User;
use App\Models\VehicleEvent;
use App\Support\Traccar\TraccarAppFields;
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
            ['geofence_enter', 'geofence_exit', 'stopped', 'running', 'slow_speed', VehicleEvent::TYPE_TRIP_COMPLETED],
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
                'email' => (bool) ($pref['email'] ?? true),
                'whatsapp' => (bool) ($pref['whatsapp'] ?? false),
            ];
        })->values()->all();
    }

    /**
     * @param  list<array{type: string, web?: bool, push?: bool}>  $items
     */
    public function update(User $user, array $items): void
    {
        $prefs = $this->normalizePrefsFromItems($items);
        $this->storeUserChannelPrefs($user, $prefs);
        $this->syncTraccarNotificationLinks($user, $prefs);
    }

    public function allowsPush(User $user, string $eventType): bool
    {
        return $this->channelEnabled($user, $eventType, 'push');
    }

    public function allowsWeb(User $user, string $eventType): bool
    {
        return $this->channelEnabled($user, $eventType, 'web');
    }

    public function allowsEmail(User $user, string $eventType): bool
    {
        return $this->channelEnabled($user, $eventType, 'email');
    }

    public function allowsWhatsApp(User $user, string $eventType): bool
    {
        return $this->channelEnabled($user, $eventType, 'whatsapp');
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
     * Per-user channel prefs — primary source of truth lives in tc_users.attributes JSON.
     *
     * @return array<string, array{web: bool, push: bool, email: bool, whatsapp: bool}>
     */
    private function loadStored(User $user): array
    {
        $fromUser = TraccarAppFields::get(
            $user->getTraccarAttributesJson(),
            TraccarAppFields::KEY_NOTIFICATION_PREFERENCES
        );
        if (is_array($fromUser) && $fromUser !== []) {
            return $this->normalizePrefsMap($fromUser);
        }

        // Legacy fallback for users who saved before per-user storage (junction + shared template).
        return $this->loadStoredFromTraccarLinks($user);
    }

    /**
     * @return array<string, array{web: bool, push: bool, email: bool, whatsapp: bool}>
     */
    private function loadStoredFromTraccarLinks(User $user): array
    {
        if (! TraccarSchema::hasTable('tc_notifications') || ! TraccarSchema::hasTable('tc_user_notification')) {
            return [];
        }

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
                'email' => (bool) ($attrs['email'] ?? true),
                'whatsapp' => (bool) ($attrs['whatsapp'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{type: string, web?: bool, push?: bool, email?: bool, whatsapp?: bool}>  $items
     * @return array<string, array{web: bool, push: bool, email: bool, whatsapp: bool}>
     */
    private function normalizePrefsFromItems(array $items): array
    {
        $prefs = [];
        foreach ($items as $item) {
            $type = (string) ($item['type'] ?? '');
            if ($type === '') {
                continue;
            }
            $prefs[$type] = $this->normalizeChannelPref($item);
        }

        return $prefs;
    }

    /**
     * @param  array<string, array{web?: bool, push?: bool, email?: bool, whatsapp?: bool}>  $prefs
     * @return array<string, array{web: bool, push: bool, email: bool, whatsapp: bool}>
     */
    private function normalizePrefsMap(array $prefs): array
    {
        $out = [];
        foreach ($prefs as $type => $pref) {
            if (! is_string($type) || ! is_array($pref)) {
                continue;
            }
            $out[$type] = $this->normalizeChannelPref($pref);
        }

        return $out;
    }

    /**
     * @param  array{web?: bool, push?: bool, email?: bool, whatsapp?: bool}  $pref
     * @return array{web: bool, push: bool, email: bool, whatsapp: bool}
     */
    private function normalizeChannelPref(array $pref): array
    {
        return [
            'web' => (bool) ($pref['web'] ?? true),
            'push' => (bool) ($pref['push'] ?? true),
            'email' => (bool) ($pref['email'] ?? true),
            'whatsapp' => (bool) ($pref['whatsapp'] ?? false),
        ];
    }

    /**
     * @param  array<string, array{web: bool, push: bool, email: bool, whatsapp: bool}>  $prefs
     */
    private function storeUserChannelPrefs(User $user, array $prefs): void
    {
        $user->patchTraccarAppAttributes([
            TraccarAppFields::KEY_NOTIFICATION_PREFERENCES => $prefs,
        ]);
        $user->save();
    }

    /**
     * Keep Traccar tc_user_notification links in sync without overwriting shared
     * tc_notifications rows (those are type templates, not per-user channel prefs).
     *
     * @param  array<string, array{web: bool, push: bool, email: bool, whatsapp: bool}>  $prefs
     */
    private function syncTraccarNotificationLinks(User $user, array $prefs): void
    {
        if (! TraccarSchema::hasTable('tc_notifications') || ! TraccarSchema::hasTable('tc_user_notification')) {
            return;
        }

        foreach (self::controllableTypes() as $type) {
            $pref = $this->resolvePref($prefs, $type);
            $subscribed = ($pref['web'] ?? true)
                || ($pref['push'] ?? true)
                || ($pref['email'] ?? true)
                || ($pref['whatsapp'] ?? false);
            $notificationId = $this->ensureNotificationTemplateId($type);
            if ($notificationId === null) {
                continue;
            }

            if ($subscribed) {
                DB::table('tc_user_notification')->updateOrInsert(
                    ['userid' => $user->id, 'notificationid' => $notificationId],
                    ['userid' => $user->id, 'notificationid' => $notificationId]
                );
            } else {
                DB::table('tc_user_notification')
                    ->where('userid', $user->id)
                    ->where('notificationid', $notificationId)
                    ->delete();
            }
        }
    }

    private function ensureNotificationTemplateId(string $type): ?int
    {
        $existing = DB::table('tc_notifications')->where('type', $type)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        $columns = TraccarSchema::filterColumns('tc_notifications', [
            'type' => $type,
            'always' => false,
            'web' => true,
            'mail' => false,
            'sms' => false,
            'notificators' => 'web,firebase',
            'attributes' => json_encode(['web' => true, 'push' => true, 'email' => true, 'whatsapp' => false]),
        ]);

        return (int) DB::table('tc_notifications')->insertGetId($columns);
    }

    private function labelForType(string $type): string
    {
        $key = 'app.tracking.notif_type_' . $type;
        $label = __($key);

        return $label !== $key ? $label : ucwords(str_replace('_', ' ', $type));
    }

    /**
     * @param  array<string, array{web?: bool, push?: bool}>  $stored
     * @return array{web?: bool, push?: bool, email?: bool, whatsapp?: bool}
     */
    private function resolvePref(array $stored, string $type): array
    {
        foreach ($this->aliasKeys($type) as $key) {
            if (isset($stored[$key])) {
                return $stored[$key];
            }
        }

        return ['web' => true, 'push' => true, 'email' => true, 'whatsapp' => false];
    }

    private function channelEnabled(User $user, string $eventType, string $channel): bool
    {
        $stored = $this->loadStored($user);

        foreach ($this->aliasKeys($eventType) as $key) {
            if (array_key_exists($channel, $stored[$key] ?? [])) {
                return (bool) $stored[$key][$channel];
            }
        }

        return $channel !== 'whatsapp';
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
            'vehicle_stopped', 'idle' => 'stopped',
            'parked' => 'parked',
            default => $type,
        };

        return array_values(array_unique([$canonical, $type]));
    }
}
