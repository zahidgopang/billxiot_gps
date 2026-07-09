/**
 * Generates a complete set of original top-down (bird's-eye) transportation
 * SVG icons for the BillX GPS fleet map. Canvas is 64x64 with a transparent
 * background (no badge circle) and every icon is drawn nose-up (north), so
 * the map layer can rotate markers directly from the raw GPS heading where
 * 0deg = north.
 *
 * Icon ids, categories and per-icon colors are sourced from the PHP configs
 * so this script and the runtime catalog never drift apart:
 *   - config/vehicle_icon_catalog.php   (icon id -> category/shape/label)
 *   - config/builtin_map_icon_sources.php (icon id -> color)
 *
 * Usage: npm run generate:map-icons
 */
import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');

const phpBin = process.env.PHP_BINARY || 'php';
const phpRoot = root.replace(/\\/g, '/');

function loadPhpConfig(file) {
    const json = execSync(`${phpBin} -r "echo json_encode(require '${phpRoot}/${file}');"`, {
        encoding: 'utf8',
    });
    return JSON.parse(json);
}

const catalog = loadPhpConfig('config/vehicle_icon_catalog.php');
const sources = loadPhpConfig('config/builtin_map_icon_sources.php');

const categoryFolders = sources.category_folders || {};
const categoryColors = sources.category_colors || {};
const iconColors = sources.icon_colors || {};
const icons = catalog.icons || {};

const outRoot = path.join(root, 'public/icons/builtin');
const CANVAS = 64;
const CX = 32;

/* ────────────────────────────────────────────────────────────────────────
 * Geometry primitives
 * ──────────────────────────────────────────────────────────────────── */

const n = (v) => Math.round(v * 100) / 100;

function attrs(obj) {
    return Object.entries(obj)
        .filter(([, v]) => v !== undefined && v !== null && v !== '')
        .map(([k, v]) => `${k}="${v}"`)
        .join(' ');
}

const rectTag = (x, y, w, h, extra = {}) =>
    `<rect x="${n(x)}" y="${n(y)}" width="${n(w)}" height="${n(h)}" ${attrs(extra)}/>`;

const rrectTag = (x, y, w, h, rx, ry = rx, extra = {}) =>
    `<rect x="${n(x)}" y="${n(y)}" width="${n(w)}" height="${n(h)}" rx="${n(rx)}" ry="${n(ry)}" ${attrs(extra)}/>`;

const ellipseTag = (cx, cy, rx, ry, extra = {}) =>
    `<ellipse cx="${n(cx)}" cy="${n(cy)}" rx="${n(rx)}" ry="${n(ry)}" ${attrs(extra)}/>`;

const circleTag = (cx, cy, r, extra = {}) =>
    `<circle cx="${n(cx)}" cy="${n(cy)}" r="${n(r)}" ${attrs(extra)}/>`;

const polygonTag = (points, extra = {}) =>
    `<polygon points="${points.map((p) => `${n(p[0])},${n(p[1])}`).join(' ')}" ${attrs(extra)}/>`;

const pathTag = (d, extra = {}) => `<path d="${d}" ${attrs(extra)}/>`;

const lineTag = (x1, y1, x2, y2, extra = {}) =>
    `<line x1="${n(x1)}" y1="${n(y1)}" x2="${n(x2)}" y2="${n(y2)}" ${attrs(extra)}/>`;

const groupTag = (inner, extra = {}) => `<g ${attrs(extra)}>${inner}</g>`;

/* ────────────────────────────────────────────────────────────────────────
 * Color helpers
 * ──────────────────────────────────────────────────────────────────── */

function shade(hex, percent) {
    const raw = String(hex || '#2563eb').replace('#', '');
    const full = raw.length === 3 ? raw.split('').map((c) => c + c).join('') : raw;
    const num = parseInt(full, 16);
    if (!Number.isFinite(num)) return hex;
    const adj = (ch) => Math.min(255, Math.max(0, Math.round(ch + (255 * percent) / 100)));
    const r = adj((num >> 16) & 0xff);
    const g = adj((num >> 8) & 0xff);
    const b = adj(num & 0xff);
    return `#${[r, g, b].map((v) => v.toString(16).padStart(2, '0')).join('')}`;
}

const darken = (hex, amt = 28) => shade(hex, -amt);
const lighten = (hex, amt = 28) => shade(hex, amt);

/* ────────────────────────────────────────────────────────────────────────
 * Body / wheel / detail helpers
 * ──────────────────────────────────────────────────────────────────── */

function roundedPolygonPath(points, radius) {
    const count = points.length;
    const radii = Array.isArray(radius) ? radius : points.map(() => radius);
    let d = '';
    for (let i = 0; i < count; i++) {
        const prev = points[(i - 1 + count) % count];
        const curr = points[i];
        const next = points[(i + 1) % count];
        const v1 = [curr[0] - prev[0], curr[1] - prev[1]];
        const v2 = [next[0] - curr[0], next[1] - curr[1]];
        const len1 = Math.hypot(v1[0], v1[1]) || 1;
        const len2 = Math.hypot(v2[0], v2[1]) || 1;
        const r = Math.max(0, Math.min(radii[i], len1 / 2, len2 / 2));
        const p1 = [curr[0] - (v1[0] / len1) * r, curr[1] - (v1[1] / len1) * r];
        const p2 = [curr[0] + (v2[0] / len2) * r, curr[1] + (v2[1] / len2) * r];
        d += i === 0 ? `M ${n(p1[0])} ${n(p1[1])} ` : `L ${n(p1[0])} ${n(p1[1])} `;
        d += `Q ${n(curr[0])} ${n(curr[1])} ${n(p2[0])} ${n(p2[1])} `;
    }
    return `${d}Z`;
}

function trapezoidPoints(topY, botY, topW, botW, cx = CX) {
    return [
        [cx - topW / 2, topY],
        [cx + topW / 2, topY],
        [cx + botW / 2, botY],
        [cx - botW / 2, botY],
    ];
}

function halfWidthAt(topY, botY, topW, botW, y) {
    const t = (y - topY) / (botY - topY);
    return (topW + (botW - topW) * t) / 2;
}

function bodyPath(topY, botY, topW, botW, radius = 4, cx = CX) {
    return roundedPolygonPath(trapezoidPoints(topY, botY, topW, botW, cx), radius);
}

/** Generic tapered vehicle body (trapezoid, rounded corners). */
function carBody(color, { topY, botY, topW, botW, radius = 5, strokeWidth = 1.4, cx = CX } = {}) {
    return pathTag(bodyPath(topY, botY, topW, botW, radius, cx), {
        fill: color,
        stroke: darken(color, 32),
        'stroke-width': strokeWidth,
        'stroke-linejoin': 'round',
    });
}

const TIRE = '#20242b';

function wheelPod(cx, cy, w, h, color = TIRE) {
    return rrectTag(cx - w / 2, cy - h / 2, w, h, Math.min(w, h) / 2, Math.min(w, h) / 2, {
        fill: color,
        'fill-opacity': 0.92,
    });
}

function axleWheels(halfW, y, w = 4.4, h = 9, color = TIRE, cx = CX) {
    return wheelPod(cx - halfW, y, w, h, color) + wheelPod(cx + halfW, y, w, h, color);
}

function glassPanel(x, y, w, h, r, color = '#eaf4ff', opacity = 0.72) {
    return rrectTag(x, y, w, h, r, r, { fill: color, 'fill-opacity': opacity });
}

function trackBase(color, { topY, botY, w = 30, cx = CX } = {}) {
    const stroke = darken(color, 40);
    const trackW = 6.5;
    let s = '';
    s += rrectTag(cx - w / 2, topY, trackW, botY - topY, 3, 3, { fill: '#2b2f38', stroke, 'stroke-width': 1 });
    s += rrectTag(cx + w / 2 - trackW, topY, trackW, botY - topY, 3, 3, { fill: '#2b2f38', stroke, 'stroke-width': 1 });
    for (let y = topY + 4; y < botY - 2; y += 5) {
        s += lineTag(cx - w / 2 + 1, y, cx - w / 2 + trackW - 1, y, { stroke: '#4b5160', 'stroke-width': 0.8 });
        s += lineTag(cx + w / 2 - trackW + 1, y, cx + w / 2 - 1, y, { stroke: '#4b5160', 'stroke-width': 0.8 });
    }
    return s;
}

function svgWrap(body, { shadow = true } = {}) {
    const filter = shadow
        ? '<filter id="ds" x="-30%" y="-30%" width="160%" height="160%"><feDropShadow dx="0" dy="1" stdDeviation="0.85" flood-color="#0b1220" flood-opacity="0.28"/></filter>'
        : '';
    const g = shadow ? `<g filter="url(#ds)">${body}</g>` : body;
    return `<svg xmlns="http://www.w3.org/2000/svg" width="${CANVAS}" height="${CANVAS}" viewBox="0 0 ${CANVAS} ${CANVAS}">\n${filter}\n${g}\n</svg>\n`;
}

