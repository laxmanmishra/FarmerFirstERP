<?php

namespace App\Models;

use App\Enums\RequirementState;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One department's work on an order. The requirement state (is the work needed?) and the
 * operational stage (how far is it?) are separate: a waived task is never completed (INV-02).
 */
#[Fillable([
    'fulfilment_id', 'fulfilment_task_type_id', 'department_id', 'requirement_state', 'stage_id', 'blocks_delivery',
    'responsible_employee_id', 'due_date', 'started_at', 'completed_at', 'requirement_remarks',
])]
class FulfilmentTask extends Model
{
    use Auditable, HasUserstamps;

    public const STAGE_PENDING = 'PENDING';

    public const STAGE_CANCELLED = 'CANCELLED';

    protected string $auditModule = 'fulfilment';

    protected function casts(): array
    {
        return [
            'requirement_state' => RequirementState::class,
            'blocks_delivery' => 'boolean',
            'due_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->stage->is_completion;
    }

    /**
     * @return BelongsTo<Fulfilment, $this>
     */
    public function fulfilment(): BelongsTo
    {
        return $this->belongsTo(Fulfilment::class);
    }

    /**
     * @return BelongsTo<FulfilmentTaskType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(FulfilmentTaskType::class, 'fulfilment_task_type_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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
     * @return HasMany<DocumentRequirement, $this>
     */
    public function documentRequirements(): HasMany
    {
        return $this->hasMany(DocumentRequirement::class);
    }

    /**
     * @return MorphMany<WorkflowStatusHistory, $this>
     */
    public function statusHistory(): MorphMany
    {
        return $this->morphMany(WorkflowStatusHistory::class, 'subject')->latest('id');
    }
}
