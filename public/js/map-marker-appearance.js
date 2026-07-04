(function (global) {
    'use strict';

    const DEFAULT_SIZE_ORDER = ['50', '75', '100', '125', '150', '200'];
    const PREVIEW_HEADING = 45;
    const MAP_ICON_BASE_PX = 64;

    function stepSize(current, delta, order) {
        const sizes = order && order.length ? order : DEFAULT_SIZE_ORDER;
        const idx = sizes.indexOf(current);
        const base = idx >= 0 ? idx : sizes.indexOf('100');
        const next = Math.max(0, Math.min(sizes.length - 1, base + delta));
        return sizes[next];
    }

    function readState(form, order) {
        const sizes = order && order.length ? order : DEFAULT_SIZE_ORDER;
        const typeEl = form.querySelector('[data-map-vehicle-type]');
        const sizeValueEl = form.querySelector('[data-map-size-value]');
        const rotationEl = form.querySelector('[data-map-rotation]');
        const rangeEl = form.querySelector('[data-map-size-range]');
        let size = sizeValueEl?.textContent || '100';
        if (rangeEl) {
            const idx = parseInt(rangeEl.value, 10);
            if (Number.isFinite(idx) && sizes[idx]) {
                size = sizes[idx];
            }
        }

        return {
            vehicle_type: typeEl?.value || 'car',
            map_marker_style: 'body',
            map_marker_size: size,
            map_icon_rotation_enabled: rotationEl ? rotationEl.checked : true,
            map_icon_source: form.dataset.customActive === '1' ? 'custom' : 'default',
            map_custom_icon_url: form.dataset.customPreviewUrl || null,
        };
    }

    function mapMarkerPixels(state, mapRendering) {
        const VM = global.VehicleMarker;
        const scale = VM?.resolveMarkerSizeScale?.(state, mapRendering) || 1;
        return Math.max(20, Math.round(MAP_ICON_BASE_PX * scale));
    }

    function showResizeNotice(form, uploadMeta, options) {
        const notice = form.querySelector('[data-map-resize-notice]');
        if (!notice) return;
        if (!uploadMeta?.resized) {
            notice.hidden = true;
            notice.textContent = '';
            return;
        }
        const from = `${uploadMeta.original_width}×${uploadMeta.original_height}`;
        const to = `${uploadMeta.width}×${uploadMeta.height}`;
        const template = options.i18n?.resized || 'Image resized from :from to :to. Use the size slider if it still looks too large on the map.';
        notice.textContent = template.replace(':from', from).replace(':to', to);
        notice.hidden = false;
    }

    function revokePreviewBlob(form) {
        const blob = form.dataset.customPreviewBlob;
        if (!blob) return;
        try {
            URL.revokeObjectURL(blob);
        } catch (_) { /* ignore */ }
        delete form.dataset.customPreviewBlob;
    }

    function resolveCustomPreviewUrl(form, state) {
        const server = form.dataset.customPreviewUrl || state.map_custom_icon_url || null;
        const blob = form.dataset.customPreviewBlob || null;
        return server || blob;
    }

    function renderLiveMapPreview(form, options) {
        const VM = global.VehicleMarker;
        const customWrap = form.querySelector('[data-map-live-custom]');
        const customImg = form.querySelector('[data-map-live-custom-img]');
        const defaultWrap = form.querySelector('[data-map-live-default]');
        const scaleEl = form.querySelector('[data-map-live-scale]');
        if (!VM || !defaultWrap) return;

        const state = readState(form, options.sizeOrder);
        const customUrl = resolveCustomPreviewUrl(form, state);
        const hasCustom = !!(customUrl && form.dataset.customActive === '1');
        const px = mapMarkerPixels(state, options.mapRendering);
        const rotate = state.map_icon_rotation_enabled ? `rotate(${PREVIEW_HEADING}deg)` : 'none';

        if (hasCustom && customWrap && customImg) {
            customWrap.hidden = false;
            defaultWrap.hidden = true;
            customWrap.style.width = `${px}px`;
            customWrap.style.height = `${px}px`;
            if (customImg.src !== customUrl) {
                customImg.src = customUrl;
            }
            customImg.style.transform = rotate;
            customImg.onerror = () => {
                const blob = form.dataset.customPreviewBlob;
                if (blob && customImg.src !== blob) {
                    customImg.src = blob;
                }
            };
        } else {
            if (customWrap) customWrap.hidden = true;
            defaultWrap.hidden = false;
            const scale = VM.resolveMarkerSizeScale(state, options.mapRendering);
            const pinW = Math.round(36 * scale);
            const pinH = Math.round(48 * scale);
            defaultWrap.style.width = `${pinW}px`;
            defaultWrap.style.height = `${pinH}px`;
            defaultWrap.style.transform = '';
            const pin = VM.pinIconFor('#2563eb', global.google);
            defaultWrap.style.backgroundImage = pin?.url ? `url("${pin.url}")` : 'none';
            defaultWrap.style.backgroundSize = 'contain';
            defaultWrap.style.backgroundRepeat = 'no-repeat';
            defaultWrap.style.backgroundPosition = 'center bottom';
            if (scaleEl) {
                const label = options.i18n?.liveScale || 'Map size: :px px (:percent%)';
                scaleEl.textContent = label
                    .replace(':px', String(pinH))
                    .replace(':percent', state.map_marker_size);
            }
            return;
        }

        if (scaleEl) {
            const label = options.i18n?.liveScale || 'Map size: :px px (:percent%)';
            scaleEl.textContent = label
                .replace(':px', String(px))
                .replace(':percent', state.map_marker_size);
        }
    }

    function renderPreview(form, options) {
        renderLiveMapPreview(form, options);
    }

    function renderSize(form, size, options) {
        const sizeLabelEl = form.querySelector('[data-map-size-label]');
        const sizeValueEl = form.querySelector('[data-map-size-value]');
        const rangeEl = form.querySelector('[data-map-size-range]');
        const sizes = options.sizeOrder || DEFAULT_SIZE_ORDER;
        if (sizeValueEl) sizeValueEl.textContent = size;
        if (sizeLabelEl && options.i18n?.sizes) {
            sizeLabelEl.textContent = options.i18n.sizes[size] || `${size}%`;
        }
        if (rangeEl) {
            const idx = sizes.indexOf(size);
            if (idx >= 0) rangeEl.value = String(idx);
        }
    }

    async function loadImageDimensions(file) {
        if (!file || file.type === 'image/svg+xml') {
            return null;
        }
        const url = URL.createObjectURL(file);
        try {
            const img = new Image();
            await new Promise((resolve, reject) => {
                img.onload = resolve;
                img.onerror = reject;
                img.src = url;
            });
            return { width: img.naturalWidth, height: img.naturalHeight };
        } catch (_) {
            return null;
        } finally {
            URL.revokeObjectURL(url);
        }
    }

    async function resizeRasterFile(file, maxSide) {
        if (!file || file.type === 'image/svg+xml') {
            return file;
        }
        const dims = await loadImageDimensions(file);
        if (!dims || (dims.width <= maxSide && dims.height <= maxSide)) {
            return file;
        }
        const ratio = Math.min(maxSide / dims.width, maxSide / dims.height);
        const width = Math.max(1, Math.round(dims.width * ratio));
        const height = Math.max(1, Math.round(dims.height * ratio));
        const bitmap = await createImageBitmap(file);
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        if (!ctx) return file;
        ctx.clearRect(0, 0, width, height);
        ctx.drawImage(bitmap, 0, 0, width, height);
        bitmap.close?.();
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png', 0.92));
        if (!blob) return file;
        const baseName = file.name.replace(/\.[^.]+$/, '') || 'icon';
        return new File([blob], `${baseName}.png`, { type: 'image/png' });
    }

    function bindForm(options) {
        const form = options.form;
        if (!form) return;

        const sizes = options.sizeOrder || DEFAULT_SIZE_ORDER;
        const typeEl = form.querySelector('[data-map-vehicle-type]');
        const btnDec = form.querySelector('[data-map-size-dec]');
        const btnInc = form.querySelector('[data-map-size-inc]');
        const rangeEl = form.querySelector('[data-map-size-range]');
        const rotationEl = form.querySelector('[data-map-rotation]');
        const fileEl = form.querySelector('[data-map-custom-file]');
        const revertBtn = form.querySelector('[data-map-revert-custom]');
        const backBtn = form.querySelector('[data-map-appearance-back]');
        const statusEl = form.querySelector('[data-map-save-status]');
        const uploadMax = parseInt(form.dataset.uploadMax || '256', 10) || 256;

        let currentSize = options.initial?.map_marker_size || '100';
        if (options.initial?.map_icon_source === 'custom' && options.initial?.map_custom_icon_url) {
            form.dataset.customActive = '1';
            form.dataset.customPreviewUrl = options.initial.map_custom_icon_url;
            revertBtn?.removeAttribute('hidden');
        }

        if (typeEl && options.initial?.vehicle_type) {
            typeEl.value = options.initial.vehicle_type;
        }
        if (rotationEl && options.initial?.map_icon_rotation_enabled === false) {
            rotationEl.checked = false;
        }

        renderSize(form, currentSize, options);
        renderPreview(form, options);

        const onSizeChange = (size) => {
            currentSize = size;
            renderSize(form, currentSize, options);
            renderPreview(form, options);
            options.onPreviewChange?.(readState(form, sizes));
        };

        btnDec?.addEventListener('click', () => onSizeChange(stepSize(currentSize, -1, sizes)));
        btnInc?.addEventListener('click', () => onSizeChange(stepSize(currentSize, 1, sizes)));
        rangeEl?.addEventListener('input', () => {
            const idx = parseInt(rangeEl.value, 10);
            if (sizes[idx]) onSizeChange(sizes[idx]);
        });
        rotationEl?.addEventListener('change', () => {
            renderPreview(form, options);
            options.onPreviewChange?.(readState(form, sizes));
        });
        backBtn?.addEventListener('click', () => {
            if (typeof options.onBack === 'function') {
                options.onBack(form);
                return;
            }

            const panel = form.closest('[data-map-appearance-panel], .map-info-card, .map-panel, .offcanvas, .modal');
            if (panel) {
                panel.dispatchEvent(new CustomEvent('map-appearance:back', { bubbles: true }));
            }

            if (window.history.length > 1) {
                window.history.back();
            }
        });

        fileEl?.addEventListener('change', async () => {
            const file = fileEl.files?.[0];
            if (!file || !options.uploadUrl) return;
            statusEl && (statusEl.textContent = '');
            const body = new FormData();
            try {
                const prepared = await resizeRasterFile(file, uploadMax);
                revokePreviewBlob(form);
                const blobUrl = URL.createObjectURL(prepared);
                form.dataset.customPreviewBlob = blobUrl;
                form.dataset.customActive = '1';
                form.dataset.customPreviewUrl = blobUrl;
                revertBtn?.removeAttribute('hidden');
                renderPreview(form, options);

                body.append('icon', prepared);
                const res = await fetch(options.uploadUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': options.csrf || '',
                    },
                    body,
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) {
                    throw new Error(data.message || 'Upload failed');
                }
                const appearance = data.appearance || {};
                if (appearance.map_custom_icon_url) {
                    form.dataset.customPreviewUrl = appearance.map_custom_icon_url;
                }
                showResizeNotice(form, appearance.upload_meta, options);
                options.onSaved?.(appearance);
                renderPreview(form, options);
                if (statusEl) statusEl.textContent = options.i18n?.uploaded || 'Uploaded';
            } catch (err) {
                if (statusEl) statusEl.textContent = err.message || options.i18n?.failed || 'Failed';
            } finally {
                fileEl.value = '';
            }
        });

        revertBtn?.addEventListener('click', async () => {
            if (!options.deleteUrl) return;
            try {
                const res = await fetch(options.deleteUrl, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': options.csrf || '',
                    },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) {
                    throw new Error(data.message || 'Failed');
                }
                form.dataset.customActive = '0';
                delete form.dataset.customPreviewUrl;
                revokePreviewBlob(form);
                revertBtn.setAttribute('hidden', 'hidden');
                showResizeNotice(form, null, options);
                options.onSaved?.(data.appearance || {});
                renderPreview(form, options);
            } catch (err) {
                if (statusEl) statusEl.textContent = err.message || options.i18n?.failed || 'Failed';
            }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const state = readState(form, sizes);
            const payload = {
                map_marker_size: state.map_marker_size,
                map_icon_rotation_enabled: state.map_icon_rotation_enabled,
            };
            if (form.dataset.customActive === '1') {
                payload.map_marker_style = 'body';
            }

            const btn = form.querySelector('[type="submit"]');
            btn?.setAttribute('disabled', 'disabled');
            if (statusEl) statusEl.textContent = '';

            try {
                const res = await fetch(options.saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': options.csrf || '',
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) {
                    throw new Error(data.message || 'Save failed');
                }
                const appearance = data.appearance || payload;
                options.onSaved?.(appearance);
                renderPreview(form, options);
                if (statusEl) {
                    statusEl.textContent = options.i18n?.saved || 'Saved';
                }
            } catch (err) {
                if (statusEl) {
                    statusEl.textContent = err.message || options.i18n?.failed || 'Failed';
                }
            } finally {
                btn?.removeAttribute('disabled');
            }
        });
    }

    global.MapMarkerAppearance = {
        bindForm,
        stepSize,
        DEFAULT_SIZE_ORDER,
        renderPreview,
        renderLiveMapPreview,
        mapMarkerPixels,
    };
})(window);
