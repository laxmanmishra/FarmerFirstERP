<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer master (SRS §12). Created from a WON enquiry, permanently linked
 * 1:1 to its farmer; one customer has many deals.
 */
#[Fillable([
    'customer_no', 'farmer_id', 'branch_id', 'name', 'father_name', 'mobile', 'alternate_mobile', 'whatsapp_number',
    'email', 'village_id', 'address', 'pin_code', 'pan', 'customer_since', 'source_enquiry_id', 'possible_duplicate_of_id', 'is_active',
])]
class Customer extends Model
{
    use Auditable, HasActiveFlag, HasFactory, HasUserstamps;

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['customer_since' => 'date'];
    }

    /**
     * @return BelongsTo<Farmer, $this>
     */
    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Village, $this>
     */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function possibleDuplicateOf(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'possible_duplicate_of_id');
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class)->latest('id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('id');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->latest('id');
    }

    /**
     * @return HasMany<Quotation, $this>
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->latest('id');
    }

    /**
     * All enquiries of the farmer, including those made before they became a customer.
     *
     * @return HasMany<Enquiry, $this>
     */
    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class, 'farmer_id', 'farmer_id')->latest('id');
    }

    public function locationLabel(): string
    {
        $village = $this->village;

        return collect([$village?->name, $village?->tehsil?->name, $village?->tehsil?->district?->name])->filter()->implode(', ');
    }

    /**
     * Customers in permitted branches; salesmen limited to their own see customers of
     * their enquiries or deals.
     *
     * @param  Builder<Customer>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->hasAllBranchAccess()) {
            $query->whereIn('branch_id', $user->accessibleBranches()->pluck('id'));
        }

        if ($user->canAny(['enquiries.view_all', 'deals.approve'])) {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->whereHas('enquiries', fn (Builder $query) => $query->visibleTo($user))
            ->orWhereHas('deals', fn (Builder $query) => $query->visibleTo($user))
            ->orWhereHas('orders', fn (Builder $query) => $query->visibleTo($user)));
    }
}
