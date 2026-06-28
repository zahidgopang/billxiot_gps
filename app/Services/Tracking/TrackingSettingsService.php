<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Models\User;
use App\Services\Authorization\TenantScopeService;

class TrackingSettingsService
{
    /** @var list<string> */
    public const KEYS = [
        'stopped_speed_kmh',
        'slow_speed_max_kmh',
        'overspeed_kmh',
        'low_battery_percent',
        'moving_speed_kmh',
        'delayed_min_seconds',
        'stale_min_seconds',
        'offline_seconds',
        'event_cooldown_seconds',
        'gsm_weak_percent',
        'gps_weak_percent',
        'gps_min_satellites',
    ];

    public function __construct(
        private TenantScopeService $tenantScope,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forActor(User $actor): array
    {
        return $this->merge(config('tracking', []), $this->loadOverrides($actor));
    }

    /**
     * Resolved tracking thresholds for a device's owner tenant/user.
     *
     * @return array<string, mixed>
     */
    public function forDevice(Device $device): array
    {
        $ownerId = $device->resolveTraccarOwnerUserId();
        if ($ownerId) {
            $owner = User::query()->find((int) $ownerId);
            if ($owner) {
                return $this->forActor($owner);
            }
        }

        return config('tracking', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return config('tracking', []);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{success: bool, settings: array<string, mixed>, message?: string}
     */
    public function update(User $actor, array $settings): array
    {
        $filtered = [];
        foreach (self::KEYS as $key) {
            if (array_key_exists($key, $settings)) {
                $filtered[$key] = is_numeric($settings[$key]) ? (float) $settings[$key] : $settings[$key];
            }
        }

        if ($filtered === []) {
            return [
                'success' => false,
                'settings' => $this->forActor($actor),
                'message' => (string) __('app.tracking.settings_nothing_to_save'),
            ];
        }

        if (! $this->storeOverrides($actor, $filtered)) {
            return [
                'success' => false,
                'settings' => $this->forActor($actor),
                'message' => (string) __('app.tracking.settings_save_failed'),
            ];
        }

        return [
            'success' => true,
            'settings' => $this->forActor($actor),
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function merge(array $base, array $overrides): array
    {
        $out = $base;
        foreach (self::KEYS as $key) {
            if (array_key_exists($key, $overrides)) {
                $out[$key] = $overrides[$key];
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadOverrides(User $actor): array
    {
        $clientId = $this->tenantScope->primaryClientIdForUser($actor);
        if ($clientId) {
            $client = \App\Models\Client::query()->find($clientId);
            if ($client) {
                $settings = $client->settings ?? [];
                if (is_string($settings)) {
                    $settings = json_decode($settings, true) ?: [];
                }

                return (array) ($settings['tracking'] ?? []);
            }
        }

        $attrs = $actor->attributes ?? [];
        if (is_string($attrs)) {
            $attrs = json_decode($attrs, true) ?: [];
        }

        return (array) ($attrs['tracking_settings'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function storeOverrides(User $actor, array $overrides): bool
    {
        $clientId = $this->tenantScope->primaryClientIdForUser($actor);
        if ($clientId) {
            $client = \App\Models\Client::query()->find($clientId);
            if ($client) {
                $settings = $client->settings ?? [];
                if (is_string($settings)) {
                    $settings = json_decode($settings, true) ?: [];
                }
                $settings['tracking'] = array_merge((array) ($settings['tracking'] ?? []), $overrides);
                $client->settings = $settings;
                $client->save();

                return true;
            }
        }

        $attrs = is_array($actor->attributes) ? $actor->attributes : [];
        if (is_string($actor->attributes)) {
            $attrs = json_decode((string) $actor->attributes, true) ?: [];
        }
        $attrs['tracking_settings'] = array_merge((array) ($attrs['tracking_settings'] ?? []), $overrides);
        $actor->attributes = $attrs;
        $actor->save();

        return true;
    }
}
