<?php

$catalog = require __DIR__.'/vehicle_icon_catalog.php';

return [
    'catalog' => $catalog,

    /** @var array<string, array<string, mixed>> */
    'defaults' => collect($catalog['icons'])->mapWithKeys(function (array $meta, string $key) use ($catalog) {
        $category = $meta['category'] ?? 'generic';
        $legacyGroup = $catalog['legacy_group_map'][$category] ?? 'other';

        return [$key => array_merge($meta, [
            'label_key' => $meta['label_key'] ?? 'vehicle_type_'.$key,
            'group' => $legacyGroup,
        ])];
    })->all(),

    'categories' => $catalog['categories'],
    'aliases' => $catalog['aliases'],

    'size_presets' => [
        '50' => 0.5,
        '75' => 0.75,
        '100' => 1.0,
        '125' => 1.25,
        '150' => 1.5,
        '200' => 2.0,
    ],

    'legacy_size_map' => [
        'small' => '75',
        'medium' => '100',
        'large' => '150',
    ],

    'marker_colors' => [
        '#2563eb', '#22c55e', '#ef4444', '#f97316', '#eab308',
        '#a855f7', '#06b6d4', '#64748b', '#0f172a', '#ec4899',
    ],

    'builtin' => [
        'public_root' => 'icons/builtin',
    ],

    'shared' => [
        'public_root' => 'icons/shared',
    ],

    'upload' => [
        'disk' => 'public',
        'directory' => 'device-icons',
        'max_kb' => 512,
        'max_width' => 256,
        'max_height' => 256,
        'auto_resize' => true,
        'trim_transparent' => true,
        'thumbnail_size' => 64,
        'allowed_extensions' => ['png', 'svg'],
        'allowed_mimes' => ['image/png', 'image/svg+xml'],
    ],
];
