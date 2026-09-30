<?php

namespace App\Enums;

/**
 * Stored follow-up state. "Overdue" and "due today" are derived from due_at, never stored.
 */
enum FollowUpStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
