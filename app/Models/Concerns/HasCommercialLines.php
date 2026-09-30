<?php

namespace App\Models\Concerns;

use App\Enums\LineType;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared behaviour of quotation and deal lines.
 */
trait HasCommercialLines
{
    public function initializeHasCommercialLines(): void
    {
        $this->mergeCasts([
            'line_type' => LineType::class,
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ]);
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
     * @return array<string, mixed>
     */
    public function commercialAttributes(): array
    {
        return $this->only(['line_type', 'product_id', 'product_variant_id', 'description', 'quantity', 'unit_price',
            'discount_amount', 'tax_percent', 'tax_amount', 'line_total', 'sort_order']);
    }
}
