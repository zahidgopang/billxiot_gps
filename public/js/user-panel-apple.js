(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const body = document.body;
        if (!body.classList.contains('apple-hig')) return;

        wireGlobalSearch();

        const toggleBtn = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('filterPanel');
        const overlay = document.getElementById('sidebarOverlay');
        const closeBtn = document.getElementById('closeSidebar');
        const app = document.querySelector('.ud-app');
        const desktop = () => window.matchMedia('(min-width: 992px)').matches;
        const STORAGE_KEY = 'udSidebarOpen';

        if (!toggleBtn || !sidebar || !app) return;

        function isRtl() {
            return document.documentElement.getAttribute('dir') === 'rtl'
                || body.getAttribute('dir') === 'rtl'
                || app.getAttribute('dir') === 'rtl'
                || app.getAttribute('data-layout-dir') === 'rtl'
                || body.classList.contains('user-panel-rtl');
        }

        function clearLegacyDrawerStyles() {
            sidebar.style.removeProperty('transform');
            sidebar.style.removeProperty('left');
            sidebar.style.removeProperty('right');
            sidebar.style.removeProperty('inset-inline-start');
            sidebar.style.removeProperty('inset-inline-end');
            sidebar.style.removeProperty('opacity');
            sidebar.style.removeProperty('visibility');
        }

        function isDesktopOpen() {
            return !app.classList.contains('sidebar-nav-closed');
        }

        function setDesktopOpen(open) {
            sidebar.classList.remove('collapsed', 'sidebar-closed');
            app.classList.toggle('sidebar-nav-closed', !open);
            sidebar.classList.toggle('is-collapsed', !open);
            toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            try {
                localStorage.setItem(STORAGE_KEY, open ? '1' : '0');
            } catch (_) { /* ignore */ }
        }

        function syncSidebarLayout() {
            clearLegacyDrawerStyles();

            if (desktop()) {
                sidebar.classList.remove('show');
                overlay?.classList.remove('show');
                body.style.overflow = '';
                toggleBtn.setAttribute('aria-expanded', isDesktopOpen() ? 'true' : 'false');
                return;
            }

            app.classList.remove('sidebar-nav-closed');
            sidebar.classList.remove('sidebar-closed', 'collapsed');

            if (isRtl()) {
                sidebar.style.left = 'auto';
                sidebar.style.right = '0';
            } else {
                sidebar.style.left = '0';
                sidebar.style.right = 'auto';
            }
        }

        try {
            const legacyCollapsed = localStorage.getItem('udSidebarCollapsed');
            if (legacyCollapsed !== null) {
                localStorage.removeItem('udSidebarCollapsed');
                localStorage.setItem(STORAGE_KEY, legacyCollapsed === '1' ? '0' : '1');
            }
            if (desktop() && localStorage.getItem(STORAGE_KEY) === '0') {
                app.classList.add('sidebar-nav-closed');
                sidebar.classList.add('is-collapsed');
            }
        } catch (_) { /* ignore */ }

        function openMobile() {
            clearLegacyDrawerStyles();
            app.classList.remove('sidebar-nav-closed');
            sidebar.classList.remove('sidebar-closed', 'collapsed');
            sidebar.classList.add('show');
            overlay?.classList.add('show');
            document.body.style.overflow = 'hidden';
            toggleBtn.setAttribute('aria-expanded', 'true');
            if (isRtl()) {
                sidebar.style.left = 'auto';
                sidebar.style.right = '0';
            } else {
                sidebar.style.left = '0';
                sidebar.style.right = 'auto';
            }
        }

        function closeMobile() {
            sidebar.classList.remove('show');
            overlay?.classList.remove('show');
            document.body.style.overflow = '';
            toggleBtn.setAttribute('aria-expanded', 'false');
            syncSidebarLayout();
        }

        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (desktop()) {
                setDesktopOpen(!isDesktopOpen());
                syncSidebarLayout();
                return;
            }

            sidebar.classList.contains('show') ? closeMobile() : openMobile();
        });

        overlay?.addEventListener('click', closeMobile);
        closeBtn?.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (desktop()) {
                setDesktopOpen(false);
                syncSidebarLayout();
            } else {
                closeMobile();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (desktop()) {
                    setDesktopOpen(false);
                    syncSidebarLayout();
                } else {
                    closeMobile();
                }
            }
        });

        window.addEventListener('resize', function () {
            syncSidebarLayout();
            if (desktop()) {
                closeMobile();
            }
        });

        syncSidebarLayout();
    });

    function wireGlobalSearch() {
        const globalSearch = document.getElementById('udGlobalSearch');
        if (!globalSearch) return;

        const fleetSearch = document.getElementById('udFleetSearch');
        const deviceSearch = document.getElementById('deviceSearch');
        const devicesUrl = globalSearch.dataset.devicesUrl || '';

        function applyQuery(q) {
            if (fleetSearch) {
                fleetSearch.value = q;
                fleetSearch.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (deviceSearch) {
                deviceSearch.value = q;
                deviceSearch.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        const params = new URLSearchParams(window.location.search);
        const initial = params.get('q') || '';
        if (initial) {
            globalSearch.value = initial;
            applyQuery(initial);
        }

        globalSearch.addEventListener('input', function () {
            applyQuery(globalSearch.value);
        });

        globalSearch.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const q = globalSearch.value.trim();
            if (fleetSearch) {
                document.getElementById('udFleetTable')?.closest('.ud-card')
                    ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }
            if (deviceSearch) {
                return;
            }
            if (!devicesUrl) return;
            const url = new URL(devicesUrl, window.location.origin);
            if (q) url.searchParams.set('q', q);
            window.location.href = url.toString();
        });
    }
})();