/* ────────────────────────────────────────────────────────────────────────
 * Shape renderers — keyed by catalog `shape` value
 * ──────────────────────────────────────────────────────────────────── */

const shapes = {};

/* ── Cars / light vehicles ─────────────────────────────────────────── */

shapes.car = (color) => {
    const topY = 16, botY = 50, topW = 21, botW = 25;
    let s = carBody(color, { topY, botY, topW, botW, radius: 6 });
    s += glassPanel(CX - 7.5, topY + 3.5, 15, 7, 3);
    s += rrectTag(CX - 9, topY + 12, 18, 11, 3, 3, { fill: darken(color, 12), 'fill-opacity': 0.5 });
    s += glassPanel(CX - 7, botY - 9, 14, 5.5, 2.5);
    s += axleWheels(halfWidthAt(topY, botY, topW, botW, topY + 8) + 0.6, topY + 8);
    s += axleWheels(halfWidthAt(topY, botY, topW, botW, botY - 6) + 0.6, botY - 6);
    return s;
};

shapes.suv = (color) => {
    const topY = 13, botY = 52, topW = 25, botW = 28;
    let s = carBody(color, { topY, botY, topW, botW, radius: 5 });
    s += glassPanel(CX - 9, topY + 4, 18, 7, 3);
    s += rrectTag(CX - 10.5, topY + 12.5, 21, 20, 3, 3, { fill: darken(color, 12), 'fill-opacity': 0.5 });
    s += lineTag(CX, topY + 13, CX, botY - 8, { stroke: darken(color, 25), 'stroke-width': 0.7, 'stroke-opacity': 0.5 });
    s += glassPanel(CX - 8, botY - 9, 16, 6, 2.5);
    s += axleWheels(halfWidthAt(topY, botY, topW, botW, topY + 9) + 0.8, topY + 9, 4.8, 9.5);
    s += axleWheels(halfWidthAt(topY, botY, topW, botW, botY - 7) + 0.8, botY - 7, 4.8, 9.5);
    return s;
};

shapes.pickup = (color) => {
    const topY = 14, botY = 52, topW = 23, botW = 25;
    let s = carBody(color, { topY, botY, topW, botW, radius: 5 });
    s += glassPanel(CX - 8, topY + 3.5, 16, 7, 3);
    s += rrectTag(CX - 9.5, topY + 11.5, 19, 6, 2, 2, { fill: darken(color, 12), 'fill-opacity': 0.5 });
    // open bed with floor panel + tailgate line
    s += rrectTag(CX - 10.5, topY + 19, 21, botY - (topY + 19) - 2, 2, 2, {
        fill: darken(color, 18),
        stroke: darken(color, 34),
        'stroke-width': 1,
    });
    s += lineTag(CX - 9, botY - 4, CX + 9, botY - 4, { stroke: darken(color, 40), 'stroke-width': 0.9 });
    s += axleWheels(halfWidthAt(topY, botY, topW, botW, topY + 8) + 0.6, topY + 8);
    s += axleWheels(halfWidthAt(topY, botY, topW, botW, botY - 6) + 0.6, botY - 6);
    return s;
};

function boxyVan(color, { topY, botY, topW, botW, sideWindows = 2 } = {}) {
    let s = carBody(color, { topY, botY, topW, botW, radius: 4 });
    s += glassPanel(CX - 8, topY + 2.5, 16, 5.5, 2.5);
    for (let i = 0; i < sideWindows; i++) {
        const wy = topY + 11 + i * 8.5;
        s += glassPanel(CX - halfWidthAt(topY, botY, topW, botW, wy) - 2.2, wy, 4, 6, 1.5, '#eaf4ff', 0.55);
        s += glassPanel(CX + halfWidthAt(topY, botY, topW, botW, wy) - 1.8, wy, 4, 6, 1.5, '#eaf4ff', 0.55);
    }
    s += axleWheels(halfWidthAt(topY, botY, topW, botW, topY + 8) + 0.6, topY + 8, 4.6, 9);
    s += axleWheels(halfWidthAt(topY, botY, topW, botW, botY - 6) + 0.6, botY - 6, 4.6, 9);
    return s;
}

shapes.van = (color) => boxyVan(color, { topY: 12, botY: 54, topW: 25, botW: 27, sideWindows: 2 });
shapes.mini_van = (color) => boxyVan(color, { topY: 14, botY: 52, topW: 23, botW: 25, sideWindows: 1 });

shapes.cargo_van = (color) => {
    const topY = 12, botY = 54, topW = 25, botW = 27;
    let s = carBody(color, { topY, botY, topW, botW, radius: 4 });
    s += glassPanel(CX - 8, topY + 2.5, 16, 5.5, 2.5);
    s += lineTag(CX - halfWidthAt(topY, botY, topW, botW, topY + 10), topY + 10, CX + halfWidthAt(topY, botY, topW, botW, topY + 10), topY + 10, {
        stroke: darken(color, 30),
        'stroke-width': 0.8,
        'stroke-opacity': 0.6,
    });
    s += lineTag(CX, topY + 11, CX, botY - 3, { stroke: darken(color, 20), 'stroke-width': 0.6, 'stroke-opacity': 0.4 });
    s += axleWheels(halfWidthAt(topY, botY, topW, botW, topY + 8) + 0.6, topY + 8, 4.6, 9);
    s += axleWheels(halfWidthAt(topY, botY, topW, botW, botY - 6) + 0.6, botY - 6, 4.6, 9);
    return s;
};

shapes.taxi = (color) => {
    let s = shapes.car(color);
    s += rrectTag(CX - 3, 26.5, 6, 4, 1, 1, { fill: '#111827', stroke: '#fde047', 'stroke-width': 0.6 });
    s += rectTag(CX - 12, 33, 24, 2.4, { fill: '#111827', 'fill-opacity': 0.85 });
    return s;
};

shapes.bus = (color) => {
    const topY = 8, botY = 58, w = 26;
    let s = carBody(color, { topY, botY, topW: w, botW: w, radius: 4 });
    s += glassPanel(CX - 10, topY + 2, 20, 5, 2);
    for (let y = topY + 9; y < botY - 6; y += 6.2) {
        s += glassPanel(CX - w / 2 - 1.6, y, 3.6, 4.6, 1, '#eaf4ff', 0.55);
        s += glassPanel(CX + w / 2 - 2, y, 3.6, 4.6, 1, '#eaf4ff', 0.55);
    }
    s += rectTag(CX - w / 2 + 2, botY - 4.5, w - 4, 1.6, { fill: darken(color, 35), 'fill-opacity': 0.6 });
    s += axleWheels(w / 2 + 0.4, topY + 10, 4.6, 9.5);
    s += axleWheels(w / 2 + 0.4, botY - 10, 4.6, 9.5);
    return s;
};

shapes.school_bus = (color) => {
    let s = shapes.bus(color);
    s += lineTag(CX - 12.5, 15, CX + 12.5, 15, { stroke: '#111827', 'stroke-width': 1.1, 'stroke-opacity': 0.55 });
    s += lineTag(CX - 12.5, 52, CX + 12.5, 52, { stroke: '#111827', 'stroke-width': 1.1, 'stroke-opacity': 0.55 });
    s += polygonTag(
        [
            [CX - 15, 30], [CX - 12, 27], [CX - 9, 27], [CX - 7, 30], [CX - 9, 33], [CX - 12, 33],
        ],
        { fill: '#dc2626', stroke: '#7f1d1d', 'stroke-width': 0.6 }
    );
    return s;
};

shapes.truck = (color) => {
    const cabTopY = 10, cabBotY = 24, boxBotY = 54, w = 25;
    let s = carBody(color, { topY: cabTopY, botY: cabBotY, topW: w - 3, botW: w, radius: 4 });
    s += glassPanel(CX - 8, cabTopY + 2.5, 16, 6, 2.5);
    s += pathTag(bodyPath(cabBotY - 1, boxBotY, w + 1, w + 1, 2.5), {
        fill: lighten(color, 8),
        stroke: darken(color, 34),
        'stroke-width': 1.3,
    });
    s += lineTag(CX - (w + 1) / 2 + 1.5, (cabBotY + boxBotY) / 2, CX + (w + 1) / 2 - 1.5, (cabBotY + boxBotY) / 2, {
        stroke: darken(color, 20),
        'stroke-width': 0.6,
        'stroke-opacity': 0.4,
    });
    s += axleWheels(halfWidthAt(cabTopY, cabBotY, w - 3, w, cabTopY + 7) + 0.6, cabTopY + 7, 4.4, 8.5);
    s += axleWheels(w / 2 + 0.8, boxBotY - 12, 4.6, 9);
    s += axleWheels(w / 2 + 0.8, boxBotY - 3, 4.6, 9);
    return s;
};

/* ── Cab + trailer combos ──────────────────────────────────────────── */

