<?php

namespace App\Enums;

enum DocumentSensitivity: string
{
    case Normal = 'normal';
    case Sensitive = 'sensitive';

    public function label(): string
    {
        return match ($this) {
            self::Normal => __('Normal'),
            self::Sensitive => __('Sensitive'),
        };
    }
}
