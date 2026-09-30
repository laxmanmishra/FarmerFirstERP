<?php

namespace App\Enums;

/**
 * Commercial line kinds on quotations and deals (SRS v6.1 §4): the product itself,
 * accessories, and charges such as RTO, insurance or handling.
 */
enum LineType: string
{
    case Product = 'product';
    case Accessory = 'accessory';
    case Charge = 'charge';

    public function label(): string
    {
        return match ($this) {
            self::Product => __('Product'),
            self::Accessory => __('Accessory'),
            self::Charge => __('Charge'),
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
