<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Immutable stock ledger entry for a unit (SRS §56).
 */
#[Fillable(['inventory_unit_id', 'type', 'from_location_id', 'to_location_id', 'from_status', 'to_status', 'order_id', 'remarks', 'user_id'])]
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stock movements are immutable.'));
        static::deleting(fn () => throw new LogicException('Stock movements cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['type' => MovementType::class];
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'from_location_id');
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'to_location_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
