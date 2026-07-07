/**
 * Drag-to-reposition floating map panels; persists layout per storage key.
 */
(function (global) {
    'use strict';

    const mounted = new Map();
    const SAFE_PAD = 8;

    function readPos(key) {
        try {
            const raw = localStorage.getItem(key);
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed.x !== 'number' || typeof parsed.y !== 'number') {
                return null;
            }
            return parsed;
        } catch (e) {
            return null;
        }
    }

    function writePos(key, pos) {
        try {
            localStorage.setItem(key, JSON.stringify(pos));
        } catch (e) { /* ignore */ }
    }

    function clearPos(key) {
        try {
            localStorage.removeItem(key);
        } catch (e) { /* ignore */ }
    }

    function panelSize(panel) {
        const rect = panel.getBoundingClientRect();
        return { w: rect.width, h: rect.height };
    }

    function clampPos(panel, bounds, x, y) {
        const bRect = bounds.getBoundingClientRect();
        const size = panelSize(panel);
        const maxX = Math.max(SAFE_PAD, bRect.width - size.w - SAFE_PAD);
        const maxY = Math.max(SAFE_PAD, bRect.height - size.h - SAFE_PAD);
        return {
            x: Math.min(Math.max(SAFE_PAD, x), maxX),
            y: Math.min(Math.max(SAFE_PAD, y), maxY),
        };
    }

    function captureCurrentPos(panel, bounds) {
        const pRect = panel.getBoundingClientRect();
        const bRect = bounds.getBoundingClientRect();
        return {
            x: pRect.left - bRect.left,
            y: pRect.top - bRect.top,
        };
    }

    function applyPos(panel, bounds, pos) {
        const clamped = clampPos(panel, bounds, pos.x, pos.y);
        panel.classList.add('is-user-positioned');
        panel.style.left = `${clamped.x}px`;
        panel.style.top = `${clamped.y}px`;
        panel.style.right = 'auto';
        panel.style.bottom = 'auto';
        panel.style.insetInlineEnd = 'auto';
        panel.style.insetInlineStart = `${clamped.x}px`;
        panel.style.transform = 'none';
        return clamped;
    }

    function resetPanel(panel, bounds, storageKey) {
        panel.classList.remove('is-user-positioned', 'is-dragging');
        panel.style.left = '';
        panel.style.top = '';
        panel.style.right = '';
        panel.style.bottom = '';
        panel.style.insetInlineStart = '';
        panel.style.insetInlineEnd = '';
        panel.style.transform = '';
        clearPos(storageKey);
    }

    function mount(options) {
        const panel = typeof options.panel === 'string'
            ? document.querySelector(options.panel)
            : options.panel;
        const bounds = options.bounds || document.getElementById('mapArea');
        const storageKey = options.storageKey;
        if (!panel || !bounds || !storageKey || mounted.has(panel)) {
            return null;
        }

        const entry = {
            panel,
            bounds,
            storageKey,
            dragging: false,
        };
        mounted.set(panel, entry);

        const saved = readPos(storageKey);
        if (saved) {
            requestAnimationFrame(() => applyPos(panel, bounds, saved));
        }

        panel.addEventListener('pointerdown', (e) => {
            const grip = e.target.closest('.map-panel-drag-grip, [data-map-drag-grip]');
            if (!grip || !panel.contains(grip)) return;
            if (e.button !== 0) return;

            e.preventDefault();
            e.stopPropagation();

            if (e.detail >= 2) {
                resetPanel(panel, bounds, storageKey);
                return;
            }

            if (!panel.classList.contains('is-user-positioned')) {
                const current = captureCurrentPos(panel, bounds);
                applyPos(panel, bounds, current);
            }

            const bRect = bounds.getBoundingClientRect();
            const startX = parseFloat(panel.style.left) || 0;
            const startY = parseFloat(panel.style.top) || 0;
            const offsetX = e.clientX - bRect.left - startX;
            const offsetY = e.clientY - bRect.top - startY;

            entry.dragging = true;
            panel.classList.add('is-dragging');
            panel.setPointerCapture?.(e.pointerId);

            const onMove = (ev) => {
                if (!entry.dragging) return;
                const next = clampPos(
                    panel,
                    bounds,
                    ev.clientX - bRect.left - offsetX,
                    ev.clientY - bRect.top - offsetY
                );
                panel.style.left = `${next.x}px`;
                panel.style.top = `${next.y}px`;
                panel.style.insetInlineStart = `${next.x}px`;
            };

            const onEnd = (ev) => {
                if (!entry.dragging) return;
                entry.dragging = false;
                panel.classList.remove('is-dragging');
                panel.releasePointerCapture?.(ev.pointerId);
                panel.removeEventListener('pointermove', onMove);
                panel.removeEventListener('pointerup', onEnd);
                panel.removeEventListener('pointercancel', onEnd);

                const finalPos = {
                    x: parseFloat(panel.style.left) || 0,
                    y: parseFloat(panel.style.top) || 0,
                };
                writePos(storageKey, finalPos);
            };

            panel.addEventListener('pointermove', onMove);
            panel.addEventListener('pointerup', onEnd);
            panel.addEventListener('pointercancel', onEnd);
        });

        return entry;
    }

    function reclampAll() {
        mounted.forEach((entry) => {
            const { panel, bounds, storageKey } = entry;
            if (!panel.classList.contains('is-user-positioned')) return;
            const saved = readPos(storageKey);
            if (!saved) return;
            applyPos(panel, bounds, saved);
        });
    }

    global.MapPanelPosition = {
        mount,
        reclampAll,
        reset(panelEl) {
            const entry = mounted.get(panelEl);
            if (!entry) return;
            resetPanel(entry.panel, entry.bounds, entry.storageKey);
        },
    };
})(typeof window !== 'undefined' ? window : globalThis);
