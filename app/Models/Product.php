<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalogue model (e.g. "575 DI"). Physical units are tracked by Inventory, never here.
 */
#[Fillable(['brand_id', 'product_type', 'name', 'hp', 'description', 'is_active'])]
class Product extends Model
{
    use Auditable, HasActiveFlag, HasFactory, HasUserstamps;

    protected string $auditModule = 'products';

    protected function casts(): array
    {
        return ['product_type' => ProductType::class, 'hp' => 'integer'];
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function displayName(): string
    {
        return trim($this->brand->name.' '.$this->name.($this->hp ? " ({$this->hp} HP)" : ''));
    }
}
