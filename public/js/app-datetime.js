/**
 * FalconEyeGPS — Pakistan (Asia/Karachi) date/time formatting for web UI.
 */
(function (global) {
    'use strict';

    const TZ = 'Asia/Karachi';

    function parseDate(value) {
        if (!value) return null;
        const ms = parseTimestampMs(value);
        if (ms == null) return null;
        return new Date(ms);
    }

    /**
     * Parse API / DB timestamps to UTC epoch ms.
     * Y-m-d H:i:s (no offset) is treated as Asia/Karachi — matches backend AppDateTime.
     */
    function parseTimestampMs(value) {
        if (value == null || value === '') return null;
        if (value instanceof Date) {
            const t = value.getTime();
            return Number.isNaN(t) ? null : t;
        }

        const s = String(value).trim();
        if (!s) return null;

        const logMatch = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/.exec(s);
        if (logMatch) {
            const y = Number(logMatch[1]);
            const mo = Number(logMatch[2]);
            const d = Number(logMatch[3]);
            const h = Number(logMatch[4]);
            const mi = Number(logMatch[5]);
            const sec = Number(logMatch[6] || 0);
            // PKT (UTC+5) → UTC
            return Date.UTC(y, mo - 1, d, h - 5, mi, sec);
        }

        const parsed = Date.parse(s);
        return Number.isNaN(parsed) ? null : parsed;
    }

    /** @deprecated alias */
    function parsePktTimestampMs(value) {
        return parseTimestampMs(value);
    }

    function capitalizeAmPm(text) {
        return String(text).replace(/\b(am|pm)\b/gi, (m) => m.toUpperCase());
    }

    /** 29 May 2026, 10:35 AM */
    function formatDateTime(value) {
        const d = parseDate(value);
        if (!d) return '—';
        const parts = new Intl.DateTimeFormat('en-GB', {
            timeZone: TZ,
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }).formatToParts(d);

        const get = (type) => parts.find((p) => p.type === type)?.value ?? '';
        const day = get('day');
        const month = get('month');
        const year = get('year');
        const hour = get('hour');
        const minute = get('minute');
        const dayPeriod = get('dayPeriod').toUpperCase();

        return `${day} ${month} ${year}, ${hour}:${minute} ${dayPeriod}`;
    }

    /** 29-May-2026 10:35 AM */
    function formatDateTimeShort(value) {
        const d = parseDate(value);
        if (!d) return '—';
        const parts = new Intl.DateTimeFormat('en-GB', {
            timeZone: TZ,
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }).formatToParts(d);

        const get = (type) => parts.find((p) => p.type === type)?.value ?? '';
        const day = get('day').padStart(2, '0');
        const month = get('month');
        const year = get('year');
        const hour = get('hour');
        const minute = get('minute');
        const dayPeriod = get('dayPeriod').toUpperCase();

        return `${day}-${month}-${year} ${hour}:${minute} ${dayPeriod}`;
    }

    /** 10:35 AM */
    function formatTime(value) {
        const d = parseDate(value);
        if (!d) return '—';
        return capitalizeAmPm(
            new Intl.DateTimeFormat('en-US', {
                timeZone: TZ,
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
            }).format(d)
        );
    }

    /** 29 May 2026 */
    function formatDate(value) {
        const d = parseDate(value);
        if (!d) return '—';
        const parts = new Intl.DateTimeFormat('en-GB', {
            timeZone: TZ,
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        }).formatToParts(d);

        const get = (type) => parts.find((p) => p.type === type)?.value ?? '';
        return `${get('day')} ${get('month')} ${get('year')}`;
    }

    /** Y-m-d in app timezone (for API / history filters). */
    function formatDateYmd(value) {
        const d = parseDate(value);
        if (!d) return '';
        return new Intl.DateTimeFormat('en-CA', {
            timeZone: TZ,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).format(d);
    }

    function todayYmd() {
        return formatDateYmd(new Date());
    }

    /** Shift a Y-m-d calendar day in app timezone. */
    function shiftYmd(ymd, deltaDays) {
        if (!ymd || !/^\d{4}-\d{2}-\d{2}$/.test(ymd)) return ymd;
        const anchor = new Date(`${ymd}T12:00:00+05:00`);
        anchor.setUTCDate(anchor.getUTCDate() + deltaDays);
        return formatDateYmd(anchor);
    }

    global.AppDateTime = {
        TZ,
        formatDateTime,
        formatDateTimeShort,
        formatTime,
        formatDate,
        formatDateYmd,
        todayYmd,
        shiftYmd,
        parseTimestampMs,
        parsePktTimestampMs,
    };
})(typeof window !== 'undefined' ? window : globalThis);
