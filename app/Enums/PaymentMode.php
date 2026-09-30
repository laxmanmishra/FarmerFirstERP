<?php

namespace App\Enums;

enum PaymentMode: string
{
    case Cash = 'cash';
    case Cheque = 'cheque';
    case DemandDraft = 'dd';
    case Upi = 'upi';
    case Neft = 'neft';
    case Rtgs = 'rtgs';
    case Card = 'card';
    case FinanceDisbursement = 'finance_disbursement';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Cheque => __('Cheque'),
            self::DemandDraft => __('Demand draft'),
            self::Upi => __('UPI'),
            self::Neft => __('NEFT'),
            self::Rtgs => __('RTGS'),
            self::Card => __('Card'),
            self::FinanceDisbursement => __('Finance disbursement'),
        };
    }

    /**
     * Modes that carry a bank instrument or transaction reference.
     */
    public function needsReference(): bool
    {
        return $this !== self::Cash;
    }

    /**
     * Instruments that can bounce after they were accepted.
     */
    public function canBounce(): bool
    {
        return in_array($this, [self::Cheque, self::DemandDraft], true);
    }
}
