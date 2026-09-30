<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\Followable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Retail & Finance file (SRS §57, §65–73): one per order that needs finance. Its status
 * mirrors what the external financer reports; it describes finance only (INV-03).
 */
#[Fillable([
    'file_no', 'order_id', 'fulfilment_task_id', 'branch_id', 'stage_id', 'financer_id', 'financer_contact_id', 'responsible_employee_id',
    'loan_amount', 'sanctioned_amount', 'down_payment', 'tenure_months', 'interest_rate', 'emi_amount', 'loan_account_no',
    'do_number', 'do_date', 'do_amount', 'do_valid_until', 'disbursed_amount', 'disbursed_on', 'remarks',
])]
class FinanceFile extends Model implements Followable
{
    use Auditable, HasUserstamps;

    protected string $auditModule = 'finance';

    protected function casts(): array
    {
        return [
            'do_date' => 'date',
            'do_valid_until' => 'date',
            'disbursed_on' => 'date',
            'tenure_months' => 'integer',
            'interest_rate' => 'decimal:2',
        ] + array_fill_keys(['loan_amount', 'sanctioned_amount', 'down_payment', 'emi_amount', 'do_amount', 'disbursed_amount'], 'decimal:2');
    }

    public function isDoExpired(): bool
    {
        return $this->do_valid_until !== null && $this->do_valid_until->lt(today());
    }

    public function followUpSubject(): string
    {
        return $this->file_no.' · '.$this->order->customer->name;
    }

    public function followUpUrl(): string
    {
        return route('fulfilment.finance.show', $this);
    }

    public function followUpManagerPermission(): string
    {
        return 'finance.assign';
    }

    public function followUpBranchId(): int
    {
        return $this->branch_id;
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<FulfilmentTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(FulfilmentTask::class, 'fulfilment_task_id');
    }

    /**
     * @return BelongsTo<WorkflowStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'stage_id');
    }

    /**
     * @return BelongsTo<Financer, $this>
     */
    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class);
    }

    /**
     * @return BelongsTo<FinancerContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(FinancerContact::class, 'financer_contact_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_employee_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return MorphMany<FollowUp, $this>
     */
    public function followUps(): MorphMany
    {
        return $this->morphMany(FollowUp::class, 'followable')->orderBy('due_at');
    }

    /**
     * @return MorphMany<FileQuery, $this>
     */
    public function queries(): MorphMany
    {
        return $this->morphMany(FileQuery::class, 'queryable')->latest('id');
    }

    /**
     * @return MorphMany<WorkflowStatusHistory, $this>
     */
    public function statusHistory(): MorphMany
    {
        return $this->morphMany(WorkflowStatusHistory::class, 'subject')->latest('id');
    }

    /**
     * @param  Builder<FinanceFile>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn($this->qualifyColumn('order_id'), Order::query()->visibleTo($user)->select('orders.id'));
    }
}
