/**
 * @deprecated Built-in icons are static SVG files under /icons/builtin/.
 * Use BuiltinMapIcons (builtin-map-icons.js) instead.
 */
(function (global) {
    'use strict';

    global.VehicleIconShapes = {
        CENTER: 32,
        VIEWBOX: 64,
        renderInner: function () {
            return '';
        },
        previewDataUrl: function (shapeOrType, color, size) {
            const url = global.BuiltinMapIcons?.urlForType?.(shapeOrType) || '';
            if (!url) {
                return '';
            }
            const px = size || 64;
            const esc = encodeURIComponent(url);
            return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(
                `<svg xmlns="http://www.w3.org/2000/svg" width="${px}" height="${px}" viewBox="0 0 ${px} ${px}">` +
                `<image href="${url.replace(/"/g, '&quot;')}" width="${px}" height="${px}"/>` +
                `</svg>`
            )}`;
        },
        resolveShapeKey: function (key) {
            return global.BuiltinMapIcons?.resolveIconId?.(key) || String(key || 'car');
        },
        knownShapes: function () {
            return [];
        },
    };
})(typeof window !== 'undefined' ? window : globalThis);
