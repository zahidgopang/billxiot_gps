<?php

namespace App\Services\Mobile;

use Illuminate\Http\Request;

class MobileAppVersionService
{
    public function isSupported(?string $version, mixed $build): bool
    {
        $minVersion = (string) config('mobile.min_app_version', '1.1.0');
        $minBuild = (int) config('mobile.min_build_number', 16);

        $version = is_string($version) ? trim($version) : '';
        if ($version === '') {
            return false;
        }

        if (version_compare($version, $minVersion, '<')) {
            return false;
        }

        if (version_compare($version, $minVersion, '>')) {
            return true;
        }

        return (int) ($build ?? 0) >= $minBuild;
    }

    /**
     * @return array{version: ?string, build: ?int}
     */
    public function readClientVersion(Request $request): array
    {
        $version = $request->header('X-App-Version')
            ?? $request->input('app_version');
        $buildRaw = $request->header('X-App-Build')
            ?? $request->input('app_build');

        return [
            'version' => is_string($version) && trim($version) !== '' ? trim($version) : null,
            'build' => is_numeric($buildRaw) ? (int) $buildRaw : null,
        ];
    }

    public function updateRequiredMessage(): string
    {
        return (string) config(
            'mobile.update_required_message',
            'A new version of BillX GPS is available. Please update the app to continue.',
        );
    }
}
