<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Models\Device;
use App\Services\Mobile\MobileDevicePresenter;
use App\Services\Tracking\DevicePositionLoader;
use App\Services\Tracking\GlobalTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FleetController extends Controller
{
    use RespondsWithMobileJson;

    public function __construct(
        private GlobalTrackingService $tracking,
        private DevicePositionLoader $positionLoader,
        private MobileDevicePresenter $presenter,
    ) {}

    public function live(Request $request)
    {
        $user = $request->user();
        $requested = $this->parseIdList($request);
        $allowed = $requested === []
            ? array_slice($this->tracking->allowedDeviceIds($user), 0, GlobalTrackingService::MAX_LIVE_DEVICES)
            : $this->tracking->filterAllowedIds($user, $requested);

        $allowed = array_slice($allowed, 0, GlobalTrackingService::MAX_LIVE_DEVICES);

        if ($allowed === []) {
            return $this->mobileSuccess([])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        sort($allowed);
        $cacheSeconds = (int) config('tracking.live_json_cache_seconds', 3);
        $cacheKey = 'mobile.fleet.live.'
            . $user->id
            . '.'
            . md5(implode(',', $allowed));

        $build = function () use ($allowed): array {
            $devices = Device::query()->whereIn('id', $allowed)->get()->keyBy('id');
            $this->positionLoader->attachLatestToMany($devices);

            $items = [];
            foreach ($allowed as $deviceId) {
                $device = $devices->get($deviceId);
                if (! $device) {
                    continue;
                }

                try {
                    $payload = $this->presenter->livePosition($device, withStatusDuration: false);
                } catch (\Throwable $e) {
                    report($e);

                    continue;
                }

                if ($payload !== null) {
                    $items[] = array_merge(['id' => $device->id], $payload);
                }
            }

            return $items;
        };

        $items = $cacheSeconds > 0
            ? Cache::remember($cacheKey, $cacheSeconds, $build)
            : $build();

        return $this->mobileSuccess($items)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * @return list<int>
     */
    private function parseIdList(Request $request): array
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
}
