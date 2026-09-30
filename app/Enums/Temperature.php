<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * Enquiry temperature from the expected purchase date (SRS §8):
 * 0–1 days Extra Hot, 2–6 Hot, 7–14 Warm, 15+ Cold. Thresholds are in config/erp/crm.php.
 */
enum Temperature: string
{
    case ExtraHot = 'extra_hot';
    case Hot = 'hot';
    case Warm = 'warm';
    case Cold = 'cold';

    public static function fromExpectedDate(CarbonInterface $expected, ?CarbonInterface $today = null): self
    {
        $today ??= now();
        $days = (int) $today->copy()->startOfDay()->diffInDays($expected->copy()->startOfDay(), false);
        $thresholds = config('erp.crm.temperature_max_days');

        return match (true) {
            $days <= $thresholds['extra_hot'] => self::ExtraHot,
            $days <= $thresholds['hot'] => self::Hot,
            $days <= $thresholds['warm'] => self::Warm,
            default => self::Cold,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ExtraHot => __('Extra Hot'),
            self::Hot => __('Hot'),
            self::Warm => __('Warm'),
            self::Cold => __('Cold'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::ExtraHot => 'rose',
            self::Hot => 'amber',
            self::Warm => 'sky',
            self::Cold => 'slate',
        };
    }
}
