/**
 * GPS Map — interactive guided tour (user & admin).
 */
(function () {
    'use strict';

    const cfg = window.DEVICE_MAP_CONFIG || {};
    const tourCfg = cfg.mapTour || {};
    const isAdmin = !!cfg.isAdminMap;

    const i18n = tourCfg.i18n || {};
    const isRtl = () => document.documentElement.dir === 'rtl';

    const STEP_ICONS = {
        welcome: 'fa-compass',
        nav: 'fa-truck',
        back: 'fa-arrow-left',
        sidebar: 'fa-sliders-h',
        date: 'fa-calendar-alt',
        device_summary: 'fa-microchip',
        route_summary: 'fa-chart-line',
        trip_events: 'fa-list-ul',
        geofence_list: 'fa-draw-polygon',
        hud: 'fa-tachometer-alt',
        alerts: 'fa-bell',
        controls_right: 'fa-layer-group',
        controls_left: 'fa-toolbox',
        playback: 'fa-play-circle',
        geofence_draw: 'fa-vector-square',
        route_click: 'fa-route',
    };

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
        step.stepKey = meta.id;
        step.title = t.title || '';
        step.body = t.body || '';
        step.icon = t.icon || STEP_ICONS[meta.id] || 'fa-map-pin';
        if (meta.sidebar === 'open') {
            step.beforeShow = openSidebar;
            step.needsSidebarOpen = true;
        }
        if (meta.sidebar === 'close') {
            step.beforeShow = closeSidebar;
            step.needsSidebarClose = true;
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
            const span = optRepeat.parentElement.querySelector('span');
            if (span) {
                span.textContent = i18n.pref_repeat || span.textContent;
            }
        }
        if (optDismiss && optDismiss.parentElement) {
            const span = optDismiss.parentElement.querySelector('span');
            if (span) {
                span.textContent = i18n.pref_dismiss || span.textContent;
            }
        }
        root.querySelector('[data-tour-skip]').textContent = i18n.skip || 'Skip';
        const dismissBtn = root.querySelector('[data-tour-dismiss]');
        if (dismissBtn) {
            dismissBtn.textContent = i18n.dont_show_again || "Don't show again";
        }
        root.querySelector('[data-tour-prev]').textContent = i18n.back || 'Back';
        root.querySelector('[data-tour-next]').textContent = i18n.next || 'Next';
    }

    let steps = [];
    let index = 0;
    let root = null;
    let active = false;
    let skippedEarly = false;
    const HIGHLIGHT_CLASS = 'map-tour-target--active';
    const HEADING_HIGHLIGHT_CLASS = 'map-tour-heading--active';
    const NAV_ELEVATED_CLASS = 'map-tour-elevated';
    const HEADING_SELECTORS = '.card-header, .card-header h6, .form-label, .map-nav-title, .sidebar-back-link, .nav-map-tour-btn';

    function clearHighlight() {
        document.querySelectorAll('.' + HIGHLIGHT_CLASS).forEach(function (el) {
            el.classList.remove(HIGHLIGHT_CLASS);
        });
        document.querySelectorAll('.' + HEADING_HIGHLIGHT_CLASS).forEach(function (el) {
            el.classList.remove(HEADING_HIGHLIGHT_CLASS);
        });
        document.querySelectorAll('.' + NAV_ELEVATED_CLASS).forEach(function (el) {
            el.classList.remove(NAV_ELEVATED_CLASS);
        });
    }

    function highlightSectionHeading(el) {
        if (!el) {
            return;
        }
        const heading = el.querySelector(HEADING_SELECTORS) || el;
        heading.classList.add(HEADING_HIGHLIGHT_CLASS);
    }

    function setHighlightTarget(el) {
        clearHighlight();
        if (!el) {
            return;
        }
        el.classList.add(HIGHLIGHT_CLASS);
        highlightSectionHeading(el);
        const elevateSelectors = ['.navbar', '.filter-panel', '.smart-controls', '.map-tools-left', '.map-hud', '.map-live-panel', '.map-page-nav'];
        elevateSelectors.forEach(function (sel) {
            const parent = el.closest(sel);
            if (parent) {
                parent.classList.add(NAV_ELEVATED_CLASS);
            }
        });
    }

    function isSidebarVisible() {
        if (typeof window.isMapSidebarOpen === 'function') {
            return window.isMapSidebarOpen();
        }
        const sidebar = document.getElementById('filterPanel');
        return !!(sidebar && sidebar.classList.contains('show'));
    }

    function openSidebar() {
        if (isSidebarVisible()) {
            return;
        }
        if (typeof window.applyMapSidebarState === 'function') {
            window.applyMapSidebarState(true);
            return;
        }
        if (typeof window.toggleMapSidebar === 'function') {
            window.toggleMapSidebar();
            return;
        }
        const sidebar = document.getElementById('filterPanel');
        if (!sidebar) {
            return;
        }
        sidebar.classList.add('show');
        sidebar.setAttribute('aria-hidden', 'false');
        document.querySelector('.map-area')?.classList.add('sidebar-open');
        document.body.classList.add('map-sidebar-open');
        const toggleBtn = document.getElementById('toggleSidebar');
        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', 'true');
        }
        if (typeof window.deviceMapResize === 'function') {
            window.deviceMapResize();
        }
    }

    function closeSidebar() {
        if (!isSidebarVisible()) {
            return;
        }
        if (typeof window.applyMapSidebarState === 'function') {
            window.applyMapSidebarState(false);
            return;
        }
        if (typeof window.toggleMapSidebar === 'function') {
            window.toggleMapSidebar();
            return;
        }
        const sidebar = document.getElementById('filterPanel');
        if (!sidebar) {
            return;
        }
        sidebar.classList.remove('show');
        sidebar.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('map-sidebar-open');
        const mapArea = document.querySelector('.map-area');
        mapArea?.classList.remove('sidebar-open');
        if (typeof window.deviceMapResize === 'function') {
            setTimeout(window.deviceMapResize, 400);
        }
    }

    function ensureSidebarForSelector(selector) {
        if (!selector) {
            return;
        }
        const el = document.querySelector(selector);
        if (el && el.closest('#filterPanel')) {
            openSidebar();
        }
    }

    function layoutDelayForStep(step) {
        if (step.needsSidebarOpen) {
            return 520;
        }
        if (step.needsSidebarClose) {
            return 420;
        }
        if (typeof step.beforeShow === 'function') {
            return 320;
        }
        if (step.type === 'modal' || step.type === 'finish') {
            return 0;
        }
        return 80;
    }

    function ensureDom() {
        if (document.getElementById('mapTourRoot')) {
            root = document.getElementById('mapTourRoot');
            applyTourUiStrings();
            return;
        }
        root = document.createElement('div');
        root.id = 'mapTourRoot';
        root.className = 'map-tour-root';
        root.setAttribute('aria-hidden', 'true');
        root.innerHTML = `
            <div class="map-tour-backdrop" data-tour-backdrop></div>
            <div class="map-tour-spotlight-wrap" data-tour-spotlight-wrap hidden>
                <span class="map-tour-spotlight__label" data-tour-spotlight-label></span>
                <div class="map-tour-spotlight" data-tour-spotlight></div>
            </div>
            <div class="map-tour-card" role="dialog" aria-modal="true" aria-labelledby="mapTourTitle">
                <div class="map-tour-card__progress">
                    <div class="map-tour-card__progress-bar" data-tour-progress></div>
                    <span class="map-tour-card__step-label" data-tour-step-label>Step 1 of 1</span>
                </div>
                <div class="map-tour-card__header">
                    <div class="map-tour-card__icon" data-tour-icon aria-hidden="true"><i class="fas fa-map"></i></div>
                    <div class="map-tour-card__titles">
                        <span class="map-tour-card__kicker" data-tour-kicker></span>
                        <h2 class="map-tour-card__title" id="mapTourTitle" data-tour-title>Tour</h2>
                    </div>
                </div>
                <p class="map-tour-card__body" data-tour-body></p>
                <p class="map-tour-card__hint map-tour-card__hint--warn" data-tour-hint hidden></p>
                <div class="map-tour-prefs" data-tour-prefs hidden>
                    <p class="map-tour-prefs__lead">When you open this map again:</p>
                    <label class="map-tour-prefs__option">
                        <input type="radio" name="mapTourPref" value="repeat" checked>
                        <span><strong>Show tour every time</strong> (recommended for new users)</span>
                    </label>
                    <label class="map-tour-prefs__option">
                        <input type="radio" name="mapTourPref" value="dismiss">
                        <span><strong>Don\'t show automatically</strong> — use Map tour button to replay</span>
                    </label>
                </div>
                <div class="map-tour-card__actions">
                    <div class="map-tour-card__actions-side">
                        <button type="button" class="map-tour-btn map-tour-btn--ghost" data-tour-skip>Skip tour</button>
                        <button type="button" class="map-tour-btn map-tour-btn--ghost" data-tour-dismiss hidden>Don&apos;t show again</button>
                    </div>
                    <div class="map-tour-card__nav">
                        <button type="button" class="map-tour-btn map-tour-btn--ghost" data-tour-prev hidden>Back</button>
                        <button type="button" class="map-tour-btn map-tour-btn--primary" data-tour-next>Next</button>
                    </div>
                </div>
            </div>
        `;
        document.documentElement.appendChild(root);
        syncTourNavInset();
        applyTourUiStrings();

        root.querySelector('[data-tour-skip]')?.addEventListener('click', skipToFinish);
        root.querySelector('[data-tour-dismiss]')?.addEventListener('click', dismissTourPermanently);
        root.querySelector('[data-tour-prev]')?.addEventListener('click', prev);
        root.querySelector('[data-tour-next]')?.addEventListener('click', next);
        root.querySelector('[data-tour-backdrop]')?.addEventListener('click', (e) => {
            if (e.target === e.currentTarget) next();
        });
    }

    function getSelectedPref() {
        const checked = root?.querySelector('input[name="mapTourPref"]:checked');
        return checked?.value === 'dismiss' ? 'dismiss' : 'repeat';
    }

    function setPrefRadios(mode) {
        const val = mode === 'dismiss' ? 'dismiss' : 'repeat';
        root?.querySelectorAll('input[name="mapTourPref"]').forEach((el) => {
            el.checked = el.value === val;
        });
    }

    async function savePreference(mode) {
        const url = tourCfg.saveUrl;
        if (!url) return;
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': cfg.csrfToken || '',
                },
                body: JSON.stringify({ mode }),
            });
            if (res.ok) {
                const data = await res.json();
                if (typeof data.show_on_load === 'boolean') {
                    tourCfg.showOnLoad = data.show_on_load;
                }
                if (data.mode) {
                    tourCfg.mode = data.mode;
                }
            }
        } catch (e) {
            console.warn('Map tour preference save failed', e);
        }
    }

    function showRoot(show) {
        if (!root) return;
        root.classList.toggle('map-tour-root--active', show);
        root.setAttribute('aria-hidden', show ? 'false' : 'true');
        document.body.classList.toggle('map-tour-open', show);
        if (show) {
            syncTourNavInset();
            syncWelcomePaneCssVars();
        } else {
            root.classList.remove('map-tour-root--welcome');
        }
    }

    function getNavHeight() {
        const nav = document.querySelector('nav.navbar, .navbar');
        return nav ? Math.ceil(nav.getBoundingClientRect().height) : 70;
    }

    function syncTourNavInset() {
        document.documentElement.style.setProperty('--map-tour-nav-h', getNavHeight() + 'px');
    }

    function isDesktopMap() {
        return window.innerWidth > 768;
    }

    /** Map area only — never includes the filter sidebar. */
    function insetRectForOpenSidebar(rect) {
        const sidebar = document.getElementById('filterPanel');
        if (!sidebar || !isSidebarVisible() || !isDesktopMap()) {
            return rect;
        }
        const sr = sidebar.getBoundingClientRect();
        if (isRtl()) {
            const right = Math.min(rect.right, sr.left);
            return {
                top: rect.top,
                left: rect.left,
                right: right,
                bottom: rect.bottom,
                width: Math.max(120, right - rect.left),
                height: rect.height,
            };
        }
        const left = Math.max(rect.left, sr.right);
        return {
            top: rect.top,
            left: left,
            right: rect.right,
            bottom: rect.bottom,
            width: Math.max(120, rect.right - left),
            height: rect.height,
        };
    }

    function mapPaneFallbackRect() {
        const navH = getNavHeight();
        return insetRectForOpenSidebar({
            top: navH,
            left: 0,
            right: window.innerWidth,
            bottom: window.innerHeight,
            width: window.innerWidth,
            height: Math.max(200, window.innerHeight - navH),
        });
    }

    /** Visible map pane (below navbar), not the full browser viewport. */
    function getMapPaneRect() {
        const navH = getNavHeight();
        const expectedH = Math.max(200, window.innerHeight - navH);
        const mapArea = document.getElementById('mapArea') || document.querySelector('.map-area');
        if (mapArea) {
            const r = mapArea.getBoundingClientRect();
            if (r.width > 120 && r.height >= expectedH * 0.35) {
                return insetRectForOpenSidebar({
                    top: r.top,
                    left: r.left,
                    right: r.right,
                    bottom: r.bottom,
                    width: r.width,
                    height: r.height,
                });
            }
            const tracking = document.querySelector('.tracking-container');
            if (tracking) {
                const tr = tracking.getBoundingClientRect();
                if (tr.height >= expectedH * 0.35) {
                    const sidebarW = isSidebarVisible() && isDesktopMap() ? 380 : 0;
                    const width = Math.max(120, tr.width - sidebarW);
                    const left = isRtl() ? tr.left : tr.left + sidebarW;
                    return {
                        top: tr.top,
                        left: left,
                        right: left + width,
                        bottom: tr.bottom,
                        width: width,
                        height: tr.height,
                    };
                }
            }
        }
        return mapPaneFallbackRect();
    }

    function isWelcomeModalStep() {
        const step = steps[index];
        return !!(step && step.type === 'modal' && index === 0);
    }

    /** Welcome intro is about the map — use full width below navbar (LTR sidebar must be closed). */
    function forceMapFullWidthForWelcome() {
        const mapArea = document.getElementById('mapArea');
        if (mapArea) {
            mapArea.classList.remove('sidebar-open');
            mapArea.style.marginLeft = '';
            mapArea.style.marginRight = '';
            mapArea.style.width = '';
        }
        document.body.classList.remove('map-sidebar-open');
        if (isSidebarVisible()) {
            if (typeof window.applyMapSidebarState === 'function') {
                window.applyMapSidebarState(false);
            } else {
                closeSidebar();
            }
        }
    }

    function getWelcomePaneRect() {
        syncTourNavInset();
        const navH = getNavHeight();
        const mapArea = document.getElementById('mapArea');
        if (mapArea) {
            const r = mapArea.getBoundingClientRect();
            if (r.width > 120 && r.height > 100) {
                let left = r.left;
                let right = r.right;
                let width = r.width;
                const sidebar = document.getElementById('filterPanel');
                if (sidebar && isDesktopMap() && !isRtl()) {
                    const sr = sidebar.getBoundingClientRect();
                    if (sr.right > 40 && sr.right < right - 80) {
                        left = Math.max(left, sr.right);
                        width = right - left;
                    }
                }
                if (sidebar && isDesktopMap() && isRtl()) {
                    const sr = sidebar.getBoundingClientRect();
                    if (sr.left > left + 80 && sr.left < window.innerWidth) {
                        right = Math.min(right, sr.left);
                        width = right - left;
                    }
                }
                return {
                    top: Math.max(navH, r.top),
                    left: left,
                    right: right,
                    bottom: r.bottom,
                    width: Math.max(120, width),
                    height: r.height,
                };
            }
        }
        return {
            top: navH,
            left: 0,
            right: window.innerWidth,
            bottom: window.innerHeight,
            width: window.innerWidth,
            height: Math.max(200, window.innerHeight - navH),
        };
    }

    function isWelcomePaneReady() {
        if (isSidebarVisible()) {
            return false;
        }
        const mapArea = document.getElementById('mapArea');
        if (!mapArea) {
            return true;
        }
        const r = mapArea.getBoundingClientRect();
        const tracking = document.querySelector('.tracking-container');
        if (!tracking) {
            return r.width > 200;
        }
        const tr = tracking.getBoundingClientRect();
        if (r.width < tr.width * 0.88) {
            return false;
        }
        if (isRtl()) {
            return true;
        }
        return r.left <= tr.left + 32;
    }

    function isMapPaneFullWidth() {
        return isWelcomePaneReady();
    }

    function syncWelcomePaneCssVars() {
        if (!root) {
            return;
        }
        const welcome = isWelcomeModalStep();
        root.classList.toggle('map-tour-root--welcome', welcome);
        if (!welcome) {
            return;
        }
        const pane = getWelcomePaneRect();
        root.style.setProperty('--tour-pane-top', pane.top + 'px');
        root.style.setProperty('--tour-pane-left', pane.left + 'px');
        root.style.setProperty('--tour-pane-width', pane.width + 'px');
        root.style.setProperty('--tour-pane-height', pane.height + 'px');
    }

    function welcomeLayoutDelay() {
        return isWelcomeModalStep() ? 480 : 0;
    }

    function setCardLayoutPending(pending) {
        const card = root?.querySelector('.map-tour-card');
        if (card) {
            card.classList.toggle('map-tour-card--layout-pending', pending);
        }
    }

    function clampCardInRect(left, top, cardW, cardH, rect, pad) {
        const margin = pad ?? 12;
        const minLeft = rect.left + margin;
        const maxLeft = rect.right - cardW - margin;
        const minTop = rect.top + margin;
        const maxTop = rect.bottom - cardH - margin;
        return {
            left: Math.min(Math.max(minLeft, left), Math.max(minLeft, maxLeft)),
            top: Math.min(Math.max(minTop, top), Math.max(minTop, maxTop)),
        };
    }

    function getClampRectForTarget(el) {
        const navH = getNavHeight();
        if (el && el.closest('#filterPanel')) {
            return getMapPaneRect();
        }
        if (el && el.closest('.navbar, nav.navbar')) {
            return {
                top: navH,
                left: 0,
                right: window.innerWidth,
                bottom: window.innerHeight,
                width: window.innerWidth,
                height: window.innerHeight - navH,
            };
        }
        return getMapPaneRect();
    }

    function positionSpotlight(el, pad, label) {
        const wrap = root.querySelector('[data-tour-spotlight-wrap]');
        const spot = root.querySelector('[data-tour-spotlight]');
        const labelEl = root.querySelector('[data-tour-spotlight-label]');
        if (!wrap || !spot) {
            return;
        }
        if (!el) {
            wrap.hidden = true;
            if (labelEl) {
                labelEl.hidden = true;
            }
            clearHighlight();
            return;
        }
        setHighlightTarget(el);
        const rect = el.getBoundingClientRect();
        const p = pad ?? 12;
        wrap.hidden = false;
        spot.style.top = `${Math.max(0, rect.top - p)}px`;
        spot.style.left = `${Math.max(0, rect.left - p)}px`;
        spot.style.width = `${rect.width + p * 2}px`;
        spot.style.height = `${rect.height + p * 2}px`;
        if (labelEl) {
            labelEl.textContent = label || '';
            labelEl.hidden = !label;
            if (label) {
                labelEl.style.top = `${Math.max(8, rect.top - p - 38)}px`;
                labelEl.style.left = `${Math.max(8, rect.left - p)}px`;
                labelEl.style.right = 'auto';
                requestAnimationFrame(function () {
                    const lw = labelEl.offsetWidth || 0;
                    let lx = Math.max(8, rect.left - p);
                    lx = Math.min(lx, window.innerWidth - lw - 8);
                    labelEl.style.left = `${lx}px`;
                });
            }
        }
    }

    function resetCardInlinePosition(card) {
        card.style.top = '';
        card.style.left = '';
        card.style.right = '';
        card.style.bottom = '';
        card.style.insetInlineStart = '';
        card.style.transform = '';
    }

    function cardDimensions(card, pane) {
        const paneW = pane ? pane.width : window.innerWidth;
        const maxW = Math.min(420, Math.max(280, paneW - 32));
        const cardRect = card.getBoundingClientRect();
        const w = cardRect.width || card.offsetWidth || maxW;
        const h = Math.max(
            cardRect.height || 0,
            card.scrollHeight || 0,
            card.offsetHeight || 0,
            180
        );
        return { w: Math.min(w, maxW), h: h };
    }

    function setCardPointer(placement) {
        const card = root.querySelector('.map-tour-card');
        if (!card) return;
        card.classList.remove(
            'map-tour-card--pointer-top',
            'map-tour-card--pointer-bottom',
            'map-tour-card--pointer-left',
            'map-tour-card--pointer-right',
            'map-tour-card--center'
        );
        if (placement === 'center') {
            card.classList.add('map-tour-card--center');
            return;
        }
        card.classList.add('map-tour-card--pointer-' + (placement || 'bottom'));
    }

    function positionCardCenter() {
        const card = root.querySelector('.map-tour-card');
        if (!card) {
            return false;
        }
        if (isWelcomeModalStep()) {
            syncWelcomePaneCssVars();
            if (!isWelcomePaneReady()) {
                return false;
            }
        }
        resetCardInlinePosition(card);
        setCardPointer('center');
        const pane = isWelcomeModalStep() ? getWelcomePaneRect() : getMapPaneRect();
        const dims = cardDimensions(card, pane);
        card.style.maxWidth = `${Math.min(420, Math.max(280, pane.width - 32))}px`;
        let left = pane.left + pane.width / 2 - dims.w / 2;
        let top = pane.top + pane.height / 2 - dims.h / 2;
        const pos = clampCardInRect(left, top, dims.w, dims.h, pane, 16);
        card.style.left = `${pos.left + dims.w / 2}px`;
        card.style.top = `${pos.top + dims.h / 2}px`;
        card.style.transform = 'translate(-50%, -50%)';
        return true;
    }

    function positionCardBesideSidebar(target) {
        const card = root.querySelector('.map-tour-card');
        if (!card || !target) return;
        resetCardInlinePosition(card);
        const pane = getMapPaneRect();
        const dims = cardDimensions(card, pane);
        const targetRect = target.getBoundingClientRect();
        const pad = 16;
        let left;
        let pointer;
        if (isRtl()) {
            left = pane.right - dims.w - pad;
            pointer = 'left';
        } else {
            left = pane.left + pad;
            pointer = 'right';
        }
        let top = targetRect.top + targetRect.height / 2 - dims.h / 2;
        if (pane.width < 340) {
            positionCardCenter();
            return;
        }
        const pos = clampCardInRect(left, top, dims.w, dims.h, pane, pad);
        setCardPointer(pointer);
        card.style.left = `${pos.left}px`;
        card.style.top = `${pos.top}px`;
    }

    function positionCard(placement, el) {
        const card = root.querySelector('.map-tour-card');
        if (!card) return;
        resetCardInlinePosition(card);
        if (!el || placement === 'center') {
            positionCardCenter();
            return;
        }
        if (el.closest('#filterPanel')) {
            positionCardBesideSidebar(el);
            return;
        }
        setCardPointer(placement);
        const rect = el.getBoundingClientRect();
        const margin = 18;
        const clampRect = getClampRectForTarget(el);
        const dims = cardDimensions(card, clampRect);
        let top = rect.bottom + margin;
        let left = rect.left;
        if (placement === 'top') {
            top = rect.top - dims.h - margin;
        } else if (placement === 'left') {
            top = rect.top;
            left = rect.left - dims.w - margin;
        } else if (placement === 'right') {
            top = rect.top;
            left = rect.right + margin;
        }
        const pos = clampCardInRect(left, top, dims.w, dims.h, clampRect, 12);
        card.style.left = `${pos.left}px`;
        card.style.top = `${pos.top}px`;
    }

    function applyModalCardLayout() {
        if (isWelcomeModalStep()) {
            forceMapFullWidthForWelcome();
        }
        if (typeof window.deviceMapResize === 'function') {
            window.deviceMapResize();
        }
        syncTourNavInset();
        clearHighlight();
        positionSpotlight(null, 0, '');
        const placed = positionCardCenter();
        if (isWelcomeModalStep() && placed) {
            setCardLayoutPending(false);
        }
    }

    function layoutModalChrome() {
        if (tourChromeBusy || !root) {
            return;
        }
        tourChromeBusy = true;
        setCardLayoutPending(true);
        applyModalCardLayout();
        requestAnimationFrame(function () {
            if (!active || !root) {
                tourChromeBusy = false;
                return;
            }
            applyModalCardLayout();
            requestAnimationFrame(function () {
                if (!active || !root) {
                    tourChromeBusy = false;
                    return;
                }
                applyModalCardLayout();
            });
        });
        [80, 220, 400, 520, 680].forEach(function (ms) {
            setTimeout(function () {
                if (!active || !root) {
                    return;
                }
                applyModalCardLayout();
                if (ms >= 680) {
                    tourChromeBusy = false;
                    if (isWelcomeModalStep()) {
                        setCardLayoutPending(false);
                    }
                }
            }, ms);
        });
    }

    function layoutTourChrome(step, target, isModal) {
        if (isModal) {
            layoutModalChrome();
            return;
        }
        setCardLayoutPending(false);
        if (target) {
            positionSpotlight(target, step.pad, step.title);
            positionCard(step.placement || 'bottom', target);
            requestAnimationFrame(function () {
                positionSpotlight(target, step.pad, step.title);
                positionCard(step.placement || 'bottom', target);
            });
            return;
        }
        clearHighlight();
        positionSpotlight(null, 0, '');
        positionCard('center');
        const hintEl = root.querySelector('[data-tour-hint]');
        if (hintEl && i18n.target_missing) {
            hintEl.textContent = i18n.target_missing;
            hintEl.hidden = false;
        }
    }

    function renderStep() {
        const step = steps[index];
        const total = steps.length;
        const isFinish = step.type === 'finish';
        const isModal = step.type === 'modal' || isFinish;

        const kicker = root.querySelector('[data-tour-kicker]');
        const hint = root.querySelector('[data-tour-hint]');
        const iconWrap = root.querySelector('[data-tour-icon]');
        const cardEl = root.querySelector('.map-tour-card');

        root.querySelector('[data-tour-title]').textContent = step.title;
        root.querySelector('[data-tour-body]').textContent = step.body;
        root.querySelector('[data-tour-step-label]').textContent = (i18n.step_label || 'Step :current of :total').replace(':current', index + 1).replace(':total', total);
        root.querySelector('[data-tour-progress]').style.width = `${((index + 1) / total) * 100}%`;

        if (kicker) {
            kicker.textContent = (isModal || isFinish) ? '' : step.title;
        }
        if (hint) {
            hint.hidden = true;
        }

        const iconEl = root.querySelector('[data-tour-icon] i');
        if (iconEl) {
            iconEl.className = `fas ${step.icon || 'fa-map-pin'}`;
        }
        if (iconWrap) {
            iconWrap.classList.toggle('map-tour-card__icon--finish', isFinish);
            iconWrap.classList.toggle('map-tour-card__icon--modal', isModal && !isFinish);
        }
        if (cardEl) {
            cardEl.setAttribute('dir', isRtl() ? 'rtl' : 'ltr');
            cardEl.lang = document.documentElement.lang || 'en';
        }

        if (isWelcomeModalStep()) {
            forceMapFullWidthForWelcome();
        }

        if (isModal) {
            setCardLayoutPending(true);
            if (!welcomeLayoutDelay()) {
                applyModalCardLayout();
            }
        }

        root.querySelector('[data-tour-prefs]').hidden = !isFinish;
        root.querySelector('[data-tour-prev]').hidden = index === 0;
        root.querySelector('[data-tour-next]').textContent = isFinish ? (i18n.finish || 'Finish') : (i18n.next || 'Next');

        const skipBtn = root.querySelector('[data-tour-skip]');
        if (skipBtn) {
            skipBtn.hidden = isFinish;
        }

        const dismissBtn = root.querySelector('[data-tour-dismiss]');
        if (dismissBtn) {
            dismissBtn.hidden = isFinish || !isWelcomeModalStep();
        }

        if (isFinish) {
            setPrefRadios(tourCfg.mode || 'repeat');
            if (skippedEarly) {
                root.querySelector('[data-tour-title]').textContent = i18n.skip_title || 'Skip tour';
                root.querySelector('[data-tour-body]').textContent = i18n.skip_body || '';
            }
        }

        if (step.needsSidebarOpen) {
            openSidebar();
        } else if (typeof step.beforeShow === 'function') {
            step.beforeShow();
        }

        let target = null;
        if (!isModal && step.selector) {
            ensureSidebarForSelector(step.selector);
            target = document.querySelector(step.selector);
            if (target) {
                target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        }

        let delay = layoutDelayForStep(step) + welcomeLayoutDelay();

        setTimeout(function () {
            if (!isModal && step.selector && step.needsSidebarOpen && !isSidebarVisible()) {
                openSidebar();
            }
            if (!isModal && step.selector) {
                target = document.querySelector(step.selector);
            }

            layoutTourChrome(step, target, isModal);
            if (!isModal && typeof window.deviceMapResize === 'function') {
                window.deviceMapResize();
            }
        }, delay);
    }

    function skipToFinish() {
        if (!active || !steps.length) {
            return;
        }
        skippedEarly = true;
        index = steps.length - 1;
        renderStep();
    }

    function dismissTourPermanently() {
        if (!active) {
            return;
        }
        tourCfg.showOnLoad = false;
        tourCfg.mode = 'dismiss';
        finish('dismiss');
    }

    let tourResizeTimer = null;
    let tourChromeBusy = false;

    function onTourResize() {
        if (!active || !root) {
            return;
        }
        clearTimeout(tourResizeTimer);
        tourResizeTimer = setTimeout(function () {
            syncTourNavInset();
            const step = steps[index];
            if (!step) {
                return;
            }
            const isFinish = step.type === 'finish';
            const isModal = step.type === 'modal' || isFinish;
            let target = null;
            if (!isModal && step.selector) {
                target = document.querySelector(step.selector);
            }
            layoutTourChrome(step, target, isModal);
        }, 120);
    }

    function start(force) {
        if (active) return;
        steps = buildSteps();
        index = 0;
        skippedEarly = false;
        active = true;
        ensureDom();
        window.addEventListener('resize', onTourResize);
        syncTourNavInset();
        if (typeof window.deviceMapResize === 'function') {
            window.deviceMapResize();
        }
        showRoot(true);
        if (steps[0] && steps[0].type === 'modal') {
            forceMapFullWidthForWelcome();
        }
        renderStep();
    }

    function stop() {
        active = false;
        tourChromeBusy = false;
        window.removeEventListener('resize', onTourResize);
        clearTimeout(tourResizeTimer);
        clearHighlight();
        showRoot(false);
        closeSidebar();
    }

    function next() {
        const step = steps[index];
        if (step.type === 'finish') {
            finish(getSelectedPref());
            return;
        }
        if (index < steps.length - 1) {
            index += 1;
            renderStep();
        } else {
            finish(getSelectedPref());
        }
    }

    function prev() {
        if (index > 0) {
            index -= 1;
            renderStep();
        }
    }

    function finish(mode) {
        savePreference(mode);
        stop();
    }

    window.MapTour = {
        start,
        restart: () => start(true),
    };

    document.getElementById('btnMapTour')?.addEventListener('click', () => start(true));

    window.addEventListener('device-map-ready', function () {
        if (!tourCfg.showOnLoad) {
            return;
        }
        requestAnimationFrame(function () {
            if (typeof window.deviceMapResize === 'function') {
                window.deviceMapResize();
            }
            setTimeout(function () {
                start(false);
            }, 120);
        });
    });

    if (tourCfg.showOnLoad && document.getElementById('map')?.dataset?.tourReady) {
        requestAnimationFrame(function () {
            setTimeout(function () {
                start(false);
            }, 120);
        });
    }
})();