function tractorCab(color, { topY = 10, botY = 27, topW = 21, botW = 25 } = {}) {
    let s = carBody(color, { topY, botY, topW, botW, radius: 5 });
    s += glassPanel(CX - 7.5, topY + 2.5, 15, 6, 3);
    s += circleTag(CX - botW / 2 + 1.5, botY - 3, 1.4, { fill: darken(color, 45) });
    s += circleTag(CX + botW / 2 - 1.5, botY - 3, 1.4, { fill: darken(color, 45) });
    return { markup: s, botW };
}

shapes.semi = (color) => {
    const stroke = darken(color, 34);
    const { markup, botW } = tractorCab(color, {});
    let s = '';
    s += rrectTag(CX - 8, 27, 16, 20, 1.5, 1.5, { fill: darken(color, 46), stroke, 'stroke-width': 1 });
    s += rrectTag(CX - 5.5, 27, 11, 4.5, 1.2, 1.2, { fill: darken(color, 55) });
    s += markup;
    s += axleWheels(halfWidthAt(10, 27, 21, botW, 18) + 0.6, 18, 4.2, 8.5);
    s += axleWheels(10.5, 40, 4.8, 10);
    s += axleWheels(10.5, 51, 4.8, 10);
    return s;
};

function cabPlusTrailer(color, { trailerTopY = 30, trailerBotY = 58, trailerW = 27, radius = 2.5, trailerFill, trailerStroke, extra = '' } = {}) {
    const { markup, botW } = tractorCab(color, {});
    const strokeColor = darken(color, 34);
    let s = '';
    s += rectTag(CX - 2.2, 27, 4.4, trailerTopY - 27 + 1, { fill: darken(color, 48) });
    s += markup;
    s += axleWheels(halfWidthAt(10, 27, 21, botW, 18) + 0.6, 18, 4.2, 8.5);
    s += pathTag(bodyPath(trailerTopY, trailerBotY, trailerW, trailerW, radius), {
        fill: trailerFill || lighten(color, 10),
        stroke: trailerStroke || strokeColor,
        'stroke-width': 1.4,
    });
    s += extra;
    const midY = trailerTopY + (trailerBotY - trailerTopY) * 0.42;
    const tailY = trailerBotY - 6;
    s += axleWheels(trailerW / 2 + 0.6, midY, 4.4, 9);
    s += axleWheels(trailerW / 2 + 0.6, tailY, 4.4, 9);
    return s;
}

shapes.trailer_truck = (color) =>
    cabPlusTrailer(color, {
        trailerTopY: 29,
        trailerBotY: 59,
        trailerW: 26,
        extra: lineTag(CX - 12, 44, CX + 12, 44, { stroke: darken(color, 22), 'stroke-width': 0.6, 'stroke-opacity': 0.45 }),
    });

shapes.flatbed = (color) => {
    const deckColor = '#8a6d4b';
    let s = cabPlusTrailer(color, {
        trailerTopY: 29,
        trailerBotY: 58,
        trailerW: 24,
        radius: 1.5,
        trailerFill: deckColor,
        trailerStroke: darken(deckColor, 30),
    });
    for (let y = 32; y < 56; y += 4.5) {
        s += lineTag(CX - 10.5, y, CX + 10.5, y, { stroke: darken(deckColor, 20), 'stroke-width': 0.6, 'stroke-opacity': 0.5 });
    }
    s += rrectTag(CX - 6, 34, 12, 12, 1, 1, { fill: lighten(color, 4), stroke: darken(color, 30), 'stroke-width': 0.9 });
    return s;
};

shapes.container_truck = (color) => {
    const boxColor = lighten(color, 4);
    let s = cabPlusTrailer(color, {
        trailerTopY: 28,
        trailerBotY: 58,
        trailerW: 25,
        radius: 2,
        trailerFill: boxColor,
    });
    for (let x = -9; x <= 9; x += 4.5) {
        s += lineTag(CX + x, 30, CX + x, 56, { stroke: darken(boxColor, 22), 'stroke-width': 0.6, 'stroke-opacity': 0.55 });
    }
    s += rectTag(CX - 12, 29, 3, 3, { fill: darken(boxColor, 40) });
    s += rectTag(CX + 9, 29, 3, 3, { fill: darken(boxColor, 40) });
    s += rectTag(CX - 12, 54, 3, 3, { fill: darken(boxColor, 40) });
    s += rectTag(CX + 9, 54, 3, 3, { fill: darken(boxColor, 40) });
    return s;
};

shapes.tanker = (color) => {
    const { markup, botW } = tractorCab(color, {});
    const strokeColor = darken(color, 34);
    let s = '';
    s += rectTag(CX - 2.2, 27, 4.4, 4, { fill: darken(color, 48) });
    s += markup;
    s += axleWheels(halfWidthAt(10, 27, 21, botW, 18) + 0.6, 18, 4.2, 8.5);
    s += pathTag(bodyPath(30, 58, 22, 22, 11), { fill: '#c7cdd6', stroke: strokeColor, 'stroke-width': 1.4 });
    s += ellipseTag(CX, 34, 8.5, 3.4, { fill: 'none', stroke: darken('#c7cdd6', 25), 'stroke-width': 0.8, 'stroke-opacity': 0.7 });
    s += ellipseTag(CX, 50, 8.5, 3.4, { fill: 'none', stroke: darken('#c7cdd6', 25), 'stroke-width': 0.8, 'stroke-opacity': 0.7 });
    s += rectTag(CX - 11, 41.5, 22, 3, { fill: color, 'fill-opacity': 0.85 });
    s += axleWheels(12, 45, 4.4, 9);
    s += axleWheels(12, 55, 4.4, 9);
    return s;
};

shapes.dump_truck = (color) => {
    const { markup, botW } = tractorCab(color, { topY: 9, botY: 24, topW: 19, botW: 23 });
    const strokeColor = darken(color, 34);
    let s = '';
    s += markup;
    s += axleWheels(halfWidthAt(9, 24, 19, botW, 17) + 0.6, 17, 4.2, 8);
    s += pathTag(bodyPath(25, 58, 27, 25, 2), { fill: lighten(color, 6), stroke: strokeColor, 'stroke-width': 1.5 });
    for (let y = 29; y < 55; y += 4) {
        s += lineTag(CX - 12, y, CX + 12, y, { stroke: darken(color, 26), 'stroke-width': 0.7, 'stroke-opacity': 0.45 });
    }
    s += rectTag(CX - 13, 55, 26, 2, { fill: darken(color, 42) });
    s += axleWheels(13, 44, 4.6, 9.5);
    s += axleWheels(13, 54, 4.6, 9.5);
    return s;
};

shapes.refrigerated_truck = (color) => {
    const boxColor = '#e2e8f0';
    let s = cabPlusTrailer(color, {
        trailerTopY: 27,
        trailerBotY: 58,
        trailerW: 25,
        radius: 2,
        trailerFill: boxColor,
        trailerStroke: darken(boxColor, 35),
    });
    s += rectTag(CX - 6, 28, 12, 4, { fill: '#94a3b8', stroke: darken('#94a3b8', 25), 'stroke-width': 0.6 });
    s += rectTag(CX - 12.5, 40, 25, 3.4, { fill: color, 'fill-opacity': 0.55 });
    return s;
};

shapes.cement_mixer = (color) => {
    const { markup, botW } = tractorCab(color, { topY: 9, botY: 24, topW: 19, botW: 22 });
    let s = '';
    s += markup;
    s += axleWheels(halfWidthAt(9, 24, 19, botW, 17) + 0.6, 17, 4.2, 8);
    s += rectTag(CX - 4, 24, 8, 8, { fill: darken(color, 30) });
    s += ellipseTag(CX, 40, 12, 15, { fill: lighten(color, 10), stroke: darken(color, 36), 'stroke-width': 1.4 });
    for (const dy of [-9, -3, 3, 9]) {
        s += lineTag(CX - 8, 40 + dy - 3, CX + 8, 40 + dy + 3, { stroke: darken(color, 24), 'stroke-width': 1, 'stroke-opacity': 0.5 });
    }
    s += circleTag(CX, 28, 3.4, { fill: darken(color, 18), stroke: darken(color, 40), 'stroke-width': 0.8 });
    s += axleWheels(12.5, 48, 4.4, 9);
    s += axleWheels(12.5, 56, 4.4, 9);
    return s;
};

shapes.garbage_truck = (color) => {
    const { markup, botW } = tractorCab(color, { topY: 9, botY: 23, topW: 19, botW: 22 });
    const strokeColor = darken(color, 34);
    let s = '';
    s += markup;
    s += axleWheels(halfWidthAt(9, 23, 19, botW, 16) + 0.6, 16, 4.2, 8);
    s += pathTag(bodyPath(24, 56, 26, 24, 2.5), { fill: lighten(color, 4), stroke: strokeColor, 'stroke-width': 1.5 });
    s += rrectTag(CX - 10, 27, 20, 5, 1, 1, { fill: darken(color, 20), 'fill-opacity': 0.55 });
    s += rectTag(CX - 11.5, 51, 23, 4, { fill: darken(color, 38) });
    s += axleWheels(12.5, 42, 4.6, 9);
    s += axleWheels(12.5, 53, 4.6, 9.5);
    return s;
};

