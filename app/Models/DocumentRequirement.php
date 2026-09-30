<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\RequirementState;
use App\Enums\RequirementStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "This order needs this document for this department" (SRS §199). Existence,
 * verification and satisfaction stay separate questions (INV-09); the status is always
 * derived from the linked document, never stored.
 */
#[Fillable([
    'order_id', 'document_type_id', 'department_id', 'document_requirement_rule_id', 'fulfilment_task_id', 'requirement_state',
    'blocks_delivery', 'due_date', 'responsible_employee_id', 'document_id', 'linked_by', 'linked_at',
])]
class DocumentRequirement extends Model
{
    use Auditable, HasUserstamps;

    protected string $auditModule = 'documents';

    /** Ageing buckets in days (SRS §213): label => [from, to|null]. */
    public const AGEING_BUCKETS = ['0-1' => [0, 1], '2-3' => [2, 3], '4-7' => [4, 7], '8-15' => [8, 15], '15+' => [16, null]];

    protected function casts(): array
    {
        return [
            'requirement_state' => RequirementState::class,
            'blocks_delivery' => 'boolean',
            'due_date' => 'date',
            'linked_at' => 'datetime',
        ];
    }

    /**
     * Needs `document.type` loaded.
     */
    public function status(): RequirementStatus
    {
        return match (true) {
            $this->requirement_state === RequirementState::NotRequired => RequirementStatus::NotRequired,
            $this->requirement_state === RequirementState::Waived => RequirementStatus::Waived,
            $this->document === null => RequirementStatus::Pending,
            $this->document->status === DocumentStatus::Rejected => RequirementStatus::Rejected,
            $this->document->isExpired() => RequirementStatus::Expired,
            default => RequirementStatus::from($this->document->status->value),
        };
    }

    public function isSatisfied(): bool
    {
        return $this->requirement_state !== RequirementState::NotRequired && $this->document !== null && $this->document->isUsable();
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null && $this->due_date->lt(today()) && ! $this->isSatisfied()
            && in_array($this->requirement_state, [RequirementState::Required, RequirementState::Conditional], true);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<FulfilmentTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(FulfilmentTask::class, 'fulfilment_task_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_employee_id');
    }

    /**
     * Requirements of orders that are not cancelled.
     *
     * @param  Builder<DocumentRequirement>  $query
     */
    public function scopeOnOpenOrders(Builder $query): void
    {
        $query->whereIn($this->qualifyColumn('order_id'), Order::query()->whereNull('cancelled_at')->select('id'));
    }

    /**
     * @param  Builder<DocumentRequirement>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn($this->qualifyColumn('order_id'), Order::query()->visibleTo($user)->select('orders.id'));
    }

    /**
     * @param  Builder<DocumentRequirement>  $query
     */
    public function scopeWithStatus(Builder $query, RequirementStatus $status): void
    {
        if ($status === RequirementStatus::NotRequired || $status === RequirementStatus::Waived) {
            $query->where('requirement_state', $status->value);

            return;
        }

        $query->whereIn('requirement_state', [RequirementState::Required, RequirementState::Conditional]);

        match ($status) {
            RequirementStatus::Pending => $query->whereNull('document_id'),
            RequirementStatus::Rejected => $query->whereHas('document', fn (Builder $query) => $query->where('status', DocumentStatus::Rejected)),
            RequirementStatus::Expired => $query->whereHas('document', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('status', DocumentStatus::Expired)
                ->orWhere(fn (Builder $query) => $query->where('status', '!=', DocumentStatus::Rejected)->whereDate('expiry_date', '<', today())))),
            default => $query->whereHas('document', fn (Builder $query) => $query->current()->where('status', $status->value)),
        };
    }

    /**
     * Needed (required or conditional, not waived) and not yet satisfied by a usable document.
     *
     * @param  Builder<DocumentRequirement>  $query
     */
    public function scopeUnsatisfied(Builder $query): void
    {
        $query->whereIn('requirement_state', [RequirementState::Required, RequirementState::Conditional])
            ->whereDoesntHave('document', fn (Builder $query) => $query->current()->where(fn (Builder $query) => $query
                ->where('status', DocumentStatus::Verified)
                ->orWhereHas('type', fn (Builder $query) => $query->where('verification_required', false))));
    }

    /**
     * "Documents Blocking Delivery" (SRS §216).
     *
     * @param  Builder<DocumentRequirement>  $query
     */
    public function scopeBlockingDelivery(Builder $query): void
    {
        $query->onOpenOrders()->unsatisfied()->where('blocks_delivery', true);
    }

    /**
     * Unsatisfied requirements whose age (days since created) falls into an ageing bucket.
     *
     * @param  Builder<DocumentRequirement>  $query
     */
    public function scopeAgedBetween(Builder $query, int $fromDays, ?int $toDays): void
    {
        $query->where('created_at', '<=', now()->subDays($fromDays)->endOfDay())
            ->when($toDays !== null, fn (Builder $query) => $query->where('created_at', '>=', now()->subDays($toDays)->startOfDay()));
    }
}
