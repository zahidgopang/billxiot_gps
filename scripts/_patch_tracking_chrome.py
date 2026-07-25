from pathlib import Path
import re

root = Path(r"d:\laragon\www\billxiot_gps")
traccar = root / "resources/views/tracking/traccar.blade.php"
text = traccar.read_text(encoding="utf-8")

# Fix orphaned font-family block under .tc-app media query
text = text.replace(
    """        @media (max-height: 520px), ((max-width: 900px) and (orientation: landscape)) {
            .tc-app { min-height: 0; }
        }
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'SF Pro Display', 'Helvetica Neue', sans-serif;
            letter-spacing: -0.018em;
        }
""",
    """        @media (max-height: 520px), ((max-width: 900px) and (orientation: landscape)) {
            .tc-app { min-height: 0; }
        }
        .tc-app {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            letter-spacing: -0.015em;
        }
""",
)

# Neutralize old workspace-nav / tabs / chips CSS by wrapping markers —
# tracking-chrome.css owns these selectors with equal/higher specificity via later load.
# Replace the large "Apple-style module navigation" through chip.active blocks with a short note.
old_nav = """        /* ===== Apple-style module navigation ===== */
        .tc-workspace-nav {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            min-height: 34px;
            padding: 0.25rem 0.5rem;
            background: var(--apple-bg-primary);
            border-bottom: 0.5px solid var(--tc-border);
            z-index: 30;
        }"""

if old_nav not in text:
    raise SystemExit("workspace nav css start missing")

# Keep collapse/reveal display rules that depend on html/body classes — chrome CSS also has them.
# Insert a comment after design tokens note; leave legacy rules but they'll be overridden.
# Safer: delete from Apple-style module nav through .tc-chip.active:hover inclusive, keep Unread badge and rest.

start = text.index("        /* ===== Apple-style module navigation ===== */")
end_marker = "        /* Alert controls in map area (legacy fallback) */"
end = text.index(end_marker)
replacement = """        /* Module nav / top pills / sidebar chrome → public/css/tracking-chrome.css */

        /* Legacy aliases */
        .tc-iconbar { display: contents; }
        .tc-nav-toggle, .tc-nav-close, .tc-iconbar-links { display: none; }
        .tc-workspace-nav__divider { display: none; }
        .tc-workspace-nav__tool-btn { display: none; }

"""
text = text[:start] + replacement + text[end:]

# Remove old left-panel tabs/chip styles that conflict (keep panel layout geometry)
# Replace from .tc-tabs { through .tc-chip.active:hover ... keeping .tc-panel geometry
tabs_start = text.index("        .tc-tabs {")
# Find next section after chips — .tc-chip.active:hover block ends before more panel styles
chips_end = text.index("        .tc-chip.active:hover { color: #fff; background: #0062cc; }")
# include that line
line_end = text.index("\n", chips_end) + 1
# Also remove older .tc-tab { position relative } unread badge? Keep badge in chrome.
# Remove early .tc-tab { position: relative } block if present before panel
early_tab = "        /* Unread badge on the Events tab */\n        .tc-tab { position: relative; }\n        .tc-tab-badge {\n            display: inline-flex;\n            align-items: center;\n            justify-content: center;\n            min-width: 17px;\n            height: 17px;\n            padding: 0 4px;\n            margin-inline-start: 5px;\n            border-radius: 9px;\n            background: var(--tc-alert);\n            color: #fff;\n            font-size: 0.62rem;\n            font-weight: 700;\n            vertical-align: middle;\n        }\n\n"
if early_tab in text:
    text = text.replace(early_tab, "        /* Events tab badge styles live in tracking-chrome.css */\n\n")

# Recalculate after previous edits
tabs_start = text.index("        .tc-tabs {")
chips_end = text.index("        .tc-chip.active:hover { color: #fff; background: #0062cc; }")
line_end = text.index("\n", chips_end) + 1
text = text[:tabs_start] + "        /* Sidebar tabs / search / chips → public/css/tracking-chrome.css */\n\n" + text[line_end:]

# Also soften .tc-panel background override — chrome sets it; keep width rules
text = text.replace(
    """        .tc-panel {
            width: min(272px, 94vw);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            background: var(--tc-sidebar);
            border-inline-end: 0.5px solid var(--tc-border);
            z-index: 4;
        }""",
    """        .tc-panel {
            width: min(280px, 94vw);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            background: #f8fafc;
            border-inline-end: 1px solid rgba(15, 23, 42, 0.08);
            z-index: 4;
        }""",
)

