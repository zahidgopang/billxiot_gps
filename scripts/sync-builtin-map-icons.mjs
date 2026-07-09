/**
 * @deprecated LEGACY generator. Produces side-view Tabler Icons (MIT) inside a
 * colored badge circle. Kept only for historical reference / rollback.
 *
 * Prefer `npm run generate:map-icons` (scripts/generate-topdown-map-icons.mjs),
 * which draws original, top-down (bird's-eye) fleet markers with a transparent
 * background and a nose-up orientation suited for GPS-heading map rotation.
 *
 * Copy Tabler Icons (MIT) into public/icons/builtin/ with vivid per-icon colors.
 * Usage: npm run sync:map-icons
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
    const json = execSync(
        `${phpBin} -r "echo json_encode(require '${phpRoot}/${file}');"`,
        { encoding: 'utf8' }
    );
    return JSON.parse(json);
}

const catalog = loadPhpConfig('config/vehicle_icon_catalog.php');
const sources = loadPhpConfig('config/builtin_map_icon_sources.php');
const tablerMap = sources.tabler || {};
const categoryFolders = sources.category_folders || {};
const categoryColors = sources.category_colors || {};
const iconColors = sources.icon_colors || {};
const render = sources.render || {};
const iconCategories = Object.fromEntries(
    Object.entries(catalog.icons || {}).map(([id, meta]) => [id, meta.category || 'vehicles'])
);

const outlineRoot = path.join(root, sources.source_path || 'node_modules/@tabler/icons/icons/outline');
const filledRoot = path.join(root, sources.filled_path || 'node_modules/@tabler/icons/icons/filled');
const outRoot = path.join(root, 'public/icons/builtin');
const canvas = Number(render.canvas) || 64;
const iconScale = Number(render.icon_scale) || 2;
const padding = Number(render.padding) || 8;
const preferFilled = render.prefer_filled !== false;
const softBackground = render.soft_background !== false;

if (!fs.existsSync(outlineRoot)) {
    console.error('Tabler icons not installed. Run: npm install @tabler/icons --save-dev');
    process.exit(1);
}

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

function colorForIcon(iconId) {
    if (iconColors[iconId]) return iconColors[iconId];
    const cat = iconCategories[iconId] || 'vehicles';
    return categoryColors[cat] || '#2563eb';
}

function extractInner(svg) {
    return svg
        .replace(/<\?xml[^?]*\?>/gi, '')
        .replace(/<svg[^>]*>/i, '')
        .replace(/<\/svg>\s*$/i, '')
        .replace(/\sclass="[^"]*"/gi, '');
}

function colorizeInner(inner, color, isFilled) {
    if (isFilled) {
        return inner;
    }

    return inner
        .replace(/fill="currentColor"/gi, `fill="${color}"`)
        .replace(/stroke="currentColor"/gi, `stroke="${shade(color, -18)}"`);
}

function buildMapSvg(inner, color, isFilled) {
    const bg = shade(color, 78);
    const stroke = shade(color, -18);
    const colored = colorizeInner(inner, color, isFilled);
    const tx = padding;
    const ty = padding;
    const bgCircle = softBackground
        ? `<circle cx="${canvas / 2}" cy="${canvas / 2}" r="${canvas / 2 - 2}" fill="${bg}" stroke="${stroke}" stroke-width="1.5" stroke-opacity="0.35"/>`
        : '';
    const paint = isFilled
        ? `fill="${color}" stroke="${stroke}" stroke-width="0.35"`
        : `fill="none" stroke="${color}" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"`;

    return `<svg xmlns="http://www.w3.org/2000/svg" width="${canvas}" height="${canvas}" viewBox="0 0 ${canvas} ${canvas}">
${bgCircle}
<g transform="translate(${tx}, ${ty}) scale(${iconScale})" ${paint}>
${colored}
</g>
</svg>`;
}

fs.mkdirSync(outRoot, { recursive: true });
for (const folder of new Set(Object.values(categoryFolders))) {
    fs.mkdirSync(path.join(outRoot, folder), { recursive: true });
}

let copied = 0;
const missing = [];

for (const [iconId, tablerName] of Object.entries(tablerMap)) {
    const category = iconCategories[iconId] || 'vehicles';
    const folder = categoryFolders[category] || 'Vehicles';
    const filledSrc = path.join(filledRoot, `${tablerName}.svg`);
    const outlineSrc = path.join(outlineRoot, `${tablerName}.svg`);
    const useFilled = preferFilled && fs.existsSync(filledSrc);
    const src = useFilled ? filledSrc : outlineSrc;
    const dest = path.join(outRoot, folder, `${iconId}.svg`);

    if (!fs.existsSync(src)) {
        missing.push({ iconId, tablerName });
        continue;
    }

    const raw = fs.readFileSync(src, 'utf8');
    const inner = extractInner(raw);
    const color = colorForIcon(iconId);
    const svg = buildMapSvg(inner, color, useFilled);
    fs.writeFileSync(dest, svg);
    copied++;
}

const attribution = `# Built-in map marker icons

Icons are sourced from [Tabler Icons](https://tabler.io/icons) v${sources.source_version || '3.x'} (MIT License).

Colors are applied at build time via \`npm run sync:map-icons\` for vivid map markers (not monochrome outlines).

To refresh assets after updating the catalog mapping:

\`\`\`bash
npm run sync:map-icons
\`\`\`

Do not edit files in this folder manually — changes will be overwritten by the sync script.
`;

fs.writeFileSync(path.join(outRoot, 'ATTRIBUTION.md'), attribution);

console.log(`Synced ${copied} colorful built-in map icons to public/icons/builtin/`);
if (missing.length) {
    console.warn('Missing Tabler sources:', missing.map((m) => `${m.iconId} (${m.tablerName})`).join(', '));
    process.exitCode = 1;
}
