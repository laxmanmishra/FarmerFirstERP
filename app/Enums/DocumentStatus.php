<?php

namespace App\Enums;

/**
 * State of a physical document (SRS §194). Whether it satisfies a requirement is
 * decided separately (INV-09).
 */
enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case UnderVerification = 'under_verification';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => __('Uploaded'),
            self::UnderVerification => __('Under verification'),
            self::Verified => __('Verified'),
            self::Rejected => __('Rejected'),
            self::Expired => __('Expired'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Uploaded => 'sky',
            self::UnderVerification => 'amber',
            self::Verified => 'green',
            self::Rejected => 'rose',
            self::Expired => 'slate',
        };
    }

    public function awaitsVerification(): bool
    {
        return in_array($this, [self::Uploaded, self::UnderVerification], true);
    }
}