shapes.tow_truck = (color) => {
    const { markup, botW } = tractorCab(color, { topY: 12, botY: 28, topW: 21, botW: 25 });
    const strokeColor = darken(color, 34);
    let s = '';
    s += markup;
    s += rrectTag(CX - 9, 28, 18, 22, 2, 2, { fill: darken(color, 10), stroke: strokeColor, 'stroke-width': 1.3 });
    s += polygonTag([[CX - 1.6, 30], [CX + 1.6, 30], [CX + 4, 12], [CX - 4, 12]], {
        fill: darken(color, 46),
        stroke: strokeColor,
        'stroke-width': 0.8,
    });
    s += circleTag(CX, 12, 2.1, { fill: 'none', stroke: darken(color, 46), 'stroke-width': 1.2 });
    s += rectTag(CX - 3, 26, 6, 2.2, { fill: '#fbbf24', stroke: '#92400e', 'stroke-width': 0.5 });
    s += axleWheels(halfWidthAt(12, 28, 21, botW, 20) + 0.6, 20, 4.4, 9);
    s += axleWheels(10, 44, 4.6, 9.5);
    return s;
};

/* ── Heavy equipment ────────────────────────────────────────────────── */

shapes.forklift = (color) => {
    const strokeColor = darken(color, 34);
    let s = '';
    s += lineTag(CX - 4.5, 8, CX - 4.5, 24, { stroke: darken(color, 25), 'stroke-width': 2.6 });
    s += lineTag(CX + 4.5, 8, CX + 4.5, 24, { stroke: darken(color, 25), 'stroke-width': 2.6 });
    s += rrectTag(CX - 8, 22, 16, 8, 1.5, 1.5, { fill: darken(color, 15), stroke: strokeColor, 'stroke-width': 1.1 });
    s += rrectTag(CX - 7.5, 28, 15, 20, 3, 3, { fill: color, stroke: strokeColor, 'stroke-width': 1.4 });
    s += glassPanel(CX - 5.5, 30, 11, 9, 2, '#eaf4ff', 0.6);
    s += rectTag(CX - 6, 40, 12, 5, { fill: darken(color, 20) });
    s += axleWheels(8, 33, 4.4, 6.5);
    s += axleWheels(6, 47, 4.8, 7);
    return s;
};

shapes.excavator = (color) => {
    const strokeColor = darken(color, 34);
    let s = '';
    s += trackBase(color, { topY: 30, botY: 58, w: 30 });
    s += rrectTag(CX - 10, 32, 20, 22, 3, 3, { fill: color, stroke: strokeColor, 'stroke-width': 1.4 });
    s += circleTag(CX, 40, 8.5, { fill: darken(color, 8), stroke: strokeColor, 'stroke-width': 1.2 });
    s += glassPanel(CX - 5, 34, 10, 7, 2, '#eaf4ff', 0.6);
    s += polygonTag([[CX - 2.6, 34], [CX + 2.6, 34], [CX + 4.5, 16], [CX - 4.5, 16]], {
        fill: darken(color, 18),
        stroke: strokeColor,
        'stroke-width': 1,
    });
    s += polygonTag([[CX - 3.2, 18], [CX + 3.2, 18], [CX + 2.2, 8], [CX - 2.2, 8]], {
        fill: darken(color, 26),
        stroke: strokeColor,
        'stroke-width': 1,
    });
    s += pathTag(`M ${CX - 4.5} 9 Q ${CX} 4 ${CX + 4.5} 9 L ${CX + 2.6} 12 Q ${CX} 9 ${CX - 2.6} 12 Z`, {
        fill: darken(color, 34),
        stroke: strokeColor,
        'stroke-width': 0.8,
    });
    return s;
};

shapes.bulldozer = (color) => {
    const strokeColor = darken(color, 34);
    let s = '';
    s += trackBase(color, { topY: 20, botY: 56, w: 32 });
    s += rrectTag(CX - 11, 24, 22, 24, 3, 3, { fill: color, stroke: strokeColor, 'stroke-width': 1.4 });
    s += glassPanel(CX - 6, 28, 12, 8, 2, '#eaf4ff', 0.6);
    s += rrectTag(CX - 6, 38, 12, 8, 1.5, 1.5, { fill: darken(color, 16) });
    s += pathTag(
        `M ${CX - 13} 20 L ${CX + 13} 20 L ${CX + 15} 12 Q ${CX} 8 ${CX - 15} 12 Z`,
        { fill: darken(color, 12), stroke: strokeColor, 'stroke-width': 1.3 }
    );
    s += lineTag(CX - 9, 16, CX - 9, 20, { stroke: strokeColor, 'stroke-width': 1 });
    s += lineTag(CX + 9, 16, CX + 9, 20, { stroke: strokeColor, 'stroke-width': 1 });
    return s;
};

shapes.crane = (color) => {
    const strokeColor = darken(color, 34);
    let s = '';
    s += rrectTag(CX - 12, 34, 24, 24, 2.5, 2.5, { fill: darken(color, 10), stroke: strokeColor, 'stroke-width': 1.3 });
    s += rrectTag(CX - 14, 36, 3.4, 8, 1, 1, { fill: darken(color, 30) });
    s += rrectTag(CX + 10.6, 36, 3.4, 8, 1, 1, { fill: darken(color, 30) });
    s += rrectTag(CX - 14, 48, 3.4, 8, 1, 1, { fill: darken(color, 30) });
    s += rrectTag(CX + 10.6, 48, 3.4, 8, 1, 1, { fill: darken(color, 30) });
    s += circleTag(CX, 42, 8.5, { fill: color, stroke: strokeColor, 'stroke-width': 1.3 });
    s += glassPanel(CX - 4.5, 44, 9, 8, 2, '#eaf4ff', 0.6);
    s += rectTag(CX - 2.6, 8, 5.2, 32, { fill: darken(color, 14), stroke: strokeColor, 'stroke-width': 1 });
    for (let y = 12; y < 38; y += 5) {
        s += lineTag(CX - 2.6, y, CX + 2.6, y + 3, { stroke: strokeColor, 'stroke-width': 0.6, 'stroke-opacity': 0.6 });
        s += lineTag(CX + 2.6, y, CX - 2.6, y + 3, { stroke: strokeColor, 'stroke-width': 0.6, 'stroke-opacity': 0.6 });
    }
    s += lineTag(CX, 8, CX, 4, { stroke: darken(color, 40), 'stroke-width': 1 });
    s += circleTag(CX, 3.4, 1.3, { fill: darken(color, 40) });
    return s;
};

shapes.loader = (color) => {
    const strokeColor = darken(color, 34);
    let s = '';
    s += rrectTag(CX - 10, 28, 20, 22, 3, 3, { fill: color, stroke: strokeColor, 'stroke-width': 1.4 });
    s += glassPanel(CX - 5.5, 32, 11, 9, 2, '#eaf4ff', 0.6);
    s += rectTag(CX - 5, 42, 10, 6, { fill: darken(color, 16) });
    s += rectTag(CX - 3.2, 16, 6.4, 14, { fill: darken(color, 20), stroke: strokeColor, 'stroke-width': 1 });
    s += pathTag(`M ${CX - 9} 16 Q ${CX} 8 ${CX + 9} 16 L ${CX + 7} 20 Q ${CX} 14 ${CX - 7} 20 Z`, {
        fill: darken(color, 8),
        stroke: strokeColor,
        'stroke-width': 1.1,
    });
    s += axleWheels(10.5, 34, 5, 8.5);
    s += axleWheels(10.5, 48, 5, 8.5);
    return s;
};

/* ── Emergency & light utility ─────────────────────────────────────── */

shapes.ambulance = (color) => {
    let s = boxyVan(color, { topY: 12, botY: 54, topW: 24, botW: 26, sideWindows: 1 });
    s += rectTag(CX - 5.5, 30, 11, 3, { fill: '#dc2626' });
    s += rectTag(CX - 1.5, 26, 3, 11, { fill: '#dc2626' });
    s += rrectTag(CX - 7, 15.5, 14, 3, 1, 1, { fill: '#1d4ed8' });
    s += rectTag(CX - 7, 15.5, 4.6, 3, { fill: '#dc2626' });
    return s;
};

shapes.police = (color) => {
    let s = shapes.car(color);
    s += rrectTag(CX - 6, 21, 12, 3.2, 1, 1, { fill: '#0f172a' });
    s += rectTag(CX - 6, 21, 6, 3.2, { fill: '#dc2626' });
    s += rectTag(CX, 21, 6, 3.2, { fill: '#2563eb' });
    s += rectTag(CX - 10.5, 30, 21, 3, { fill: '#0f172a', 'fill-opacity': 0.85 });
    return s;
};

