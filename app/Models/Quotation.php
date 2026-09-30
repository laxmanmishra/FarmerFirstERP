<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versioned quotation (SRS v6.1 §4). A revision is a new row with the same
 * quotation_no and a higher version; older versions are superseded, never edited.
 */
#[Fillable([
    'quotation_no', 'version', 'enquiry_id', 'farmer_id', 'customer_id', 'branch_id', 'prepared_by_employee_id', 'status',
    'valid_until', 'gross_total', 'discount_total', 'tax_total', 'items_total', 'charges_total', 'exchange_value',
    'net_amount', 'finance_amount', 'customer_contribution', 'discount_percent', 'terms', 'remarks',
    'discount_approved_by', 'discount_approved_at', 'approval_remarks', 'issued_at', 'decided_at', 'decision_remarks',
])]
class Quotation extends Model
{
    use Auditable, HasUserstamps;

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'version' => 'integer',
            'valid_until' => 'date',
            'discount_approved_at' => 'datetime',
            'issued_at' => 'datetime',
            'decided_at' => 'datetime',
        ] + array_fill_keys(['gross_total', 'discount_total', 'tax_total', 'items_total', 'charges_total', 'exchange_value',
            'net_amount', 'finance_amount', 'customer_contribution', 'discount_percent'], 'decimal:2');
    }

    public function reference(): string
    {
        return $this->version > 1 ? "{$this->quotation_no} v{$this->version}" : $this->quotation_no;
    }

    public function isExpired(): bool
    {
        return $this->status === QuotationStatus::Issued && $this->valid_until->isPast() && ! $this->valid_until->isToday();
    }

    /**
     * @return HasMany<QuotationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<Enquiry, $this>
     */
    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    /**
     * @return BelongsTo<Farmer, $this>
     */
    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'prepared_by_employee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function discountApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_approved_by');
    }

    /**
     * Quotations of enquiries the user may see.
     *
     * @param  Builder<Quotation>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn('enquiry_id', Enquiry::query()->visibleTo($user)->select('id'));
    }
}
