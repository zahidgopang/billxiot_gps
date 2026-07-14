/**
 * Continuous multi-vehicle motion engine (Wialon / Samsara style).
 *
 * Data:    setFix(id, gps) — push sparse GPS (never touches the map).
 * State:   continuous lat/lng/heading/speed per vehicle (never resets t→0).
 * Time:    prefers recorded_at / device time; falls back to receive time.
 * Motion:  dead-reckons between fixes, then softly reconciles to the next fix.
 * Render:  one shared requestAnimationFrame drives all vehicles via onPose.
 */
(function (global) {
    'use strict';

    var EARTH_RADIUS_M = 6371000;
    var MAX_DT_S = 0.1;
    /** Soft catch-up time constant (seconds). */
    var RECONCILE_TAU_S = 0.65;
    /** Stop dead-reckoning below this speed (m/s). */
    var MIN_DR_MPS = 0.15;
    /** Consider target absorbed within this residual (meters). */
    var TARGET_ABSORB_M = 1.25;
    /** Cap dead-reckon coast without a fix (seconds). */
    var MAX_COAST_S = 12;
    /** Keep the shared rAF alive briefly after last motion (ms). */
    var IDLE_KEEPALIVE_MS = 400;

    function clamp(n, min, max) {
        return Math.max(min, Math.min(max, n));
    }

    function normalizeHeading(deg) {
        var n = Number(deg);
        if (!Number.isFinite(n)) return 0;
        return ((n % 360) + 360) % 360;
    }

    function shortestHeadingDelta(from, to) {
        return ((normalizeHeading(to) - normalizeHeading(from) + 540) % 360) - 180;
    }

    function lerpHeading(from, to, t) {
        var start = normalizeHeading(from);
        var delta = shortestHeadingDelta(start, to);
        return normalizeHeading(start + delta * clamp(t, 0, 1));
    }

    function lerp(a, b, t) {
        return a + (b - a) * t;
    }

    function easeInOutCubic(t) {
        var x = clamp(t, 0, 1);
        return x < 0.5
            ? 4 * x * x * x
            : 1 - Math.pow(-2 * x + 2, 3) / 2;
    }

    function haversineMeters(a, b) {
        if (!a || !b) return 0;
        var toRad = Math.PI / 180;
        var dLat = (b.lat - a.lat) * toRad;
        var dLng = (b.lng - a.lng) * toRad;
        var lat1 = a.lat * toRad;
        var lat2 = b.lat * toRad;
        var h = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return 2 * EARTH_RADIUS_M * Math.asin(Math.min(1, Math.sqrt(h)));
    }

    function bearingDegrees(from, to) {
        if (!from || !to) return 0;
        var toRad = Math.PI / 180;
        var lat1 = from.lat * toRad;
        var lat2 = to.lat * toRad;
        var dLng = (to.lng - from.lng) * toRad;
        var y = Math.sin(dLng) * Math.cos(lat2);
        var x = Math.cos(lat1) * Math.sin(lat2)
            - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLng);
        return normalizeHeading(Math.atan2(y, x) * 180 / Math.PI);
    }

    /** Move `meters` along `headingDeg` from `{lat,lng}`. */
    function offsetMeters(from, meters, headingDeg) {
        if (!from || !(meters > 0)) {
            return { lat: from.lat, lng: from.lng };
        }
        var toRad = Math.PI / 180;
        var brng = normalizeHeading(headingDeg) * toRad;
        var lat1 = from.lat * toRad;
        var lng1 = from.lng * toRad;
        var ang = meters / EARTH_RADIUS_M;
        var lat2 = Math.asin(
            Math.sin(lat1) * Math.cos(ang)
            + Math.cos(lat1) * Math.sin(ang) * Math.cos(brng)
        );
        var lng2 = lng1 + Math.atan2(
            Math.sin(brng) * Math.sin(ang) * Math.cos(lat1),
            Math.cos(ang) - Math.sin(lat1) * Math.sin(lat2)
        );
        return {
            lat: lat2 * 180 / Math.PI,
            lng: ((lng2 * 180 / Math.PI + 540) % 360) - 180,
        };
    }

    /**
     * Legacy helper kept for callers that still estimate a catch-up window.
     * Continuous engine no longer animates with start/duration segments.
     */
    function durationMs(options) {
        var opts = options || {};
        var from = opts.from;
        var to = opts.to;
        var speedKmh = Math.max(0, Number(opts.speedKmh) || 0);
        var dist = haversineMeters(from, to);
        var gap = opts.intervalMs != null ? Number(opts.intervalMs) : 2000;
        var observedGap = opts.observedIntervalMs != null
            ? Number(opts.observedIntervalMs)
            : gap;
        var interval = clamp(observedGap || gap, 700, 5000);
        var bySpeed = interval;
        if (speedKmh >= 1 && dist > 0.5) {
            bySpeed = (dist / (speedKmh / 3.6)) * 1000;
        } else if (dist < 1.5) {
            bySpeed = Math.min(interval, 450);
        }
        var blended = (bySpeed * 0.4) + (interval * 0.6);
        var max = opts.maxMs != null ? Number(opts.maxMs) : 2800;
        var min = opts.minMs != null ? Number(opts.minMs) : 220;
        return clamp(blended, min, max);
    }

    function parseFixTimeMs(fix) {
        if (!fix) return null;
        var raw = fix.recorded_at != null ? fix.recorded_at
            : (fix.recordedAt != null ? fix.recordedAt
                : (fix.device_time != null ? fix.device_time
                    : (fix.deviceTime != null ? fix.deviceTime
                        : (fix.fixtime != null ? fix.fixtime
                            : (fix.last_update != null ? fix.last_update
                                : (fix.timestamp != null ? fix.timestamp : null))))));
        if (raw == null || raw === '') return null;
        if (typeof raw === 'number' && Number.isFinite(raw)) {
            return raw < 1e12 ? raw * 1000 : raw;
        }
        var parsed = Date.parse(String(raw));
        return Number.isFinite(parsed) ? parsed : null;
    }

    function kmhToMps(kmh) {
        return Math.max(0, Number(kmh) || 0) / 3.6;
    }

    function createMotionEngine(options) {
        var opts = options || {};
        var vehicles = new Map();
        var rafId = null;
        var lastTickPerf = null;
        var lastActivityPerf = 0;
        var getNow = typeof opts.getNow === 'function'
            ? opts.getNow
            : function () { return performance.now(); };
        var getWallNow = typeof opts.getWallNow === 'function'
            ? opts.getWallNow
            : function () { return Date.now(); };

        function emitPose(id, st, extra) {
            if (typeof opts.onPose !== 'function') return;

            // Pedestal / parked: emit once when settled to cut map writes at scale.
            if (!extra?.snap && !st.hasTarget && st.speedMps <= MIN_DR_MPS) {
                if (st._settledEmitted) return;
                st._settledEmitted = true;
            } else {
                st._settledEmitted = false;
            }

            // Micro-motion noise filter (helps hundreds of concurrent markers).
            if (!extra?.snap && st._lastEmitLat != null) {
                var jump = haversineMeters(
                    { lat: st._lastEmitLat, lng: st._lastEmitLng },
                    { lat: st.lat, lng: st.lng }
                );
                var dH = Math.abs(shortestHeadingDelta(st._lastEmitHeading || 0, st.heading));
                var minMove = vehicles.size > 200 ? 0.45 : (vehicles.size > 80 ? 0.3 : 0.18);
                if (jump < minMove && dH < 0.6 && st.hasTarget) {
                    // Still reconciling but visually unchanged — skip frame.
                    return;
                }
                if (jump < minMove * 0.5 && dH < 0.35 && !st.hasTarget) {
                    return;
                }
            }

            st._lastEmitLat = st.lat;
            st._lastEmitLng = st.lng;
            st._lastEmitHeading = st.heading;

            var pose = {
                id: id,
                lat: st.lat,
                lng: st.lng,
                heading: st.heading,
                speedKmh: st.speedMps * 3.6,
                target: st.hasTarget
                    ? { lat: st.targetLat, lng: st.targetLng }
                    : { lat: st.lat, lng: st.lng },
                targetHeading: st.hasTarget ? st.targetHeading : st.heading,
                color: st.color,
                meta: st.meta,
                fixTimeMs: st.lastFixMs,
                deadReckoning: !st.hasTarget && st.speedMps > MIN_DR_MPS,
                t: st.hasTarget ? 0.5 : 1,
                done: !st.hasTarget && st.speedMps <= MIN_DR_MPS,
            };
            if (extra) {
                Object.keys(extra).forEach(function (k) { pose[k] = extra[k]; });
            }
            opts.onPose(id, pose);
        }

        function ensureLoop() {
            if (rafId == null) {
                lastTickPerf = null;
                rafId = global.requestAnimationFrame(tick);
            }
        }

        function shouldKeepLoop(now) {
            var keep = false;
            vehicles.forEach(function (st) {
                if (st.hasTarget || st.speedMps > MIN_DR_MPS) keep = true;
            });
            if (keep) return true;
            return (now - lastActivityPerf) < IDLE_KEEPALIVE_MS;
        }

        function tick(now) {
            var dt = lastTickPerf == null
                ? 0
                : clamp((now - lastTickPerf) / 1000, 0, MAX_DT_S);
            lastTickPerf = now;

            vehicles.forEach(function (st, id) {
                if (st.paused) return;
                stepVehicle(st, dt, now);
                emitPose(id, st);
            });

            if (shouldKeepLoop(now)) {
                rafId = global.requestAnimationFrame(tick);
            } else {
                rafId = null;
                lastTickPerf = null;
                if (typeof opts.onIdle === 'function') opts.onIdle();
            }
        }

        function stepVehicle(st, dt, now) {
            if (!(dt > 0)) return;

            // 1) Dead reckoning along current course/speed.
            var coastAge = (now - st.lastFixReceivedAt) / 1000;
            var canCoast = st.speedMps > MIN_DR_MPS && coastAge < MAX_COAST_S;
            if (canCoast) {
                var moved = offsetMeters(
                    { lat: st.lat, lng: st.lng },
                    st.speedMps * dt,
                    st.courseHeading
                );
                st.lat = moved.lat;
                st.lng = moved.lng;
                // Keep nose aligned with travel direction.
                st.heading = lerpHeading(st.heading, st.courseHeading, clamp(dt / 0.35, 0, 1));
            } else if (st.speedMps > 0 && !st.hasTarget) {
                // Bleed speed when coast window expires (parked / stale).
                st.speedMps = Math.max(0, st.speedMps - dt * 2.5);
            }

            // 2) Soft reconcile toward latest GPS fix (never snap, never t→0).
            if (st.hasTarget) {
                var display = { lat: st.lat, lng: st.lng };
                var target = { lat: st.targetLat, lng: st.targetLng };
                var err = haversineMeters(display, target);
                var alpha = 1 - Math.exp(-dt / RECONCILE_TAU_S);

                // Large residual: bias course toward the fix and slightly boost speed
                // so DR + attract blend instead of teleporting.
                if (err > TARGET_ABSORB_M) {
                    var toward = bearingDegrees(display, target);
                    st.courseHeading = lerpHeading(st.courseHeading, toward, clamp(dt / 0.45, 0, 1));
                    var catchMps = Math.max(st.targetSpeedMps, err / Math.max(RECONCILE_TAU_S, 0.2));
                    st.speedMps = lerp(st.speedMps, clamp(catchMps, 0, 45), alpha);
                    st.lat = lerp(st.lat, st.targetLat, alpha);
                    st.lng = lerp(st.lng, st.targetLng, alpha);
                    st.heading = lerpHeading(st.heading, st.targetHeading, alpha);
                    lastActivityPerf = now;
                } else {
                    st.lat = st.targetLat;
                    st.lng = st.targetLng;
                    st.heading = normalizeHeading(st.targetHeading);
                    st.courseHeading = st.heading;
                    st.speedMps = st.targetSpeedMps;
                    st.hasTarget = false;
                    lastActivityPerf = now;
                }
            } else if (st.speedMps > MIN_DR_MPS) {
                lastActivityPerf = now;
            }
        }

        /**
         * Push a GPS fix. Does not restart a 0→1 segment — only updates continuous state.
         */
        function setFix(id, fix) {
            if (!fix || !Number.isFinite(Number(fix.lat)) || !Number.isFinite(Number(fix.lng))) {
                return;
            }
            var now = getNow();
            var wall = getWallNow();
            var to = { lat: Number(fix.lat), lng: Number(fix.lng) };
            var fixMs = parseFixTimeMs(fix);
            if (fixMs == null) fixMs = wall;

            var heading = Number(fix.heading);
            if (!Number.isFinite(heading)) heading = null;
            var speedKmh = Math.max(0, Number(fix.speedKmh != null ? fix.speedKmh : fix.speed) || 0);
            var speedMps = kmhToMps(speedKmh);
            var moving = fix.moving !== false && speedMps >= MIN_DR_MPS;

            var st = vehicles.get(id);
            if (!st) {
                var h0 = heading != null ? normalizeHeading(heading) : 0;
                st = {
                    lat: to.lat,
                    lng: to.lng,
                    heading: h0,
                    courseHeading: h0,
                    speedMps: moving ? speedMps : 0,
                    hasTarget: false,
                    targetLat: to.lat,
                    targetLng: to.lng,
                    targetHeading: h0,
                    targetSpeedMps: moving ? speedMps : 0,
                    lastFixLat: to.lat,
                    lastFixLng: to.lng,
                    lastFixHeading: h0,
                    lastFixSpeedMps: moving ? speedMps : 0,
                    lastFixMs: fixMs,
                    lastFixReceivedAt: now,
                    color: fix.color,
                    meta: fix.meta || null,
                    paused: false,
                };
                vehicles.set(id, st);
                lastActivityPerf = now;
                emitPose(id, st, { snap: true });
                ensureLoop();
                return;
            }

            // Ignore stale/out-of-order fixes (GPS clock).
            if (fixMs < st.lastFixMs - 250) {
                return;
            }

            var prev = { lat: st.lastFixLat, lng: st.lastFixLng };
            var gpsDtS = Math.max(0.001, (fixMs - st.lastFixMs) / 1000);
            var gpsDist = haversineMeters(prev, to);

            // Prefer device speed; if missing/low but position moved, use GPS path speed.
            var pathMps = gpsDist / gpsDtS;
            if ((!moving || speedMps < MIN_DR_MPS) && pathMps > MIN_DR_MPS) {
                speedMps = clamp(pathMps, 0, 50);
                moving = true;
            }

            var toH = heading;
            if (toH == null || (!moving && speedMps < MIN_DR_MPS * 2)) {
                if (gpsDist > 1.5) toH = bearingDegrees(prev, to);
                else toH = st.heading;
            }
            toH = normalizeHeading(toH);

            // Continuous retarget — sample current display pose as-is (no ease restart).
            st.hasTarget = true;
            st.targetLat = to.lat;
            st.targetLng = to.lng;
            st.targetHeading = toH;
            st.targetSpeedMps = moving ? speedMps : 0;
            // When stopped intentionally, bleed DR quickly toward standstill.
            if (!moving) {
                st.targetSpeedMps = 0;
            } else if (st.speedMps < MIN_DR_MPS) {
                // Seed DR so the nose starts coasting immediately.
                st.speedMps = speedMps;
                st.courseHeading = toH;
            }

            st.lastFixLat = to.lat;
            st.lastFixLng = to.lng;
            st.lastFixHeading = toH;
            st.lastFixSpeedMps = st.targetSpeedMps;
            st.lastFixMs = fixMs;
            st.lastFixReceivedAt = now;
            if (fix.color != null) st.color = fix.color;
            if (fix.meta !== undefined) st.meta = fix.meta;

            lastActivityPerf = now;
            ensureLoop();
        }

        // Back-compat alias used by older call sites.
        function setTarget(id, pose) {
            setFix(id, pose);
        }

        function currentPose(id) {
            var st = vehicles.get(id);
            if (!st) return null;
            return {
                id: id,
                lat: st.lat,
                lng: st.lng,
                heading: st.heading,
                speedKmh: st.speedMps * 3.6,
                target: st.hasTarget
                    ? { lat: st.targetLat, lng: st.targetLng }
                    : { lat: st.lat, lng: st.lng },
                targetHeading: st.hasTarget ? st.targetHeading : st.heading,
                color: st.color,
                meta: st.meta,
                fixTimeMs: st.lastFixMs,
                deadReckoning: !st.hasTarget && st.speedMps > MIN_DR_MPS,
                t: st.hasTarget ? 0.5 : 1,
                done: !st.hasTarget && st.speedMps <= MIN_DR_MPS,
            };
        }

        function pause(id, paused) {
            var st = vehicles.get(id);
            if (st) st.paused = !!paused;
        }

        function clear(id) {
            if (id == null) {
                vehicles.clear();
            } else {
                vehicles.delete(id);
            }
            if (vehicles.size === 0 && rafId != null) {
                global.cancelAnimationFrame(rafId);
                rafId = null;
                lastTickPerf = null;
            }
        }

        function stop() {
            clear();
        }

        function seed(id, pose) {
            if (!pose || !Number.isFinite(Number(pose.lat)) || !Number.isFinite(Number(pose.lng))) {
                return;
            }
            vehicles.delete(id);
            setFix(id, Object.assign({}, pose, {
                speed: pose.speedKmh != null ? pose.speedKmh : pose.speed,
                moving: false,
            }));
            var st = vehicles.get(id);
            if (st) {
                st.hasTarget = false;
                st.speedMps = kmhToMps(pose.speedKmh != null ? pose.speedKmh : pose.speed);
            }
        }

        return {
            setFix: setFix,
            setTarget: setTarget,
            seed: seed,
            currentPose: currentPose,
            pause: pause,
            clear: clear,
            stop: stop,
            get size() { return vehicles.size; },
        };
    }

    global.VehicleMotion = {
        createMotionEngine: createMotionEngine,
        durationMs: durationMs,
        haversineMeters: haversineMeters,
        bearingDegrees: bearingDegrees,
        offsetMeters: offsetMeters,
        lerpHeading: lerpHeading,
        normalizeHeading: normalizeHeading,
        easeInOutCubic: easeInOutCubic,
        lerp: lerp,
        parseFixTimeMs: parseFixTimeMs,
    };
})(typeof window !== 'undefined' ? window : globalThis);