shapes.fire_truck = (color) => {
    const { markup, botW } = tractorCab(color, { topY: 9, botY: 24, topW: 20, botW: 23 });
    const strokeColor = darken(color, 34);
    let s = '';
    s += markup;
    s += rrectTag(CX - 6, 20, 12, 3, 1, 1, { fill: '#0f172a' });
    s += rectTag(CX - 6, 20, 6, 3, { fill: '#dc2626' });
    s += rectTag(CX, 20, 6, 3, { fill: '#2563eb' });
    s += axleWheels(halfWidthAt(9, 24, 20, botW, 17) + 0.6, 17, 4.4, 8.5);
    s += pathTag(bodyPath(25, 57, 26, 25, 2), { fill: color, stroke: strokeColor, 'stroke-width': 1.5 });
    for (let y = 29; y < 54; y += 5) {
        s += lineTag(CX - 12, y, CX + 12, y, { stroke: '#fde68a', 'stroke-width': 0.8, 'stroke-opacity': 0.65 });
    }
    s += axleWheels(12.5, 44, 4.6, 9.5);
    s += axleWheels(12.5, 54, 4.6, 9.5);
    return s;
};

shapes.atv = (color) => {
    const topY = 20, botY = 48, topW = 16, botW = 18;
    let s = carBody(color, { topY, botY, topW, botW, radius: 3 });
    s += lineTag(CX - 7, 20, CX + 7, 20, { stroke: darken(color, 30), 'stroke-width': 1.6 });
    s += rrectTag(CX - 4, 26, 8, 14, 2, 2, { fill: darken(color, 14) });
    s += axleWheels(halfWidthAt(topY, botY, topW, botW, 24) + 1.4, 24, 5.4, 10, '#15181d');
    s += axleWheels(halfWidthAt(topY, botY, topW, botW, 44) + 1.4, 44, 5.4, 10, '#15181d');
    return s;
};

/* ── Two-wheelers ───────────────────────────────────────────────────── */

shapes.bicycle = (color) => {
    const stroke = darken(color, 15);
    let s = '';
    s += ellipseTag(CX, 16, 4.2, 6, { fill: 'none', stroke: color, 'stroke-width': 2.4 });
    s += ellipseTag(CX, 48, 4.2, 6, { fill: 'none', stroke: color, 'stroke-width': 2.4 });
    s += lineTag(CX, 22, CX, 42, { stroke, 'stroke-width': 2 });
    s += lineTag(CX - 6, 30, CX + 6, 30, { stroke, 'stroke-width': 1.8 });
    s += lineTag(CX, 22, CX - 5, 30, { stroke, 'stroke-width': 1.8 });
    s += lineTag(CX, 42, CX + 5, 30, { stroke, 'stroke-width': 1.8 });
    s += lineTag(CX - 6.5, 21, CX + 6.5, 21, { stroke, 'stroke-width': 2, 'stroke-linecap': 'round' });
    s += circleTag(CX, 44, 1.6, { fill: stroke });
    return s;
};

shapes.motorcycle = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += ellipseTag(CX, 14, 4.2, 7, { fill: '#15181d', stroke: '#000000', 'stroke-width': 0.6 });
    s += ellipseTag(CX, 50, 4.2, 7, { fill: '#15181d', stroke: '#000000', 'stroke-width': 0.6 });
    s += rrectTag(CX - 3.6, 19, 7.2, 28, 3.4, 3.4, { fill: color, stroke, 'stroke-width': 1.3 });
    s += ellipseTag(CX, 27, 4.6, 6.2, { fill: lighten(color, 22), stroke, 'stroke-width': 0.8 });
    s += rrectTag(CX - 2.6, 37, 5.2, 9, 2, 2, { fill: darken(color, 16) });
    s += lineTag(CX - 8.5, 17, CX + 8.5, 17, { stroke, 'stroke-width': 2.3, 'stroke-linecap': 'round' });
    return s;
};

shapes.scooter = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += ellipseTag(CX, 16, 3, 5.2, { fill: '#20242b' });
    s += ellipseTag(CX, 48, 3, 5.2, { fill: '#20242b' });
    s += rrectTag(CX - 2.4, 24, 4.8, 8, 1.5, 1.5, { fill: color, stroke, 'stroke-width': 1.1 });
    s += rrectTag(CX - 5.5, 33, 11, 12, 2.5, 2.5, { fill: color, stroke, 'stroke-width': 1.2 });
    s += lineTag(CX - 6, 19, CX + 6, 19, { stroke, 'stroke-width': 1.8, 'stroke-linecap': 'round' });
    return s;
};

shapes.tractor = (color) => {
    const strokeColor = darken(color, 32);
    let s = '';
    s += ellipseTag(CX - 12.5, 44, 6.5, 11, { fill: '#20242b', stroke: '#000', 'stroke-width': 0.6 });
    s += ellipseTag(CX + 12.5, 44, 6.5, 11, { fill: '#20242b', stroke: '#000', 'stroke-width': 0.6 });
    s += ellipseTag(CX - 6.5, 18, 3, 5, { fill: '#20242b' });
    s += ellipseTag(CX + 6.5, 18, 3, 5, { fill: '#20242b' });
    s += rrectTag(CX - 6, 14, 12, 16, 3, 3, { fill: darken(color, 8), stroke: strokeColor, 'stroke-width': 1.2 });
    s += rrectTag(CX - 7.5, 28, 15, 18, 3, 3, { fill: color, stroke: strokeColor, 'stroke-width': 1.4 });
    s += glassPanel(CX - 5, 30, 10, 9, 2, '#eaf4ff', 0.6);
    s += circleTag(CX + 8, 16, 1.1, { fill: darken(color, 40) });
    return s;
};

/* ── Aircraft ───────────────────────────────────────────────────────── */

shapes.airplane = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += pathTag(`M ${CX} 6 Q ${CX + 3} 12 ${CX + 2.4} 24 L ${CX + 1.8} 52 Q ${CX} 56 ${CX - 1.8} 52 L ${CX - 2.4} 24 Q ${CX - 3} 12 ${CX} 6 Z`, {
        fill: color,
        stroke,
        'stroke-width': 1.2,
    });
    s += polygonTag([[CX, 22], [CX + 24, 34], [CX + 20, 38], [CX + 1.6, 30]], { fill: color, stroke, 'stroke-width': 1 });
    s += polygonTag([[CX, 22], [CX - 24, 34], [CX - 20, 38], [CX - 1.6, 30]], { fill: color, stroke, 'stroke-width': 1 });
    s += polygonTag([[CX, 44], [CX + 9, 51], [CX + 7, 53], [CX + 0.8, 48]], { fill: darken(color, 8), stroke, 'stroke-width': 0.8 });
    s += polygonTag([[CX, 44], [CX - 9, 51], [CX - 7, 53], [CX - 0.8, 48]], { fill: darken(color, 8), stroke, 'stroke-width': 0.8 });
    s += rectTag(CX - 1.6, 48, 3.2, 6, { fill: darken(color, 20) });
    s += glassPanel(CX - 1.6, 11, 3.2, 6, 1.4, '#eaf4ff', 0.7);
    return s;
};

shapes.private_jet = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += pathTag(`M ${CX} 8 Q ${CX + 2.4} 16 ${CX + 2} 30 L ${CX + 1.5} 52 Q ${CX} 55 ${CX - 1.5} 52 L ${CX - 2} 30 Q ${CX - 2.4} 16 ${CX} 8 Z`, {
        fill: color,
        stroke,
        'stroke-width': 1.2,
    });
    s += polygonTag([[CX + 1.4, 32], [CX + 22, 46], [CX + 18, 49], [CX + 1.2, 38]], { fill: color, stroke, 'stroke-width': 1 });
    s += polygonTag([[CX - 1.4, 32], [CX - 22, 46], [CX - 18, 49], [CX - 1.2, 38]], { fill: color, stroke, 'stroke-width': 1 });
    s += polygonTag([[CX, 46], [CX + 6, 53], [CX + 4.5, 54.5], [CX + 0.6, 50]], { fill: darken(color, 8), stroke, 'stroke-width': 0.7 });
    s += polygonTag([[CX, 46], [CX - 6, 53], [CX - 4.5, 54.5], [CX - 0.6, 50]], { fill: darken(color, 8), stroke, 'stroke-width': 0.7 });
    s += circleTag(CX + 10, 44, 1.6, { fill: darken(color, 30) });
    s += circleTag(CX - 10, 44, 1.6, { fill: darken(color, 30) });
    s += glassPanel(CX - 1.4, 13, 2.8, 6, 1.2, '#eaf4ff', 0.7);
    return s;
};

