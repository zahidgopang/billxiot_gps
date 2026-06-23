<?php

namespace App\Enums;

use Carbon\Carbon;

enum PlanBillingCycle: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        $key = 'app.billing.billing_cycle_' . $this->value;

        return __($key) !== $key ? __($key) : ucfirst($this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function durationMonths(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Yearly => 12,
        };
    }

    public function endDateFrom(Carbon $start): Carbon
    {
        $from = $start->copy()->startOfDay();

        return match ($this) {
            self::Monthly => $from->copy()->addMonth(),
            self::Yearly => $from->copy()->addYear(),
        };
    }

    public static function tryFromDurationMonths(int $months): self
    {
        return $months >= 12 ? self::Yearly : self::Monthly;
    }
}
