<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Deal master (SRS §14). Links CUSTOMER, FARMER and ENQUIRY; holds the approved
 * commercial snapshot copied from the accepted quotation and the fulfilment flags
 * that Phase 4 turns into order tasks.
 */
#[Fillable([
    'deal_no', 'enquiry_id', 'customer_id', 'farmer_id', 'branch_id', 'primary_salesman_employee_id', 'quotation_id', 'stage_id',
    'gross_total', 'discount_total', 'tax_total', 'charges_total', 'exchange_value', 'deal_value', 'finance_required',
    'finance_amount', 'customer_contribution', 'booking_amount', 'expected_delivery_date', 'rto_required', 'insurance_required',
    'pdi_required', 'remarks', 'submitted_at', 'submitted_by', 'approved_at', 'approved_by', 'closed_at',
])]
class Deal extends Model
{
    use Auditable, HasUserstamps;

    public const STAGE_DRAFT = 'DRAFT';

    public const STAGE_READY = 'DEAL_READY';

    public const STAGE_SENT_BACK = 'SENT_BACK';

    public const STAGE_APPROVED = 'APPROVED';

    public const STAGE_REJECTED = 'REJECTED';

    protected function casts(): array
    {
        return [
            'finance_required' => 'boolean',
            'rto_required' => 'boolean',
            'insurance_required' => 'boolean',
            'pdi_required' => 'boolean',
            'expected_delivery_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
        ] + array_fill_keys(['gross_total', 'discount_total', 'tax_total', 'charges_total', 'exchange_value', 'deal_value',
            'finance_amount', 'customer_contribution', 'booking_amount'], 'decimal:2');
    }

    /**
     * Commercial lines and header values open to edit (draft or sent back).
     */
    public function isEditable(): bool
    {
        return in_array($this->stage->code, [self::STAGE_DRAFT, self::STAGE_SENT_BACK], true);
    }

    public function isAwaitingApproval(): bool
    {
        return $this->stage->code === self::STAGE_READY;
    }

    /**
     * @return array<string, mixed>
     */
    public function commercialSnapshot(): array
    {
        return [
            ...$this->only(['quotation_id', 'gross_total', 'discount_total', 'tax_total', 'charges_total', 'exchange_value', 'deal_value',
                'finance_required', 'finance_amount', 'customer_contribution', 'booking_amount', 'rto_required', 'insurance_required', 'pdi_required']),
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),
            'items' => $this->items->map->commercialAttributes()->all(),
        ];
    }

    /**
     * @return BelongsTo<WorkflowStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'stage_id');
    }

    /**
     * @return HasMany<DealItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DealItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<DealApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(DealApproval::class)->latest('id');
    }

    /**
     * @return BelongsTo<Enquiry, $this>
     */
    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function primarySalesman(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'primary_salesman_employee_id');
    }

    /**
     * @return MorphMany<WorkflowStatusHistory, $this>
     */
    public function statusHistory(): MorphMany
    {
        return $this->morphMany(WorkflowStatusHistory::class, 'subject')->latest('id');
    }

    /**
     * Same scope as enquiries, extended to the deal's primary salesman.
     *
     * @param  Builder<Deal>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->hasAllBranchAccess()) {
            $query->whereIn('branch_id', $user->accessibleBranches()->pluck('id'));
        }

        if ($user->canAny(['enquiries.view_all', 'deals.approve'])) {
            return;
        }

        $employee = $user->employee;

        if ($employee === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $ids = $user->can('enquiries.view_team') ? $employee->teamMemberIds() : [$employee->id];

        $query->where(fn (Builder $query) => $query
            ->whereIn('primary_salesman_employee_id', $ids)
            ->orWhereIn('enquiry_id', Enquiry::query()->visibleTo($user)->select('id')));
    }
}
