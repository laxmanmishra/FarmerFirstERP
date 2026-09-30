<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Immutable status-change record (SRS §70, INV-10).
 */
#[Fillable(['workflow_definition_id', 'subject_type', 'subject_id', 'from_stage_id', 'to_stage_id', 'remarks', 'meta', 'user_id'])]
class WorkflowStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Status history is immutable.'));
        static::deleting(fn () => throw new LogicException('Status history cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
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

    /**
     * @return BelongsTo<WorkflowDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
