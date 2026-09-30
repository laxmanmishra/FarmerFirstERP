<?php

namespace App\Enums;

use App\Models\Order;

/**
 * When a fulfilment task type applies to an order, read from the approved deal flags.
 */
enum TaskCondition: string
{
    case Always = 'always';
    case FinanceRequired = 'finance_required';
    case RtoRequired = 'rto_required';
    case InsuranceRequired = 'insurance_required';
    case PdiRequired = 'pdi_required';

    public function label(): string
    {
        return match ($this) {
            self::Always => __('Every order'),
            self::FinanceRequired => __('Finance required'),
            self::RtoRequired => __('RTO required'),
            self::InsuranceRequired => __('Insurance required'),
            self::PdiRequired => __('PDI required'),
        };
    }

    public function appliesTo(Order $order): bool
    {
        return match ($this) {
            self::Always => true,
            self::FinanceRequired => $order->finance_required,
            self::RtoRequired => $order->rto_required,
            self::InsuranceRequired => $order->insurance_required,
            self::PdiRequired => $order->pdi_required,
        };
    }
}
