<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Yard, showroom or workshop where physical units stand (SRS §54).
 */
#[Fillable(['branch_id', 'code', 'name', 'type', 'is_active'])]
class StockLocation extends Model
{
    use Auditable, HasActiveFlag, HasUserstamps;

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true];

    public const TYPES = ['yard' => 'Yard', 'showroom' => 'Showroom', 'workshop' => 'Workshop', 'warehouse' => 'Warehouse'];

    protected string $auditModule = 'inventory';

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<InventoryUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(InventoryUnit::class);
    }
}
