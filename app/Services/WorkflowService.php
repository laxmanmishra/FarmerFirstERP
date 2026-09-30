<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowStatusHistory;
use App\Models\WorkflowTransition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Generic configurable status engine (SRS §68–70, §76–78).
 *
 * - Uncontrolled definitions allow a move to any other active stage.
 * - Controlled definitions allow only configured, effective transitions the user's roles permit.
 * - Moves out of a final stage are refused; reopening is an explicit, audited action that uses force.
 * - Stage flags (requires_remark, requires_followup) are enforced on entry.
 * - Every move writes an immutable WorkflowStatusHistory row and an audit entry.
 */
class WorkflowService
{
    public function __construct(private readonly AuditService $audit) {}

    public function initialStage(string $definitionCode): WorkflowStage
    {
        $stage = WorkflowStage::query()->ofDefinition($definitionCode)->active()->where('is_initial', true)->orderBy('sequence')->first();

        if ($stage === null) {
            throw new BusinessRuleException(__('Workflow [:code] has no active initial stage. Configure one in Workflow Configuration.', ['code' => $definitionCode]), 'workflow_no_initial_stage');
        }

        return $stage;
    }

    /**
     * @return Collection<int, WorkflowStage>
     */
    public function activeStages(string $definitionCode): Collection
    {
        return WorkflowStage::query()->ofDefinition($definitionCode)->active()->orderBy('sequence')->orderBy('id')->get();
    }

    /**
     * Stages the user may move a record to from $current.
     *
     * @return Collection<int, WorkflowStage>
     */
    public function availableTargets(WorkflowStage $current, User $user): Collection
    {
        if ($current->is_final) {
            return new Collection;
        }

        $definition = $current->definition;

        if (! $definition->controlled_transitions) {
            return WorkflowStage::query()->where('workflow_definition_id', $definition->id)->active()
                ->whereKeyNot($current->id)->orderBy('sequence')->get();
        }

        $targetIds = $this->permittedTransitions($current, $user)
            ->reject(fn (WorkflowTransition $transition) => $transition->requires_approval)
            ->pluck('to_stage_id');

        return WorkflowStage::query()->whereKey($targetIds)->active()->orderBy('sequence')->get();
    }

    /**
     * Moves $subject->{$column} to $to.
     *
     * @param  array<string, mixed>  $meta  e.g. ['follow_up_scheduled' => true]
     * @param  bool  $force  bypasses transition rules (system placement, approved reopen) but never stage flags
     */
    public function transition(
        Model $subject,
        string $column,
        WorkflowStage $to,
        User $user,
        ?string $remarks = null,
        array $meta = [],
        bool $force = false,
    ): WorkflowStatusHistory {
        return DB::transaction(function () use ($subject, $column, $to, $user, $remarks, $meta, $force): WorkflowStatusHistory {
            $from = $subject->{$column} ? WorkflowStage::query()->find($subject->{$column}) : null;

            if ($from !== null && $from->workflow_definition_id !== $to->workflow_definition_id) {
                throw new BusinessRuleException(__('The selected status belongs to a different workflow.'), 'workflow_mismatch');
            }

            if (! $to->is_active) {
                throw new BusinessRuleException(__('The status ":name" is no longer active.', ['name' => $to->name]), 'workflow_inactive_stage');
            }

            if (! $force && $from !== null && ! $this->availableTargets($from, $user)->contains('id', $to->id)) {
                throw new BusinessRuleException(__('Moving from ":from" to ":to" is not permitted.', ['from' => $from->name, 'to' => $to->name]), 'workflow_transition_not_allowed');
            }

            $this->assertEntryRequirements($to, $remarks, $meta);

            $subject->forceFill([$column => $to->id])->save();

            $history = WorkflowStatusHistory::create([
                'workflow_definition_id' => $to->workflow_definition_id,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'from_stage_id' => $from?->id,
                'to_stage_id' => $to->id,
                'remarks' => $remarks,
                'meta' => $meta === [] ? null : $meta,
                'user_id' => $user->id,
            ]);

            $this->audit->record('status_changed', $subject->getTable(), $subject,
                [$column => $from?->code], [$column => $to->code], $remarks);

            return $history;
        });
    }

    /**
     * History row for a record created directly in its initial stage.
     *
     * @param  array<string, mixed>  $meta
     */
    public function recordInitial(Model $subject, WorkflowStage $stage, User $user, array $meta = []): WorkflowStatusHistory
    {
        return WorkflowStatusHistory::create([
            'workflow_definition_id' => $stage->workflow_definition_id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'from_stage_id' => null,
            'to_stage_id' => $stage->id,
            'meta' => $meta === [] ? null : $meta,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Last non-final stage the subject held in this definition (for reopening).
     */
    public function lastOpenStage(Model $subject, string $definitionCode): ?WorkflowStage
    {
        $definitionId = WorkflowDefinition::byCode($definitionCode)->id;

        return WorkflowStatusHistory::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('workflow_definition_id', $definitionId)
            ->whereHas('toStage', fn ($query) => $query->where('is_final', false)->where('is_active', true))
            ->latest('id')
            ->first()?->toStage;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function assertEntryRequirements(WorkflowStage $to, ?string $remarks, array $meta): void
    {
        if ($to->requires_remark && trim((string) $remarks) === '') {
            throw new BusinessRuleException(__('A remark is required when moving to ":name".', ['name' => $to->name]), 'workflow_remark_required');
        }

        if ($to->requires_followup && empty($meta['follow_up_scheduled'])) {
            throw new BusinessRuleException(__('A next follow-up date is required when moving to ":name".', ['name' => $to->name]), 'workflow_followup_required');
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, WorkflowTransition>
     */
    private function permittedTransitions(WorkflowStage $current, User $user): \Illuminate\Support\Collection
    {
        return WorkflowTransition::query()
            ->where('workflow_definition_id', $current->workflow_definition_id)
            ->where(fn ($query) => $query->where('from_stage_id', $current->id)->orWhereNull('from_stage_id'))
            ->active()
            ->get()
            ->filter(fn (WorkflowTransition $transition) => $transition->isEffective() && $transition->permitsUser($user))
            ->values();
    }
}
