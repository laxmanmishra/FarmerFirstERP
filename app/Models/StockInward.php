<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Goods receipt note (SRS §54): the units received in one delivery from the OEM / supplier.
 */
#[Fillable(['grn_no', 'branch_id', 'stock_location_id', 'supplier_name', 'supplier_invoice_no', 'supplier_invoice_date', 'received_on', 'remarks'])]
class StockInward extends Model
{
    use Auditable, HasUserstamps;

    protected string $auditModule = 'inventory';

    protected function casts(): array
    {
        return ['supplier_invoice_date' => 'date', 'received_on' => 'date'];
    }

    /**
     * @return HasMany<InventoryUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(InventoryUnit::class)->orderBy('id');
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
