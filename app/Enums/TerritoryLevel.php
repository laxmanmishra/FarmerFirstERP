<?php

namespace App\Enums;

/**
 * Salesman territory may be assigned at District, Tehsil or Village level (SRS §6).
 * The most specific level wins when resolving the primary salesman of a village.
 */
enum TerritoryLevel: string
{
    case District = 'district';
    case Tehsil = 'tehsil';
    case Village = 'village';

    public function label(): string
    {
        return match ($this) {
            self::District => __('District'),
            self::Tehsil => __('Tehsil'),
            self::Village => __('Village'),
        };
    }

    public function column(): string
    {
        return $this->value.'_id';
    }
}
