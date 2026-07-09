<?php

namespace App\Support\VehicleIcons;

final class BuiltinMapIconStorage
{
    /**
     * @return array<string, string> category id => folder name
     */
    public static function categoryFolders(): array
    {
        return config('builtin_map_icon_sources.category_folders', []);
    }

    public static function folderForCategory(string $category): string
    {
        $folders = self::categoryFolders();

        return $folders[$category] ?? ucfirst($category);
    }

    public static function publicRoot(): string
    {
        return trim((string) config('vehicle_icons.builtin.public_root', 'icons/builtin'), '/');
    }

    public static function diskRoot(): string
    {
        return public_path(self::publicRoot());
    }

    public static function relativePathForType(string $type): string
    {
        $resolved = VehicleIconLibrary::resolveType($type);
        $meta = config('vehicle_icons.defaults.'.$resolved, []);
        $category = (string) ($meta['category'] ?? 'vehicles');

        return self::folderForCategory($category).'/'.$resolved.'.svg';
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
        if (str_contains($normalized, '..') || ! str_ends_with(strtolower($normalized), '.svg')) {
            return false;
        }

        $parts = explode('/', $normalized);
        if (count($parts) !== 2) {
            return false;
        }

        [$folder, $file] = $parts;
        $allowedFolders = array_values(self::categoryFolders());
        if (! in_array($folder, $allowedFolders, true)) {
            return false;
        }

        if (! preg_match('/^[a-z0-9_]+\.svg$/', $file)) {
            return false;
        }

        return is_file(self::absolutePath($normalized));
    }

    public static function resolveRelativePath(?string $storedPath, ?string $vehicleType = null): string
    {
        if (self::isValidRelativePath($storedPath)) {
            return (string) $storedPath;
        }

        return self::relativePathForType($vehicleType ?: 'car');
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

    public static function urlForType(?string $type): string
    {
        return self::urlForRelativePath(self::relativePathForType($type ?: 'car'));
    }

    /**
     * @return list<string>
     */
    public static function allRelativePaths(): array
    {
        $paths = [];
        $root = self::diskRoot();

        if (! is_dir($root)) {
            return $paths;
        }

        foreach (self::categoryFolders() as $folder) {
            $dir = $root.DIRECTORY_SEPARATOR.$folder;
            if (! is_dir($dir)) {
                continue;
            }

            foreach (glob($dir.'/*.svg') ?: [] as $file) {
                $paths[] = $folder.'/'.basename($file);
            }
        }

        sort($paths);

        return $paths;
    }
}
