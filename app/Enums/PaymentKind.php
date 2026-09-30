<?php

namespace App\Enums;

enum PaymentKind: string
{
    case Receipt = 'receipt';
    case Reversal = 'reversal';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => __('Receipt'),
            self::Reversal => __('Reversal'),
            self::Refund => __('Refund'),
        };
    }
}
