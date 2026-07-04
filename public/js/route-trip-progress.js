(function (global) {
    'use strict';

    class RouteTripProgress {
        constructor(options) {
            this.opts = options;
            this.state = null;
            this.expanded = false;
            this.routePolyline = null;
            this.adminPolyline = null;
            this.assignedPolyline = null;
            this.actualPolyline = null;
            this.joinPolyline = null;
            this.navigationPolyline = null;
            this.checkpointMarkers = [];
            this.directionsRequestId = 0;
            this.polylineResolveId = 0;
            this.alertedMilestones = new Set();
            this.tripId = null;
            this.seedMilestonesOnNextUpdate = false;
            this.displayPos = 0;
            this.lastProgressKey = null;
            this.el = document.getElementById(options.containerId || 'routeTripProgressBar');
            if (options.compactFooter && this.el) {
                this.el.classList.add('route-trip-bar--footer');
            }
        }

        isCompactFooter() {
            return !!this.opts.compactFooter;
        }

        buildCompactStatsHtml(i18n, traveledKm, remainingKm, progress, arrivalTime) {
            return `<div class="route-trip-bar__stats route-trip-bar__stats--compact">
                <div class="route-trip-bar__stat">
                    <span class="route-trip-bar__stat-label">${escapeHtml(i18n.traveled || 'Traveled')}</span>
                    <span class="route-trip-bar__stat-value">${traveledKm.toFixed(1)} km</span>
                </div>
                <div class="route-trip-bar__stat">
                    <span class="route-trip-bar__stat-label">${escapeHtml(i18n.remaining || 'Remaining')}</span>
                    <span class="route-trip-bar__stat-value">${remainingKm.toFixed(1)} km</span>
                </div>
                <div class="route-trip-bar__stat">
                    <span class="route-trip-bar__stat-label">${escapeHtml(i18n.eta || 'ETA')}</span>
                    <span class="route-trip-bar__stat-value">${escapeHtml(progress.eta_human || '—')}</span>
                </div>
                <div class="route-trip-bar__stat">
                    <span class="route-trip-bar__stat-label">${escapeHtml(i18n.arrivalTime || 'Arrival')}</span>
                    <span class="route-trip-bar__stat-value">${escapeHtml(arrivalTime)}</span>
                </div>
            </div>`;
        }

        update(payload) {
            const next = payload?.route_trip || payload || null;
            const nextTripId = next?.trip?.id ?? null;
            if (nextTripId !== this.tripId) {
                this.alertedMilestones.clear();
                this.tripId = nextTripId;
                this.seedMilestonesOnNextUpdate = true;
                this.displayPos = 0;
                this.lastProgressKey = null;
            }
            this.state = next;
            this.renderBar();
            if (this.canShowPolyline()) {
                this.renderPolyline();
            } else {
                this.clearPolylines();
            }

            const milestones = this.state?.progress?.milestones || [];
            if (this.seedMilestonesOnNextUpdate) {
                milestones.forEach((m) => {
                    if (m.status === 'reached') {
                        this.alertedMilestones.add(String(m.id || m.label || ''));
                    }
                });
                this.seedMilestonesOnNextUpdate = false;
            } else {
                this.checkMilestoneAlerts(milestones);
            }
        }

        clear() {
            this.state = null;
            this.expanded = false;
            this.alertedMilestones.clear();
            this.tripId = null;
            this.seedMilestonesOnNextUpdate = false;
            this.displayPos = 0;
            this.lastProgressKey = null;
            if (this.el) {
                this.el.hidden = true;
                this.el.classList.remove('is-expanded');
            }
            this.routePolyline?.setMap(null);
            this.navigationPolyline?.setMap(null);
            this.actualPolyline?.setMap(null);
            this.joinPolyline?.setMap(null);
            this.adminPolyline?.setMap(null);
            this.assignedPolyline?.setMap(null);
            this.checkpointMarkers.forEach((marker) => marker.setMap(null));
            this.checkpointMarkers = [];
        }

        toggleExpanded() {
            this.expanded = !this.expanded;
            this.el?.classList.toggle('is-expanded', this.expanded);
        }

        sanitizeCityLabel(label) {
            const text = String(label || '').trim();
            if (!text) return '';
            if (/^[23456789CFGHJMPQRVWX]{4,8}\+[23456789CFGHJMPQRVWX]{2,3}$/i.test(text)) {
                return '';
            }
            const stripped = text.replace(/^[23456789CFGHJMPQRVWX]{4,8}\+[23456789CFGHJMPQRVWX]{2,3}\s*/i, '').trim();
            return (stripped || text).split(',')[0].trim() || text;
        }

        resolveProgressMetrics(progress, route) {
            const navState = progress.navigation_state || 'on_assigned';
            const offRoute = navState === 'off_route';
            const frozen = progress.progress_frozen === true;
            const waitingForStart = progress.waiting_for_start === true || progress.progress_waiting_for_start === true;
            const counting = !waitingForStart && progress.progress_counting !== false && !offRoute;
            const isLive = progress.position_is_live !== false;
            const atDestination = navState === 'destination_reached' || progress.at_destination === true;

            let totalKm = Number(progress.expected_distance_km ?? route.guided_distance_km ?? route.expected_distance_km ?? 0);
            let traveledKm = Number(progress.actual_distance_km ?? progress.distance_travelled_km ?? 0);
            let corridorKm = Number(progress.corridor_progress_km ?? progress.distance_travelled_km ?? 0);
            let remainingKm = Number(progress.remaining_distance_km ?? 0);
            let pct = Number(progress.percentage_completed ?? 0);
            let pos = Number(progress.progress_bar_position ?? pct / 100) * 100;
            const extraKm = Number(progress.extra_distance_km ?? Math.max(0, traveledKm - totalKm));

            if (!counting && !frozen) {
                traveledKm = 0;
                corridorKm = 0;
                pct = 0;
                pos = 0;
                remainingKm = totalKm > 0 ? totalKm : remainingKm;
            } else if (totalKm > 0) {
                pct = Math.round((corridorKm / totalKm) * 100);
                pos = Math.max(0, Math.min(100, (corridorKm / totalKm) * 100));
                remainingKm = Math.max(0, remainingKm);
            } else {
                pct = Math.round(pct);
                pos = Math.max(0, Math.min(100, pos));
            }

            if (atDestination && counting) {
                pct = 100;
                pos = 100;
                remainingKm = 0;
                if (totalKm > 0) traveledKm = totalKm;
            }

            return {
                offRoute,
                frozen,
                waitingForStart,
                unreliable: !waitingForStart && !counting && !frozen,
                counting,
                navState,
                isLive,
                atDestination,
                pct,
                pos,
                traveledKm,
                remainingKm,
                totalKm,
                extraKm,
                corridorKm,
                speed: Number(progress.current_speed_kmh ?? 0),
            };
        }

        navigationBadge(navState, i18n) {
            const labels = {
                on_assigned: i18n.navOnAssigned || 'On assigned route',
                joining_assigned: i18n.navJoining || 'Joining route',
                route_recalculated: i18n.navRecalculated || 'Route recalculated',
                slight_deviation: i18n.navSlightDeviation || 'Slight deviation',
                off_route: i18n.offRouteBadge || 'Off route',
                gps_lost: i18n.navGpsLost || 'GPS lost',
                destination_reached: i18n.navDestinationReached || 'Destination reached',
            };
            const icons = {
                on_assigned: '🟢',
                joining_assigned: '🔵',
                route_recalculated: '🟡',
                slight_deviation: '🟠',
                off_route: '🔴',
                gps_lost: '⚫',
                destination_reached: '✅',
            };
            const state = labels[navState] ? navState : 'on_assigned';
            return {
                text: `${icons[state] || ''} ${labels[state] || labels.on_assigned}`.trim(),
                className: `route-trip-bar__nav-badge route-trip-bar__nav-badge--${state}`,
            };
        }

        waitingBadge(i18n) {
            return {
                text: i18n.waitingForStart || 'Waiting for start area',
                className: 'route-trip-bar__nav-badge route-trip-bar__nav-badge--joining_assigned',
            };
        }

        renderBar() {
            if (!this.el) return;
            const data = this.state;
            if (!data?.route?.show_progress_bar) {
                this.el.hidden = true;
                return;
            }

            const i18n = this.opts.i18n || {};
            const trip = data.trip || {};

            if (!data.trip_mode_active) {
                this.el.hidden = false;
                this.el.classList.remove('is-expanded');
                const route = data.route;
                const actionBtns = [];
                if (trip.can_start_trip || trip.can_start_new) {
                    actionBtns.push(`<button type="button" class="btn btn-sm btn-primary route-trip-start-btn">${escapeHtml(i18n.startTrip || i18n.startNew || 'Start trip')}</button>`);
                }
                this.el.innerHTML = `
                    <div class="route-trip-bar__card route-trip-bar__card--idle">
                        <div class="route-trip-bar__compact">
                            <div class="route-trip-bar__head">
                                <span class="route-trip-bar__head-title">${escapeHtml(i18n.assignedRoute || 'Assigned route')}</span>
                            </div>
                            <div class="route-trip-bar__hint">${escapeHtml(route.start_city || '')} → ${escapeHtml(route.destination_city || '')}</div>
                            <div class="route-trip-bar__hint route-trip-bar__hint--muted">${escapeHtml(i18n.startTripHint || 'Press Start Trip to begin navigation and progress tracking.')}</div>
                            ${actionBtns.length ? `<div class="route-trip-bar__actions">${actionBtns.join('')}</div>` : ''}
                        </div>
                    </div>`;
                this.el.querySelector('.route-trip-start-btn')?.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.opts.onStartNew?.();
                });
                return;
            }

            this.el.hidden = false;
            this.el.classList.toggle('is-expanded', this.expanded);

            const route = data.route;
            const progress = data.progress || {};
            const breakdown = progress.segment_breakdown || {};
            const metrics = this.resolveProgressMetrics(progress, route);
            const {
                offRoute, frozen, waitingForStart, unreliable, navState, isLive, atDestination,
                pct, pos, traveledKm, remainingKm, totalKm, extraKm, speed,
            } = metrics;
            const navBadge = waitingForStart ? this.waitingBadge(i18n) : this.navigationBadge(navState, i18n);

            const pctHint = !isLive
                ? (progress.progress_stale_label || i18n.progressStale || 'Last known position')
                : (waitingForStart
                    ? (progress.progress_unreliable_label || i18n.waitingForStartHint || 'Progress will start automatically when the vehicle enters the start area.')
                    : (frozen
                    ? (progress.progress_frozen_label || i18n.progressFrozen || 'Last on-route')
                    : (unreliable ? (progress.progress_unreliable_label || i18n.progressOffRoute || 'On route only') : '')));
            const suggestedLabel = progress.suggested_route_label || i18n.suggestedRoute || '';
            const navRemainingKm = progress.navigation_remaining_km;
            const suggestedHint = suggestedLabel && navRemainingKm != null
                ? `${suggestedLabel} · ${Number(navRemainingKm).toFixed(1)} km`
                : suggestedLabel;
            const manageUrl = this.opts.manageRoutesUrl;
            const hint = route.assigned_hint || i18n.assignedHint || '';
            const durationMin = progress.expected_duration_minutes ?? route.expected_duration_minutes;
            const elapsedMin = breakdown.elapsed_minutes;
            const consolidatedExpected = breakdown.expected_minutes ?? durationMin;

            const currentCity = this.sanitizeCityLabel(
                progress.current_city
                || (progress.milestones || []).find((m) => m.status === 'current')?.label
                || route.start_city,
            ) || '—';
            const nextCityRaw = atDestination
                ? null
                : (progress.next_city || progress.next_checkpoint_label);
            const nextCity = this.sanitizeCityLabel(nextCityRaw) || (atDestination ? '—' : '—');
            const arrivalTime = progress.estimated_arrival_time || progress.eta_human || '—';

            const progressKey = `${pct}|${pos}|${traveledKm}|${remainingKm}|${navState}|${isLive}`;
            const shouldAnimate = isLive && speed > 0 && this.lastProgressKey !== progressKey;
            if (!shouldAnimate) {
                this.displayPos = pos;
            } else {
                this.displayPos += (pos - this.displayPos) * 0.35;
            }
            this.lastProgressKey = progressKey;
            const animPos = (!isLive || speed <= 0) ? pos : (
                Math.abs(pos - this.displayPos) < 0.05 ? pos : this.displayPos
            );

            const pctClass = offRoute
                ? (frozen ? 'route-trip-bar__pct--frozen' : 'route-trip-bar__pct--warn')
                : '';
            const fillClass = frozen
                ? 'route-trip-bar__line-fill--frozen'
                : (unreliable ? 'route-trip-bar__line-fill--warn' : '');

            const actionBtns = [];
            if (data.trip?.can_complete) {
                actionBtns.push(`<button type="button" class="btn btn-sm btn-success route-trip-complete-btn">${escapeHtml(i18n.complete || 'Complete')}</button>`);
            }
            if (data.trip?.can_restart_trip) {
                actionBtns.push(`<button type="button" class="btn btn-sm btn-warning route-trip-restart-btn">${escapeHtml(i18n.restartTrip || 'Restart trip')}</button>`);
            }
            if (data.trip?.can_start_new) {
                actionBtns.push(`<button type="button" class="btn btn-sm btn-primary route-trip-start-btn">${escapeHtml(i18n.startNew || 'Start new trip')}</button>`);
            }

            const segmentHtml = (breakdown.segments || []).map((seg) => {
                const actual = seg.actual_minutes != null
                    ? `${seg.actual_minutes} ${i18n.minAbbr || 'min'}`
                    : (i18n.pending || '—');
                const expected = seg.expected_minutes
                    ? ` / ${seg.expected_minutes} ${i18n.minAbbr || 'min'}`
                    : '';
                return `<div class="route-trip-bar__segment route-trip-bar__segment--${escapeHtml(seg.status || 'pending')}">
                    <span class="route-trip-bar__segment-seq">${seg.sequence}</span>
                    <span class="route-trip-bar__segment-label">${escapeHtml(seg.label || '')}</span>
                    <span class="route-trip-bar__segment-time">${escapeHtml(actual)}${escapeHtml(expected)}</span>
                </div>`;
            }).join('');

            const consolidatedHtml = (elapsedMin != null || consolidatedExpected)
                ? `<div class="route-trip-bar__consolidated">
                    ${elapsedMin != null ? `<span>${escapeHtml(i18n.elapsed || 'Elapsed')}: ${elapsedMin} ${escapeHtml(i18n.minAbbr || 'min')}</span>` : ''}
                    ${consolidatedExpected ? `<span>${escapeHtml(i18n.planned || 'Planned')}: ${consolidatedExpected} ${escapeHtml(i18n.minAbbr || 'min')}</span>` : ''}
                    ${breakdown.checkpoint_minutes_total != null ? `<span>${escapeHtml(i18n.checkpointTotal || 'Checkpoints')}: ${breakdown.checkpoint_minutes_total} ${escapeHtml(i18n.minAbbr || 'min')}</span>` : ''}
                   </div>`
                : '';

            const milestones = this.prepareMilestones(progress.milestones || this.buildMilestonesFromRoute(route), route);
            const routeLineHtml = this.buildRouteLine(milestones, animPos, fillClass, frozen || !isLive);
            const latestReached = [...milestones].reverse().find((m) => m.status === 'reached');

            const speedText = speed != null && Number(speed) >= 0
                ? `${Math.round(Number(speed))} ${i18n.kmhUnit || 'km/h'}`
                : '—';

            const compactFooter = this.isCompactFooter();
            const statsHtml = compactFooter
                ? this.buildCompactStatsHtml(i18n, traveledKm, remainingKm, progress, arrivalTime)
                : `<div class="route-trip-bar__stats">
                                <div class="route-trip-bar__stat">
                                    <span class="route-trip-bar__stat-label">${escapeHtml(i18n.traveled || 'Traveled')}</span>
                                    <span class="route-trip-bar__stat-value">${traveledKm.toFixed(1)} km</span>
                                </div>
                                <div class="route-trip-bar__stat">
                                    <span class="route-trip-bar__stat-label">${escapeHtml(i18n.remaining || 'Remaining')}</span>
                                    <span class="route-trip-bar__stat-value">${remainingKm.toFixed(1)} km</span>
                                </div>
                                <div class="route-trip-bar__stat">
                                    <span class="route-trip-bar__stat-label">${escapeHtml(i18n.eta || 'ETA')}</span>
                                    <span class="route-trip-bar__stat-value">${escapeHtml(progress.eta_human || '—')}</span>
                                </div>
                                <div class="route-trip-bar__stat">
                                    <span class="route-trip-bar__stat-label">${escapeHtml(i18n.arrivalTime || 'Arrival')}</span>
                                    <span class="route-trip-bar__stat-value">${escapeHtml(arrivalTime)}</span>
                                </div>
                                <div class="route-trip-bar__stat">
                                    <span class="route-trip-bar__stat-label">${escapeHtml(i18n.expectedDistance || 'Expected')}</span>
                                    <span class="route-trip-bar__stat-value">${totalKm.toFixed(1)} km</span>
                                </div>
                                <div class="route-trip-bar__stat">
                                    <span class="route-trip-bar__stat-label">${escapeHtml(i18n.extraDistance || 'Extra')}</span>
                                    <span class="route-trip-bar__stat-value">${extraKm > 0 ? '+' : ''}${extraKm.toFixed(1)} km</span>
                                </div>
                                <div class="route-trip-bar__stat">
                                    <span class="route-trip-bar__stat-label">${escapeHtml(i18n.avgSpeed || 'Avg speed')}</span>
                                    <span class="route-trip-bar__stat-value">${Number(progress.avg_speed_kmh ?? 0).toFixed(0)} ${i18n.kmhUnit || 'km/h'}</span>
                                </div>
                                <div class="route-trip-bar__stat">
                                    <span class="route-trip-bar__stat-label">${escapeHtml(i18n.currentCity || 'Current city')}</span>
                                    <span class="route-trip-bar__stat-value route-trip-bar__stat-value--muted">${escapeHtml(currentCity)}</span>
                                </div>
                                <div class="route-trip-bar__stat">
                                    <span class="route-trip-bar__stat-label">${escapeHtml(i18n.nextCity || 'Next city')}</span>
                                    <span class="route-trip-bar__stat-value route-trip-bar__stat-value--muted">${escapeHtml(nextCity)}</span>
                                </div>
                            </div>`;

            this.el.innerHTML = `
                <div class="route-trip-bar__card">
                    <div class="route-trip-bar__compact" role="button" tabindex="0" aria-expanded="${this.expanded ? 'true' : 'false'}">
                        <div class="route-trip-bar__compact-main">
                            <div class="route-trip-bar__head">
                                <span class="route-trip-bar__head-title">${escapeHtml(i18n.progressTitle || 'Route progress')}</span>
                                <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                                    <span class="${navBadge.className}">${escapeHtml(navBadge.text)}</span>
                                    <span class="route-trip-bar__pct ${pctClass}">${pct}%</span>
                                </div>
                            </div>
                            ${!compactFooter && pctHint ? `<div class="route-trip-bar__compact-hint">${escapeHtml(pctHint)}</div>` : ''}
                            ${!compactFooter && suggestedHint ? `<div class="route-trip-bar__compact-hint route-trip-bar__compact-hint--nav">${escapeHtml(suggestedHint)}</div>` : ''}
                            ${compactFooter ? `${routeLineHtml}${statsHtml}` : `${statsHtml}${routeLineHtml}`}
                        </div>
                        <button type="button" class="route-trip-bar__chevron" aria-label="${escapeHtml(i18n.toggleDetails || 'Toggle details')}">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="route-trip-bar__details">
                        <div class="route-trip-bar__details-inner">
                            ${hint ? `<div class="route-trip-bar__hint">${manageUrl
                                ? `<a href="${escapeHtml(manageUrl)}" class="route-trip-bar__manage-link">${escapeHtml(i18n.manageRoutes || 'Manage routes')}</a> · `
                                : ''}${escapeHtml(hint)}</div>` : ''}
                            ${offRoute ? `<div class="route-trip-bar__alert" role="alert"><i class="fas fa-triangle-exclamation"></i> ${escapeHtml(progress.rejoin_route_hint || i18n.offRoute || 'You are not on the assigned route')}</div>` : ''}
                            ${latestReached ? `<div class="route-trip-bar__success" role="status"><i class="fas fa-circle-check"></i> ${escapeHtml(this.milestoneAlertMessage(latestReached, i18n))}</div>` : ''}
                            <div class="route-trip-bar__meta">
                                <span>${escapeHtml(progress.current_segment_label || '')}</span>
                                <span>${Number(progress.remaining_distance_km ?? 0).toFixed(1)} km ${escapeHtml(i18n.remaining || 'left')}</span>
                                <span>${escapeHtml(i18n.eta || 'ETA')}: ${escapeHtml(progress.eta_human || '—')}</span>
                                ${data.trip?.status_label ? `<span>${escapeHtml(data.trip.status_label)}</span>` : ''}
                            </div>
                            ${segmentHtml ? `<div class="route-trip-bar__segments">${segmentHtml}</div>` : ''}
                            ${consolidatedHtml}
                            ${actionBtns.length ? `<div class="route-trip-bar__actions">${actionBtns.join('')}</div>` : ''}
                        </div>
                    </div>
                </div>`;

            const compactEl = this.el.querySelector('.route-trip-bar__compact');
            const chevron = this.el.querySelector('.route-trip-bar__chevron');
            const toggle = (e) => {
                if (e?.target?.closest?.('.route-trip-bar__actions') || e?.target?.closest?.('a')) return;
                if (e?.target?.closest?.('.route-trip-complete-btn') || e?.target?.closest?.('.route-trip-start-btn')) return;
                this.toggleExpanded();
                compactEl?.setAttribute('aria-expanded', this.expanded ? 'true' : 'false');
            };
            compactEl?.addEventListener('click', toggle);
            compactEl?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggle(e);
                }
            });
            chevron?.addEventListener('click', (e) => {
                e.stopPropagation();
                toggle(e);
            });

            this.el.querySelector('.route-trip-restart-btn')?.addEventListener('click', (e) => {
                e.stopPropagation();
                this.opts.onRestart?.();
            });
            this.el.querySelector('.route-trip-complete-btn')?.addEventListener('click', (e) => {
                e.stopPropagation();
                this.opts.onComplete?.();
            });
            this.el.querySelector('.route-trip-start-btn')?.addEventListener('click', (e) => {
                e.stopPropagation();
                this.opts.onStartNew?.();
            });
        }

        prepareMilestones(milestones, route) {
            const total = milestones.length;
            return milestones.map((m, index) => {
                let positionPct = Number(m.position_pct);
                if (!Number.isFinite(positionPct)) {
                    if (m.kind === 'start') positionPct = 0;
                    else if (m.kind === 'destination') positionPct = 100;
                    else positionPct = total > 2 ? (index / (total - 1)) * 100 : 50;
                }
                const label = this.sanitizeCityLabel(m.label)
                    || (m.kind === 'start' ? this.sanitizeCityLabel(route.start_city) : '')
                    || (m.kind === 'destination' ? this.sanitizeCityLabel(route.destination_city) : '')
                    || m.label
                    || '';
                return { ...m, label, position_pct: Math.max(0, Math.min(100, positionPct)) };
            });
        }

        buildMilestonesFromRoute(route) {
            const items = [{
                id: 'start',
                kind: 'start',
                label: route.start_city || '',
                status: 'current',
                position_pct: 0,
            }];
            const cps = (route.checkpoints || []).filter((cp) => !cp.kind || cp.kind === 'checkpoint');
            cps.forEach((cp, index) => {
                items.push({
                    id: `checkpoint-${cp.sequence || index + 1}`,
                    kind: 'checkpoint',
                    label: cp.label || '',
                    status: 'pending',
                    position_pct: cps.length > 0 ? ((index + 1) / (cps.length + 1)) * 100 : 50,
                });
            });
            items.push({
                id: 'destination',
                kind: 'destination',
                label: route.destination_city || '',
                status: 'pending',
                position_pct: 100,
            });
            return items;
        }

        buildRouteLine(milestones, pos, fillClass, frozen) {
            const markers = milestones.map((m) => {
                const kindClass = m.kind ? ` route-trip-bar__marker--${m.kind}` : '';
                const stateClass = m.status ? ` route-trip-bar__marker--${m.status}` : '';
                const left = m.kind === 'start' ? 0 : (m.kind === 'destination' ? 100 : m.position_pct);
                const style = m.kind === 'start' || m.kind === 'destination'
                    ? ''
                    : `style="left:${left}%"`;
                const kindAttr = m.kind === 'start'
                    ? 'style="left:0%"'
                    : (m.kind === 'destination' ? 'style="left:100%"' : style);
                return `<div class="route-trip-bar__marker${kindClass}${stateClass}" ${kindAttr} data-milestone="${escapeHtml(m.id)}">
                    <span class="route-trip-bar__dot" aria-hidden="true"></span>
                    <span class="route-trip-bar__label">${escapeHtml(m.label || '')}</span>
                </div>`;
            }).join('');

            const vehicleClass = frozen ? ' route-trip-bar__vehicle--frozen' : '';

            return `<div class="route-trip-bar__route-line" aria-label="Route progress">
                <div class="route-trip-bar__line-bg" aria-hidden="true"></div>
                <div class="route-trip-bar__line-fill ${fillClass}" style="width:${pos}%" aria-hidden="true"></div>
                <div class="route-trip-bar__markers">${markers}</div>
                <div class="route-trip-bar__vehicle${vehicleClass}" style="left:${pos}%" aria-hidden="true">🚌</div>
            </div>`;
        }

        milestoneAlertMessage(milestone, i18n) {
            const city = milestone.label || '';
            if (milestone.kind === 'start') {
                return (i18n.reachedStart || 'Trip started — departed from {city}').replace('{city}', city);
            }
            if (milestone.kind === 'destination') {
                return (i18n.reachedDestination || 'Reached destination: {city}').replace('{city}', city);
            }
            return (i18n.reachedCheckpoint || 'Reached checkpoint: {city}').replace('{city}', city);
        }

        checkMilestoneAlerts(milestones) {
            milestones.forEach((milestone) => {
                if (milestone.status !== 'reached') return;
                const key = String(milestone.id || milestone.label || '');
                if (!key || this.alertedMilestones.has(key)) return;
                this.alertedMilestones.add(key);
                const i18n = this.opts.i18n || {};
                const message = this.milestoneAlertMessage(milestone, i18n);
                if (typeof this.opts.onMilestoneReached === 'function') {
                    this.opts.onMilestoneReached(milestone, message);
                    return;
                }
                if (global.Swal) {
                    global.Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: message,
                        timer: 4500,
                        showConfirmButton: false,
                    });
                }
            });
        }

        normalizePath(points) {
            return (points || []).map((p) => ({
                lat: parseFloat(p.lat),
                lng: parseFloat(p.lng),
            })).filter((p) => Number.isFinite(p.lat) && Number.isFinite(p.lng));
        }

        canShowPolyline() {
            const flag = this.opts.showPolyline;
            if (typeof flag === 'function') {
                if (!flag()) return false;
            } else if (flag === false) {
                return false;
            }
            const route = this.state?.route;
            if (route && route.show_polyline === false) {
                return false;
            }
            return true;
        }

        isStraightFallbackPath(route, guided, adminPath) {
            if (route.is_road_polyline || route.has_stored_polyline) {
                return false;
            }
            const checkpoints = route.checkpoints || [];
            const waypointCount = checkpoints.filter((cp) => cp.kind === 'checkpoint').length;
            const expectedStraight = waypointCount + 2;
            if (guided.length > 0 && guided.length <= expectedStraight + 1) {
                return true;
            }
            if (adminPath.length >= 2 && guided.length === adminPath.length) {
                return true;
            }
            return guided.length > 0 && guided.length < 10;
        }

        routeEndpoints(route) {
            const checkpoints = route.checkpoints || [];
            const start = checkpoints.find((cp) => cp.kind === 'start');
            const dest = checkpoints.find((cp) => cp.kind === 'destination');
            if (start && dest) {
                return { origin: start, dest };
            }
            const origin = route.guided_polyline?.[0]
                || route.admin_polyline?.[0]
                || route.polyline?.[0];
            const destination = route.guided_polyline?.slice(-1)[0]
                || route.admin_polyline?.slice(-1)[0]
                || route.polyline?.slice(-1)[0];
            return { origin, dest };
        }

        renderPolyline() {
            if (!this.canShowPolyline()) {
                this.clearPolylines();
                return;
            }
            const map = this.opts.getMap?.();
            const google = this.opts.googleMaps || global.google;
            const data = this.state;
            if (!map || !google?.maps || !data?.route) {
                this.clearPolylines();
                return;
            }

            const route = data.route;
            const assigned = this.normalizePath(
                route.assigned_polyline || route.guided_polyline || route.polyline || route.admin_polyline,
            );
            const adminPath = this.normalizePath(route.admin_polyline);

            const drawAssignedPrimary = (path) => {
                if (!path || path.length < 2) return;
                this.drawPath(path, '#0ea5e9', 5, 0.9, 'navigation');
                this.fitMapToPath(path);
            };

            if (!data.trip_mode_active) {
                this.joinPolyline?.setMap(null);
                this.actualPolyline?.setMap(null);
                this.assignedPolyline?.setMap(null);
                this.adminPolyline?.setMap(null);

                if (assigned.length >= 2) {
                    drawAssignedPrimary(assigned);
                } else {
                    const snap = this.state;
                    const requestId = ++this.polylineResolveId;
                    this.resolveDrivingPath(route, assigned, adminPath).then((path) => {
                        if (requestId !== this.polylineResolveId || this.state !== snap) return;
                        if (path?.length >= 2) {
                            drawAssignedPrimary(path);
                        } else {
                            this.navigationPolyline?.setMap(null);
                        }
                    });
                }

                this.renderCheckpoints(map, google, route.checkpoints || []);
                return;
            }

            const actual = this.normalizePath(route.actual_polyline || []);
            const navigation = this.normalizePath(route.navigation_polyline || route.dynamic_polyline || []);
            const join = this.normalizePath(route.join_polyline || []);

            this.adminPolyline?.setMap(null);

            if (assigned.length >= 2) {
                this.drawAssignedOverlay(assigned, map, google, {
                    strokeColor: '#0ea5e9',
                    strokeOpacity: 0.45,
                    strokeWeight: 5,
                });
            } else {
                this.assignedPolyline?.setMap(null);
            }
            if (join.length >= 2) {
                this.drawPath(join, '#6366f1', 5, 0.85, 'join');
            } else {
                this.joinPolyline?.setMap(null);
            }
            if (actual.length >= 2) {
                this.drawPath(actual, '#22c55e', 4, 0.85, 'actual');
            } else {
                this.actualPolyline?.setMap(null);
            }
            if (navigation.length >= 2) {
                this.drawPath(navigation, '#f59e0b', 6, 0.95, 'navigation');
            } else {
                this.navigationPolyline?.setMap(null);
                this.ensureSuggestedNavigationRoute(route, assigned, adminPath);
            }

            const fitPath = [];
            if (join.length >= 2) fitPath.push(...join);
            if (navigation.length >= 2) fitPath.push(...navigation);
            else if (assigned.length >= 2) fitPath.push(...assigned);
            if (fitPath.length >= 2) {
                this.fitMapToPath(fitPath);
            }

            this.renderCheckpoints(map, google, route.checkpoints || []);
        }

        fitMapToPath(path) {
            const map = this.opts.getMap?.();
            const google = this.opts.googleMaps || global.google;
            if (!map || !google?.maps || !path || path.length < 2) return;
            if (typeof this.opts.shouldFitRouteBounds === 'function' && !this.opts.shouldFitRouteBounds()) {
                return;
            }
            const bounds = new google.maps.LatLngBounds();
            path.forEach((p) => bounds.extend(p));
            const vehicle = this.opts.getVehiclePosition?.();
            if (vehicle && Number.isFinite(vehicle.lat) && Number.isFinite(vehicle.lng)) {
                bounds.extend(vehicle);
            }
            this.opts.onBeforeRouteBoundsFit?.();
            map.fitBounds(bounds, { top: 72, right: 40, bottom: 140, left: 40 });
            if (typeof this.opts.onRouteBoundsFitted === 'function') {
                this.opts.onRouteBoundsFitted();
            }
        }

        drawAssignedOverlay(path, map, google, style = {}) {
            if (!map || !google?.maps) return;
            if (!this.assignedPolyline) {
                this.assignedPolyline = new google.maps.Polyline({
                    map,
                    strokeColor: style.strokeColor || '#64748b',
                    strokeOpacity: style.strokeOpacity ?? 0.55,
                    strokeWeight: style.strokeWeight ?? 4,
                    zIndex: 190,
                });
            } else {
                this.assignedPolyline.setOptions({
                    strokeColor: style.strokeColor || '#64748b',
                    strokeOpacity: style.strokeOpacity ?? 0.55,
                    strokeWeight: style.strokeWeight ?? 4,
                });
            }
            this.assignedPolyline.setPath(path);
            this.assignedPolyline.setMap(map);
        }

        async resolveDrivingPath(route, guided, adminPath) {
            if (!this.canShowPolyline()) return null;
            if (route.has_stored_polyline && guided.length >= 2) {
                return guided;
            }

            if (guided.length >= 2 && !this.isStraightFallbackPath(route, guided, adminPath)) {
                return guided;
            }

            const client = await this.fetchClientDirections(route);
            if (client?.length >= 2) {
                return client;
            }

            const server = await this.fetchServerGuidance();
            if (server?.length >= 2) {
                return server;
            }

            if (guided.length >= 2) {
                return guided;
            }
            if (adminPath.length >= 2) {
                return adminPath;
            }

            return null;
        }

        ensureSuggestedNavigationRoute(route, assigned, adminPath) {
            const snap = this.state;
            const requestId = ++this.polylineResolveId;
            this.resolveNavigationPath(route, assigned, adminPath).then((path) => {
                if (requestId !== this.polylineResolveId || this.state !== snap) return;
                if (path?.length >= 2) {
                    this.drawPath(path, '#f59e0b', 6, 0.95, 'navigation');
                    const fitPath = [];
                    const join = this.normalizePath(snap?.route?.join_polyline || []);
                    if (join.length >= 2) fitPath.push(...join);
                    fitPath.push(...path);
                    if (fitPath.length >= 2) {
                        this.fitMapToPath(fitPath);
                    }
                }
            });
        }

        async resolveNavigationPath(route, assigned, adminPath) {
            if (!this.canShowPolyline()) return null;
            const existing = this.normalizePath(route.navigation_polyline || route.dynamic_polyline || []);
            if (existing.length >= 2) {
                return existing;
            }

            const client = await this.fetchClientNavigationRoute(route);
            if (client?.length >= 2) {
                return client;
            }

            const server = await this.fetchServerGuidance();
            if (server?.length >= 2) {
                return server;
            }

            return null;
        }

        async fetchClientNavigationRoute(route) {
            const google = this.opts.googleMaps || global.google;
            const vehicle = typeof this.opts.getVehiclePosition === 'function'
                ? this.opts.getVehiclePosition()
                : null;
            const { dest } = this.routeEndpoints(route);
            if (!google?.maps?.DirectionsService || !vehicle || !dest) {
                return null;
            }

            const requestId = ++this.directionsRequestId;
            const waypoints = (route.checkpoints || [])
                .filter((cp) => (cp.kind || 'checkpoint') === 'checkpoint')
                .map((cp) => ({
                    location: { lat: parseFloat(cp.lat), lng: parseFloat(cp.lng) },
                    stopover: true,
                }))
                .filter((wp) => Number.isFinite(wp.location.lat) && Number.isFinite(wp.location.lng));

            const service = new google.maps.DirectionsService();

            return new Promise((resolve) => {
                service.route({
                    origin: { lat: parseFloat(vehicle.lat), lng: parseFloat(vehicle.lng) },
                    destination: { lat: parseFloat(dest.lat), lng: parseFloat(dest.lng) },
                    waypoints,
                    travelMode: google.maps.TravelMode.DRIVING,
                    optimizeWaypoints: false,
                }, (result, status) => {
                    if (requestId !== this.directionsRequestId) return resolve(null);
                    if (status !== 'OK' || !result?.routes?.[0]) return resolve(null);

                    const path = [];
                    result.routes[0].legs.forEach((leg) => {
                        leg.steps.forEach((step) => {
                            step.path.forEach((latLng) => {
                                path.push({ lat: latLng.lat(), lng: latLng.lng() });
                            });
                        });
                    });
                    resolve(path.length >= 2 ? path : null);
                });
            });
        }

        async fetchServerGuidance() {
            if (!this.canShowPolyline()) return null;
            const url = this.opts.routeGuidanceUrl;
            const deviceId = typeof this.opts.getDeviceId === 'function' ? this.opts.getDeviceId() : null;
            if (!url || !deviceId) {
                return null;
            }

            try {
                const res = await fetch(`${url}?device_id=${encodeURIComponent(deviceId)}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) {
                    return null;
                }
                const data = await res.json();
                if (!data.success || !Array.isArray(data.vertices) || data.vertices.length < 2) {
                    return null;
                }
                return this.normalizePath(data.vertices);
            } catch (_) {
                return null;
            }
        }

        drawPath(path, color, weight, opacity, layer = 'navigation') {
            const map = this.opts.getMap?.();
            const google = this.opts.googleMaps || global.google;
            if (!map || !google?.maps) return;

            const key = `${layer}Polyline`;
            if (!this[key]) {
                this[key] = new google.maps.Polyline({
                    map,
                    strokeColor: color,
                    strokeOpacity: opacity,
                    strokeWeight: weight,
                    zIndex: layer === 'actual' ? 220 : (layer === 'navigation' ? 215 : (layer === 'join' ? 205 : 200)),
                });
            } else {
                this[key].setOptions({ strokeColor: color, strokeOpacity: opacity, strokeWeight: weight });
                this[key].setMap(map);
            }
            this[key].setPath(path);
        }

        clearPolylines() {
            this.routePolyline?.setMap(null);
            this.navigationPolyline?.setMap(null);
            this.actualPolyline?.setMap(null);
            this.joinPolyline?.setMap(null);
            this.adminPolyline?.setMap(null);
            this.assignedPolyline?.setMap(null);
            this.checkpointMarkers.forEach((marker) => marker.setMap(null));
            this.checkpointMarkers = [];
        }

        renderCheckpoints(map, google, checkpoints) {
            this.checkpointMarkers.forEach((marker) => marker.setMap(null));
            this.checkpointMarkers = [];

            const statusById = {};
            (this.state?.progress?.milestones || []).forEach((m) => {
                statusById[m.id] = m.status;
            });

            checkpoints.forEach((cp, index) => {
                const lat = parseFloat(cp.lat);
                const lng = parseFloat(cp.lng);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

                const kind = cp.kind || 'checkpoint';
                const milestoneId = kind === 'start'
                    ? 'start'
                    : (kind === 'destination' ? 'destination' : `checkpoint-${cp.sequence || index}`);
                const reached = statusById[milestoneId] === 'reached';
                const isCurrent = statusById[milestoneId] === 'current';
                const isSkipped = statusById[milestoneId] === 'skipped';
                const displayLabel = this.markerLabel(cp.label, kind);
                const color = reached ? '#16a34a' : (isSkipped ? '#cbd5e1' : (isCurrent ? '#2563eb' : '#94a3b8'));

                const marker = new google.maps.Marker({
                    map,
                    position: { lat, lng },
                    title: cp.label || '',
                    zIndex: 50 + index,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        fillColor: color,
                        fillOpacity: 1,
                        strokeColor: '#ffffff',
                        strokeWeight: 2,
                        scale: kind === 'checkpoint' ? 9 : 10,
                    },
                    label: {
                        text: displayLabel,
                        color: '#ffffff',
                        fontSize: kind === 'checkpoint' ? '9px' : '10px',
                        fontWeight: '700',
                    },
                });
                this.checkpointMarkers.push(marker);
            });
        }

        markerLabel(name, kind) {
            const clean = String(name || '').trim();
            if (!clean) {
                return kind === 'start' ? 'S' : (kind === 'destination' ? 'D' : '•');
            }
            const short = clean.split(/[,\s]/)[0];
            if (kind === 'start' || kind === 'destination') {
                return short.length > 10 ? `${short.slice(0, 9)}…` : short;
            }
            return short.length > 8 ? `${short.slice(0, 7)}…` : short;
        }

        async fetchClientDirections(route) {
            const google = this.opts.googleMaps || global.google;
            if (!google?.maps?.DirectionsService) return null;

            const requestId = ++this.directionsRequestId;
            const waypoints = (route.checkpoints || [])
                .filter((cp) => cp.kind === 'checkpoint')
                .map((cp) => ({
                    location: { lat: parseFloat(cp.lat), lng: parseFloat(cp.lng) },
                    stopover: true,
                }));

            const { origin, dest } = this.routeEndpoints(route);
            if (!origin || !dest) return null;

            const service = new google.maps.DirectionsService();

            return new Promise((resolve) => {
                service.route({
                    origin: { lat: parseFloat(origin.lat), lng: parseFloat(origin.lng) },
                    destination: { lat: parseFloat(dest.lat), lng: parseFloat(dest.lng) },
                    waypoints,
                    travelMode: google.maps.TravelMode.DRIVING,
                    optimizeWaypoints: false,
                }, (result, status) => {
                    if (requestId !== this.directionsRequestId) return resolve(null);
                    if (status !== 'OK' || !result?.routes?.[0]) return resolve(null);

                    const path = [];
                    result.routes[0].legs.forEach((leg) => {
                        leg.steps.forEach((step) => {
                            step.path.forEach((latLng) => {
                                path.push({ lat: latLng.lat(), lng: latLng.lng() });
                            });
                        });
                    });
                    resolve(path);
                });
            });
        }
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    global.RouteTripProgress = RouteTripProgress;
}(window));
