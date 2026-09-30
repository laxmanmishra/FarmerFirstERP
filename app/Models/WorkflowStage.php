<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A configurable status. Code relies on its flags (is_final, is_completion,
 * requires_remark, …), never on its name (SRS §76–77).
 */
#[Fillable([
    'workflow_definition_id', 'code', 'name', 'sequence', 'color', 'is_initial', 'is_final', 'is_completion',
    'is_hold', 'is_rejection', 'blocks_delivery', 'requires_remark', 'requires_followup', 'requires_document',
    'sla_hours', 'is_active', 'is_system',
])]
class WorkflowStage extends Model
{
    use Auditable, HasActiveFlag, HasUserstamps;

    public const COLORS = ['slate', 'brand', 'green', 'amber', 'rose', 'sky', 'violet'];

    public const FLAGS = [
        'is_initial', 'is_final', 'is_completion', 'is_hold', 'is_rejection',
        'blocks_delivery', 'requires_remark', 'requires_followup', 'requires_document',
    ];

    /**
     * In-memory defaults matching the column defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true, 'is_system' => false, 'color' => 'slate', 'is_initial' => false, 'is_final' => false, 'is_completion' => false,
        'is_hold' => false, 'is_rejection' => false, 'blocks_delivery' => false, 'requires_remark' => false,
        'requires_followup' => false, 'requires_document' => false,
    ];

    protected string $auditModule = 'workflow';

    protected function casts(): array
    {
        return array_fill_keys(self::FLAGS, 'boolean') + ['is_system' => 'boolean', 'sequence' => 'integer', 'sla_hours' => 'integer'];
    }

    /**
     * @return BelongsTo<WorkflowDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    /**
     * @param  Builder<WorkflowStage>  $query
     */
    public function scopeOfDefinition(Builder $query, string $code): void
    {
        $query->whereHas('definition', fn (Builder $query) => $query->where('code', $code));
    }

    /**
     * @param  Builder<WorkflowStage>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('is_final', false);
    }

    public static function findByCode(string $definition, string $code): self
    {
        return static::query()->ofDefinition($definition)->where('code', $code)->firstOrFail();
    }

    /**
     * Whether the stage has ever been used by a record (then it can only be deactivated, SRS §69).
     */
    public function isUsed(): bool
    {
        return WorkflowStatusHistory::query()->where('to_stage_id', $this->id)->orWhere('from_stage_id', $this->id)->exists();
    }
}
