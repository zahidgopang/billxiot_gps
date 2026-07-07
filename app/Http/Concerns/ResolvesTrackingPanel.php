<?php

namespace App\Http\Concerns;

use App\Models\User;
use App\Services\Authorization\RbacService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait ResolvesTrackingPanel
{
    protected function resolvePanel(Request $request): string
    {
        if ($request->routeIs('tracking.*')) {
            $user = $request->user();

            return $user instanceof User
                ? app(RbacService::class)->panelFor($user)
                : 'user';
        }

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
        return 'tracking.layouts.app';
    }

    /**
     * @return array<string, string>
     */
    protected function trackingHubRoutes(string $panel): array
    {
        unset($panel);

        return [
            'live' => 'tracking.index',
            'history' => 'tracking.history',
            'reports' => 'tracking.reports.index',
            'events' => 'tracking.events.index',
            'geofences' => 'tracking.geofences.index',
            'notifications' => 'tracking.notifications.index',
            'maintenance' => 'tracking.maintenance.index',
            'drivers' => 'tracking.drivers.index',
            'commands' => 'tracking.commands.index',
            'tasks' => 'tracking.tasks.index',
            'settings' => 'tracking.settings.index',
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

    /**
     * Resolve device ids for reports (`all` = every visible vehicle).
     *
     * @return list<int>
     */
    protected function resolveReportDeviceIds(Request $request, ?\App\Models\User $user = null): array
    {
        $user ??= $request->user();
        $raw = $request->input('ids', $request->query('ids', ''));

        if (is_string($raw) && strtolower(trim($raw)) === 'all') {
            return $this->tracking->allowedDeviceIds($user);
        }

        $ids = $this->parseTrackingIdList($request);

        return $ids === [] ? [] : $this->tracking->filterAllowedIds($user, $ids);
    }

    protected function noStoreJson(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
