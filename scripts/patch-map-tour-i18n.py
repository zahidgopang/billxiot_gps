from pathlib import Path

path = Path(__file__).resolve().parents[1] / "public/js/map-tour.js"
text = path.read_text(encoding="utf-8")

start = text.find("    const USER_STEPS = [")
end = text.find("    let steps = [];")
if start < 0 or end < 0:
    raise SystemExit("markers not found")

new_block = r'''    const i18n = tourCfg.i18n || {};

    const STEP_META_USER = [
        { id: 'welcome', type: 'modal' },
        { id: 'nav', selector: '.map-nav-device-row', placement: 'bottom', pad: 10 },
        { id: 'sidebar', selector: '#toggleSidebar', placement: 'bottom', sidebar: 'open' },
        { id: 'date', selector: '[data-map-tour="date-range"]', placement: 'right', sidebar: 'open' },
        { id: 'device_summary', selector: '[data-map-tour="device-summary"]', placement: 'right', sidebar: 'open' },
        { id: 'route_summary', selector: '[data-map-tour="route-summary"]', placement: 'right', sidebar: 'open' },
        { id: 'trip_events', selector: '[data-map-tour="trip-events"]', placement: 'right', sidebar: 'open' },
        { id: 'geofence_list', selector: '[data-map-tour="geofence-list"]', placement: 'right', sidebar: 'open' },
        { id: 'hud', selector: '#mapHud', placement: 'bottom', sidebar: 'close' },
        { id: 'alerts', selector: '#navAlertsBtn', placement: 'bottom' },
        { id: 'controls_right', selector: '.smart-controls', placement: 'left' },
        { id: 'controls_left', selector: '.map-tools-left', placement: 'right' },
        { id: 'playback', selector: '#playbackFab', placement: 'top' },
        { id: 'geofence_draw', selector: '#btnGeofence', placement: 'left' },
        { id: 'route_click', type: 'modal' },
    ];

    const STEP_META_ADMIN = [
        { id: 'welcome', type: 'modal' },
        { id: 'nav', selector: '.map-nav-device-row', placement: 'bottom', pad: 10 },
        { id: 'back', selector: '[data-map-tour="sidebar-back"]', placement: 'bottom', sidebar: 'open' },
        { id: 'sidebar', selector: '#toggleSidebar', placement: 'bottom', sidebar: 'open' },
        { id: 'date', selector: '[data-map-tour="date-range"]', placement: 'right', sidebar: 'open' },
        { id: 'device_summary', selector: '[data-map-tour="device-summary"]', placement: 'right', sidebar: 'open' },
        { id: 'route_summary', selector: '[data-map-tour="route-summary"]', placement: 'right', sidebar: 'open' },
        { id: 'trip_events', selector: '[data-map-tour="trip-events"]', placement: 'right', sidebar: 'open' },
        { id: 'geofence_list', selector: '[data-map-tour="geofence-list"]', placement: 'right', sidebar: 'open' },
        { id: 'hud', selector: '#mapHud', placement: 'bottom', sidebar: 'close' },
        { id: 'alerts', selector: '#navAlertsBtn', placement: 'bottom' },
        { id: 'controls_right', selector: '.smart-controls', placement: 'left' },
        { id: 'controls_left', selector: '.map-tools-left', placement: 'right' },
        { id: 'playback', selector: '#playbackFab', placement: 'top' },
        { id: 'geofence_draw', selector: '#btnGeofence', placement: 'left' },
        { id: 'route_click', type: 'modal' },
    ];

    function mirrorPlacement(placement) {
        if (document.documentElement.dir !== 'rtl') {
            return placement;
        }
        if (placement === 'left') {
            return 'right';
        }
        if (placement === 'right') {
            return 'left';
        }
        return placement;
    }

    function stepFromMeta(meta, pack) {
        const t = pack[meta.id] || {};
        const step = Object.assign({}, meta);
        step.title = t.title || '';
        step.body = t.body || '';
        if (t.icon) {
            step.icon = t.icon;
        }
        if (meta.sidebar === 'open') {
            step.beforeShow = openSidebar;
        }
        if (meta.sidebar === 'close') {
            step.beforeShow = closeSidebar;
        }
        if (step.placement) {
            step.placement = mirrorPlacement(step.placement);
        }
        delete step.id;
        delete step.sidebar;
        return step;
    }

    function buildSteps() {
        const pack = isAdmin ? (i18n.admin || {}) : (i18n.user || {});
        const meta = isAdmin ? STEP_META_ADMIN : STEP_META_USER;
        const list = meta.map(function (m) {
            return stepFromMeta(m, pack);
        });
        const fin = i18n.finish_step || {};
        list.push({
            type: 'finish',
            icon: fin.icon || 'fa-check-circle',
            title: fin.title || '',
            body: fin.body || '',
        });
        return list;
    }

    function applyTourUiStrings() {
        if (!root) {
            return;
        }
        const lead = root.querySelector('.map-tour-prefs__lead');
        const optRepeat = root.querySelector('.map-tour-prefs__option input[value="repeat"]');
        const optDismiss = root.querySelector('.map-tour-prefs__option input[value="dismiss"]');
        if (lead) {
            lead.textContent = i18n.prefs_lead || lead.textContent;
        }
        if (optRepeat && optRepeat.parentElement) {
            optRepeat.parentElement.querySelector('span').innerHTML =
                '<strong>' + (i18n.pref_repeat || '') + '</strong>';
        }
        if (optDismiss && optDismiss.parentElement) {
            optDismiss.parentElement.querySelector('span').innerHTML =
                '<strong>' + (i18n.pref_dismiss || '') + '</strong>';
        }
        root.querySelector('[data-tour-skip]').textContent = i18n.skip || 'Skip';
        root.querySelector('[data-tour-prev]').textContent = i18n.back || 'Back';
        root.querySelector('[data-tour-next]').textContent = i18n.next || 'Next';
    }

'''

text = text[:start] + new_block + text[end:]

# ensureDom calls applyTourUiStrings
text = text.replace(
    "        document.body.appendChild(root);\n\n        root.querySelector('[data-tour-skip]')",
    "        document.body.appendChild(root);\n        applyTourUiStrings();\n\n        root.querySelector('[data-tour-skip]')",
    1,
)

# renderStep i18n
text = text.replace(
    "root.querySelector('[data-tour-step-label]').textContent = `Step ${index + 1} of ${total}`;",
    "root.querySelector('[data-tour-step-label]').textContent = (i18n.step_label || 'Step :current of :total').replace(':current', index + 1).replace(':total', total);",
    1,
)
text = text.replace(
    "root.querySelector('[data-tour-next]').textContent = isFinish ? 'Finish' : 'Next';",
    "root.querySelector('[data-tour-next]').textContent = isFinish ? (i18n.finish || 'Finish') : (i18n.next || 'Next');",
    1,
)
text = text.replace(
    "root.querySelector('[data-tour-title]').textContent = 'Skip tour';\n                root.querySelector('[data-tour-body]').textContent =\n                    'Choose how often you want to see this tour when you open the map. You can replay it anytime from the Map tour button in the top bar.';",
    "root.querySelector('[data-tour-title]').textContent = i18n.skip_title || 'Skip tour';\n                root.querySelector('[data-tour-body]').textContent = i18n.skip_body || '';",
    1,
)
text = text.replace(
    "positionCard(step.placement || 'bottom', target);",
    "positionCard(mirrorPlacement(step.placement || 'bottom'), target);",
    1,
)

path.write_text(text, encoding="utf-8")
print("map-tour.js patched")
