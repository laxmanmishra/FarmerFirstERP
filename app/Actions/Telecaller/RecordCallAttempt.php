<?php

namespace App\Actions\Telecaller;

use App\Exceptions\BusinessRuleException;
use App\Models\CallAttempt;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Services\TerritoryService;
use App\Services\WorkflowService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Records a telecaller call and applies its outcome to the validation workflow (SRS §10):
 *
 * - outcome flagged completion (VALID) → enters the sales pipeline at its initial stage
 * - outcome flagged final otherwise (INVALID, WRONG NUMBER …) → closed, reason retained
 * - non-final outcome (CALLBACK, NO ANSWER …) → stays in the queue, callback time kept
 *
 * The telecaller must hold the claim. The claim is released after every call.
 */
class RecordCallAttempt
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly TerritoryService $territory,
    ) {}

    public function handle(
        User $actor,
        Enquiry $enquiry,
        WorkflowStage $outcome,
        ?string $remarks,
        ?int $durationSeconds = null,
        ?CarbonInterface $nextCallbackAt = null,
    ): CallAttempt {
        $telecaller = $actor->employee;

        if ($telecaller === null || $enquiry->claimed_by_employee_id !== $telecaller->id) {
            throw new BusinessRuleException(__('Claim the enquiry before recording a call.'), 'claim_required');
        }

        if (! $enquiry->isAwaitingValidation()) {
            throw new BusinessRuleException(__('This enquiry is no longer awaiting validation.'), 'not_in_validation_queue');
        }

        if ($outcome->definition->code !== WorkflowDefinition::ENQUIRY_VALIDATION || $outcome->is_initial) {
            throw new BusinessRuleException(__('Choose a call outcome.'), 'invalid_outcome');
        }

        if ($nextCallbackAt !== null && $nextCallbackAt->isPast()) {
            throw new BusinessRuleException(__('The callback time must be in the future.'), 'past_callback');
        }

        if ($outcome->requires_remark && trim((string) $remarks) === '') {
            throw new BusinessRuleException(__('A remark is required when moving to ":name".', ['name' => $outcome->name]), 'workflow_remark_required');
        }

        if ($outcome->requires_followup && $nextCallbackAt === null) {
            throw new BusinessRuleException(__('A next follow-up date is required when moving to ":name".', ['name' => $outcome->name]), 'workflow_followup_required');
        }

        return DB::transaction(function () use ($actor, $enquiry, $outcome, $remarks, $durationSeconds, $nextCallbackAt, $telecaller): CallAttempt {
            $attempt = CallAttempt::create([
                'enquiry_id' => $enquiry->id,
                'employee_id' => $telecaller->id,
                'called_at' => now(),
                'duration_seconds' => $durationSeconds,
                'outcome_stage_id' => $outcome->id,
                'remarks' => $remarks,
                'next_callback_at' => $nextCallbackAt,
            ]);

            if ($enquiry->validation_stage_id !== $outcome->id) {
                $this->workflow->transition($enquiry, 'validation_stage_id', $outcome, $actor, $remarks,
                    ['call_attempt_id' => $attempt->id, 'follow_up_scheduled' => $nextCallbackAt !== null]);
            }

            $changes = [
                'claimed_by_employee_id' => null,
                'claimed_at' => null,
                'callback_at' => $outcome->is_final ? null : $nextCallbackAt,
                'last_activity_at' => now(),
            ];

            if ($outcome->is_completion) {
                $changes += ['validated_at' => now(), 'validated_by_employee_id' => $telecaller->id];

                if ($enquiry->assigned_employee_id === null) {
                    $changes['assigned_employee_id'] = $this->territory->primarySalesmanFor($enquiry->village)?->id;
                }
            } elseif ($outcome->is_final) {
                $changes += ['closed_at' => now(), 'close_remarks' => $remarks];
            }

            $enquiry->forceFill($changes)->save();

            if ($outcome->is_completion) {
                $pipelineStart = $this->workflow->initialStage(WorkflowDefinition::SALES_PIPELINE);
                $enquiry->forceFill(['pipeline_stage_id' => $pipelineStart->id])->save();
                $this->workflow->recordInitial($enquiry, $pipelineStart, $actor, ['event' => 'validated', 'call_attempt_id' => $attempt->id]);
            }

            return $attempt;
        });
    }
}
