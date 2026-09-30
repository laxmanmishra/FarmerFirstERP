<?php

namespace App\Models;

use App\Enums\DealDecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Immutable approval-history entry with the commercial values at decision time (SRS §14).
 */
#[Fillable(['deal_id', 'action', 'user_id', 'remarks', 'snapshot'])]
class DealApproval extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Deal approval history is immutable.'));
        static::deleting(fn () => throw new LogicException('Deal approval history cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['action' => DealDecision::class, 'snapshot' => 'array'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}
