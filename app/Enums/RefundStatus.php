<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Requested => __('Awaiting approval'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::Paid => __('Paid'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Requested => 'amber',
            self::Approved => 'sky',
            self::Rejected => 'rose',
            self::Paid => 'green',
        };
    }
}
