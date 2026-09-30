<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permitted move between stages when the definition uses controlled transitions (SRS §68, §78).
 * A null from_stage_id means "from any stage".
 */
#[Fillable(['workflow_definition_id', 'from_stage_id', 'to_stage_id', 'allowed_roles', 'requires_approval', 'effective_from', 'effective_to', 'is_active'])]
class WorkflowTransition extends Model
{
    use Auditable, HasActiveFlag, HasUserstamps;

    protected string $auditModule = 'workflow';

    protected function casts(): array
    {
        return [
            'allowed_roles' => 'array',
            'requires_approval' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<WorkflowStage, $this>
     */
    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'from_stage_id');
    }

    /**
     * @return BelongsTo<WorkflowStage, $this>
     */
    public function toStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'to_stage_id');
    }

    public function isEffective(): bool
    {
        $today = now()->startOfDay();

        return $this->is_active
            && ($this->effective_from === null || $this->effective_from->lte($today))
            && ($this->effective_to === null || $this->effective_to->gte($today));
    }

    public function permitsUser(User $user): bool
    {
        return empty($this->allowed_roles) || $user->isSuperAdmin() || $user->hasAnyRole($this->allowed_roles);
    }
}
