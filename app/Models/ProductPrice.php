<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Price master with effective dates (SRS v6.1 §4). A variant- and branch-specific
 * price wins over a generic one.
 */
#[Fillable(['product_id', 'product_variant_id', 'branch_id', 'price', 'tax_percent', 'effective_from', 'effective_to', 'is_active'])]
class ProductPrice extends Model
{
    use Auditable, HasActiveFlag, HasUserstamps;

    protected string $auditModule = 'products';

    protected $attributes = ['is_active' => true, 'tax_percent' => '0.00'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * Current applicable price for a product (optionally variant and branch) on a date.
     */
    public static function resolve(int $productId, ?int $variantId = null, ?int $branchId = null, ?CarbonInterface $on = null): ?self
    {
        $on = ($on ?? today())->toDateString();

        return static::query()->active()
            ->where('product_id', $productId)
            ->where(fn ($query) => $query->whereNull('product_variant_id')->orWhere('product_variant_id', $variantId))
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->where('effective_from', '<=', $on)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $on))
            ->orderByRaw('case when product_variant_id is null then 1 else 0 end')
            ->orderByRaw('case when branch_id is null then 1 else 0 end')
            ->orderByDesc('effective_from')
            ->first();
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
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
