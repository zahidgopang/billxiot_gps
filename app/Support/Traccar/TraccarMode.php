<?php

namespace App\Support\Traccar;

final class TraccarMode
{
    public const OFF = 'off';

    public const DUAL_WRITE = 'dual_write';

    public const READ_TRACCAR = 'read_traccar';

    public const FULL = 'full';

    public static function current(): string
    {
        if (! config('traccar.enabled', false)) {
            return self::OFF;
        }

        $mode = config('traccar.mode', self::OFF);

        return in_array($mode, [self::OFF, self::DUAL_WRITE, self::READ_TRACCAR, self::FULL], true)
            ? $mode
            : self::OFF;
    }

    public static function isActive(): bool
    {
        return self::current() !== self::OFF;
    }

    /** tc_positions / tc_events are the only read/write target for GPS + alerts. */
    public static function isSingleSource(): bool
    {
        return self::current() === self::FULL;
    }

    public static function writesLegacy(): bool
    {
        if (! self::isActive() || self::isSingleSource()) {
            return false;
        }

        $writeLegacy = config('traccar.write_legacy_tables');

        if ($writeLegacy === false) {
            return false;
        }

        return in_array(self::current(), [self::DUAL_WRITE, self::READ_TRACCAR], true);
    }

    public static function writesTraccar(): bool
    {
        return in_array(self::current(), [self::DUAL_WRITE, self::READ_TRACCAR, self::FULL], true);
    }

    public static function readsTraccar(): bool
    {
        return in_array(self::current(), [self::READ_TRACCAR, self::FULL], true);
    }

    /** Mirror Laravel CRUD into tc_* via observers (disable in full single-source mode). */
    public static function shouldSyncObservers(): bool
    {
        return self::isActive() && config('traccar.sync_on_change', ! self::isSingleSource());
    }

    public static function usesLegacyTracking(): bool
    {
        return self::writesLegacy() || (self::current() === self::OFF);
    }
}
