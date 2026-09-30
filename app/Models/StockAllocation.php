<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unit ↔ order allocation. Released, never deleted: reallocation history stays complete
 * (SRS §55). `active_unit_key` + unique index = one active allocation per unit (INV-04).
 */
#[Fillable(['inventory_unit_id', 'order_id', 'active_unit_key', 'allocated_by', 'allocated_at', 'released_at', 'released_by', 'release_reason'])]
class StockAllocation extends Model
{
    use Auditable;

    protected string $auditModule = 'inventory';

    protected function casts(): array
    {
        return ['allocated_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function isActive(): bool
    {
        return $this->released_at === null;
    }

    /**
     * @return BelongsTo<InventoryUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
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
    public function allocator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
