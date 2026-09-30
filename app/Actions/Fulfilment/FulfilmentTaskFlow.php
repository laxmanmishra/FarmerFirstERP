<?php

namespace App\Actions\Fulfilment;

use App\Enums\RequirementState;
use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\FulfilmentTask;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Services\DepartmentFileProvisioner;
use App\Services\DocumentRequirementService;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Department work on a fulfilment task (SRS §23): operational status, requirement
 * state and the responsible employee. Waivers (Phase 7) are the only way to WAIVED.
 */
class FulfilmentTaskFlow
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly DocumentRequirementService $requirements,
        private readonly DepartmentFileProvisioner $files,
    ) {}

    /**
     * Stages the user may move the task to (never the system Cancelled stage).
     *
     * @return Collection<int, WorkflowStage>
     */
    public function targets(FulfilmentTask $task, User $user): Collection
    {
        $task->loadMissing(['type', 'stage.definition', 'fulfilment.order']);

        if (! $this->canWork($user, $task) || $task->type->driven_by !== null || ! $task->requirement_state->isApplicable() || $task->fulfilment->order->isCancelled()) {
            return new Collection;
        }

        return $this->workflow->availableTargets($task->stage, $user)->reject(fn (WorkflowStage $stage) => $stage->code === FulfilmentTask::STAGE_CANCELLED)->values();
    }

    public function canWork(User $user, FulfilmentTask $task): bool
    {
        return $user->can($task->type->update_permission);
    }

    public function move(User $actor, FulfilmentTask $task, WorkflowStage $to, ?string $remarks): FulfilmentTask
    {
        $task->loadMissing(['type', 'stage', 'fulfilment.order.stage']);
        $this->assertWorkable($actor, $task);

        if (! $task->requirement_state->isApplicable()) {
            throw new BusinessRuleException(__('This task is not required for the order.'), 'task_not_required');
        }

        if ($to->code === FulfilmentTask::STAGE_CANCELLED) {
            throw new BusinessRuleException(__('Tasks are cancelled only by cancelling the order.'), 'task_cancel_via_order');
        }

        if ($task->type->driven_by !== null) {
            throw new BusinessRuleException(__(':task follows its department file; update it there.', ['task' => $task->type->name]), 'task_driven_by_file');
        }

        return DB::transaction(function () use ($actor, $task, $to, $remarks): FulfilmentTask {
            $this->apply($actor, $task, $to, $remarks, force: false);

            return $task->fresh(['stage']);
        });
    }

    /**
     * Moves a file-driven task to follow its department file: completion → Completed,
     * hold or final rejection → On hold, initial → Pending, anything else → In progress.
     */
    public function follow(User $actor, ?FulfilmentTask $task, WorkflowStage $source, ?string $remarks = null): void
    {
        if ($task === null) {
            return;
        }

        $code = match (true) {
            $source->is_completion => 'COMPLETED',
            $source->is_hold || ($source->is_final && $source->is_rejection) => 'ON_HOLD',
            $source->is_initial => FulfilmentTask::STAGE_PENDING,
            default => 'IN_PROGRESS',
        };

        $this->followCode($actor, $task, $code, $code === 'ON_HOLD' ? trim($source->name.': '.($remarks ?? ''), ': ') : null);
    }

    /**
     * Moves a file-driven task to the stage with the given code (no-op when already there,
     * when the order is cancelled or when that stage was deactivated).
     */
    public function followCode(User $actor, FulfilmentTask $task, string $code, ?string $remarks = null): void
    {
        $task->loadMissing(['stage', 'fulfilment.order.stage']);
        $to = WorkflowStage::query()->ofDefinition(WorkflowDefinition::FULFILMENT_TASK)->active()->where('code', $code)->first();

        if ($to === null || $task->stage_id === $to->id || $task->fulfilment->order->isCancelled()) {
            return;
        }

        $this->apply($actor, $task, $to, $remarks, force: true);
        $task->unsetRelation('stage');
    }

    private function apply(User $actor, FulfilmentTask $task, WorkflowStage $to, ?string $remarks, bool $force): void
    {
        $this->workflow->transition($task, 'stage_id', $to, $actor, $remarks, force: $force);

        $task->update([
            'started_at' => $task->started_at ?? ($to->is_initial ? null : now()),
            'completed_at' => $to->is_completion ? now() : null,
        ]);

        $order = $task->fulfilment->order;

        if ($order->stage->code === Order::STAGE_BOOKED && ! $to->is_initial) {
            $this->workflow->transition($order, 'stage_id', WorkflowStage::findByCode(WorkflowDefinition::ORDER, Order::STAGE_IN_FULFILMENT), $actor, force: true);
            $order->unsetRelation('stage');
        }
    }

    /**
     * Changes whether the work is needed on this order (e.g. the customer arranges
     * their own insurance). Reason required; document requirements follow.
     */
    public function changeRequirement(User $actor, FulfilmentTask $task, RequirementState $state, string $reason): FulfilmentTask
    {
        $task->loadMissing(['type', 'stage', 'fulfilment.order']);

        if (! $actor->can('orders.create')) {
            throw new BusinessRuleException(__('You are not allowed to change fulfilment requirements.'), 'not_allowed');
        }

        if (! in_array($state, [RequirementState::Required, RequirementState::NotRequired, RequirementState::Conditional], true)) {
            throw new BusinessRuleException(__('Waivers are requested through the waiver workflow.'), 'waiver_via_workflow');
        }

        if ($task->requirement_state === RequirementState::Waived) {
            throw new BusinessRuleException(__('A waived task changes only through its waiver.'), 'task_waived');
        }

        if ($task->fulfilment->order->isCancelled()) {
            throw new BusinessRuleException(__('The order is cancelled.'), 'order_cancelled');
        }

        if ($task->stage->is_completion) {
            throw new BusinessRuleException(__('The task is already completed.'), 'task_completed');
        }

        if (trim($reason) === '') {
            throw new BusinessRuleException(__('Give a reason for the change.'), 'reason_required');
        }

        if ($task->requirement_state === $state) {
            return $task;
        }

        DB::transaction(function () use ($actor, $task, $state, $reason): void {
            $task->update([
                'requirement_state' => $state,
                'blocks_delivery' => $state !== RequirementState::NotRequired && $task->type->blocks_delivery,
                'requirement_remarks' => $reason,
            ]);

            $order = $task->fulfilment->order->fresh();
            $this->files->provision($order, $actor);
            $this->requirements->sync($order, $actor);
        });

        return $task;
    }

    public function assign(User $actor, FulfilmentTask $task, ?Employee $employee): FulfilmentTask
    {
        $task->loadMissing(['type', 'stage', 'fulfilment.order']);
        $this->assertWorkable($actor, $task);

        if ($employee !== null && ! $employee->departments()->whereKey($task->department_id)->exists()) {
            throw new BusinessRuleException(__(':name does not work in this department.', ['name' => $employee->name]), 'employee_not_in_department');
        }

        if ($employee !== null && ! $employee->is_active) {
            throw new BusinessRuleException(__(':name is inactive.', ['name' => $employee->name]), 'employee_inactive');
        }

        DB::transaction(function () use ($task, $employee): void {
            $task->update(['responsible_employee_id' => $employee?->id]);
            $task->documentRequirements()->update(['responsible_employee_id' => $employee?->id]);
        });

        return $task;
    }

    private function assertWorkable(User $actor, FulfilmentTask $task): void
    {
        if (! $this->canWork($actor, $task)) {
            throw new BusinessRuleException(__('Only :department can work on this task.', ['department' => $task->department()->value('name')]), 'not_task_department');
        }

        if ($task->fulfilment->order->isCancelled()) {
            throw new BusinessRuleException(__('The order is cancelled.'), 'order_cancelled');
        }
    }
}
