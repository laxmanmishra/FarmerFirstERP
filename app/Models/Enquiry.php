<?php

namespace App\Models;

use App\Enums\DealType;
use App\Enums\Temperature;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Enquiry (SRS §7–11). Two independent configurable workflows:
 * validation (telecaller, `validation_stage_id`) and sales pipeline
 * (`pipeline_stage_id`, set once the enquiry is validated).
 */
#[Fillable([
    'enquiry_no', 'farmer_id', 'branch_id', 'village_id', 'created_by_employee_id', 'assigned_employee_id',
    'source_code', 'deal_type', 'expected_purchase_date', 'temperature', 'budget', 'remarks',
    'validation_stage_id', 'pipeline_stage_id', 'claimed_by_employee_id', 'claimed_at', 'callback_at',
    'validated_at', 'validated_by_employee_id', 'closed_at', 'close_reason_code', 'close_remarks',
    'duplicate_override_reason', 'last_activity_at',
])]
class Enquiry extends Model
{
    use Auditable, HasFactory, HasUserstamps;

    /** @var list<string> */
    protected array $auditExclude = ['last_activity_at', 'temperature', 'claimed_at', 'claimed_by_employee_id'];

    protected function casts(): array
    {
        return [
            'deal_type' => DealType::class,
            'temperature' => Temperature::class,
            'expected_purchase_date' => 'date',
            'budget' => 'decimal:2',
            'claimed_at' => 'datetime',
            'callback_at' => 'datetime',
            'validated_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Farmer, $this>
     */
    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Village, $this>
     */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function creatorEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by_employee_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'claimed_by_employee_id');
    }

    /**
     * @return BelongsTo<WorkflowStage, $this>
     */
    public function validationStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'validation_stage_id');
    }

    /**
     * @return BelongsTo<WorkflowStage, $this>
     */
    public function pipelineStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'pipeline_stage_id');
    }

    /**
     * @return HasMany<EnquiryRequirement, $this>
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(EnquiryRequirement::class);
    }

    /**
     * @return HasOne<ExchangeTractor, $this>
     */
    public function exchangeTractor(): HasOne
    {
        return $this->hasOne(ExchangeTractor::class);
    }

    /**
     * @return HasMany<EnquiryAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(EnquiryAttachment::class);
    }

    /**
     * @return HasMany<CallAttempt, $this>
     */
    public function callAttempts(): HasMany
    {
        return $this->hasMany(CallAttempt::class)->latest('called_at')->latest('id');
    }

    /**
     * @return HasMany<EnquiryAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(EnquiryAssignment::class)->latest('id');
    }

    /**
     * @return MorphMany<FollowUp, $this>
     */
    public function followUps(): MorphMany
    {
        return $this->morphMany(FollowUp::class, 'followable')->orderBy('due_at');
    }

    /**
     * @return HasMany<ReopenRequest, $this>
     */
    public function reopenRequests(): HasMany
    {
        return $this->hasMany(ReopenRequest::class)->latest('id');
    }

    /**
     * @return MorphMany<WorkflowStatusHistory, $this>
     */
    public function statusHistory(): MorphMany
    {
        return $this->morphMany(WorkflowStatusHistory::class, 'subject')->latest('id');
    }

    /**
     * The stage that currently describes the enquiry: its pipeline stage once
     * validated, otherwise its validation stage. Requires both relations loaded.
     */
    public function currentStage(): ?WorkflowStage
    {
        return $this->pipelineStage ?? $this->validationStage;
    }

    /**
     * Closed = rejected in validation or WON/LOST/DROPPED in the pipeline. `closed_at` is
     * maintained by the workflow actions and is the single source of truth.
     */
    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function isAwaitingValidation(): bool
    {
        return $this->pipeline_stage_id === null && ! $this->validationStage->is_final;
    }

    /**
     * Records the user may see: view_all → permitted branches; view_team → own team;
     * view_own → assigned to or created by the user's employee (SRS §5, RBAC_MATRIX).
     *
     * @param  Builder<Enquiry>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->hasAllBranchAccess()) {
            $query->whereIn($this->qualifyColumn('branch_id'), $user->accessibleBranches()->pluck('id'));
        }

        if ($user->can('enquiries.view_all')) {
            return;
        }

        $employee = $user->employee;

        if ($employee === null || ! $user->canAny(['enquiries.view_team', 'enquiries.view_own'])) {
            $query->whereRaw('1 = 0');

            return;
        }

        $employeeIds = $user->can('enquiries.view_team') ? $employee->teamMemberIds() : [$employee->id];

        $query->where(fn (Builder $query) => $query
            ->whereIn($this->qualifyColumn('assigned_employee_id'), $employeeIds)
            ->orWhereIn($this->qualifyColumn('created_by_employee_id'), $employeeIds));
    }

    /**
     * @param  Builder<Enquiry>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at');
    }
}
