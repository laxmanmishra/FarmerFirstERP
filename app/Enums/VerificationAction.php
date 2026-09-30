<?php

namespace App\Enums;

enum VerificationAction: string
{
    case Started = 'started';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Started => __('Verification started'),
            self::Verified => __('Verified'),
            self::Rejected => __('Rejected'),
            self::Expired => __('Expired'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Started => 'amber',
            self::Verified => 'green',
            self::Rejected => 'rose',
            self::Expired => 'slate',
        };
    }
}
