<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Contracts\Tracking\PositionReaderInterface;
use App\Http\Controllers\Controller;
use App\Http\Concerns\ResolvesHistoryDateRange;
use App\Http\Concerns\ResolvesMobileDevice;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    use ResolvesHistoryDateRange;
    use ResolvesMobileDevice;

    public function __construct(
        private PositionReaderInterface $positions,
    ) {}

    public function csv(Request $request): StreamedResponse
    {
        $device = $this->resolveExportDevice($request);
        $points = $this->historyPoints($device, $request);
        $filename = 'route-' . $device->id . '-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($points) {
            echo "lat,lng,speed,heading,recorded_at,battery,ignition,odometer\n";
            foreach ($points as $p) {
                echo implode(',', [
                    $p['lat'],
                    $p['lng'],
                    $p['speed'],
                    $p['heading'],
                    $p['recorded_at'] ?? '',
                    $p['battery'] ?? '',
                    $p['ignition'] ? 1 : 0,
                    $p['odometer'] ?? '',
                ]) . "\n";
            }
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function gpx(Request $request): StreamedResponse
    {
        $device = $this->resolveExportDevice($request);
        $points = $this->historyPoints($device, $request);
        $name = $device->name ?: 'Route';
        $filename = 'route-' . $device->id . '-' . now()->format('Ymd_His') . '.gpx';

        return response()->streamDownload(function () use ($points, $name) {
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<gpx version="1.1" creator="FalconEyeGPS">' . "\n";
            echo '  <trk><name>' . htmlspecialchars($name, ENT_XML1) . '</name><trkseg>' . "\n";
            foreach ($points as $p) {
                $t = $p['recorded_at']
                    ? (new \DateTime($p['recorded_at']))->format(\DateTime::ATOM)
                    : now()->toAtomString();
                echo '      <trkpt lat="' . $p['lat'] . '" lon="' . $p['lng'] . '">';
                echo '<time>' . $t . '</time><speed>' . ($p['speed'] ?? 0) . '</speed></trkpt>' . "\n";
            }
            echo '  </trkseg></trk>' . "\n";
            echo '</gpx>';
        }, $filename, ['Content-Type' => 'application/gpx+xml']);
    }

    private function resolveExportDevice(Request $request)
    {
        $request->validate([
            'device_id' => 'required|integer',
        ]);

        return $this->findMobileDevice($request->user(), $request->query('device_id', $request->input('device_id')));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function historyPoints($device, Request $request): array
    {
        $range = $this->resolveHistoryRange($request);

        return $this->positions->historyForDevice($device, $range['from'], $range['to'], 'asc')
            ->map(fn ($loc) => [
                'lat' => (float) $loc->lat,
                'lng' => (float) $loc->lng,
                'speed' => (float) ($loc->speed ?? 0),
                'heading' => (float) ($loc->heading ?? 0),
                'recorded_at' => app_datetime_api($loc->recorded_at),
                'battery' => $loc->battery_level,
                'ignition' => (bool) $loc->ignition,
                'odometer' => $loc->odometer,
            ])
            ->values()
            ->all();
    }
}
