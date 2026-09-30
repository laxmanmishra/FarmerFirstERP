<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * ORDER / BOOKING (SRS §15): created once from an approved deal with a frozen copy of
 * its commercial terms. The bridge between sales and fulfilment (Deal 1:1 Order 1:1 Fulfilment).
 */
#[Fillable([
    'order_no', 'deal_id', 'customer_id', 'farmer_id', 'branch_id', 'primary_salesman_employee_id', 'stage_id', 'order_date',
    'gross_total', 'discount_total', 'tax_total', 'charges_total', 'exchange_value', 'order_value', 'finance_required',
    'finance_amount', 'customer_contribution', 'booking_amount', 'expected_delivery_date', 'rto_required', 'insurance_required',
    'pdi_required', 'deal_snapshot', 'remarks', 'cancelled_at', 'cancelled_by', 'cancellation_reason',
])]
class Order extends Model
{
    use Auditable, HasUserstamps;

    public const STAGE_BOOKED = 'BOOKED';

    public const STAGE_IN_FULFILMENT = 'IN_FULFILMENT';

    public const STAGE_CANCELLED = 'CANCELLED';

    /**
     * Holders of any of these see every order of their branches (department work queues).
     *
     * @var list<string>
     */
    public const FULFILMENT_WIDE_PERMISSIONS = [
        'enquiries.view_all', 'deals.approve', 'orders.cancel', 'documents.verify', 'documents.dashboard',
        'finance.view', 'accounts.view', 'inventory.view', 'rto.view', 'insurance.view', 'pdi.view', 'delivery.execute',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'finance_required' => 'boolean',
            'rto_required' => 'boolean',
            'insurance_required' => 'boolean',
            'pdi_required' => 'boolean',
            'deal_snapshot' => 'array',
            'cancelled_at' => 'datetime',
        ] + array_fill_keys(['gross_total', 'discount_total', 'tax_total', 'charges_total', 'exchange_value', 'order_value',
            'finance_amount', 'customer_contribution', 'booking_amount'], 'decimal:2');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * @return BelongsTo<WorkflowStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'stage_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasOne<Fulfilment, $this>
     */
    public function fulfilment(): HasOne
    {
        return $this->hasOne(Fulfilment::class);
    }

    /**
     * @return HasMany<DocumentRequirement, $this>
     */
    public function documentRequirements(): HasMany
    {
        return $this->hasMany(DocumentRequirement::class);
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
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
     * @return BelongsTo<Employee, $this>
     */
    public function primarySalesman(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'primary_salesman_employee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return MorphMany<WorkflowStatusHistory, $this>
     */
    public function statusHistory(): MorphMany
    {
        return $this->morphMany(WorkflowStatusHistory::class, 'subject')->latest('id');
    }

    /**
     * Department staff and managers see their branches' orders; salesmen see their own
     * (or their team's) orders.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->hasAllBranchAccess()) {
            $query->whereIn($this->qualifyColumn('branch_id'), $user->accessibleBranches()->pluck('id'));
        }

        if ($user->canAny(self::FULFILMENT_WIDE_PERMISSIONS)) {
            return;
        }

        $employee = $user->employee;

        if ($employee === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($this->qualifyColumn('primary_salesman_employee_id'), $user->can('enquiries.view_team') ? $employee->teamMemberIds() : [$employee->id]);
    }
}
