(function (global) {
    'use strict';

    const DEFAULT_SIZE_ORDER = ['50', '75', '100', '125', '150', '200'];
    /** Sample GPS heading for the “map sample” panel only (not for nose picking). */
    const PREVIEW_HEADING = 45;
    const MAP_ICON_BASE_PX = 64;

    function parseOffsetDegrees(value) {
        const n = Number(value);
        return Number.isFinite(n) ? n : 0;
    }

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
        const pathEl = form.querySelector('[data-map-builtin-icon-path]');
        const sizeValueEl = form.querySelector('[data-map-size-value]');
        const rotationEl = form.querySelector('[data-map-rotation]');
        const offsetEl = form.querySelector('[data-map-rotation-offset]');
        const rangeEl = form.querySelector('[data-map-size-range]');
        let size = sizeValueEl?.textContent || '100';
        if (rangeEl) {
            const idx = parseInt(rangeEl.value, 10);
            if (Number.isFinite(idx) && sizes[idx]) {
                size = sizes[idx];
            }
        }

        const registry = (() => {
            try { return JSON.parse(form.dataset.iconRegistry || '{}'); } catch (_) { return {}; }
        })();
        const type = typeEl?.value || 'car';
        const path = pathEl?.value || null;
        const iconMeta = registry?.icons?.[type];
        const offsetFromSelect = offsetEl?.value;
        const offsetFromIcon = iconMeta?.rotation_offset;
        const offsetFromForm = form.dataset.rotationOffset;
        const customActive = form.dataset.customActive === '1';
        const customUrl = customActive ? (form.dataset.customPreviewUrl || null) : null;
        const builtinUrl = (!customActive && path && global.BuiltinMapIcons?.urlForPath)
            ? global.BuiltinMapIcons.urlForPath(path, registry)
            : ((!customActive && type && global.BuiltinMapIcons?.urlForType)
                ? global.BuiltinMapIcons.urlForType(type, registry)
                : (iconMeta?.url || null));
        const resolvedOffset = parseOffsetDegrees(
            offsetFromSelect != null && offsetFromSelect !== ''
                ? offsetFromSelect
                : (offsetFromForm != null && offsetFromForm !== ''
                    ? offsetFromForm
                    : (offsetFromIcon ?? (iconMeta?.shared ? -90 : 0)))
        );
        return {
            vehicle_type: type,
            map_builtin_icon_path: path,
            map_builtin_icon_url: builtinUrl,
            map_marker_style: 'body',
            map_marker_size: size,
            map_icon_rotation_enabled: rotationEl ? rotationEl.checked : true,
            map_icon_rotation_offset: resolvedOffset,
            map_icon_source: customUrl ? 'custom' : 'default',
            map_custom_icon_url: customUrl,
        };
    }

    function mapMarkerPixels(state, mapRendering) {
        const scale = resolvePreviewScale(state, mapRendering);
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

    function readIconRegistry(form, options) {
        try {
            return JSON.parse(form.dataset.iconRegistry || '{}');
        } catch (_) {
            return options?.iconRegistry || {};
        }
    }

    function resolvePreviewScale(state, mapRendering) {
        const VM = global.VehicleMarker;
        if (VM?.resolveMarkerSizeScale) {
            return Number(VM.resolveMarkerSizeScale(state, mapRendering)) || 1;
        }
        const size = String(state?.map_marker_size || '100');
        const scales = mapRendering?.marker_size_scales || {
            50: 0.5, 75: 0.75, 100: 1, 125: 1.25, 150: 1.5, 200: 2,
        };
        return Number(scales[size] ?? 1) || 1;
    }

    function resolvePreviewIconUrl(state, registry) {
        const iconMeta = registry?.icons?.[state.vehicle_type || ''];
        if (iconMeta?.url) {
            return iconMeta.url;
        }
        if (state.map_builtin_icon_path && global.BuiltinMapIcons?.urlForPath) {
            return global.BuiltinMapIcons.urlForPath(state.map_builtin_icon_path, registry);
        }
        if (state.vehicle_type && global.BuiltinMapIcons?.urlForType) {
            return global.BuiltinMapIcons.urlForType(state.vehicle_type, registry);
        }
        return '';
    }

    function applyPreviewIcon(pinEl, imgEl, url, bodyPx, rotateCss) {
        if (!pinEl) return;
        pinEl.style.width = `${bodyPx}px`;
        pinEl.style.height = `${bodyPx}px`;
        // Keep translate centering; never overwrite with rotate alone.
        pinEl.style.transform = rotateCss
            ? `translate(-50%, -50%) ${rotateCss}`
            : 'translate(-50%, -50%)';
        pinEl.style.transformOrigin = 'center center';
        if (imgEl) {
            if (url && imgEl.src !== url) {
                imgEl.src = url;
            }
            imgEl.style.transform = 'none';
        } else if (pinEl.style) {
            pinEl.style.backgroundImage = url ? `url("${url}")` : 'none';
            pinEl.style.backgroundSize = 'contain';
            pinEl.style.backgroundRepeat = 'no-repeat';
            pinEl.style.backgroundPosition = 'center center';
        }
    }

    function renderArtworkPreview(form, options, state, registry, customUrl, hasCustom, bodyPx) {
        const customWrap = form.querySelector('[data-map-art-custom]');
        const customImg = form.querySelector('[data-map-art-custom-img]');
        const defaultWrap = form.querySelector('[data-map-art-default]');
        if (!defaultWrap && !customWrap) return;

        // Unrotated — this is what nose direction must match.
        if (hasCustom && customWrap && customImg) {
            if (customWrap) customWrap.hidden = false;
            if (defaultWrap) defaultWrap.hidden = true;
            applyPreviewIcon(customWrap, customImg, customUrl, bodyPx, null);
            customImg.onerror = () => {
                const blob = form.dataset.customPreviewBlob;
                if (blob && customImg.src !== blob) {
                    customImg.src = blob;
                }
            };
        } else if (defaultWrap) {
            if (customWrap) customWrap.hidden = true;
            defaultWrap.hidden = false;
            const previewUrl = resolvePreviewIconUrl(state, registry);
            applyPreviewIcon(defaultWrap, null, previewUrl, bodyPx, null);
        }
    }

    function renderLiveMapPreview(form, options) {
        const customWrap = form.querySelector('[data-map-live-custom]');
        const customImg = form.querySelector('[data-map-live-custom-img]');
        const defaultWrap = form.querySelector('[data-map-live-default]');
        const scaleEl = form.querySelector('[data-map-live-scale]');

        const state = readState(form, options.sizeOrder);
        const registry = readIconRegistry(form, options);
        const customUrl = resolveCustomPreviewUrl(form, state);
        const hasCustom = !!(customUrl && form.dataset.customActive === '1');
        const scale = resolvePreviewScale(state, options.mapRendering);
        const bodyPx = Math.max(28, Math.round(MAP_ICON_BASE_PX * scale));
        const artPx = Math.max(40, Math.round(72 * Math.min(scale, 1.25)));
        const offset = parseOffsetDegrees(state.map_icon_rotation_offset);
        const previewAngle = state.map_icon_rotation_enabled
            ? ((PREVIEW_HEADING + offset) % 360 + 360) % 360
            : 0;
        const rotateCss = state.map_icon_rotation_enabled ? `rotate(${previewAngle}deg)` : null;

        renderArtworkPreview(form, options, state, registry, customUrl, hasCustom, artPx);

        if (defaultWrap) {
            if (hasCustom && customWrap && customImg) {
                customWrap.hidden = false;
                defaultWrap.hidden = true;
                applyPreviewIcon(customWrap, customImg, customUrl, bodyPx, rotateCss);
                customImg.onerror = () => {
                    const blob = form.dataset.customPreviewBlob;
                    if (blob && customImg.src !== blob) {
                        customImg.src = blob;
                    }
                };
            } else {
                if (customWrap) customWrap.hidden = true;
                defaultWrap.hidden = false;
                const previewUrl = resolvePreviewIconUrl(state, registry);
                applyPreviewIcon(defaultWrap, null, previewUrl, bodyPx, rotateCss);
            }
        }

        if (scaleEl) {
            const label = options.i18n?.liveScale || 'Map size: :px px (:percent%)';
            scaleEl.textContent = label
                .replace(':px', String(bodyPx))
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

    function bindIconPicker(form, options) {
        const root = form.querySelector('[data-vehicle-icon-picker]');
        const typeEl = form.querySelector('[data-map-vehicle-type]');
        const pathEl = form.querySelector('[data-map-builtin-icon-path]');
        if (!root || !typeEl || form.dataset.canChangeIcon !== '1') return;

        let registry = readIconRegistry(form, options);
        if (!registry?.icons || !registry?.categories) return;

        const RECENT_KEY = 'billx.mapIcon.recent';
        const FAV_KEY = 'billx.mapIcon.favorites';
        const readList = (key) => {
            try {
                const raw = JSON.parse(localStorage.getItem(key) || '[]');
                return Array.isArray(raw) ? raw.map(String) : [];
            } catch (_) {
                return [];
            }
        };
        const writeList = (key, list) => {
            try {
                localStorage.setItem(key, JSON.stringify(list.slice(0, 24)));
            } catch (_) { /* ignore */ }
        };

        let recent = readList(RECENT_KEY);
        let favorites = readList(FAV_KEY);
        let specialFilter = 'all'; // all | recent | favorites

        const catsEl = root.querySelector('[data-icon-categories]');
        const gridEl = root.querySelector('[data-icon-grid]');
        const searchEl = root.querySelector('[data-icon-search]');
        let activeCat = registry.categories[0]?.id || 'vehicles';
        let query = '';

        const iconList = Object.values(registry.icons);

        const matches = (icon) => {
            if (specialFilter === 'recent') {
                if (!recent.includes(icon.id)) return false;
            } else if (specialFilter === 'favorites') {
                if (!favorites.includes(icon.id)) return false;
            } else if (activeCat && icon.category !== activeCat) {
                return false;
            }
            if (!query) return true;
            const hay = `${icon.id} ${icon.label} ${(icon.tags || []).join(' ')}`.toLowerCase();
            return hay.includes(query);
        };

        const pushRecent = (id) => {
            recent = [id, ...recent.filter((x) => x !== id)].slice(0, 16);
            writeList(RECENT_KEY, recent);
        };

        const toggleFavorite = (id) => {
            if (favorites.includes(id)) {
                favorites = favorites.filter((x) => x !== id);
            } else {
                favorites = [id, ...favorites].slice(0, 24);
            }
            writeList(FAV_KEY, favorites);
            renderGrid();
        };

        const renderFilterChips = () => {
            root.querySelectorAll('[data-icon-filter]').forEach((chip) => {
                chip.classList.toggle('is-active', chip.getAttribute('data-icon-filter') === specialFilter);
            });
        };

        const renderCats = () => {
            if (!catsEl) return;
            const disabled = specialFilter !== 'all';
            catsEl.innerHTML = registry.categories.map((cat) => {
                const label = cat.label;
                const active = !disabled && cat.id === activeCat ? ' is-active' : '';
                return `<button type="button" class="vehicle-icon-picker__cat${active}" data-cat="${cat.id}" ${disabled ? 'disabled' : ''}>${label}</button>`;
            }).join('');
            catsEl.querySelectorAll('[data-cat]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    specialFilter = 'all';
                    activeCat = btn.getAttribute('data-cat');
                    renderFilterChips();
                    renderCats();
                    renderGrid();
                });
            });
        };

        const thumbFor = (icon) => icon.url || global.BuiltinMapIcons?.urlForType?.(icon.id, registry) || '';

        const sortedItems = () => {
            let items = iconList.filter(matches);
            if (specialFilter === 'recent') {
                items = recent
                    .map((id) => registry.icons[id])
                    .filter(Boolean)
                    .filter((icon) => !query || matches({ ...icon, category: icon.category }));
            }
            return items;
        };

        const renderGrid = () => {
            if (!gridEl) return;
            const selected = typeEl.value;
            const selectedPath = pathEl?.value || '';
            const items = sortedItems();
            gridEl.innerHTML = items.map((icon) => {
                const active = (icon.id === selected || (selectedPath && icon.path === selectedPath)) ? ' is-active' : '';
                const favOn = favorites.includes(icon.id) ? ' is-on' : '';
                const url = thumbFor(icon);
                return `<button type="button" class="vehicle-icon-picker__btn${active}" data-icon-id="${icon.id}" role="option" aria-selected="${active ? 'true' : 'false'}" title="${icon.label}">
                    <span class="vehicle-icon-picker__fav${favOn}" data-fav-id="${icon.id}" title="${options.i18n?.favorite || 'Favorite'}"><i class="fas fa-star"></i></span>
                    <img class="vehicle-icon-picker__thumb" src="${url}" alt="" width="60" height="60">
                    <span class="vehicle-icon-picker__name">${icon.label}</span>
                </button>`;
            }).join('') || `<div class="small text-muted p-3 text-center">${options.i18n?.noIcons || 'No icon found'}</div>`;

            gridEl.querySelectorAll('[data-icon-id]').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    if (e.target.closest('[data-fav-id]')) return;
                    const id = btn.getAttribute('data-icon-id');
                    const icon = registry.icons[id];
                    typeEl.value = id;
                    if (pathEl && icon?.path) {
                        pathEl.value = icon.path;
                    }
                    const nextOffset = String(icon?.rotation_offset ?? (icon?.shared ? -90 : 0));
                    form.dataset.rotationOffset = nextOffset;
                    const offsetSelect = form.querySelector('[data-map-rotation-offset]');
                    if (offsetSelect) offsetSelect.value = nextOffset;
                    form.dataset.customActive = '0';
                    delete form.dataset.customPreviewUrl;
                    const revertBtn = form.querySelector('[data-map-revert-custom]');
                    revertBtn?.setAttribute('hidden', 'hidden');
                    pushRecent(id);
                    renderGrid();
                    renderPreview(form, options);
                    options.onPreviewChange?.(readState(form, options.sizeOrder || DEFAULT_SIZE_ORDER));
                });
            });

            gridEl.querySelectorAll('[data-fav-id]').forEach((favBtn) => {
                favBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleFavorite(favBtn.getAttribute('data-fav-id'));
                });
            });
        };

        root.querySelectorAll('[data-icon-filter]').forEach((chip) => {
            chip.addEventListener('click', () => {
                specialFilter = chip.getAttribute('data-icon-filter') || 'all';
                renderFilterChips();
                renderCats();
                renderGrid();
            });
        });

        searchEl?.addEventListener('input', () => {
            query = (searchEl.value || '').trim().toLowerCase();
            renderGrid();
        });

        renderFilterChips();
        renderCats();
        renderGrid();
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
        const offsetEl = form.querySelector('[data-map-rotation-offset]');
        const fileEl = form.querySelector('[data-map-custom-file]');
        const revertBtn = form.querySelector('[data-map-revert-custom]');
        const backBtn = form.querySelector('[data-map-appearance-back]');
        const statusEl = form.querySelector('[data-map-save-status]');
        const uploadMax = parseInt(form.dataset.uploadMax || '256', 10) || 256;
        const registry = readIconRegistry(form, options);

        let currentSize = options.initial?.map_marker_size || '100';
        if (options.initial?.map_icon_source === 'custom' && options.initial?.map_custom_icon_url) {
            form.dataset.customActive = '1';
            form.dataset.customPreviewUrl = options.initial.map_custom_icon_url;
            revertBtn?.removeAttribute('hidden');
        }

        if (typeEl && options.initial?.vehicle_type) {
            typeEl.value = options.initial.vehicle_type;
        }
        const pathEl = form.querySelector('[data-map-builtin-icon-path]');
        if (pathEl && options.initial?.map_builtin_icon_path) {
            pathEl.value = options.initial.map_builtin_icon_path;
        } else if (pathEl && options.initial?.vehicle_type && registry?.icons?.[options.initial.vehicle_type]?.path) {
            pathEl.value = registry.icons[options.initial.vehicle_type].path;
        }
        if (rotationEl && options.initial?.map_icon_rotation_enabled === false) {
            rotationEl.checked = false;
        }
        if (offsetEl && options.initial?.map_icon_rotation_offset != null) {
            offsetEl.value = String(options.initial.map_icon_rotation_offset);
            form.dataset.rotationOffset = String(options.initial.map_icon_rotation_offset);
        }

        bindIconPicker(form, options);

        renderSize(form, currentSize, options);
        renderPreview(form, options);

        const onSizeChange = (size) => {
            currentSize = size;
            renderSize(form, currentSize, options);
            renderPreview(form, options);
            options.onPreviewChange?.(readState(form, sizes));
        };

        typeEl?.addEventListener('change', () => {
            form.dataset.customActive = '0';
            delete form.dataset.customPreviewUrl;
            revokePreviewBlob(form);
            revertBtn?.setAttribute('hidden', 'hidden');
            renderPreview(form, options);
            options.onPreviewChange?.(readState(form, sizes));
        });

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
        offsetEl?.addEventListener('change', () => {
            form.dataset.rotationOffset = String(offsetEl.value ?? 0);
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
            if (!file) return;
            if (!options.uploadUrl) {
                const msg = options.i18n?.uploadUnavailable || options.i18n?.failed || 'Custom upload is not available.';
                if (statusEl) statusEl.textContent = msg;
                options.onError?.(msg);
                return;
            }
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
                // Clear library selection highlight — custom upload is the active icon.
                form.querySelectorAll('.vehicle-icon-picker__btn.is-active').forEach((btn) => {
                    btn.classList.remove('is-active');
                    btn.setAttribute('aria-selected', 'false');
                });
                renderPreview(form, options);

                body.append('icon', prepared);
                const offsetEl = form.querySelector('[data-map-rotation-offset]');
                if (offsetEl?.value != null && offsetEl.value !== '') {
                    body.append('map_icon_rotation_offset', String(offsetEl.value));
                }
                const deviceIds = typeof options.getDeviceIds === 'function' ? options.getDeviceIds() : null;
                if (Array.isArray(deviceIds)) {
                    if (deviceIds.length === 0) {
                        throw new Error(options.i18n?.selectVehicles || 'Select at least one vehicle.');
                    }
                    deviceIds.forEach((id) => body.append('device_ids[]', String(id)));
                }
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
                    throw new Error(data.message || options.i18n?.failed || 'Upload failed');
                }
                const appearance = data.appearance || {};
                if (appearance.map_custom_icon_url) {
                    form.dataset.customPreviewUrl = appearance.map_custom_icon_url;
                }
                form.dataset.customActive = '1';
                showResizeNotice(form, appearance.upload_meta || data.upload_meta, options);
                options.onSaved?.(appearance, data);
                renderPreview(form, options);
                if (statusEl) statusEl.textContent = data.message || options.i18n?.uploaded || 'Uploaded';
            } catch (err) {
                const msg = err.message || options.i18n?.failed || 'Failed';
                if (statusEl) statusEl.textContent = msg;
                options.onError?.(msg);
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
            const payload = {};
            const customActive = form.dataset.customActive === '1';

            // Custom upload is already stored by the upload endpoint.
            // Do NOT re-send library vehicle_type on Save — that used to wipe the custom icon.
            if (customActive) {
                payload.map_marker_style = 'body';
                payload.map_icon_rotation_offset = state.map_icon_rotation_offset ?? 0;
            } else if (form.dataset.canChangeIcon === '1' || form.querySelector('[data-map-vehicle-type]:not([type="hidden"])')) {
                payload.vehicle_type = state.vehicle_type;
                payload.map_builtin_icon_path = state.map_builtin_icon_path
                    || registry?.icons?.[state.vehicle_type]?.path
                    || null;
                payload.map_icon_source = 'default';
                payload.map_marker_style = 'body';
                payload.map_icon_rotation_offset = state.map_icon_rotation_offset ?? 0;
            }
            if (form.querySelector('[data-map-size-range]') || form.querySelector('[data-map-size-value]')) {
                payload.map_marker_size = state.map_marker_size;
                payload.map_icon_rotation_enabled = state.map_icon_rotation_enabled;
            }

            const btn = form.querySelector('[type="submit"]');
            btn?.setAttribute('disabled', 'disabled');
            if (statusEl) statusEl.textContent = '';

            try {
                if (typeof options.getDeviceIds === 'function') {
                    const deviceIds = options.getDeviceIds();
                    if (!Array.isArray(deviceIds) || deviceIds.length === 0) {
                        throw new Error(options.i18n?.selectVehicles || 'Select at least one vehicle.');
                    }
                    payload.device_ids = deviceIds;
                }

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
                options.onSaved?.(appearance, data);
                renderPreview(form, options);
                if (statusEl) {
                    statusEl.textContent = data.message || options.i18n?.saved || 'Saved';
                }
            } catch (err) {
                const msg = err.message || options.i18n?.failed || 'Failed';
                if (statusEl) {
                    statusEl.textContent = msg;
                }
                options.onError?.(msg);
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
