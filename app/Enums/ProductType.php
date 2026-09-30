<?php

namespace App\Enums;

/**
 * Requirement type is Tractor or Implement (SRS §7).
 */
enum ProductType: string
{
    case Tractor = 'tractor';
    case Implement = 'implement';

    public function label(): string
    {
        return match ($this) {
            self::Tractor => __('Tractor'),
            self::Implement => __('Implement'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
