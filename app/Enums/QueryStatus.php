<?php

namespace App\Enums;

/**
 * Query raised by an external party (financer, RTO, insurer) on a department file (SRS §71).
 */
enum QueryStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::InProgress => __('In progress'),
            self::Submitted => __('Submitted'),
            self::Resolved => __('Resolved'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'rose',
            self::InProgress => 'amber',
            self::Submitted => 'sky',
            self::Resolved => 'green',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Resolved;
    }

    /**
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::Submitted, self::Resolved],
            self::InProgress => [self::Submitted, self::Resolved],
            self::Submitted => [self::InProgress, self::Resolved],
            self::Resolved => [],
        };
    }
}
