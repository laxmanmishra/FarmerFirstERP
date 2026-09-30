<?php

namespace App\Enums;

use App\Support\Money;

/**
 * Computed payment position of an account file (SRS §104): never stored.
 */
enum AccountPosition: string
{
    case NotPaid = 'not_paid';
    case Short = 'short';
    case Cleared = 'cleared';
    case Excess = 'excess';

    public static function of(string $receivable, string $cleared): self
    {
        return match (true) {
            Money::compare($cleared, 0) <= 0 => self::NotPaid,
            Money::compare($cleared, $receivable) < 0 => self::Short,
            Money::compare($cleared, $receivable) === 0 => self::Cleared,
            default => self::Excess,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::NotPaid => __('Nothing cleared'),
            self::Short => __('Short'),
            self::Cleared => __('Fully cleared'),
            self::Excess => __('Excess received'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::NotPaid => 'slate',
            self::Short => 'amber',
            self::Cleared => 'green',
            self::Excess => 'violet',
        };
    }
}
