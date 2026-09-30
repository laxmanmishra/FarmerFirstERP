<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which document a department needs for an order. Without a task type the document is
 * needed on every order; with one it follows that task's requirement state.
 */
#[Fillable(['document_type_id', 'department_id', 'fulfilment_task_type_id', 'blocks_delivery', 'due_offset_days', 'is_active'])]
class DocumentRequirementRule extends Model
{
    use Auditable, HasActiveFlag, HasUserstamps;

    protected string $auditModule = 'documents';

    protected function casts(): array
    {
        return ['blocks_delivery' => 'boolean', 'due_offset_days' => 'integer'];
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
     * @return BelongsTo<FulfilmentTaskType, $this>
     */
    public function taskType(): BelongsTo
    {
        return $this->belongsTo(FulfilmentTaskType::class, 'fulfilment_task_type_id');
    }
}
