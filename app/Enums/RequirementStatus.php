<?php

namespace App\Enums;

/**
 * Documentation-dashboard status of a document requirement (SRS §205): Pending while
 * nothing is linked, otherwise the linked document's state, plus Not Required / Waived.
 */
enum RequirementStatus: string
{
    case Pending = 'pending';
    case Uploaded = 'uploaded';
    case UnderVerification = 'under_verification';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Waived = 'waived';
    case NotRequired = 'not_required';

    /**
     * @return list<self>
     */
    public static function dashboardColumns(): array
    {
        return [self::Pending, self::Uploaded, self::UnderVerification, self::Verified, self::Rejected, self::Expired];
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Uploaded => __('Uploaded'),
            self::UnderVerification => __('Under verification'),
            self::Verified => __('Verified'),
            self::Rejected => __('Rejected'),
            self::Expired => __('Expired'),
            self::Waived => __('Waived'),
            self::NotRequired => __('Not required'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Uploaded => 'sky',
            self::UnderVerification => 'violet',
            self::Verified => 'green',
            self::Rejected, self::Expired => 'rose',
            self::Waived => 'violet',
            self::NotRequired => 'slate',
        };
    }
}
