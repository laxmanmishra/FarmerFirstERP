<?php

namespace App\Enums;

/**
 * Entries in a deal's approval history (SRS §14: Approve, Send Back, Reject).
 */
enum DealDecision: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case SentBack = 'sent_back';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => __('Submitted for approval'),
            self::Approved => __('Approved'),
            self::SentBack => __('Sent back'),
            self::Rejected => __('Rejected'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Submitted => 'sky',
            self::Approved => 'green',
            self::SentBack => 'amber',
            self::Rejected => 'rose',
        };
    }
}
