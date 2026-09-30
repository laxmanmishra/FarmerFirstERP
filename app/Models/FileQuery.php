<?php

namespace App\Models;

use App\Enums\QueryStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Query from an external party on a department file: Open → In Progress → Submitted →
 * Resolved (SRS §71). Shared by Finance now and RTO / Insurance later.
 */
#[Fillable(['queryable_type', 'queryable_id', 'raised_by_party', 'subject', 'description', 'status', 'due_date', 'assigned_employee_id', 'response', 'resolved_at', 'resolved_by'])]
class FileQuery extends Model
{
    use Auditable, HasUserstamps;

    protected string $auditModule = 'queries';

    protected function casts(): array
    {
        return ['status' => QueryStatus::class, 'due_date' => 'date', 'resolved_at' => 'datetime'];
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_date !== null && $this->due_date->lt(today());
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function queryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
