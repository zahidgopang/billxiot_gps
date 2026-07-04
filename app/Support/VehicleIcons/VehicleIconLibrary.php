<?php

namespace App\Support\VehicleIcons;

final class VehicleIconLibrary
{
    /**
     * @return array<string, string> key => label
     */
    public static function defaultTypes(): array
    {
        return collect(self::iconDefinitions())->mapWithKeys(function (array $meta, string $key) {
            return [$key => self::labelFor($key, $meta)];
        })->all();
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
                'order' => (int) ($meta['order'] ?? 999),
            ];
        })->sortBy('order')->values()->all();
    }

    /**
     * Client-side registry for icon picker + JS renderer.
     *
     * @return array{categories: list<array<string, mixed>>, icons: array<string, array<string, mixed>>, aliases: array<string, string>, marker_colors: list<string>}
     */
    public static function clientRegistry(): array
    {
        $icons = [];
        foreach (self::iconDefinitions() as $id => $meta) {
            $icons[$id] = [
                'id' => $id,
                'category' => (string) ($meta['category'] ?? 'generic'),
                'shape' => (string) ($meta['shape'] ?? 'sedan'),
                'label' => self::labelFor($id, $meta),
                'tags' => array_values($meta['tags'] ?? []),
            ];
        }

        return [
            'categories' => self::categories(),
            'icons' => $icons,
            'aliases' => config('vehicle_icons.aliases', []),
            'marker_colors' => config('vehicle_icons.marker_colors', []),
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

        $aliases = config('vehicle_icons.aliases', []);
        if (isset($aliases[$type])) {
            $type = (string) $aliases[$type];
        }

        return self::isValidDefaultType($type) ? $type : 'car';
    }

    public static function isValidDefaultType(?string $type): bool
    {
        return is_string($type) && $type !== '' && isset(self::iconDefinitions()[$type]);
    }

    public static function shapeFor(?string $type): string
    {
        $resolved = self::resolveType($type);
        $meta = self::iconDefinitions()[$resolved] ?? [];

        return (string) ($meta['shape'] ?? 'sedan');
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
    private static function labelFor(string $key, array $meta): string
    {
        $labelKey = $meta['label_key'] ?? 'vehicle_type_'.$key;
        $translated = __('app.forms.'.$labelKey);
        if ($translated !== 'app.forms.'.$labelKey) {
            return $translated;
        }

        return (string) ($meta['label'] ?? ucfirst(str_replace('_', ' ', $key)));
    }
}
