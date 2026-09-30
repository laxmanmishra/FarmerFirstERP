<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['enquiry_id', 'requirement_type', 'brand_id', 'product_id', 'product_variant_id', 'quantity', 'description'])]
class EnquiryRequirement extends Model
{
    /**
     * Always needed for summary(); loaded in bulk wherever requirements are eager loaded.
     *
     * @var list<string>
     */
    protected $with = ['brand', 'product', 'variant'];

    protected function casts(): array
    {
        return ['requirement_type' => ProductType::class, 'quantity' => 'integer'];
    }

    /**
     * @return BelongsTo<Enquiry, $this>
     */
    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
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

    public function summary(): string
    {
        $parts = array_filter([
            $this->brand?->name,
            $this->product?->name,
            $this->variant?->name,
        ]);

        $label = $parts === [] ? ($this->description ?: $this->requirement_type->label()) : implode(' ', $parts);

        return $this->quantity > 1 ? "{$label} × {$this->quantity}" : $label;
    }
}