# HTML: alert controls
text = text.replace(
    """        <button type="button" class="tracking-topbar__alert-btn on" id="tcSoundToggle"
                title="{{ __('app.tracking.alert_sound') }}" aria-label="{{ __('app.tracking.alert_sound') }}" aria-pressed="true">
            <i class="fas fa-volume-high"></i>
        </button>
        <button type="button" class="tracking-topbar__alert-btn" id="tcDesktopToggle"
                title="{{ __('app.tracking.alert_desktop') }}" aria-label="{{ __('app.tracking.alert_desktop') }}" aria-pressed="false">
            <i class="fas fa-bell"></i>
        </button>""",
    """        <button type="button" class="tracking-topbar__alert-btn on" id="tcSoundToggle"
                title="{{ __('app.tracking.alert_sound') }}" aria-label="{{ __('app.tracking.alert_sound') }}" aria-pressed="true">
            <i class="ti ti-volume" aria-hidden="true"></i>
        </button>
        <button type="button" class="tracking-topbar__alert-btn" id="tcDesktopToggle"
                title="{{ __('app.tracking.alert_desktop') }}" aria-label="{{ __('app.tracking.alert_desktop') }}" aria-pressed="false">
            <i class="ti ti-bell" aria-hidden="true"></i>
        </button>""",
)

# Panel toggle + collapse icons
text = text.replace(
    """            <button type="button" class="tc-workspace-nav__panel-btn active" id="tcPanelToggle"
                    aria-label="{{ __('app.tracking.tab_objects') }}" title="{{ __('app.tracking.tab_objects') }}"
                    aria-expanded="true" aria-controls="tcPanel">
                <i class="fas fa-list-ul"></i>""",
    """            <button type="button" class="tc-workspace-nav__panel-btn active" id="tcPanelToggle"
                    aria-label="{{ __('app.tracking.tab_objects') }}" title="{{ __('app.tracking.tab_objects') }}"
                    aria-expanded="true" aria-controls="tcPanel">
                <i class="ti ti-layout-sidebar" aria-hidden="true"></i>""",
)

text = text.replace(
    """                <button type="button" class="tc-workspace-nav__collapse-btn" id="tcNavClose"
                        aria-label="{{ __('app.tracking.nav_collapse') }}" title="{{ __('app.tracking.nav_collapse') }}">
                    <i class="fas fa-chevron-up"></i>
                </button>""",
    """                <button type="button" class="tc-workspace-nav__collapse-btn" id="tcNavClose"
                        aria-label="{{ __('app.tracking.nav_collapse') }}" title="{{ __('app.tracking.nav_collapse') }}">
                    <i class="ti ti-chevron-up" aria-hidden="true"></i>
                </button>""",
)

text = text.replace(
    """            <button type="button" class="tc-workspace-nav__reveal-btn" id="tcNavRevealBtn">
                <i class="fas fa-chevron-down"></i><span>{{ __('app.tracking.nav_show') }}</span>
            </button>""",
    """            <button type="button" class="tc-workspace-nav__reveal-btn" id="tcNavRevealBtn">
                <i class="ti ti-chevron-down" aria-hidden="true"></i><span>{{ __('app.tracking.nav_show') }}</span>
            </button>""",
)

# Sidebar tabs with icons
text = text.replace(
    """                <div class="tc-tabs" role="tablist">
                    @if(! empty($tabPerms['objects']))
                    <button type="button" class="tc-tab{{ $firstSidebarTab === 'objects' ? ' active' : '' }}" data-tab="objects">{{ __('app.tracking.tab_objects') }}</button>
                    @endif
                    @if(! empty($tabPerms['events']))
                    <button type="button" class="tc-tab{{ $firstSidebarTab === 'events' ? ' active' : '' }}" data-tab="events">{{ __('app.tracking.events_nav') }}<span class="tc-tab-badge" id="tcEventsBadge" hidden>0</span></button>
                    @endif
                    @if(! empty($tabPerms['places']))
                    <button type="button" class="tc-tab{{ $firstSidebarTab === 'places' ? ' active' : '' }}" data-tab="places">{{ __('app.tracking.tab_places') }}</button>
                    @endif
                    @if(! empty($tabPerms['history']))
                    <button type="button" class="tc-tab{{ $firstSidebarTab === 'history' ? ' active' : '' }}" data-tab="history">{{ __('app.tracking.history_link') }}</button>
                    @endif
                </div>""",
    """                <div class="tc-tabs" role="tablist">
                    @if(! empty($tabPerms['objects']))
                    <button type="button" class="tc-tab{{ $firstSidebarTab === 'objects' ? ' active' : '' }}" data-tab="objects"><i class="ti ti-car" aria-hidden="true"></i><span>{{ __('app.tracking.tab_objects') }}</span></button>
                    @endif
                    @if(! empty($tabPerms['events']))
                    <button type="button" class="tc-tab{{ $firstSidebarTab === 'events' ? ' active' : '' }}" data-tab="events"><i class="ti ti-bolt" aria-hidden="true"></i><span>{{ __('app.tracking.events_nav') }}</span><span class="tc-tab-badge" id="tcEventsBadge" hidden>0</span></button>
                    @endif
                    @if(! empty($tabPerms['places']))
                    <button type="button" class="tc-tab{{ $firstSidebarTab === 'places' ? ' active' : '' }}" data-tab="places"><i class="ti ti-map-pin" aria-hidden="true"></i><span>{{ __('app.tracking.tab_places') }}</span></button>
                    @endif
                    @if(! empty($tabPerms['history']))
                    <button type="button" class="tc-tab{{ $firstSidebarTab === 'history' ? ' active' : '' }}" data-tab="history"><i class="ti ti-history" aria-hidden="true"></i><span>{{ __('app.tracking.history_link') }}</span></button>
                    @endif
                </div>""",
)

