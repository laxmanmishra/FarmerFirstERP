<?php

namespace App\Actions\Pipeline;

use App\Events\EnquiryWon;
use App\Exceptions\BusinessRuleException;
use App\Models\Enquiry;
use App\Models\LookupValue;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\DB;

/**
 * Moves a validated enquiry through the sales pipeline (SRS §11).
 * Final stages close the enquiry; a lost/dropped (final + rejection) stage needs a
 * configured close reason. A WON stage (final + completion) raises EnquiryWon.
 */
class MoveEnquiryStage
{
    public function __construct(private readonly WorkflowService $workflow) {}

    public function handle(User $actor, Enquiry $enquiry, WorkflowStage $to, ?string $remarks = null, ?string $closeReasonCode = null): Enquiry
    {
        if ($enquiry->pipeline_stage_id === null) {
            throw new BusinessRuleException(__('Only validated enquiries are in the sales pipeline.'), 'not_in_pipeline');
        }

        if ($enquiry->isClosed()) {
            throw new BusinessRuleException(__('This enquiry is closed. Reopen it to continue.'), 'enquiry_closed');
        }

        if ($to->definition->code !== WorkflowDefinition::SALES_PIPELINE) {
            throw new BusinessRuleException(__('Choose a pipeline stage.'), 'invalid_stage');
        }

        $isLoss = $to->is_final && $to->is_rejection;

        if ($isLoss && ! LookupValue::options(LookupValue::CLOSE_REASON)->has((string) $closeReasonCode)) {
            throw new BusinessRuleException(__('Choose why the enquiry was :stage.', ['stage' => mb_strtolower($to->name)]), 'close_reason_required');
        }

        DB::transaction(function () use ($actor, $enquiry, $to, $remarks, $closeReasonCode, $isLoss): void {
            $this->workflow->transition($enquiry, 'pipeline_stage_id', $to, $actor, $remarks, $isLoss ? ['close_reason' => $closeReasonCode] : []);

            $enquiry->forceFill([
                'closed_at' => $to->is_final ? now() : null,
                'close_reason_code' => $isLoss ? $closeReasonCode : null,
                'close_remarks' => $to->is_final ? $remarks : null,
                'last_activity_at' => now(),
            ])->save();
        });

        if ($to->is_final && $to->is_completion) {
            EnquiryWon::dispatch($enquiry, $actor);
        }

        return $enquiry;
    }
}
