<?php

namespace App\Enums;

/**
 * Lifecycle of one payment (SRS §96–101). Each payment is verified and cleared on its
 * own; returns and reversals never edit the amount.
 */
enum PaymentStatus: string
{
    case PendingVerification = 'pending_verification';
    case Verified = 'verified';
    case Cleared = 'cleared';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::PendingVerification => __('Verification pending'),
            self::Verified => __('Verified'),
            self::Cleared => __('Cleared'),
            self::Rejected => __('Rejected'),
            self::Returned => __('Returned / bounced'),
            self::Reversed => __('Reversed'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PendingVerification => 'amber',
            self::Verified => 'sky',
            self::Cleared => 'green',
            self::Rejected, self::Returned => 'rose',
            self::Reversed => 'slate',
        };
    }

    /**
     * Counts towards the cleared balance. A reversed payment still counts; its negative
     * reversal entry offsets it, so both stay visible in the ledger.
     *
     * @return list<self>
     */
    public static function counted(): array
    {
        return [self::Cleared, self::Reversed];
    }
}
