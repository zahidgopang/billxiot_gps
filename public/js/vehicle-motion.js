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
    /** Soft catch-up time constant (seconds) for position (straight driving). */
    var RECONCILE_TAU_S = 0.65;
    /** Slower residual pull during sharp turns (accuracy over catch-up). */
    var TURN_RECONCILE_TAU_S = 1.35;
    /** Hard cap — one natural turn, never several full revolutions. */
    var MAX_TURN_DEG_PER_S = 160;
    /** Slightly faster yaw allowed while navigating a corner. */
    var TURN_MAX_TURN_DEG_PER_S = 200;
    /** Treat as moving only above this speed (m/s ≈ 2 km/h). */
    var MIN_DR_MPS = 0.56;
    /** Device speed at or below this (km/h) is stationary — ignore heading/DR. */
    var IDLE_SPEED_KMH = 2;
    /** Position noise while parked — ignore smaller jumps. */
    var IDLE_MAX_DIST_M = 5;
    /** Parked relocate (GPS reboot / tow) — snap without leaving idle. */
    var IDLE_RELOCATE_M = 25;
    /** Consider moving-target absorbed within this residual (meters). */
    var TARGET_ABSORB_M = 1.25;
    /** Cap dead-reckon coast without a fix (seconds). */
    var MAX_COAST_S = 8;
    /** Keep the shared rAF alive briefly after last motion (ms). */
    var IDLE_KEEPALIVE_MS = 250;
    /** Heading delta between fixes that starts sharp-turn / low-prediction mode. */
    var SHARP_TURN_DEG = 35;
    /** Heading considered stable again — resume normal prediction. */
    var STABLE_HEADING_DEG = 18;
    /** U-turn / reverse-direction class (extra-conservative prediction). */
    var UTURN_DEG = 120;

    function clamp(n, min, max) {
        return Math.max(min, Math.min(max, n));
    }

    function normalizeHeading(deg) {
        var n = Number(deg);
        if (!Number.isFinite(n)) return 0;
        return ((n % 360) + 360) % 360;
    }

    function shortestHeadingDelta(from, to) {
        // Explicit positive modulo — JS % is signed and can break the classic formula.
        var a = normalizeHeading(from);
        var b = normalizeHeading(to);
        var raw = (b - a) % 360;
        if (raw > 180) raw -= 360;
        if (raw < -180) raw += 360;
        return raw;
    }

    function lerpHeading(from, to, t) {
        var start = normalizeHeading(from);
        var delta = shortestHeadingDelta(start, to);
        return normalizeHeading(start + delta * clamp(t, 0, 1));
    }

    /**
     * Rotate toward goal using only the shortest arc, rate-limited so a noisy GPS
     * heading can never whip the marker through multiple full spins.
     */
    function turnToward(current, goal, dt, maxDegPerSec) {
        var delta = shortestHeadingDelta(current, goal);
        var limit = Math.max(0, Number(maxDegPerSec) || MAX_TURN_DEG_PER_S) * Math.max(0, dt);
        if (Math.abs(delta) <= limit) {
            return normalizeHeading(goal);
        }
        return normalizeHeading(Number(current) + (delta < 0 ? -limit : limit));
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
                idle: !!st.idle,
                turnMode: !!st.turnMode,
                deadReckoning: !st.idle
                    && !st.hasTarget
                    && !st.suppressDr
                    && !st.turnMode
                    && st.speedMps > MIN_DR_MPS,
                t: st.hasTarget ? 0.5 : 1,
                done: !!st.idle || (!st.hasTarget && st.speedMps <= MIN_DR_MPS),
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
                if (st.idle) return;
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
                if (st.paused || st.idle) return;
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

        function enterIdle(st, to, now) {
            st.idle = true;
            st.hasTarget = false;
            st.speedMps = 0;
            st.targetSpeedMps = 0;
            st.turnMode = false;
            st.suppressDr = false;
            st.turnHeadingDelta = 0;
            if (to && Number.isFinite(to.lat) && Number.isFinite(to.lng)) {
                st.lat = to.lat;
                st.lng = to.lng;
                st.targetLat = to.lat;
                st.targetLng = to.lng;
                st.lastFixLat = to.lat;
                st.lastFixLng = to.lng;
            }
            // Freeze heading — never rotate while parked.
            st.courseHeading = st.heading;
            st.targetHeading = st.heading;
            st.lastFixHeading = st.heading;
            st.lastPathBearing = st.heading;
            st.lastFixSpeedMps = 0;
            st.lastFixReceivedAt = now;
            st._settledEmitted = false;
        }

        function leaveIdle(st) {
            st.idle = false;
            st._settledEmitted = false;
        }

        function stepVehicle(st, dt, now) {
            if (!(dt > 0) || st.idle) return;

            if (st.hasTarget) {
                // Stopping mid-glide: freeze immediately, no heading chase.
                if (st.targetSpeedMps < MIN_DR_MPS) {
                    enterIdle(st, { lat: st.targetLat, lng: st.targetLng }, now);
                    return;
                }

                var display = { lat: st.lat, lng: st.lng };
                var err = haversineMeters(display, {
                    lat: st.targetLat,
                    lng: st.targetLng,
                });

                if (err <= TARGET_ABSORB_M) {
                    st.lat = st.targetLat;
                    st.lng = st.targetLng;
                    st.heading = normalizeHeading(st.targetHeading);
                    st.courseHeading = st.heading;
                    st.speedMps = st.targetSpeedMps;
                    st.hasTarget = false;
                    // After a turn segment, hold prediction until the next fix so we
                    // do not coast on a chord while the road is still bending.
                    if (st.turnMode || st.suppressDr) {
                        st.suppressDr = true;
                        st.turnMode = false;
                    }
                    if (st.speedMps < MIN_DR_MPS) {
                        enterIdle(st, { lat: st.lat, lng: st.lng }, now);
                    } else {
                        lastActivityPerf = now;
                    }
                    return;
                }

                var inTurn = !!(st.turnMode || st.suppressDr);
                var turnSeverity = clamp(
                    (Number(st.turnHeadingDelta) || (inTurn ? SHARP_TURN_DEG : 0)) / 180,
                    0,
                    1
                );
                var yawRate = inTurn ? TURN_MAX_TURN_DEG_PER_S : MAX_TURN_DEG_PER_S;
                st.heading = turnToward(
                    st.heading,
                    st.targetHeading,
                    dt,
                    yawRate
                );
                st.courseHeading = st.heading;

                // Advance along the *current* heading so the path curves through
                // the turn instead of cutting the chord between GPS fixes.
                var speed = Math.max(st.speedMps, st.targetSpeedMps * 0.55);
                if (inTurn) {
                    // Large yaw → less forward prediction (U-turns almost pause advance).
                    var forwardGain = 0.55 * (1 - 0.65 * turnSeverity);
                    var forward = Math.min(speed * dt * forwardGain, err * 0.8);
                    if (forward > 0.02) {
                        var arc = offsetMeters(display, forward, st.heading);
                        st.lat = arc.lat;
                        st.lng = arc.lng;
                    }
                    // Weak residual pull onto the new fix (keeps us on-road, not chord-dominant).
                    var pull = 1 - Math.exp(-dt / TURN_RECONCILE_TAU_S);
                    pull *= 0.22 + 0.28 * (1 - turnSeverity);
                    st.lat = lerp(st.lat, st.targetLat, pull);
                    st.lng = lerp(st.lng, st.targetLng, pull);
                } else {
                    // Straight / gentle: light heading-aligned step + normal attract.
                    var fwd = Math.min(speed * dt * 0.4, err * 0.45);
                    if (fwd > 0.05 && !st.suppressDr) {
                        var step = offsetMeters(display, fwd, st.heading);
                        st.lat = step.lat;
                        st.lng = step.lng;
                    }
                    var alpha = 1 - Math.exp(-dt / RECONCILE_TAU_S);
                    st.lat = lerp(st.lat, st.targetLat, alpha);
                    st.lng = lerp(st.lng, st.targetLng, alpha);
                }

                st.speedMps = lerp(st.speedMps, st.targetSpeedMps, Math.min(1, dt / 0.5));
                lastActivityPerf = now;
                return;
            }

            // Free coast between fixes — disabled during / after sharp turns until
            // the next stable heading period (avoids shooting past the corner).
            if (st.suppressDr || st.turnMode) {
                return;
            }

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
                lastActivityPerf = now;
            } else {
                enterIdle(st, { lat: st.lat, lng: st.lng }, now);
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
            var flaggedMoving = fix.moving !== false;

            var st = vehicles.get(id);
            if (!st) {
                var h0 = heading != null ? normalizeHeading(heading) : 0;
                var startMoving = flaggedMoving && speedKmh >= IDLE_SPEED_KMH;
                st = {
                    idle: !startMoving,
                    lat: to.lat,
                    lng: to.lng,
                    heading: h0,
                    courseHeading: h0,
                    speedMps: startMoving ? speedMps : 0,
                    hasTarget: false,
                    turnMode: false,
                    suppressDr: false,
                    turnHeadingDelta: 0,
                    targetLat: to.lat,
                    targetLng: to.lng,
                    targetHeading: h0,
                    targetSpeedMps: startMoving ? speedMps : 0,
                    lastFixLat: to.lat,
                    lastFixLng: to.lng,
                    lastFixHeading: h0,
                    lastPathBearing: h0,
                    lastFixSpeedMps: startMoving ? speedMps : 0,
                    lastFixMs: fixMs,
                    lastFixReceivedAt: now,
                    color: fix.color,
                    meta: fix.meta || null,
                    paused: false,
                };
                vehicles.set(id, st);
                lastActivityPerf = now;
                emitPose(id, st, { snap: true });
                if (startMoving) ensureLoop();
                return;
            }

            if (fixMs < st.lastFixMs - 250) {
                return;
            }

            var prev = { lat: st.lastFixLat, lng: st.lastFixLng };
            var gpsDist = haversineMeters(prev, to);
            var displayDist = haversineMeters({ lat: st.lat, lng: st.lng }, to);

            // Genuine movement requires reported speed — GPS park jitter alone must
            // never restart dead reckoning or heading updates (ignition-ON idle).
            var genuineMove = flaggedMoving && speedKmh >= IDLE_SPEED_KMH;

            if (!genuineMove) {
                st.lastFixMs = fixMs;
                st.lastFixReceivedAt = now;
                if (fix.color != null) st.color = fix.color;
                if (fix.meta !== undefined) st.meta = fix.meta;

                if (!st.idle) {
                    var stopAt = displayDist <= IDLE_MAX_DIST_M
                        ? { lat: st.lat, lng: st.lng }
                        : to;
                    enterIdle(st, stopAt, now);
                    emitPose(id, st, { snap: true });
                    return;
                }

                // Stay idle: ignore heading entirely. Snap only on large relocate.
                if (displayDist >= IDLE_RELOCATE_M || gpsDist >= IDLE_RELOCATE_M) {
                    st.lat = to.lat;
                    st.lng = to.lng;
                    st.targetLat = to.lat;
                    st.targetLng = to.lng;
                    st.lastFixLat = to.lat;
                    st.lastFixLng = to.lng;
                    emitPose(id, st, { snap: true });
                }
                return;
            }

            leaveIdle(st);

            var toH = heading;
            if (toH == null) {
                if (gpsDist > 1.5) toH = bearingDegrees(prev, to);
                else toH = st.heading;
            }
            toH = normalizeHeading(toH);

            var headingJump = Math.abs(shortestHeadingDelta(st.heading, toH));
            if (headingJump > 120 && gpsDist < 6) {
                toH = gpsDist > 1.5
                    ? normalizeHeading(bearingDegrees({ lat: st.lat, lng: st.lng }, to))
                    : normalizeHeading(st.heading);
            }

            // Detect sharp turns / U-turns from consecutive GPS courses (and path bearing).
            var pathBearing = gpsDist > 1.5 ? bearingDegrees(prev, to) : toH;
            var courseDelta = Math.abs(shortestHeadingDelta(st.lastFixHeading, toH));
            var pathDelta = Math.abs(shortestHeadingDelta(st.lastFixHeading, pathBearing));
            var turnDelta = Math.max(courseDelta, pathDelta);

            // Enter turn mode on a sharp heading jump. Do not exit here on a small
            // consecutive delta alone — gradual U-turns are many mild steps; exit
            // once stepVehicle sees heading + position stabilize, or when both
            // course and path-bend stay gentle on a later fix.
            var pathBend = Math.abs(
                shortestHeadingDelta(st.lastPathBearing != null ? st.lastPathBearing : st.lastFixHeading, pathBearing)
            );
            if (turnDelta >= SHARP_TURN_DEG || pathBend >= SHARP_TURN_DEG) {
                st.turnMode = true;
                st.suppressDr = true;
                st.turnHeadingDelta = Math.max(
                    st.turnHeadingDelta || 0,
                    Math.max(turnDelta, pathBend)
                );
            } else if (
                (st.turnMode || st.suppressDr)
                && turnDelta < STABLE_HEADING_DEG
                && pathBend < STABLE_HEADING_DEG
            ) {
                var align = Math.abs(shortestHeadingDelta(st.heading, toH));
                if (align < STABLE_HEADING_DEG) {
                    st.turnMode = false;
                    st.suppressDr = false;
                    st.turnHeadingDelta = 0;
                }
            }

            st.hasTarget = true;
            st.targetLat = to.lat;
            st.targetLng = to.lng;
            st.targetHeading = toH;
            // During U-turns, temper speed so we don't launch across the median.
            if (turnDelta >= UTURN_DEG || pathBend >= UTURN_DEG) {
                st.targetSpeedMps = Math.min(
                    Math.max(speedMps, MIN_DR_MPS),
                    Math.max(MIN_DR_MPS, speedMps * 0.55)
                );
            } else if (turnDelta >= SHARP_TURN_DEG || pathBend >= SHARP_TURN_DEG) {
                st.targetSpeedMps = Math.min(
                    Math.max(speedMps, MIN_DR_MPS),
                    Math.max(MIN_DR_MPS, speedMps * 0.75)
                );
            } else {
                st.targetSpeedMps = Math.max(speedMps, MIN_DR_MPS);
            }
            if (st.speedMps < MIN_DR_MPS) {
                st.speedMps = st.targetSpeedMps;
            }

            st.lastFixLat = to.lat;
            st.lastFixLng = to.lng;
            st.lastFixHeading = toH;
            st.lastPathBearing = pathBearing;
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
                idle: !!st.idle,
                turnMode: !!st.turnMode,
                deadReckoning: !st.idle
                    && !st.hasTarget
                    && !st.suppressDr
                    && !st.turnMode
                    && st.speedMps > MIN_DR_MPS,
                t: st.hasTarget ? 0.5 : 1,
                done: !!st.idle || (!st.hasTarget && st.speedMps <= MIN_DR_MPS),
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
                enterIdle(st, {
                    lat: Number(pose.lat),
                    lng: Number(pose.lng),
                }, getNow());
                if (Number.isFinite(Number(pose.heading))) {
                    st.heading = normalizeHeading(pose.heading);
                    st.courseHeading = st.heading;
                    st.targetHeading = st.heading;
                }
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
        turnToward: turnToward,
        shortestHeadingDelta: shortestHeadingDelta,
        normalizeHeading: normalizeHeading,
        easeInOutCubic: easeInOutCubic,
        lerp: lerp,
        parseFixTimeMs: parseFixTimeMs,
    };
})(typeof window !== 'undefined' ? window : globalThis);
