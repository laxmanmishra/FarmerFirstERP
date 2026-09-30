<?php

namespace App\Enums;

enum FulfilmentStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'sky',
            self::Completed => 'green',
            self::Cancelled => 'rose',
        };
    }
}