shapes.helicopter = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += ellipseTag(CX, 30, 6.5, 13, { fill: color, stroke, 'stroke-width': 1.3 });
    s += glassPanel(CX - 4, 20, 8, 8, 3, '#eaf4ff', 0.68);
    s += rectTag(CX - 1.6, 42, 3.2, 16, { fill: darken(color, 12), stroke, 'stroke-width': 0.9 });
    s += circleTag(CX, 58, 1.8, { fill: 'none', stroke, 'stroke-width': 1 });
    s += lineTag(CX - 3, 58, CX + 3, 58, { stroke, 'stroke-width': 1 });
    s += circleTag(CX, 30, 1.6, { fill: darken(color, 30) });
    s += lineTag(CX - 22, 24, CX + 22, 36, { stroke: darken(color, 10), 'stroke-width': 1.3, 'stroke-opacity': 0.85 });
    s += lineTag(CX - 22, 36, CX + 22, 24, { stroke: darken(color, 10), 'stroke-width': 1.3, 'stroke-opacity': 0.85 });
    s += circleTag(CX, 30, 23, { fill: 'none', stroke: darken(color, 10), 'stroke-width': 0.6, 'stroke-opacity': 0.35 });
    return s;
};

shapes.drone = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    const arms = [[-1, -1], [1, -1], [-1, 1], [1, 1]];
    for (const [dx, dy] of arms) {
        s += lineTag(CX, CX, CX + dx * 17, CX + dy * 17, { stroke: darken(color, 20), 'stroke-width': 2.2, 'stroke-linecap': 'round' });
        s += circleTag(CX + dx * 17, CX + dy * 17, 6, { fill: 'none', stroke: darken(color, 12), 'stroke-width': 1.4, 'stroke-opacity': 0.85 });
        s += circleTag(CX + dx * 17, CX + dy * 17, 1.6, { fill: darken(color, 34) });
    }
    s += rrectTag(CX - 6, CX - 8, 12, 16, 3, 3, { fill: color, stroke, 'stroke-width': 1.3 });
    s += circleTag(CX, CX - 3, 1.4, { fill: '#22c55e' });
    s += polygonTag([[CX - 3, CX - 8], [CX + 3, CX - 8], [CX, CX - 13]], { fill: darken(color, 10), stroke, 'stroke-width': 0.8 });
    return s;
};

shapes.hot_air_balloon = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += circleTag(CX, 26, 18, { fill: color, stroke, 'stroke-width': 1.4 });
    for (const dx of [-9, 0, 9]) {
        s += pathTag(`M ${CX + dx} 9 Q ${CX + dx * 1.3} 26 ${CX + dx} 43`, { fill: 'none', stroke: darken(color, 16), 'stroke-width': 1, 'stroke-opacity': 0.55 });
    }
    s += lineTag(CX - 5, 44, CX - 3.4, 51, { stroke, 'stroke-width': 1 });
    s += lineTag(CX + 5, 44, CX + 3.4, 51, { stroke, 'stroke-width': 1 });
    s += rrectTag(CX - 4.5, 51, 9, 7, 1.5, 1.5, { fill: darken(color, 24), stroke, 'stroke-width': 1 });
    return s;
};

/* ── Marine ─────────────────────────────────────────────────────────── */

function hullPath(topY, botY, w, cx = CX) {
    return `M ${cx} ${topY} Q ${cx + w / 2} ${topY + (botY - topY) * 0.28} ${cx + w / 2} ${botY - (botY - topY) * 0.18} Q ${cx + w / 2} ${botY} ${cx} ${botY} Q ${cx - w / 2} ${botY} ${cx - w / 2} ${botY - (botY - topY) * 0.18} Q ${cx - w / 2} ${topY + (botY - topY) * 0.28} ${cx} ${topY} Z`;
}

shapes.sail_boat = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += pathTag(hullPath(14, 56, 14), { fill: color, stroke, 'stroke-width': 1.3 });
    s += lineTag(CX, 12, CX, 48, { stroke: darken(color, 40), 'stroke-width': 1 });
    s += polygonTag([[CX, 14], [CX + 13, 34], [CX, 40]], { fill: '#f8fafc', stroke: '#94a3b8', 'stroke-width': 0.8, 'fill-opacity': 0.92 });
    s += lineTag(CX, 40, CX - 9, 46, { stroke: darken(color, 20), 'stroke-width': 1 });
    return s;
};

shapes.speed_boat = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += pathTag(`M ${CX} 10 Q ${CX + 9} 24 ${CX + 8} 46 Q ${CX + 7} 54 ${CX} 56 Q ${CX - 7} 54 ${CX - 8} 46 Q ${CX - 9} 24 ${CX} 10 Z`, {
        fill: color,
        stroke,
        'stroke-width': 1.3,
    });
    s += glassPanel(CX - 4, 20, 8, 8, 3, '#eaf4ff', 0.65);
    s += rectTag(CX - 3, 48, 6, 4, { fill: darken(color, 24) });
    return s;
};

shapes.yacht = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += pathTag(hullPath(12, 56, 20), { fill: color, stroke, 'stroke-width': 1.3 });
    s += rrectTag(CX - 6, 26, 12, 18, 2.5, 2.5, { fill: '#f1f5f9', stroke: '#94a3b8', 'stroke-width': 0.9 });
    s += rrectTag(CX - 3.5, 30, 7, 8, 1.5, 1.5, { fill: '#cbd5e1' });
    s += pathTag(`M 16 22 Q ${CX} 12 48 22`, { fill: 'none', stroke, 'stroke-width': 1 });
    return s;
};

shapes.cargo_ship = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += pathTag(`M ${CX} 8 L ${CX + 11} 22 L ${CX + 11} 50 Q ${CX + 11} 56 ${CX} 56 Q ${CX - 11} 56 ${CX - 11} 50 L ${CX - 11} 22 Z`, {
        fill: color,
        stroke,
        'stroke-width': 1.3,
    });
    const containerColors = ['#ef4444', '#3b82f6', '#eab308'];
    let idx = 0;
    for (let y = 24; y < 44; y += 6.5) {
        s += rectTag(CX - 8.5, y, 17, 5.2, { fill: containerColors[idx % containerColors.length], stroke: '#00000030', 'stroke-width': 0.5 });
        idx++;
    }
    s += rrectTag(CX - 7, 47, 14, 8, 1.5, 1.5, { fill: darken(color, 20), stroke, 'stroke-width': 1 });
    return s;
};

shapes.fishing_boat = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += pathTag(hullPath(14, 54, 15), { fill: color, stroke, 'stroke-width': 1.3 });
    s += rrectTag(CX - 4.5, 20, 9, 9, 2, 2, { fill: lighten(color, 8), stroke, 'stroke-width': 1 });
    s += lineTag(CX - 15, 30, CX - 22, 24, { stroke: darken(color, 20), 'stroke-width': 1 });
    s += lineTag(CX + 15, 30, CX + 22, 24, { stroke: darken(color, 20), 'stroke-width': 1 });
    s += circleTag(CX, 44, 4, { fill: 'none', stroke: darken(color, 20), 'stroke-width': 1.4 });
    return s;
};

shapes.ferry = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += pathTag(`M ${CX} 12 Q ${CX + 13} 20 ${CX + 13} 32 L ${CX + 13} 50 Q ${CX + 13} 55 ${CX} 55 Q ${CX - 13} 55 ${CX - 13} 50 L ${CX - 13} 32 Q ${CX - 13} 20 ${CX} 12 Z`, {
        fill: color,
        stroke,
        'stroke-width': 1.3,
    });
    s += rrectTag(CX - 9, 24, 18, 22, 1.5, 1.5, { fill: lighten(color, 10), stroke, 'stroke-width': 1 });
    s += rrectTag(CX - 5, 28, 10, 8, 1.5, 1.5, { fill: '#eaf4ff', 'fill-opacity': 0.65 });
    s += lineTag(CX, 47, CX, 51, { stroke: darken(color, 30), 'stroke-width': 1 });
    return s;
};

/* ── Assets ─────────────────────────────────────────────────────────── */

function containerShape(color) {
    const stroke = darken(color, 30);
    let s = '';
    s += rrectTag(CX - 9, 12, 18, 40, 2, 2, { fill: color, stroke, 'stroke-width': 1.4 });
    for (let x = -6; x <= 6; x += 3) {
        s += lineTag(CX + x, 14, CX + x, 50, { stroke: darken(color, 18), 'stroke-width': 0.6, 'stroke-opacity': 0.55 });
    }
    for (const cy of [14, 48]) {
        s += rectTag(CX - 8.5, cy === 14 ? 12.5 : 47.5, 2.4, 2.4, { fill: darken(color, 45) });
        s += rectTag(CX + 6.1, cy === 14 ? 12.5 : 47.5, 2.4, 2.4, { fill: darken(color, 45) });
    }
    s += lineTag(CX, 34, CX, 50, { stroke: darken(color, 30), 'stroke-width': 0.9 });
    return s;
}
shapes.container_red = containerShape;
shapes.container_blue = containerShape;
shapes.container_green = containerShape;
shapes.container_yellow = containerShape;

