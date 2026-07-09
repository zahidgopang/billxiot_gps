<?php

namespace App\Services\Tracking;

use App\Models\Device;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeviceVehicleIconService
{
    public function url(Device $device): ?string
    {
        $path = $device->map_custom_icon;
        if (! is_string($path) || $path === '') {
            return null;
        }

        // Source flag only — file existence is checked below so callers can
        // detect orphaned custom icons without recursion.
        if ($device->map_icon_source !== 'custom') {
            return null;
        }

        $disk = $this->disk();
        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $version = Storage::disk($disk)->lastModified($path) ?: time();
        $fileKey = pathinfo($path, PATHINFO_FILENAME);

        // Include filename so browsers never reuse a previous upload at the same device URL.
        return route('device-map-icons.show', ['device' => $device->id])
            .'?v='.$version
            .'&f='.rawurlencode((string) $fileKey);
    }

    /**
     * @return array{path: string, upload_meta: array<string, mixed>}
     */
    public function store(Device $device, UploadedFile $file): array
    {
        $this->validateUpload($file);
        $this->deleteStoredFile($device->map_custom_icon);

        $mime = strtolower((string) $file->getMimeType());
        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        $allowed = config('vehicle_icons.upload.allowed_extensions', ['png']);
        if (! in_array($extension, $allowed, true)) {
            $extension = 'png';
        }

        $uploadMeta = [
            'resized' => false,
            'original_width' => null,
            'original_height' => null,
            'width' => null,
            'height' => null,
        ];

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw ValidationException::withMessages([
                'icon' => [__('app.map.custom_icon_invalid_image')],
            ]);
        }

        if ($mime !== 'image/svg+xml' && config('vehicle_icons.upload.auto_resize', true)) {
            $normalized = $this->normalizeRasterContents($file->getRealPath(), $mime);
            if ($normalized !== null) {
                $contents = $normalized['contents'];
                $uploadMeta = $normalized['meta'];
                if ($normalized['extension'] !== null) {
                    $extension = $normalized['extension'];
                }
            }
        } elseif ($mime !== 'image/svg+xml') {
            $dims = $this->readRasterDimensions($file->getRealPath());
            if ($dims !== null) {
                $uploadMeta['width'] = $dims['width'];
                $uploadMeta['height'] = $dims['height'];
            }
        }

        // Unique filename so browsers/CDN never keep serving a replaced icon.png.
        $relativePath = $this->directory().'/'.$device->id.'/icon_'.time().'.'.$extension;
        Storage::disk($this->disk())->put($relativePath, $contents);

        $thumbnailPath = null;
        $thumbnailUrl = null;
        if ($mime !== 'image/svg+xml' && extension_loaded('gd')) {
            $thumb = $this->makeThumbnail($contents, (int) config('vehicle_icons.upload.thumbnail_size', 64));
            if ($thumb !== null) {
                $thumbnailPath = $this->directory().'/'.$device->id.'/thumb.png';
                Storage::disk($this->disk())->put($thumbnailPath, $thumb);
                $thumbnailUrl = Storage::disk($this->disk())->url($thumbnailPath);
            }
        }

        $uploadMeta['anchor_x'] = 0.5;
        $uploadMeta['anchor_y'] = 0.5;
        $uploadMeta['rotation_center_x'] = 0.5;
        $uploadMeta['rotation_center_y'] = 0.5;

        return [
            'path' => $relativePath,
            'thumbnail_path' => $thumbnailPath,
            'thumbnail_url' => $thumbnailUrl,
            'upload_meta' => $uploadMeta,
        ];
    }

    public function delete(Device $device): void
    {
        $this->deleteStoredFile($device->map_custom_icon);
        $device->map_custom_icon = null;
        $device->map_icon_source = 'default';
    }

    /**
     * @throws ValidationException
     */
    public function validateUpload(UploadedFile $file): void
    {
        $maxKb = (int) config('vehicle_icons.upload.max_kb', 512);

        Validator::make(
            ['icon' => $file],
            [
                'icon' => [
                    'required',
                    'file',
                    'max:'.$maxKb,
                    'mimes:png,svg',
                ],
            ]
        )->validate();

        $mime = strtolower((string) $file->getMimeType());
        $allowedMimes = ['image/png', 'image/svg+xml'];
        if (! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'icon' => [__('app.map.custom_icon_invalid_type')],
            ]);
        }

        if ($mime !== 'image/svg+xml') {
            $dims = $this->readRasterDimensions($file->getRealPath());
            if ($dims === null) {
                throw ValidationException::withMessages([
                    'icon' => [__('app.map.custom_icon_invalid_image')],
                ]);
            }
        }
    }

    /**
     * @return array{width: int, height: int}|null
     */
    private function readRasterDimensions(string $path): ?array
    {
        $size = @getimagesize($path);
        if (! is_array($size)) {
            return null;
        }

        return [
            'width' => (int) $size[0],
            'height' => (int) $size[1],
        ];
    }

    /**
     * Normalize a raster upload for shared or per-device icons.
     *
     * @return array{contents: string, extension: ?string, meta: array<string, mixed>}|null
     */
    public function normalizeRasterUpload(string $path, string $mime): ?array
    {
        return $this->normalizeRasterContents($path, $mime);
    }

    /**
     * @return array{contents: string, extension: ?string, meta: array<string, mixed>}|null
     */
    private function normalizeRasterContents(string $path, string $mime): ?array
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $dims = $this->readRasterDimensions($path);
        if ($dims === null) {
            return null;
        }

        $maxW = (int) config('vehicle_icons.upload.max_width', 256);
        $maxH = (int) config('vehicle_icons.upload.max_height', 256);
        $width = $dims['width'];
        $height = $dims['height'];

        $meta = [
            'resized' => false,
            'original_width' => $width,
            'original_height' => $height,
            'width' => $width,
            'height' => $height,
        ];

        if ($width <= $maxW && $height <= $maxH && ! config('vehicle_icons.upload.trim_transparent', true)) {
            return null;
        }

        $src = $this->createGdImage($path, $mime);
        if ($src === null) {
            return null;
        }

        $trimEnabled = (bool) config('vehicle_icons.upload.trim_transparent', true);
        $work = $trimEnabled ? ($this->trimTransparentPadding($src) ?? $src) : $src;
        if ($work !== $src) {
            imagedestroy($src);
            $src = $work;
            $width = imagesx($src);
            $height = imagesy($src);
            $meta['trimmed'] = true;
            $meta['original_width'] = $dims['width'];
            $meta['original_height'] = $dims['height'];
        } else {
            $meta['trimmed'] = false;
        }

        $ratio = min($maxW / max(1, $width), $maxH / max(1, $height), 1.0);
        $newW = max(1, (int) round($width * $ratio));
        $newH = max(1, (int) round($height * $ratio));

        $dst = imagecreatetruecolor($newW, $newH);
        if ($dst === false) {
            imagedestroy($src);

            return null;
        }

        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($src);

        $encoded = $this->encodeGdImage($dst, 'image/png');
        imagedestroy($dst);

        if ($encoded === null) {
            return null;
        }

        $meta['resized'] = ($newW !== $dims['width'] || $newH !== $dims['height'] || ! empty($meta['trimmed']));
        $meta['width'] = $newW;
        $meta['height'] = $newH;

        return [
            'contents' => $encoded['contents'],
            'extension' => 'png',
            'meta' => $meta,
        ];
    }

    /**
     * @param  \GdImage  $src
     * @return \GdImage|null
     */
    private function trimTransparentPadding($src)
    {
        $width = imagesx($src);
        $height = imagesy($src);
        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($src, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                if ($alpha < 120) {
                    if ($x < $minX) {
                        $minX = $x;
                    }
                    if ($y < $minY) {
                        $minY = $y;
                    }
                    if ($x > $maxX) {
                        $maxX = $x;
                    }
                    if ($y > $maxY) {
                        $maxY = $y;
                    }
                }
            }
        }

        if ($maxX < $minX || $maxY < $minY) {
            return null;
        }

        // Keep a 1px pad so edges aren't clipped.
        $minX = max(0, $minX - 1);
        $minY = max(0, $minY - 1);
        $maxX = min($width - 1, $maxX + 1);
        $maxY = min($height - 1, $maxY + 1);
        $cropW = $maxX - $minX + 1;
        $cropH = $maxY - $minY + 1;

        if ($cropW >= $width && $cropH >= $height) {
            return null;
        }

        $dst = imagecreatetruecolor($cropW, $cropH);
        if ($dst === false) {
            return null;
        }
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);
        imagecopy($dst, $src, 0, 0, $minX, $minY, $cropW, $cropH);

        return $dst;
    }

    private function makeThumbnail(string $pngContents, int $size): ?string
    {
        $src = @imagecreatefromstring($pngContents);
        if ($src === false) {
            return null;
        }
        $width = imagesx($src);
        $height = imagesy($src);
        $scale = min($size / max(1, $width), $size / max(1, $height), 1.0);
        $newW = max(1, (int) round($width * $scale));
        $newH = max(1, (int) round($height * $scale));
        $dst = imagecreatetruecolor($size, $size);
        if ($dst === false) {
            imagedestroy($src);

            return null;
        }
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);
        $offsetX = (int) floor(($size - $newW) / 2);
        $offsetY = (int) floor(($size - $newH) / 2);
        imagecopyresampled($dst, $src, $offsetX, $offsetY, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($src);
        ob_start();
        imagepng($dst, null, 6);
        $out = ob_get_clean();
        imagedestroy($dst);

        return is_string($out) ? $out : null;
    }

    /**
     * @return \GdImage|null
     */
    private function createGdImage(string $path, string $mime): mixed
    {
        return match ($mime) {
            'image/png' => @imagecreatefrompng($path) ?: null,
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path) ?: null,
            'image/webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            default => null,
        };
    }

    /**
     * @return array{contents: string, extension: string}|null
     */
    private function encodeGdImage(\GdImage $image, string $mime): ?array
    {
        ob_start();
        $ok = match ($mime) {
            'image/png' => imagepng($image, null, 6),
            'image/jpeg', 'image/jpg' => imagejpeg($image, null, 90),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, null, 90) : false,
            default => false,
        };
        $contents = ob_get_clean();

        if (! $ok || $contents === false || $contents === '') {
            return null;
        }

        $extension = match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        return [
            'contents' => $contents,
            'extension' => $extension,
        ];
    }

    private function deleteStoredFile(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        $disk = $this->disk();
        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    private function disk(): string
    {
        return (string) config('vehicle_icons.upload.disk', 'public');
    }

    private function directory(): string
    {
        return (string) config('vehicle_icons.upload.directory', 'device-icons');
    }
}
