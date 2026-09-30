<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Every telecaller call is its own immutable record (SRS §10).
 */
#[Fillable(['enquiry_id', 'employee_id', 'called_at', 'duration_seconds', 'outcome_stage_id', 'remarks', 'next_callback_at'])]
class CallAttempt extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Call attempts are immutable.'));
        static::deleting(fn () => throw new LogicException('Call attempts cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['called_at' => 'datetime', 'next_callback_at' => 'datetime', 'duration_seconds' => 'integer'];
    }

    /**
     * @return BelongsTo<Enquiry, $this>
     */
    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<WorkflowStage, $this>
     */
    public function outcome(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'outcome_stage_id');
    }
}
