/**
 * Obfuscate public JS into public/assets/protected/js for production.
 * Run: npm run build:protect
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import JavaScriptObfuscator from 'javascript-obfuscator';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');

const SOURCE_DIR = path.join(root, 'public', 'js');
const EXTRA_SOURCE_DIR = path.join(root, 'resources', 'frontend', 'js');
const OUT_DIR = path.join(root, 'public', 'assets', 'protected', 'js');

const FILES = [
    { in: path.join(SOURCE_DIR, 'admin-locations-live.js'), out: 'admin-locations-live.js' },
    { in: path.join(SOURCE_DIR, 'vehicle-marker.js'), out: 'vehicle-marker.js' },
    { in: path.join(SOURCE_DIR, 'vehicle-map-popup.js'), out: 'vehicle-map-popup.js' },
    { in: path.join(SOURCE_DIR, 'google-maps-platform.js'), out: 'google-maps-platform.js' },
    { in: path.join(SOURCE_DIR, 'fleet-map-cluster.js'), out: 'fleet-map-cluster.js' },
    { in: path.join(SOURCE_DIR, 'global-tracking.js'), out: 'global-tracking.js' },
    { in: path.join(SOURCE_DIR, 'global-tracking-history.js'), out: 'global-tracking-history.js' },
    { in: path.join(SOURCE_DIR, 'tracking-traccar.js'), out: 'tracking-traccar.js' },
    { in: path.join(SOURCE_DIR, 'tracking-reports.js'), out: 'tracking-reports.js' },
    { in: path.join(SOURCE_DIR, 'tracking-events.js'), out: 'tracking-events.js' },
    { in: path.join(SOURCE_DIR, 'tracking-geofences.js'), out: 'tracking-geofences.js' },
    { in: path.join(SOURCE_DIR, 'tracking-notifications.js'), out: 'tracking-notifications.js' },
    { in: path.join(SOURCE_DIR, 'tracking-maintenance.js'), out: 'tracking-maintenance.js' },
    { in: path.join(SOURCE_DIR, 'tracking-drivers.js'), out: 'tracking-drivers.js' },
    { in: path.join(SOURCE_DIR, 'tracking-commands.js'), out: 'tracking-commands.js' },
    { in: path.join(SOURCE_DIR, 'tracking-tasks.js'), out: 'tracking-tasks.js' },
    { in: path.join(SOURCE_DIR, 'tracking-settings.js'), out: 'tracking-settings.js' },
    { in: path.join(SOURCE_DIR, 'fleet-map-renderer.js'), out: 'fleet-map-renderer.js' },
    { in: path.join(SOURCE_DIR, 'device-map-tracker.js'), out: 'device-map-tracker.js' },
    { in: path.join(SOURCE_DIR, 'device-status-toggle.js'), out: 'device-status-toggle.js' },
    { in: path.join(SOURCE_DIR, 'form-enhancements.js'), out: 'form-enhancements.js' },
    { in: path.join(EXTRA_SOURCE_DIR, 'client-protection.js'), out: 'client-protection.js' },
    { in: path.join(SOURCE_DIR, 'map-tour.js'), out: 'map-tour.js' },
    { in: path.join(SOURCE_DIR, 'map-session-guard.js'), out: 'map-session-guard.js' },
];

const OBFUSCATOR_OPTIONS = {
    compact: true,
    controlFlowFlattening: true,
    controlFlowFlatteningThreshold: 0.4,
    deadCodeInjection: false,
    debugProtection: false,
    disableConsoleOutput: false,
    identifierNamesGenerator: 'hexadecimal',
    renameGlobals: false,
    selfDefending: true,
    simplify: true,
    splitStrings: true,
    splitStringsChunkLength: 8,
    stringArray: true,
    stringArrayCallsTransform: true,
    stringArrayEncoding: ['base64'],
    stringArrayIndexShift: true,
    stringArrayRotate: true,
    stringArrayShuffle: true,
    stringArrayWrappersCount: 2,
    stringArrayWrappersChainedCalls: true,
    stringArrayWrappersParametersMaxCount: 4,
    stringArrayWrappersType: 'function',
    stringArrayThreshold: 0.75,
    transformObjectKeys: true,
    unicodeEscapeSequence: false,
};

function obfuscateFile(inputPath, outputName) {
    if (!fs.existsSync(inputPath)) {
        console.warn(`skip (missing): ${inputPath}`);
        return;
    }

    const source = fs.readFileSync(inputPath, 'utf8');
    const result = JavaScriptObfuscator.obfuscate(source, OBFUSCATOR_OPTIONS);
    const outPath = path.join(OUT_DIR, outputName);

    fs.mkdirSync(OUT_DIR, { recursive: true });
    fs.writeFileSync(outPath, result.getObfuscatedCode(), 'utf8');

    const kb = (fs.statSync(outPath).size / 1024).toFixed(1);
    console.log(`✓ ${outputName} (${kb} KB)`);
}

console.log('Obfuscating frontend assets...\n');

for (const file of FILES) {
    obfuscateFile(file.in, file.out);
}

console.log(`\nDone. Output: public/assets/protected/js/`);
