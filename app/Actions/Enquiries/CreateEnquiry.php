<?php

namespace App\Actions\Enquiries;

use App\Enums\Temperature;
use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\Farmer;
use App\Models\User;
use App\Models\Village;
use App\Models\WorkflowDefinition;
use App\Notifications\EnquiryAssigned;
use App\Services\CrmDuplicateService;
use App\Services\NumberSeriesService;
use App\Services\TerritoryService;
use App\Services\WorkflowService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Creates an enquiry in the common UNVERIFIED queue (SRS §7–10).
 *
 * Assignment: explicit assignee (requires enquiries.assign) → the creator when they
 * only see their own enquiries (a salesman) → the territory's primary salesman → unassigned.
 */
class CreateEnquiry
{
    public function __construct(
        private readonly NumberSeriesService $numbers,
        private readonly WorkflowService $workflow,
        private readonly CrmDuplicateService $duplicates,
        private readonly TerritoryService $territory,
        private readonly SyncEnquiryDetails $details,
    ) {}

    /**
     * @param  list<UploadedFile>  $photos
     */
    public function handle(
        User $actor,
        Farmer $farmer,
        EnquiryData $data,
        ?int $assigneeId = null,
        ?string $duplicateOverrideReason = null,
        array $photos = [],
    ): Enquiry {
        if ($data->expectedPurchaseDate->isBefore(today())) {
            throw new BusinessRuleException(__('The expected purchase date cannot be in the past.'), 'past_expected_date');
        }

        $duplicateOverrideReason = trim((string) $duplicateOverrideReason) ?: null;
        $matches = $this->duplicates->enquiries($farmer, $data->dealType, $data->productIds(), $data->expectedPurchaseDate);

        if ($matches->isNotEmpty() && $duplicateOverrideReason === null) {
            throw new BusinessRuleException(
                __('This farmer already has :count open enquiry(ies) for a similar requirement. Review them, or confirm with a reason that this is a new enquiry.', ['count' => $matches->count()]),
                'duplicate_enquiry',
                ['enquiry_ids' => $matches->pluck('id')->all()],
            );
        }

        $branch = $actor->workingBranch() ?? $farmer->branch;
        $village = Village::query()->findOrFail($data->villageId ?? $farmer->village_id);
        $assignee = $this->resolveAssignee($actor, $village, $assigneeId);

        $enquiry = DB::transaction(function () use ($actor, $farmer, $data, $branch, $village, $assignee, $matches, $duplicateOverrideReason, $photos): Enquiry {
            $initial = $this->workflow->initialStage(WorkflowDefinition::ENQUIRY_VALIDATION);

            $enquiry = Enquiry::create([
                'enquiry_no' => $this->numbers->next('enquiry', $branch),
                'farmer_id' => $farmer->id,
                'branch_id' => $branch->id,
                'village_id' => $village->id,
                'created_by_employee_id' => $actor->employee?->id,
                'assigned_employee_id' => $assignee?->id,
                'source_code' => $data->sourceCode,
                'deal_type' => $data->dealType,
                'expected_purchase_date' => $data->expectedPurchaseDate,
                'temperature' => Temperature::fromExpectedDate($data->expectedPurchaseDate),
                'budget' => $data->budget,
                'remarks' => $data->remarks,
                'validation_stage_id' => $initial->id,
                'duplicate_override_reason' => $matches->isNotEmpty() ? $duplicateOverrideReason : null,
                'last_activity_at' => now(),
            ]);

            $this->details->sync($enquiry, $data, $actor, $photos);
            $this->workflow->recordInitial($enquiry, $initial, $actor, ['event' => 'created']);

            if ($assignee !== null) {
                $enquiry->assignments()->create([
                    'to_employee_id' => $assignee->id,
                    'reason' => $assignee->user_id === $actor->id ? __('Created by salesman') : __('Initial assignment'),
                    'assigned_by' => $actor->id,
                ]);
            }

            return $enquiry;
        });

        if ($assignee?->user !== null && $assignee->user_id !== $actor->id) {
            $assignee->user->notify(new EnquiryAssigned($enquiry));
        }

        return $enquiry;
    }

    private function resolveAssignee(User $actor, Village $village, ?int $assigneeId): ?Employee
    {
        if ($assigneeId !== null) {
            if (! $actor->can('enquiries.assign')) {
                throw new BusinessRuleException(__('You are not allowed to choose the assignee.'), 'assign_not_permitted');
            }

            return Employee::query()->active()->findOrFail($assigneeId);
        }

        if ($actor->employee !== null && ! $actor->canAny(['enquiries.view_team', 'enquiries.view_all'])) {
            return $actor->employee;
        }

        return $this->territory->primarySalesmanFor($village);
    }
}
