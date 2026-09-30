<?php

namespace App\Models;

use App\Enums\UnitStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One physical tractor / implement identified by chassis and engine number (SRS §53).
 * A product is the catalogue item; this is the unit that gets allocated and delivered.
 */
#[Fillable([
    'product_id', 'product_variant_id', 'chassis_no', 'engine_no', 'colour', 'model_year', 'branch_id', 'stock_location_id',
    'status', 'status_reason', 'stock_inward_id', 'purchase_cost', 'received_on',
])]
class InventoryUnit extends Model
{
    use Auditable, HasUserstamps;

    protected string $auditModule = 'inventory';

    protected function casts(): array
    {
        return ['status' => UnitStatus::class, 'model_year' => 'integer', 'purchase_cost' => 'decimal:2', 'received_on' => 'date'];
    }

    public function label(): string
    {
        return $this->product->displayName().' · '.$this->chassis_no;
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<StockInward, $this>
     */
    public function inward(): BelongsTo
    {
        return $this->belongsTo(StockInward::class, 'stock_inward_id');
    }

    /**
     * @return HasMany<StockAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(StockAllocation::class)->latest('id');
    }

    /**
     * @return HasOne<StockAllocation, $this>
     */
    public function activeAllocation(): HasOne
    {
        return $this->hasOne(StockAllocation::class)->whereNull('released_at');
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    /**
     * @param  Builder<InventoryUnit>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->hasAllBranchAccess()) {
            $query->whereIn($this->qualifyColumn('branch_id'), $user->accessibleBranches()->pluck('id'));
        }
    }
}
