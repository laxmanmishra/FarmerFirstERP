<?php

namespace App\Models;

use App\Enums\TaskCondition;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configured department task created for each new order (SRS §23), e.g. Finance when
 * the deal needs finance. `update_permission` decides who may work the task.
 */
#[Fillable(['code', 'name', 'department_id', 'condition', 'blocks_delivery', 'update_permission', 'sort_order', 'is_active'])]
class FulfilmentTaskType extends Model
{
    use Auditable, HasActiveFlag, HasUserstamps;

    protected string $auditModule = 'fulfilment';

    protected function casts(): array
    {
        return ['condition' => TaskCondition::class, 'blocks_delivery' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
