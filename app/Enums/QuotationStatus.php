<?php

namespace App\Enums;

/**
 * Quotation document lifecycle. These are document states with fixed meaning
 * (a superseded version can never be accepted), not a configurable department workflow.
 */
enum QuotationStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Issued = 'issued';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::PendingApproval => __('Discount approval pending'),
            self::Issued => __('Issued to customer'),
            self::Accepted => __('Accepted'),
            self::Declined => __('Declined'),
            self::Superseded => __('Superseded'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::PendingApproval => 'amber',
            self::Issued => 'sky',
            self::Accepted => 'green',
            self::Declined => 'rose',
            self::Superseded => 'slate',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval], true);
    }
}
