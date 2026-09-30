<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Farmer master (SRS §7). One farmer has many enquiries and becomes at most one customer.
 */
#[Fillable([
    'farmer_no', 'branch_id', 'name', 'father_name', 'mobile', 'alternate_mobile', 'whatsapp_number',
    'village_id', 'address', 'pin_code', 'land_acres', 'occupation', 'remarks', 'is_active',
])]
class Farmer extends Model
{
    use Auditable, HasActiveFlag, HasFactory, HasUserstamps;

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['land_acres' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Village, $this>
     */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<Enquiry, $this>
     */
    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class)->latest('id');
    }

    /**
     * "Village, Tehsil, District" — requires village.tehsil.district loaded.
     */
    public function locationLabel(): string
    {
        $village = $this->village;

        return collect([$village?->name, $village?->tehsil?->name, $village?->tehsil?->district?->name])->filter()->implode(', ');
    }
}
