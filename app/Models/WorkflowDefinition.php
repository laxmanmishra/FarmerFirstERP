<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'module', 'name', 'description', 'controlled_transitions', 'is_active'])]
class WorkflowDefinition extends Model
{
    use Auditable, HasActiveFlag, HasUserstamps;

    public const ENQUIRY_VALIDATION = 'enquiry_validation';

    public const SALES_PIPELINE = 'sales_pipeline';

    public const DEAL = 'deal';

    public const ORDER = 'order';

    public const FULFILMENT_TASK = 'fulfilment_task';

    protected string $auditModule = 'workflow';

    protected function casts(): array
    {
        return ['controlled_transitions' => 'boolean'];
    }

    public static function byCode(string $code): self
    {
        return static::query()->where('code', $code)->firstOrFail();
    }

    /**
     * @return HasMany<WorkflowStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowStage::class)->orderBy('sequence')->orderBy('id');
    }

    /**
     * @return HasMany<WorkflowTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class);
    }
}
