<?php

use App\Models\User;
use App\Services\Tracking\NotificationPreferenceService;
use App\Services\Tracking\TrackingSettingsService;

$user = User::query()->first();
$notif = app(NotificationPreferenceService::class);
$settings = app(TrackingSettingsService::class);

$prefs = $notif->preferencesForUser($user);
echo 'pref_count: ' . count($prefs) . PHP_EOL;
echo 'first_label: ' . ($prefs[0]['label'] ?? '') . PHP_EOL;
echo 'allows_push_offline: ' . ($notif->allowsPush($user, 'device_offline') ? 'yes' : 'no') . PHP_EOL;

$res = $settings->update($user, ['overspeed_kmh' => 85]);
echo 'settings_save: ' . ($res['success'] ? 'ok' : 'fail') . PHP_EOL;
echo 'overspeed: ' . ($settings->forActor($user)['overspeed_kmh'] ?? '') . PHP_EOL;

// restore default-ish
$settings->update($user, ['overspeed_kmh' => 80]);
echo "done\n";
