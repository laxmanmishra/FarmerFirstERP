<?php

namespace App\Enums;

enum DealType: string
{
    case New = 'new';
    case Exchange = 'exchange';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New purchase'),
            self::Exchange => __('Exchange'),
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
