<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DeviceMapIconController extends Controller
{
    public function show(Device $device): Response
    {
        if ($device->map_icon_source !== 'custom') {
            abort(404);
        }

        $path = $device->map_custom_icon;
        if (! is_string($path) || $path === '') {
            abort(404);
        }

        $disk = Storage::disk((string) config('vehicle_icons.upload.disk', 'public'));
        if (! $disk->exists($path)) {
            abort(404);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/png',
        };

        return response($disk->get($path), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
