<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Old tractor offered in exchange (SRS §7). The customer's expected price is kept
 * separate from the internally approved exchange value (SRS v6.1 §4).
 */
#[Fillable([
    'enquiry_id', 'brand_name', 'model_name', 'manufacturing_year', 'hours_used', 'registration_number',
    'condition', 'customer_expected_price', 'approved_exchange_value', 'remarks',
])]
class ExchangeTractor extends Model
{
    use Auditable, HasUserstamps;

    protected string $auditModule = 'enquiries';

    protected function casts(): array
    {
        return [
            'manufacturing_year' => 'integer',
            'hours_used' => 'integer',
            'customer_expected_price' => 'decimal:2',
            'approved_exchange_value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Enquiry, $this>
     */
    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }
}