shapes.package = (color) => {
    const stroke = darken(color, 32);
    let s = '';
    s += rrectTag(16, 16, 32, 32, 2.5, 2.5, { fill: color, stroke, 'stroke-width': 1.5 });
    s += lineTag(CX, 16, CX, 48, { stroke: darken(color, 22), 'stroke-width': 2 });
    s += lineTag(16, CX, 48, CX, { stroke: darken(color, 22), 'stroke-width': 2 });
    s += lineTag(16, 16, 24, 24, { stroke: lighten(color, 25), 'stroke-width': 1, 'stroke-opacity': 0.6 });
    s += lineTag(48, 48, 40, 40, { stroke: lighten(color, 25), 'stroke-width': 1, 'stroke-opacity': 0.6 });
    return s;
};

shapes.pallet = (color) => {
    const stroke = darken(color, 32);
    let s = '';
    s += rrectTag(12, 12, 40, 40, 1.5, 1.5, { fill: color, stroke, 'stroke-width': 1.5 });
    for (let y = 17; y < 50; y += 5.4) {
        s += rectTag(15, y, 34, 3.4, { fill: darken(color, 12), stroke: darken(color, 26), 'stroke-width': 0.5 });
    }
    return s;
};

shapes.fuel_tank = (color) => {
    const stroke = darken(color, 32);
    let s = '';
    s += circleTag(CX, CX, 20, { fill: color, stroke, 'stroke-width': 1.5 });
    s += circleTag(CX, CX, 14, { fill: 'none', stroke: darken(color, 18), 'stroke-width': 1, 'stroke-opacity': 0.6 });
    s += circleTag(CX, CX, 3.4, { fill: darken(color, 24), stroke, 'stroke-width': 0.8 });
    s += rectTag(CX - 1.6, 8, 3.2, 6, { fill: darken(color, 30) });
    return s;
};

shapes.generator = (color) => {
    const stroke = darken(color, 32);
    let s = '';
    s += rrectTag(12, 18, 34, 30, 2.5, 2.5, { fill: color, stroke, 'stroke-width': 1.5 });
    for (let x = 16; x < 42; x += 4.5) {
        s += lineTag(x, 22, x, 44, { stroke: darken(color, 16), 'stroke-width': 1, 'stroke-opacity': 0.55 });
    }
    s += circleTag(46, 24, 4, { fill: darken(color, 20), stroke, 'stroke-width': 1 });
    s += rectTag(12, 46, 34, 4, { fill: darken(color, 40) });
    return s;
};

shapes.gps_device = (color) => {
    const stroke = darken(color, 32);
    let s = '';
    s += rrectTag(20, 22, 24, 30, 5, 5, { fill: color, stroke, 'stroke-width': 1.5 });
    s += circleTag(CX, 34, 6, { fill: '#eaf4ff', 'fill-opacity': 0.85 });
    s += circleTag(CX, 34, 2.2, { fill: color });
    s += rectTag(CX - 1.2, 46, 2.4, 5, { fill: darken(color, 20) });
    s += pathTag(`M ${CX - 10} 15 A 14 14 0 0 1 ${CX + 10} 15`, { fill: 'none', stroke: color, 'stroke-width': 1.4, 'stroke-linecap': 'round', 'stroke-opacity': 0.75 });
    s += pathTag(`M ${CX - 5} 18 A 8 8 0 0 1 ${CX + 5} 18`, { fill: 'none', stroke: color, 'stroke-width': 1.4, 'stroke-linecap': 'round', 'stroke-opacity': 0.9 });
    return s;
};

shapes.radio_device = (color) => {
    const stroke = darken(color, 32);
    let s = '';
    s += rrectTag(20, 20, 24, 34, 4, 4, { fill: color, stroke, 'stroke-width': 1.5 });
    s += lineTag(CX + 4, 20, CX + 9, 8, { stroke, 'stroke-width': 1.6, 'stroke-linecap': 'round' });
    s += circleTag(CX + 9, 8, 1.2, { fill: stroke });
    s += circleTag(CX, 32, 5, { fill: darken(color, 18) });
    for (const dy of [42, 46, 50]) {
        s += lineTag(24, dy, 40, dy, { stroke: darken(color, 24), 'stroke-width': 1.2, 'stroke-opacity': 0.6 });
    }
    return s;
};

/* ── Animals (simplified top-view silhouettes) ─────────────────────── */

function animalLegs(color, positions, w = 3, h = 7) {
    let s = '';
    for (const [x, y] of positions) {
        s += ellipseTag(x, y, w / 2, h / 2, { fill: darken(color, 20) });
    }
    return s;
}

shapes.dog = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += ellipseTag(CX, 34, 10, 17, { fill: color, stroke, 'stroke-width': 1.3 });
    s += ellipseTag(CX, 15, 6, 6.5, { fill: color, stroke, 'stroke-width': 1.2 });
    s += ellipseTag(CX - 5, 11, 2.4, 3.4, { fill: darken(color, 16) });
    s += ellipseTag(CX + 5, 11, 2.4, 3.4, { fill: darken(color, 16) });
    s += animalLegs(color, [[CX - 8, 26], [CX + 8, 26], [CX - 8, 42], [CX + 8, 42]]);
    s += pathTag(`M ${CX} 50 Q ${CX + 7} 55 ${CX + 4} 60`, { fill: 'none', stroke: color, 'stroke-width': 3, 'stroke-linecap': 'round' });
    return s;
};

shapes.cat = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += ellipseTag(CX, 35, 8, 15, { fill: color, stroke, 'stroke-width': 1.3 });
    s += ellipseTag(CX, 17, 5.4, 5.8, { fill: color, stroke, 'stroke-width': 1.2 });
    s += polygonTag([[CX - 6, 14], [CX - 3, 8], [CX - 1, 13]], { fill: color, stroke, 'stroke-width': 0.8 });
    s += polygonTag([[CX + 6, 14], [CX + 3, 8], [CX + 1, 13]], { fill: color, stroke, 'stroke-width': 0.8 });
    s += animalLegs(color, [[CX - 6.5, 27], [CX + 6.5, 27], [CX - 6.5, 40], [CX + 6.5, 40]], 2.6, 6);
    s += pathTag(`M ${CX} 49 Q ${CX - 8} 54 ${CX - 3} 60`, { fill: 'none', stroke: color, 'stroke-width': 2.6, 'stroke-linecap': 'round' });
    return s;
};

shapes.horse = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += ellipseTag(CX, 36, 8, 18, { fill: color, stroke, 'stroke-width': 1.3 });
    s += rrectTag(CX - 3.6, 10, 7.2, 16, 3, 3, { fill: color, stroke, 'stroke-width': 1.2 });
    s += pathTag(`M ${CX - 3.4} 12 Q ${CX} 8 ${CX + 3.4} 12 L ${CX + 3.4} 26 L ${CX - 3.4} 26 Z`, { fill: darken(color, 18), 'fill-opacity': 0.6 });
    s += animalLegs(color, [[CX - 6, 26], [CX + 6, 26], [CX - 6, 48], [CX + 6, 48]], 3.2, 9);
    s += pathTag(`M ${CX} 53 Q ${CX + 7} 58 ${CX + 2} 63`, { fill: 'none', stroke: color, 'stroke-width': 2.6, 'stroke-linecap': 'round' });
    return s;
};

shapes.camel = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += ellipseTag(CX, 38, 8, 16, { fill: color, stroke, 'stroke-width': 1.3 });
    s += ellipseTag(CX - 3, 26, 6, 8, { fill: color, stroke, 'stroke-width': 1.1 });
    s += ellipseTag(CX + 4, 24, 5.4, 7, { fill: color, stroke, 'stroke-width': 1.1 });
    s += rrectTag(CX - 2.6, 10, 5.2, 15, 2.6, 2.6, { fill: color, stroke, 'stroke-width': 1.1 });
    s += animalLegs(color, [[CX - 6, 30], [CX + 6, 30], [CX - 6, 50], [CX + 6, 50]], 3, 9);
    return s;
};

shapes.cow = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += ellipseTag(CX, 36, 10, 17, { fill: color, stroke, 'stroke-width': 1.3 });
    s += ellipseTag(CX, 16, 6.4, 6.8, { fill: color, stroke, 'stroke-width': 1.2 });
    s += ellipseTag(CX - 6.5, 15, 2.4, 3, { fill: darken(color, 10) });
    s += ellipseTag(CX + 6.5, 15, 2.4, 3, { fill: darken(color, 10) });
    for (const [x, y, r] of [[CX - 5, 30, 3], [CX + 6, 38, 2.6], [CX - 2, 44, 2.2]]) {
        s += ellipseTag(x, y, r, r * 0.8, { fill: '#ffffff', 'fill-opacity': 0.85 });
    }
    s += animalLegs(color, [[CX - 8, 27], [CX + 8, 27], [CX - 8, 44], [CX + 8, 44]], 3.2, 8);
    return s;
};

