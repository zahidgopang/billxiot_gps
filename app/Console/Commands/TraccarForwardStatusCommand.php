<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Tracking\TraccarForwardPositionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TraccarForwardStatusCommand extends Command
{
    protected $signature = 'traccar:forward-status
                            {--test : POST a sample position to the forward endpoint}
                            {--device= : Laravel device id for --test (defaults to first active device)}';

    protected $description = 'Show Traccar → Laravel forward config and optionally test the endpoint';

    public function handle(TraccarForwardPositionService $forward): int
    {
        $mode = (string) config('traccar.broadcast_mode', 'light');
        $positions = config('traccar.broadcast_positions', true);
        $secret = (string) config('traccar.forward.secret', '');
        $enabled = $forward->isEnabled();
        $appUrl = rtrim((string) config('app.url'), '/');
        $forwardUrl = $appUrl.'/api/traccar/forward';

        $this->info('Traccar position forward');
        $this->table(['Setting', 'Value'], [
            ['TRACCAR_BROADCAST_POSITIONS', $positions ? 'true' : 'false'],
            ['TRACCAR_BROADCAST_MODE', $mode],
            ['TRACCAR_FORWARD_SECRET', $secret !== '' ? 'set ('.strlen($secret).' chars)' : 'MISSING'],
            ['Laravel forward enabled', $enabled ? 'yes' : 'no'],
            ['Forward URL (traccar.xml)', $forwardUrl],
            ['Reverb client', config('broadcasting.echo_enabled', true) ? 'enabled' : 'disabled'],
            ['Broadcast driver', (string) config('broadcasting.default', 'null')],
        ]);

        if ($mode !== 'forward') {
            $this->warn('Set TRACCAR_BROADCAST_MODE=forward in .env — currently "'.$mode.'".');
        }

        if ($secret === '') {
            $this->error('TRACCAR_FORWARD_SECRET is empty. Laravel returns 401 until it is set.');
        }

        if (! $enabled) {
            $this->warn('Forward endpoint will respond with skipped=forward mode disabled until mode=forward and broadcast_positions=true.');
        }

        $this->newLine();
        $this->line('Add to Traccar conf/traccar.xml (inside <properties>), then restart Traccar:');
        $this->line("  <entry key='forward.enable'>true</entry>");
        $this->line("  <entry key='forward.json'>true</entry>");
        $this->line("  <entry key='forward.url'>{$forwardUrl}</entry>");

        if ($secret !== '') {
            $this->line("  <entry key='forward.header'>X-Traccar-Token: {$secret}</entry>");
        } else {
            $this->line("  <entry key='forward.header'>X-Traccar-Token: YOUR_SECRET</entry>");
        }

        if (! $this->option('test')) {
            $this->newLine();
            $this->line('Run with --test to POST a sample GPS report through the HTTP endpoint.');

            return self::SUCCESS;
        }

        if ($secret === '') {
            return self::FAILURE;
        }

        $deviceId = (int) ($this->option('device') ?: Device::query()->where('status', 'active')->value('id') ?: 0);

        if ($deviceId <= 0) {
            $this->error('No active device found for --test. Pass --device=ID.');

            return self::FAILURE;
        }

        $device = Device::query()->find($deviceId);
        $imei = $device?->imei ?? '';

        $payload = [
            'device' => [
                'id' => $deviceId,
                'uniqueId' => $imei,
            ],
            'position' => [
                'deviceId' => $deviceId,
                'latitude' => 24.7136,
                'longitude' => 46.6753,
                'speed' => 12.5,
                'course' => 90,
                'fixTime' => now()->toIso8601String(),
                'attributes' => ['ignition' => true],
            ],
        ];

        $this->info("Testing forward for device #{$deviceId} ({$imei})…");

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-Traccar-Token' => $secret])
                ->post($forwardUrl, $payload);

            $this->line('HTTP '.$response->status().': '.$response->body());

            if ($response->successful() && ($response->json('broadcast') === true)) {
                $this->info('Broadcast dispatched — open /tracking and confirm marker updates via Reverb.');

                return self::SUCCESS;
            }

            if ($response->successful()) {
                $this->warn('Request OK but broadcast=false (device not mapped or rate-limited).');

                return self::FAILURE;
            }

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Request failed: '.$e->getMessage());
            $this->line('If Traccar runs on another machine, ensure APP_URL is reachable from that host.');

            return self::FAILURE;
        }
    }
}
