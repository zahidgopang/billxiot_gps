<?php

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait ResolvesTrackingPanel
{
    protected function resolvePanel(Request $request): string
    {
        if ($request->routeIs('client.*')) {
            return 'client';
        }

        if ($request->routeIs('admin.*')) {
            return 'admin';
        }

        return 'user';
    }

    protected function layoutForPanel(string $panel): string
    {
        return match ($panel) {
            'user' => 'user.layout_user',
            default => 'tracking.layouts.app',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function trackingHubRoutes(string $panel): array
    {
        return [
            'live' => "{$panel}.tracking.index",
            'history' => "{$panel}.tracking.history",
            'reports' => "{$panel}.tracking.reports.index",
            'events' => "{$panel}.tracking.events.index",
            'geofences' => "{$panel}.tracking.geofences.index",
            'notifications' => "{$panel}.tracking.notifications.index",
            'maintenance' => "{$panel}.tracking.maintenance.index",
            'drivers' => "{$panel}.tracking.drivers.index",
            'commands' => "{$panel}.tracking.commands.index",
            'tasks' => "{$panel}.tracking.tasks.index",
            'settings' => "{$panel}.tracking.settings.index",
        ];
    }

    /**
     * @return list<int>
     */
    protected function parseTrackingIdList(Request $request): array
    {
        $raw = $request->query('ids', $request->input('ids', ''));

        if (is_array($raw)) {
            return array_values(array_filter(array_map('intval', $raw)));
        }

        if ($raw === '' || $raw === null) {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', (string) $raw))));
    }

    protected function noStoreJson(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
