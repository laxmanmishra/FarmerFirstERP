<?php

namespace App\Enums;

/**
 * Whether a fulfilment task or document is needed for an order (SRS §234–235).
 * Kept separate from the operational stage: WAIVED is never COMPLETED (INV-02).
 */
enum RequirementState: string
{
    case Required = 'required';
    case NotRequired = 'not_required';
    case Conditional = 'conditional';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Required => __('Required'),
            self::NotRequired => __('Not required'),
            self::Conditional => __('Conditional'),
            self::Waived => __('Waived'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Required => 'brand',
            self::NotRequired => 'slate',
            self::Conditional => 'sky',
            self::Waived => 'violet',
        };
    }

    /**
     * The work still has to be done (a waiver only defers it).
     */
    public function isApplicable(): bool
    {
        return $this !== self::NotRequired;
    }
}
