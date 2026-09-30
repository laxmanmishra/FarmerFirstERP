<?php

namespace App\Actions\Pipeline;

use App\Exceptions\BusinessRuleException;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\AuditService;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\DB;

/**
 * Reopens a closed enquiry with a mandatory reason (SRS §11). A rejected enquiry
 * returns to the validation queue; a lost/dropped one returns to its last open
 * pipeline stage. WON enquiries continue as customers/deals and cannot be reopened.
 */
class ReopenEnquiry
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly AuditService $audit,
    ) {}

    public function handle(User $actor, Enquiry $enquiry, string $reason, ?int $reopenRequestId = null): Enquiry
    {
        $enquiry->loadMissing(['validationStage', 'pipelineStage']);

        if (! $enquiry->isClosed()) {
            throw new BusinessRuleException(__('This enquiry is already open.'), 'enquiry_open');
        }

        if ($enquiry->pipelineStage?->is_completion) {
            throw new BusinessRuleException(__('Won enquiries cannot be reopened; continue with the customer and deal.'), 'won_not_reopenable');
        }

        if (trim($reason) === '') {
            throw new BusinessRuleException(__('A reason is required to reopen an enquiry.'), 'reason_required');
        }

        DB::transaction(function () use ($actor, $enquiry, $reason, $reopenRequestId): void {
            $meta = ['event' => 'reopened', 'reopen_request_id' => $reopenRequestId];

            if ($enquiry->pipeline_stage_id !== null) {
                $target = $this->workflow->lastOpenStage($enquiry, WorkflowDefinition::SALES_PIPELINE)
                    ?? $this->workflow->initialStage(WorkflowDefinition::SALES_PIPELINE);
                $this->workflow->transition($enquiry, 'pipeline_stage_id', $target, $actor, $reason, $meta, force: true);
            } else {
                $target = $this->workflow->initialStage(WorkflowDefinition::ENQUIRY_VALIDATION);
                $this->workflow->transition($enquiry, 'validation_stage_id', $target, $actor, $reason, $meta, force: true);
            }

            $enquiry->forceFill([
                'closed_at' => null,
                'close_reason_code' => null,
                'close_remarks' => null,
                'last_activity_at' => now(),
            ])->save();

            $this->audit->record('reopened', 'enquiries', $enquiry, reason: $reason);
        });

        return $enquiry;
    }
}