# Search wrap
text = text.replace(
    """                    <div class="tc-tab-head">
                        @if(! empty($ui['vehicle_search']))
                        <input type="search" id="tcSearch" class="form-control form-control-sm"
                               placeholder="{{ __('app.tracking.search_vehicles') }}" autocomplete="off">
                        @endif""",
    """                    <div class="tc-tab-head">
                        @if(! empty($ui['vehicle_search']))
                        <div class="tc-search-wrap">
                            <i class="ti ti-search" aria-hidden="true"></i>
                            <input type="search" id="tcSearch" class="form-control form-control-sm"
                                   placeholder="{{ __('app.tracking.search_vehicles') }}" autocomplete="off">
                        </div>
                        @endif""",
)

text = text.replace(
    """                        <span class="tc-col" title="{{ __('app.tracking.col_show') }}"><i class="fas fa-eye"></i></span>""",
    """                        <span class="tc-col" title="{{ __('app.tracking.col_show') }}"><i class="ti ti-eye" aria-hidden="true"></i></span>""",
)

text = text.replace(
    """                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="tcEventsReload">
                            <i class="fas fa-sync-alt me-1"></i>{{ __('app.tracking.refresh') }}
                        </button>""",
    """                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="tcEventsReload">
                            <i class="ti ti-refresh me-1" aria-hidden="true"></i>{{ __('app.tracking.refresh') }}
                        </button>""",
)

text = text.replace(
    """                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="tcPlacesReload">
                            <i class="fas fa-sync-alt me-1"></i>{{ __('app.tracking.refresh') }}
                        </button>""",
    """                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="tcPlacesReload">
                            <i class="ti ti-refresh me-1" aria-hidden="true"></i>{{ __('app.tracking.refresh') }}
                        </button>""",
)

traccar.write_text(text, encoding="utf-8")
print("traccar.blade.php patched")

# hub-nav icons
hub = root / "resources/views/tracking/partials/hub-nav.blade.php"
hub_text = hub.read_text(encoding="utf-8")
hub_text = hub_text.replace('<i class="fas fa-list-ul"></i>', '<i class="ti ti-layout-sidebar" aria-hidden="true"></i>')
hub_text = hub_text.replace('<i class="fas fa-chevron-up"></i>', '<i class="ti ti-chevron-up" aria-hidden="true"></i>')
hub.write_text(hub_text, encoding="utf-8")
print("hub-nav patched")

# module popup icon helper
popup = root / "public/js/tracking-module-popup.js"
popup_text = popup.read_text(encoding="utf-8")
if "function iconMarkup" not in popup_text:
    popup_text = popup_text.replace(
        """    function escHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function openModule(url, title, icon) {""",
        """    function escHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function iconMarkup(icon) {
        const raw = String(icon || 'ti ti-satellite').trim();
        if (raw.includes('ti-') || raw.startsWith('ti ')) {
            return `<i class="${escHtml(raw)}" aria-hidden="true"></i>`;
        }
        const fa = raw.startsWith('fa-') ? `fas ${raw}` : raw;
        return `<i class="${escHtml(fa)}" aria-hidden="true"></i>`;
    }

    function openModule(url, title, icon) {""",
    )
    popup_text = popup_text.replace(
        "titleEl.innerHTML = `<i class=\"fas ${escHtml(icon || 'fa-satellite-dish')} me-2\"></i>${escHtml(title || '')}`;",
        "titleEl.innerHTML = `${iconMarkup(icon)} <span class=\"ms-1\">${escHtml(title || '')}</span>`;",
    )
    popup_text = popup_text.replace(
        "link.getAttribute('data-tc-module-icon') || link.dataset.tcModuleIcon || 'fa-satellite-dish'",
        "link.getAttribute('data-tc-module-icon') || link.dataset.tcModuleIcon || 'ti ti-satellite'",
    )
    popup.write_text(popup_text, encoding="utf-8")
    print("tracking-module-popup.js patched")

# alert button icon toggles in tracking-traccar.js
js = root / "public/js/tracking-traccar.js"
js_text = js.read_text(encoding="utf-8")
js_text = js_text.replace(
    "if (ic) ic.className = on ? 'fas fa-volume-high' : 'fas fa-volume-xmark';",
    "if (ic) ic.className = on ? 'ti ti-volume' : 'ti ti-volume-off';",
)
js_text = js_text.replace(
    "if (ic) ic.className = on ? 'fas fa-bell' : 'fas fa-bell-slash';",
    "if (ic) ic.className = on ? 'ti ti-bell' : 'ti ti-bell-off';",
)
js.write_text(js_text, encoding="utf-8")
print("tracking-traccar.js alert icons patched")