shapes.turtle = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += circleTag(CX, 34, 16, { fill: color, stroke, 'stroke-width': 1.4 });
    for (const [x, y, r] of [[CX, 24, 5], [CX - 9, 32, 4.5], [CX + 9, 32, 4.5], [CX - 6, 44, 4.5], [CX + 6, 44, 4.5], [CX, 40, 4]]) {
        s += circleTag(x, y, r, { fill: 'none', stroke: darken(color, 16), 'stroke-width': 0.8, 'stroke-opacity': 0.55 });
    }
    s += ellipseTag(CX, 16, 4, 4.6, { fill: color, stroke, 'stroke-width': 1 });
    s += animalLegs(color, [[CX - 13, 24], [CX + 13, 24], [CX - 13, 46], [CX + 13, 46]], 4, 6);
    return s;
};

shapes.zebra = (color) => {
    const ink = color || '#111827';
    const bodyColor = '#f8fafc';
    const bodyHalfWidthAt = (y) => 8 * Math.sqrt(Math.max(0, 1 - ((y - 36) / 18) ** 2));
    let s = '';
    s += ellipseTag(CX, 36, 8, 18, { fill: bodyColor, stroke: ink, 'stroke-width': 1.3 });
    s += rrectTag(CX - 3.6, 10, 7.2, 16, 3, 3, { fill: bodyColor, stroke: ink, 'stroke-width': 1.2 });
    s += pathTag(`M ${CX - 3.4} 12 Q ${CX} 8 ${CX + 3.4} 12 L ${CX + 3.4} 25 L ${CX - 3.4} 25 Z`, {
        fill: ink,
        'fill-opacity': 0.85,
    });
    for (let y = 14; y <= 51; y += 4.2) {
        const halfW = y < 25 ? 3.4 : bodyHalfWidthAt(y);
        if (halfW < 0.5) continue;
        s += lineTag(CX - halfW, y, CX + halfW, y + 1.6, { stroke: ink, 'stroke-width': 1.7, 'stroke-opacity': 0.92 });
    }
    s += animalLegs(ink, [[CX - 6, 26], [CX + 6, 26], [CX - 6, 48], [CX + 6, 48]], 3.2, 9);
    s += pathTag(`M ${CX} 53 Q ${CX + 7} 58 ${CX + 2} 63`, { fill: 'none', stroke: ink, 'stroke-width': 2.6, 'stroke-linecap': 'round' });
    return s;
};

shapes.sheep = (color) => {
    const stroke = darken(color, 25);
    let s = '';
    for (const [x, y, r] of [[CX, 30, 9], [CX - 8, 34, 6], [CX + 8, 34, 6], [CX - 4, 44, 6], [CX + 4, 44, 6], [CX, 40, 8]]) {
        s += circleTag(x, y, r, { fill: color, stroke, 'stroke-width': 1, 'stroke-opacity': 0.9 });
    }
    s += ellipseTag(CX, 16, 4.6, 5.4, { fill: darken(color, 35), stroke, 'stroke-width': 1 });
    s += animalLegs(color, [[CX - 6, 44], [CX + 6, 44], [CX - 6, 54], [CX + 6, 54]], 2.8, 7, darken(color, 40));
    return s;
};

shapes.goat = (color) => {
    const stroke = darken(color, 30);
    let s = '';
    s += ellipseTag(CX, 36, 7, 15, { fill: color, stroke, 'stroke-width': 1.3 });
    s += ellipseTag(CX, 17, 5, 5.6, { fill: color, stroke, 'stroke-width': 1.1 });
    s += polygonTag([[CX - 3.4, 13], [CX - 5.4, 7], [CX - 2, 12]], { fill: darken(color, 30) });
    s += polygonTag([[CX + 3.4, 13], [CX + 5.4, 7], [CX + 2, 12]], { fill: darken(color, 30) });
    s += animalLegs(color, [[CX - 5.4, 26], [CX + 5.4, 26], [CX - 5.4, 46], [CX + 5.4, 46]], 2.8, 8);
    s += pathTag(`M ${CX} 49 L ${CX + 2} 54`, { stroke: color, 'stroke-width': 2.4, 'stroke-linecap': 'round' });
    return s;
};

/* ── People (top-down head + shoulders) ─────────────────────────────── */

function personBase(color) {
    const stroke = darken(color, 30);
    let s = '';
    s += ellipseTag(CX, 40, 11, 14, { fill: color, stroke, 'stroke-width': 1.3 });
    s += circleTag(CX, 22, 9, { fill: '#f3c99d', stroke: darken('#f3c99d', 20), 'stroke-width': 1 });
    s += ellipseTag(CX - 13, 38, 3.4, 8, { fill: color, stroke, 'stroke-width': 1 });
    s += ellipseTag(CX + 13, 38, 3.4, 8, { fill: color, stroke, 'stroke-width': 1 });
    return s;
}

shapes.person = (color) => personBase(color);

shapes.walking_person = (color) => {
    let s = personBase(color);
    s += ellipseTag(CX - 4, 55, 3, 5, { fill: darken(color, 15) });
    s += ellipseTag(CX + 6, 58, 3, 5, { fill: darken(color, 15) });
    return s;
};

shapes.security_guard = (color) => {
    let s = personBase(color);
    s += pathTag(`M ${CX - 10} 18 A 10 10 0 0 1 ${CX + 10} 18 L ${CX + 10} 20 L ${CX - 10} 20 Z`, { fill: '#0f172a' });
    s += rectTag(CX - 10, 19, 20, 2.4, { fill: '#0f172a' });
    s += circleTag(CX, 40, 2.4, { fill: '#fbbf24', stroke: '#92400e', 'stroke-width': 0.5 });
    return s;
};

shapes.worker = (color) => {
    let s = personBase(color);
    s += circleTag(CX, 18, 9.5, { fill: '#f59e0b', stroke: darken('#f59e0b', 25), 'stroke-width': 1.2 });
    s += rectTag(CX - 9.5, 20, 19, 2.4, { fill: '#f59e0b', stroke: darken('#f59e0b', 25), 'stroke-width': 0.6 });
    s += rectTag(CX - 11, 34, 22, 4, { fill: '#facc15', 'fill-opacity': 0.9 });
    return s;
};

/* ────────────────────────────────────────────────────────────────────────
 * Generic fallback (only used if the catalog gains a shape with no renderer)
 * ──────────────────────────────────────────────────────────────────── */

function fallbackShape(color) {
    const stroke = darken(color, 32);
    let s = '';
    s += pathTag(bodyPath(16, 50, 22, 26, 6), { fill: color, stroke, 'stroke-width': 1.4 });
    s += glassPanel(CX - 7, 20, 14, 7, 3);
    return s;
}

/* ────────────────────────────────────────────────────────────────────────
 * Build & write files
 * ──────────────────────────────────────────────────────────────────── */

function colorForIcon(iconId, category) {
    return iconColors[iconId] || categoryColors[category] || '#2563eb';
}

fs.mkdirSync(outRoot, { recursive: true });
for (const folder of new Set(Object.values(categoryFolders))) {
    fs.mkdirSync(path.join(outRoot, folder), { recursive: true });
}

let written = 0;
const usedFallback = [];

for (const [iconId, meta] of Object.entries(icons)) {
    const category = meta.category || 'vehicles';
    const folder = categoryFolders[category] || 'Vehicles';
    const shapeKey = meta.shape || iconId;
    const renderer = shapes[shapeKey];
    const color = colorForIcon(iconId, category);

    if (!renderer) {
        usedFallback.push(iconId);
    }

    const body = (renderer || fallbackShape)(color);
    const svg = svgWrap(body);
    const dest = path.join(outRoot, folder, `${iconId}.svg`);
    fs.writeFileSync(dest, svg);
    written++;
}

const attribution = `# Built-in map marker icons

These SVG markers are **original artwork** created for BillX GPS — clean,
top-down (bird's-eye) transportation silhouettes designed specifically for
live fleet tracking maps. They are not derived from any third-party icon
set.

- Canvas: 64x64, transparent background (no badge circle).
- Orientation: every icon is drawn nose/front-up (north), so the map layer
  can rotate the marker directly from the raw GPS heading (0deg = north).
- Colors come from \`config/builtin_map_icon_sources.php\`
  (\`icon_colors\` / \`category_colors\`).
- Source of truth for the icon list is \`config/vehicle_icon_catalog.php\`.

Regenerate all icons after changing the catalog or colors:

\`\`\`bash
npm run generate:map-icons
\`\`\`

That runs \`scripts/generate-topdown-map-icons.mjs\`, which procedurally
draws every icon (no external image sources) and writes the results into
\`public/icons/builtin/{Category}/{icon_id}.svg\`.

Do not edit files in this folder manually — changes will be overwritten the
next time the generator runs.

> Legacy note: \`scripts/sync-builtin-map-icons.mjs\` (Tabler side-view
> icons with a colored badge circle) is kept only for historical reference
> and is no longer the recommended generator.
`;

fs.writeFileSync(path.join(outRoot, 'ATTRIBUTION.md'), attribution);

console.log(`Generated ${written} top-down map icons in public/icons/builtin/`);
if (usedFallback.length) {
    console.warn('No dedicated renderer for shape(s), used fallback marker:', usedFallback.join(', '));
}
