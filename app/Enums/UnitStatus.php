<?php

namespace App\Enums;

/**
 * Physical stock status of one chassis (SRS §53). Changed only by the inventory
 * services — never edited directly. Later phases add PDI and delivery states.
 */
enum UnitStatus: string
{
    case Available = 'available';
    case Allocated = 'allocated';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('Available'),
            self::Allocated => __('Allocated'),
            self::Blocked => __('Blocked'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Available => 'green',
            self::Allocated => 'brand',
            self::Blocked => 'rose',
        };
    }
}
