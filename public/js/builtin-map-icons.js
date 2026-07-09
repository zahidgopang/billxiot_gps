/**
 * Built-in map marker icons — static SVG assets under /icons/builtin/.
 * No programmatic shape generation; URLs come from the server icon registry.
 */
(function (global) {
    'use strict';

    const DEFAULT_ROOT = '/icons/builtin';

    function normalizeId(type) {
        return String(type || 'car').toLowerCase().trim();
    }

    function resolveIconId(type, registry) {
        const reg = registry || null;
        const aliases = reg?.aliases || {};
        let id = normalizeId(type);
        if (aliases[id]) {
            id = String(aliases[id]);
        }
        if (reg?.icons?.[id]) {
            return id;
        }
        return reg?.icons?.car ? 'car' : id;
    }

    function pathForType(type, registry) {
        const reg = registry || null;
        const id = resolveIconId(type, reg);
        const icon = reg?.icons?.[id];
        if (icon?.path) {
            return icon.path;
        }
        const root = String(reg?.builtin_root || DEFAULT_ROOT).replace(/\/$/, '');
        return `${root}/Vehicles/${id}.svg`;
    }

    function urlForType(type, registry) {
        const reg = registry || null;
        const id = resolveIconId(type, reg);
        const icon = reg?.icons?.[id];
        if (icon?.url) {
            return icon.url;
        }
        // Shared-only picker registries do not include builtin car — still serve the file.
        if (String(type || '').toLowerCase().startsWith('shared_') || (reg?.shared_only && !icon)) {
            return fallbackUrl(reg);
        }
        const path = pathForType(type, reg);
        if (/^https?:\/\//i.test(path) || path.startsWith('/')) {
            return path;
        }
        const root = String(reg?.builtin_root || DEFAULT_ROOT).replace(/\/$/, '');
        return `${root}/${path.replace(/^\//, '')}`;
    }

    function urlForPath(path, registry) {
        if (!path) return '';
        if (/^https?:\/\//i.test(path) || path.startsWith('/')) {
            return path;
        }
        const reg = registry || null;
        const normalized = String(path).replace(/^\//, '');
        if (normalized.startsWith('Shared/')) {
            // Prefer registry URL (includes cache-buster); if icon was removed, fall back to builtin car.
            const hit = reg?.icons
                ? Object.values(reg.icons).find((icon) => icon?.path === normalized || icon?.path === path)
                : null;
            if (hit?.url) {
                return hit.url;
            }
            if (reg?.shared_only) {
                return fallbackUrl(registry);
            }
            const sharedRoot = String(reg?.shared_root || '/icons/shared').replace(/\/$/, '');
            return `${sharedRoot}/${normalized}`;
        }
        const root = String(reg?.builtin_root || DEFAULT_ROOT).replace(/\/$/, '');
        return `${root}/${normalized}`;
    }

    /** Always-available default vehicle marker (north-up top-down car). */
    function fallbackUrl(registry) {
        const reg = registry || null;
        if (reg?.icons?.car?.url) {
            return reg.icons.car.url;
        }
        const root = String(reg?.builtin_root || DEFAULT_ROOT).replace(/\/$/, '');
        return `${root}/Vehicles/car.svg`;
    }

    global.BuiltinMapIcons = {
        DEFAULT_ROOT,
        resolveIconId,
        pathForType,
        urlForType,
        urlForPath,
        fallbackUrl,
    };
})(typeof window !== 'undefined' ? window : globalThis);
