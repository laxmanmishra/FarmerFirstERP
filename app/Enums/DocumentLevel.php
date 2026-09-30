<?php

namespace App\Enums;

/**
 * Where a document belongs (SRS §185). Customer-level documents are shared by all of
 * the customer's orders; unit-level documents attach to the order until inventory
 * units exist (Phase 5).
 */
enum DocumentLevel: string
{
    case Customer = 'customer';
    case Deal = 'deal';
    case Order = 'order';
    case Unit = 'unit';
    case Department = 'department';

    public function label(): string
    {
        return match ($this) {
            self::Customer => __('Customer'),
            self::Deal => __('Deal'),
            self::Order => __('Order'),
            self::Unit => __('Unit'),
            self::Department => __('Department'),
        };
    }
}
