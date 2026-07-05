<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Models\Device;
use App\Services\Mobile\MobileDevicePresenter;
use App\Services\Tracking\DevicePositionLoader;
use App\Services\Tracking\GlobalTrackingService;
use Illuminate\Http\Request;

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

        if ($allowed === []) {
            return $this->mobileSuccess([])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

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
