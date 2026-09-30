<?php

namespace App\Enums;

enum PayerType: string
{
    case Customer = 'customer';
    case Financer = 'financer';

    public function label(): string
    {
        return match ($this) {
            self::Customer => __('Customer'),
            self::Financer => __('Financer'),
        };
    }
}
