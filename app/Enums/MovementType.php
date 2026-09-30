<?php

namespace App\Enums;

enum MovementType: string
{
    case Inward = 'inward';
    case Transfer = 'transfer';
    case Allocated = 'allocated';
    case Released = 'released';
    case Blocked = 'blocked';
    case Unblocked = 'unblocked';

    public function label(): string
    {
        return match ($this) {
            self::Inward => __('Received (GRN)'),
            self::Transfer => __('Transferred'),
            self::Allocated => __('Allocated to order'),
            self::Released => __('Released from order'),
            self::Blocked => __('Blocked'),
            self::Unblocked => __('Unblocked'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Inward => 'green',
            self::Transfer => 'sky',
            self::Allocated => 'brand',
            self::Released => 'amber',
            self::Blocked => 'rose',
            self::Unblocked => 'slate',
        };
    }
}
