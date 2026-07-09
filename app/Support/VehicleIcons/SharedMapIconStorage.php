<?php

namespace App\Support\VehicleIcons;

use App\Models\SharedMapIcon;

final class SharedMapIconStorage
{
    public const FOLDER = 'Shared';

    public static function publicRoot(): string
    {
        return trim((string) config('vehicle_icons.shared.public_root', 'icons/shared'), '/');
    }

    public static function diskRoot(): string
    {
        return public_path(self::publicRoot());
    }

    public static function typePrefix(): string
    {
        return 'shared_';
    }

    public static function isSharedType(?string $type): bool
    {
        $type = strtolower(trim((string) $type));

        return $type !== '' && str_starts_with($type, self::typePrefix());
    }

    public static function slugFromType(?string $type): ?string
    {
        if (! self::isSharedType($type)) {
            return null;
        }

        $slug = substr(strtolower(trim((string) $type)), strlen(self::typePrefix()));

        return $slug !== '' && preg_match('/^[a-z0-9_]+$/', $slug) ? $slug : null;
    }

    public static function relativePathForSlug(string $slug, string $extension = 'png'): string
    {
        $ext = strtolower($extension);
        if (! in_array($ext, ['png', 'svg'], true)) {
            $ext = 'png';
        }

        return self::FOLDER.'/'.$slug.'.'.$ext;
    }

    public static function absolutePath(string $relativePath): string
    {
        return self::diskRoot().'/'.ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    public static function isValidRelativePath(?string $relativePath): bool
    {
        if (! is_string($relativePath) || $relativePath === '') {
            return false;
        }

        $normalized = str_replace('\\', '/', $relativePath);
        if (str_contains($normalized, '..')) {
            return false;
        }

        $parts = explode('/', $normalized);
        if (count($parts) !== 2 || $parts[0] !== self::FOLDER) {
            return false;
        }

        if (! preg_match('/^[a-z0-9_]+\.(png|svg)$/', $parts[1])) {
            return false;
        }

        return is_file(self::absolutePath($normalized));
    }

    public static function urlForRelativePath(string $relativePath): string
    {
        $relative = self::publicRoot().'/'.ltrim(str_replace('\\', '/', $relativePath), '/');
        $url = asset($relative);
        $absolute = self::absolutePath(ltrim(str_replace('\\', '/', $relativePath), '/'));
        if (is_file($absolute)) {
            $url .= (str_contains($url, '?') ? '&' : '?').'v='.filemtime($absolute);
        }

        return $url;
    }

    public static function findByType(?string $type): ?SharedMapIcon
    {
        $slug = self::slugFromType($type);
        if ($slug === null) {
            return null;
        }

        return SharedMapIcon::query()->where('slug', $slug)->first();
    }

    public static function findByRelativePath(?string $path): ?SharedMapIcon
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        return SharedMapIcon::query()
            ->where('relative_path', str_replace('\\', '/', $path))
            ->first();
    }
}
