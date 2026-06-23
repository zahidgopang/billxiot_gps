<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Contracts\Tracking\PositionReaderInterface;
use App\Contracts\Tracking\PositionWriterInterface;
use App\Data\Tracking\IncomingPosition;
use App\Models\Device;
use Carbon\Carbon;
use App\Events\DeviceLocationUpdated;
use App\Services\DeviceAccessService;
use App\Services\Traccar\TraccarEntityProvisioner;
use App\Services\Push\DeviceConnectivityPushService;
use App\Services\VehicleEventService;
use App\Support\Tracking\DeviceLocationPayload;
use App\Support\Tracking\TrackerPayloadNormalizer;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;

class DeviceDataController extends Controller
{
    public function __construct(
        private PositionWriterInterface $positionWriter,
        private PositionReaderInterface $positionReader,
        private TraccarEntityProvisioner $traccar,
    ) {}

    public function receive(Request $req)
    {
        $raw = $req->json()->all() ?: $req->all();
        $payload = TrackerPayloadNormalizer::normalize($raw);

        $imei = $payload['imei'] ?? null;
        $lat = $payload['lat'] ?? null;
        $lng = $payload['lng'] ?? null;
        $speed = (float) ($payload['speed'] ?? 0);
        $heading = (float) ($payload['heading'] ?? 0);
        $battery = $payload['battery'] ?? null;

        $recordedAt = $this->parseRecordedAt(
            $payload['timestamp'] ?? now()->toDateTimeString()
        );

        $ignition = (bool) ($payload['ignition'] ?? false);
        $acc = (bool) ($payload['acc'] ?? $ignition);
        $gsmSignal = $payload['gsm_signal'] ?? null;
        $gpsSignal = $payload['gps_signal'] ?? null;
        $satellites = $payload['satellites'] ?? null;
        $odometer = $payload['odometer'] ?? null;
        $powerCut = (bool) ($payload['power_cut'] ?? false);
        $panic = (bool) ($payload['panic'] ?? false);
        $gpsFix = $payload['gps_fix'] ?? null;

        if (!$imei || !$lat || !$lng) {

            return response()->json([
                'error' => 'imei/lat/lng required'
            ], 400);
        }

        $device = Device::with(['subscription', 'user'])->whereImei($imei)->first();

        if (! $device) {
            $device = Device::create([
                'imei' => $imei,
                'name' => 'Unknown',
                'status' => 'inactive',
            ]);
            $device->load(['subscription', 'user']);
        }

        $access = app(DeviceAccessService::class)->evaluateForIngest($device->user, $device);

        if (! $access['allowed']) {
            return response()->json([
                'error' => $access['reason'],
                'message' => $access['message'],
            ], 403);
        }

        if (TraccarMode::isActive() && TraccarSchema::isReady()) {
            $this->traccar->provisionDevice($device);
        }

        $incoming = new IncomingPosition(
            device: $device,
            lat: (float) $lat,
            lng: (float) $lng,
            speedKmh: (float) $speed,
            heading: (float) $heading,
            battery: $battery !== null ? (int) $battery : null,
            recordedAt: $recordedAt,
            ignition: $ignition,
            acc: $acc,
            gsmSignal: $gsmSignal,
            gpsSignal: $gpsSignal,
            satellites: $satellites,
            odometer: $odometer,
            powerCut: $powerCut,
            panic: $panic,
            gpsFix: $gpsFix ?? 'fix',
        );

        $loc = $this->positionWriter->store($incoming);

        // Live GPS is persisted in tc_positions (no legacy devices.meta column on tc_devices).

        event(new DeviceLocationUpdated(
            $device->id,
            DeviceLocationPayload::fromDeviceLocation($loc, $device)
        ));

        $traccarPositionId = $loc->getAttribute('traccar_position_id') ?? (
            TraccarMode::writesTraccar() && ! TraccarMode::writesLegacy() ? $loc->id : null
        );

        $previousLocation = $this->positionReader->previousBefore(
            $device,
            (int) $loc->id,
            $traccarPositionId ? (int) $traccarPositionId : null
        );

        app(VehicleEventService::class)->processLocation($device, $loc, $previousLocation);

        app(DeviceConnectivityPushService::class)->onPositionReceived($device);

        return response()->json([

            'ok' => true,

            'location_id' => $loc->id
        ]);
    }

    /**
     * Normalize device-reported time (handles clocks ahead/behind server).
     */
    private function parseRecordedAt(string $timestamp): Carbon
    {
        $at = Carbon::parse($timestamp);
        $now = now();

        if ($at->greaterThan($now->copy()->addMinutes(10))) {
            return $now;
        }

        if ($at->lessThan($now->copy()->subYears(2))) {
            return $now;
        }

        return $at;
    }

}
