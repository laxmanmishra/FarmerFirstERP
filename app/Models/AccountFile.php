<?php

namespace App\Models;

use App\Enums\AccountPosition;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Accounts file (SRS §91–113): one per order; many payments, each verified and cleared
 * on its own. Balances are always computed from the payments (INV-11).
 */
#[Fillable(['file_no', 'order_id', 'fulfilment_task_id', 'branch_id', 'stage_id', 'receivable_amount', 'customer_share', 'finance_share', 'responsible_employee_id', 'remarks'])]
class AccountFile extends Model
{
    use Auditable, HasUserstamps;

    protected string $auditModule = 'accounts';

    protected function casts(): array
    {
        return array_fill_keys(['receivable_amount', 'customer_share', 'finance_share'], 'decimal:2');
    }

    /**
     * Sum of cleared money, net of reversals and refunds.
     */
    public function clearedTotal(): string
    {
        return Money::add(...$this->payments->whereIn('status', PaymentStatus::counted())->pluck('amount')->all());
    }

    /**
     * Money received but not yet cleared (pending verification or verified).
     */
    public function unclearedTotal(): string
    {
        return Money::add(...$this->payments->whereIn('status', [PaymentStatus::PendingVerification, PaymentStatus::Verified])->pluck('amount')->all());
    }

    public function balance(): string
    {
        return Money::sub($this->receivable_amount, $this->clearedTotal());
    }

    public function position(): AccountPosition
    {
        return AccountPosition::of($this->receivable_amount, $this->clearedTotal());
    }

    /**
     * Cleared money that may be refunded: the excess over the receivable, or everything
     * when the order was cancelled — minus refunds already requested or approved.
     */
    public function refundable(): string
    {
        $base = $this->order->isCancelled() ? $this->clearedTotal() : Money::sub($this->clearedTotal(), $this->receivable_amount);
        $open = Money::add(...$this->refunds->whereIn('status', [RefundStatus::Requested, RefundStatus::Approved])->pluck('amount')->all());
        $available = Money::sub($base, $open);

        return Money::compare($available, 0) > 0 ? $available : '0.00';
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
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /**
     * @return HasMany<Receipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class)->orderBy('id');
    }

    /**
     * @return HasMany<RefundRequest, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(RefundRequest::class)->latest('id');
    }

    /**
     * @return MorphMany<WorkflowStatusHistory, $this>
     */
    public function statusHistory(): MorphMany
    {
        return $this->morphMany(WorkflowStatusHistory::class, 'subject')->latest('id');
    }

    /**
     * @param  Builder<AccountFile>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn($this->qualifyColumn('order_id'), Order::query()->visibleTo($user)->select('orders.id'));
    }
}
