<?php

namespace App\Support\VehicleIcons;

final class VehicleIconLibrary
{
    /**
     * @return array<string, string> key => label
     */
    public static function defaultTypes(): array
    {
        return self::defaultTypeLabels();
    }

    /**
     * @return array<string, string> vehicle type key => localized label
     */
    public static function defaultTypeLabels(?string $locale = null): array
    {
        $labels = collect(self::iconDefinitions())->mapWithKeys(function (array $meta, string $key) use ($locale) {
            return [$key => self::labelFor($key, $meta, $locale)];
        })->all();

        foreach (config('vehicle_icons.aliases', []) as $alias => $target) {
            if (! isset($labels[$alias]) && isset($labels[$target])) {
                $labels[$alias] = $labels[$target];
            }
        }

        return $labels;
    }

    /**
     * @return list<array{value: string, label: string, group: string, category: string, shape: string, tags: list<string>}>
     */
    public static function defaultOptions(): array
    {
        return collect(self::iconDefinitions())->map(function (array $meta, string $key) {
            return [
                'value' => $key,
                'label' => self::labelFor($key, $meta),
                'group' => (string) ($meta['group'] ?? 'other'),
                'category' => (string) ($meta['category'] ?? 'generic'),
                'shape' => (string) ($meta['shape'] ?? 'sedan'),
                'tags' => array_values($meta['tags'] ?? []),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{id: string, label_key: string, label: string, order: int}>
     */
    public static function categories(): array
    {
        $cats = config('vehicle_icons.categories', []);

        return collect($cats)->map(function (array $meta, string $id) {
            $labelKey = $meta['label_key'] ?? 'icon_cat_'.$id;
            $translated = __('app.map.'.$labelKey);

            return [
                'id' => $id,
                'label_key' => $labelKey,
                'label' => $translated !== 'app.map.'.$labelKey ? $translated : ucfirst(str_replace('_', ' ', $id)),
                'emoji' => (string) ($meta['emoji'] ?? ''),
                'order' => (int) ($meta['order'] ?? 999),
            ];
        })->sortBy('order')->values()->all();
    }

    /**
     * Client-side registry for icon picker + JS renderer.
     *
     * @return array{categories: list<array<string, mixed>>, icons: array<string, array<string, mixed>>, aliases: array<string, string>, marker_colors: list<string>}
     */
    /**
     * Icon picker registry: Super Admin uploaded icons only (no built-in library).
     *
     * @return array{categories: list<array<string, mixed>>, icons: array<string, array<string, mixed>>, aliases: array<string, string>, marker_colors: list<string>}
     */
    public static function clientRegistry(): array
    {
        $sharedService = null;
        $sharedEntries = [];
        try {
            $sharedService = app(\App\Services\Tracking\SharedMapIconService::class);
            $sharedEntries = $sharedService->registryEntries();
        } catch (\Throwable) {
            $sharedEntries = [];
        }

        $icons = [];
        foreach ($sharedEntries as $entry) {
            $icons[$entry['id']] = $entry;
        }

        $categories = $sharedService
            ? $sharedService->uploadCategories()
            : array_values(array_filter(
                self::categories(),
                fn (array $cat) => ($cat['id'] ?? '') !== 'shared'
            ));

        return [
            'categories' => $categories,
            'icons' => $icons,
            'aliases' => [],
            'marker_colors' => config('vehicle_icons.marker_colors', []),
            'builtin_root' => BuiltinMapIconStorage::publicRoot(),
            'shared_root' => SharedMapIconStorage::publicRoot(),
            'shared_only' => true,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function filterOptions(?string $search = null, ?string $category = null): array
    {
        $options = self::defaultOptions();
        $q = mb_strtolower(trim((string) $search));

        return collect($options)
            ->when($category !== null && $category !== '', fn ($c) => $c->where('category', $category))
            ->when($q !== '', function ($c) use ($q) {
                return $c->filter(function (array $row) use ($q) {
                    if (str_contains(mb_strtolower($row['value']), $q)) {
                        return true;
                    }
                    if (str_contains(mb_strtolower($row['label']), $q)) {
                        return true;
                    }

                    return collect($row['tags'] ?? [])->contains(fn ($tag) => str_contains(mb_strtolower((string) $tag), $q));
                });
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public static function sizeScales(): array
    {
        return config('vehicle_icons.size_presets', ['100' => 1.0]);
    }

    /**
     * @return list<string>
     */
    public static function markerColors(): array
    {
        return config('vehicle_icons.marker_colors', []);
    }

    public static function normalizeSizeKey(?string $value): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';
        $legacy = config('vehicle_icons.legacy_size_map', []);
        if (isset($legacy[$value])) {
            $value = (string) $legacy[$value];
        }

        $scales = self::sizeScales();

        return isset($scales[$value]) ? $value : '100';
    }

    public static function scaleForSize(?string $sizeKey): float
    {
        $key = self::normalizeSizeKey($sizeKey);

        return (float) (self::sizeScales()[$key] ?? 1.0);
    }

    public static function resolveType(?string $type): string
    {
        $type = is_string($type) ? strtolower(trim($type)) : '';
        if ($type === '') {
            return 'car';
        }

        if (SharedMapIconStorage::isSharedType($type) && SharedMapIconStorage::findByType($type)) {
            return $type;
        }

        $aliases = config('vehicle_icons.aliases', []);
        if (isset($aliases[$type])) {
            $type = (string) $aliases[$type];
        }

        return self::isValidDefaultType($type) ? $type : 'car';
    }

    public static function isValidDefaultType(?string $type): bool
    {
        if (! is_string($type) || $type === '') {
            return false;
        }

        if (isset(self::iconDefinitions()[$type])) {
            return true;
        }

        return SharedMapIconStorage::isSharedType($type)
            && SharedMapIconStorage::findByType($type) !== null;
    }

    public static function isValidIconPath(?string $path): bool
    {
        return BuiltinMapIconStorage::isValidRelativePath($path)
            || SharedMapIconStorage::isValidRelativePath($path);
    }

    public static function urlForIconPath(string $path): string
    {
        if (SharedMapIconStorage::isValidRelativePath($path)) {
            return SharedMapIconStorage::urlForRelativePath($path);
        }

        if (BuiltinMapIconStorage::isValidRelativePath($path)) {
            return BuiltinMapIconStorage::urlForRelativePath($path);
        }

        // Missing shared/custom path must never produce a broken map URL.
        return BuiltinMapIconStorage::urlForType('car');
    }

    public static function shapeFor(?string $type): string
    {
        return self::resolveType($type);
    }

    public static function builtinPathFor(?string $type): string
    {
        return BuiltinMapIconStorage::relativePathForType(self::resolveType($type));
    }

    public static function builtinUrlFor(?string $type): string
    {
        return BuiltinMapIconStorage::urlForType($type);
    }

    public static function isValidBuiltinPath(?string $path): bool
    {
        return BuiltinMapIconStorage::isValidRelativePath($path);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function iconDefinitions(): array
    {
        return config('vehicle_icons.defaults', []);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function labelFor(string $key, array $meta, ?string $locale = null): string
    {
        $labelKey = $meta['label_key'] ?? 'vehicle_type_'.$key;
        $fullKey = 'app.forms.'.$labelKey;
        $translated = $locale !== null
            ? trans($fullKey, [], $locale)
            : __($fullKey);
        if ($translated !== $fullKey) {
            return $translated;
        }

        return (string) ($meta['label'] ?? ucfirst(str_replace('_', ' ', $key)));
    }
}
