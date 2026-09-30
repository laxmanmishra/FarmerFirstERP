<?php

namespace App\Models;

use App\Enums\FollowUpStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Scheduled action against any record (enquiry now; department files later).
 * Overdue / due-today are derived from due_at (SRS §29).
 */
#[Fillable([
    'followable_type', 'followable_id', 'branch_id', 'assigned_employee_id', 'type_code', 'due_at', 'purpose',
    'status', 'completed_at', 'completed_by', 'outcome', 'reminded_at', 'overdue_notified_at',
])]
class FollowUp extends Model
{
    use Auditable, HasFactory, HasUserstamps;

    protected string $auditModule = 'follow_ups';

    /** @var list<string> */
    protected array $auditExclude = ['reminded_at', 'overdue_notified_at'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'status' => FollowUpStatus::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminded_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function followable(): MorphTo
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
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isOverdue(): bool
    {
        return $this->status === FollowUpStatus::Pending && $this->due_at->isPast();
    }

    /**
     * @param  Builder<FollowUp>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', FollowUpStatus::Pending);
    }

    /**
     * @param  Builder<FollowUp>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->pending()->where('due_at', '<', now());
    }

    /**
     * @param  Builder<FollowUp>  $query
     */
    public function scopeDueToday(Builder $query): void
    {
        $query->pending()->whereBetween('due_at', [now(), now()->endOfDay()]);
    }

    /**
     * @param  Builder<FollowUp>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->pending()->where('due_at', '>', now()->endOfDay());
    }

    /**
     * Own follow-ups, team follow-ups for managers, everything in permitted branches for view_all holders.
     *
     * @param  Builder<FollowUp>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->hasAllBranchAccess()) {
            $query->whereIn('branch_id', $user->accessibleBranches()->pluck('id'));
        }

        if ($user->can('enquiries.view_all')) {
            return;
        }

        $employee = $user->employee;

        if ($employee === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('assigned_employee_id', $user->can('enquiries.view_team') ? $employee->teamMemberIds() : [$employee->id]);
    }
}
