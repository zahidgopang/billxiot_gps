<?php

namespace App\Services\Tracking;

use App\Models\User;
use App\Services\Authorization\TenantScopeService;

class TrackingSettingsService
{
    public function __construct(
        private TenantScopeService $tenantScope,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forActor(User $actor): array
    {
        $defaults = config('tracking', []);
        $overrides = $this->loadOverrides($actor);

        return array_merge($defaults, $overrides);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function update(User $actor, array $settings): array
    {
        $allowed = [
            'stopped_speed_kmh', 'slow_speed_max_kmh', 'overspeed_kmh', 'low_battery_percent',
            'moving_speed_kmh', 'delayed_min_seconds', 'stale_min_seconds', 'offline_seconds',
            'event_cooldown_seconds', 'gsm_weak_percent', 'gps_weak_percent', 'gps_min_satellites',
        ];

        $filtered = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $settings)) {
                $filtered[$key] = is_numeric($settings[$key]) ? (float) $settings[$key] : $settings[$key];
            }
        }

        $this->storeOverrides($actor, $filtered);

        return $this->forActor($actor);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadOverrides(User $actor): array
    {
        $clientId = $this->tenantScope->primaryClientIdForUser($actor);
        if (! $clientId) {
            return [];
        }

        $client = \App\Models\Client::query()->find($clientId);
        if (! $client) {
            return [];
        }

        $settings = $client->settings ?? [];
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?: [];
        }

        return (array) ($settings['tracking'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function storeOverrides(User $actor, array $overrides): void
    {
        $clientId = $this->tenantScope->primaryClientIdForUser($actor);
        if (! $clientId) {
            return;
        }

        $client = \App\Models\Client::query()->find($clientId);
        if (! $client) {
            return;
        }

        $settings = $client->settings ?? [];
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?: [];
        }

        $settings['tracking'] = array_merge((array) ($settings['tracking'] ?? []), $overrides);
        $client->settings = $settings;
        $client->save();
    }
}
