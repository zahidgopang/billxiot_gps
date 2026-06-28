<?php

namespace App\Services\Tracking;

use App\Models\User;
use App\Models\VehicleEvent;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Support\Facades\DB;

class NotificationPreferenceService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function preferencesForUser(User $user): array
    {
        $types = array_unique(array_merge(
            VehicleEvent::criticalTypes(),
            VehicleEvent::warningTypes(),
            ['geofence_enter', 'geofence_exit', 'overspeed', 'stopped', 'running', 'offline', 'low_battery', 'panic', 'power_cut'],
        ));

        $stored = $this->loadStored($user);

        return collect($types)->map(function (string $type) use ($stored) {
            $pref = $stored[$type] ?? ['web' => true, 'push' => true];

            return [
                'type' => $type,
                'label' => ucwords(str_replace('_', ' ', $type)),
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

            $notificationId = DB::table('tc_notifications')
                ->where('type', $type)
                ->value('id');

            if (! $notificationId) {
                $notificationId = DB::table('tc_notifications')->insertGetId(
                    TraccarSchema::filterColumns('tc_notifications', [
                        'type' => $type,
                        'always' => false,
                        'web' => (bool) ($item['web'] ?? true),
                        'mail' => false,
                        'sms' => false,
                        'attributes' => json_encode(['push' => (bool) ($item['push'] ?? true)]),
                    ])
                );
            } else {
                DB::table('tc_notifications')->where('id', $notificationId)->update(
                    TraccarSchema::filterColumns('tc_notifications', [
                        'web' => (bool) ($item['web'] ?? true),
                        'attributes' => json_encode(['push' => (bool) ($item['push'] ?? true)]),
                    ])
                );
            }

            if (TraccarSchema::hasTable('tc_user_notification')) {
                DB::table('tc_user_notification')->updateOrInsert(
                    ['userid' => $user->id, 'notificationid' => $notificationId],
                    ['userid' => $user->id, 'notificationid' => $notificationId]
                );
            }
        }
    }

    public function allowsPush(User $user, string $eventType): bool
    {
        $stored = $this->loadStored($user);

        return (bool) ($stored[$eventType]['push'] ?? true);
    }

    public function allowsWeb(User $user, string $eventType): bool
    {
        $stored = $this->loadStored($user);

        return (bool) ($stored[$eventType]['web'] ?? true);
    }

    /**
     * @return array<string, array{web: bool, push: bool}>
     */
    private function loadStored(User $user): array
    {
        if (TraccarSchema::hasTable('tc_notifications') && TraccarSchema::hasTable('tc_user_notification')) {
            $rows = DB::table('tc_notifications as n')
                ->join('tc_user_notification as un', 'un.notificationid', '=', 'n.id')
                ->where('un.userid', $user->id)
                ->select('n.type', 'n.web', 'n.attributes')
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $attrs = json_decode((string) ($row->attributes ?? '{}'), true) ?: [];
                $out[(string) $row->type] = [
                    'web' => (bool) $row->web,
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
}
