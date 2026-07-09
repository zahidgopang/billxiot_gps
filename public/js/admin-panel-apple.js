(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const body = document.body;
        if (!body.classList.contains('apple-hig')) return;

        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const toggleBtn = document.getElementById('sidebarToggle') || document.getElementById('toggleSidebar');
        const STORAGE_KEY = 'adminSidebarOpen';

        if (!sidebar || !toggleBtn) return;

        function isMobile() {
            return window.innerWidth <= 768;
        }

        function setSidebarOpen(open, opts) {
            const persist = !opts || opts.persist !== false;
            const useDesktopOpen = open && !isMobile();

            sidebar.classList.toggle('is-open', open);
            body.classList.toggle('admin-sidebar-open', useDesktopOpen);

            if (overlay) {
                overlay.classList.toggle('show', open && isMobile());
            }

            body.style.overflow = (open && isMobile()) ? 'hidden' : '';

            toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-bars', !open);
                icon.classList.toggle('fa-times', open);
            }

            if (persist && !isMobile()) {
                try {
                    localStorage.setItem(STORAGE_KEY, open ? '1' : '0');
                } catch (_) { /* ignore */ }
            }
        }

        function toggleSidebar() {
            setSidebarOpen(!sidebar.classList.contains('is-open'));
        }

        function syncSidebarForViewport() {
            if (isMobile()) {
                setSidebarOpen(false, { persist: false });
                return;
            }

            let open = true;
            try {
                const stored = localStorage.getItem(STORAGE_KEY);
                if (stored !== null) {
                    open = stored === '1';
                }
            } catch (_) { /* ignore */ }

            setSidebarOpen(open, { persist: false });
        }

        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });

        overlay?.addEventListener('click', function () {
            setSidebarOpen(false);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sidebar.classList.contains('is-open') && isMobile()) {
                setSidebarOpen(false);
            }
        });

        let resizeTimer = null;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(syncSidebarForViewport, 120);
        });

        syncSidebarForViewport();
    });
})();
