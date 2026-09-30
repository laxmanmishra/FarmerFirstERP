<?php

namespace App\Livewire\Crm\Enquiries;

use App\Actions\Enquiries\AssignEnquiry;
use App\Actions\FollowUps\CompleteFollowUp;
use App\Actions\FollowUps\ScheduleFollowUp;
use App\Actions\Pipeline\MoveEnquiryStage;
use App\Actions\Pipeline\ReopenEnquiry;
use App\Actions\Pipeline\RequestReopen;
use App\Enums\ApprovalStatus;
use App\Enums\FollowUpStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\FollowUp;
use App\Models\LookupValue;
use App\Models\WorkflowStage;
use App\Services\WorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithUi;

    #[Locked]
    public int $enquiryId;

    /**
     * Name of the open modal. The modal component's click-outside sets it to false.
     */
    public mixed $modal = null;

    // Assign
    public ?int $assignTo = null;

    public string $assignReason = '';

    // Follow-up (schedule or complete)
    public ?int $followUpId = null;

    public string $followUpType = 'CALL';

    public string $followUpDueAt = '';

    public string $followUpPurpose = '';

    public ?int $followUpAssignee = null;

    public string $followUpOutcome = '';

    public bool $scheduleNext = false;

    // Stage move
    public ?int $targetStageId = null;

    public string $stageRemarks = '';

    public string $closeReason = '';

    // Reopen
    public string $reopenReason = '';

    public function mount(Enquiry $enquiry): void
    {
        abort_unless(Enquiry::query()->visibleTo(Auth::user())->whereKey($enquiry->id)->exists(), 404);

        $this->enquiryId = $enquiry->id;
    }

    public function openModal(string $modal, ?int $id = null): void
    {
        $this->resetValidation();
        $enquiry = $this->enquiry();

        match ($modal) {
            'assign' => $this->authorize('enquiries.assign'),
            'follow-up' => $this->authorize('follow_ups.manage'),
            'complete-follow-up' => $this->authorize('follow_ups.manage'),
            'stage' => $this->authorize('pipeline.move'),
            'reopen' => $this->authorize('enquiries.reopen'),
            'request-reopen' => $this->authorize('enquiries.reopen_request'),
            default => abort(404),
        };

        $this->reset('assignReason', 'followUpPurpose', 'followUpOutcome', 'scheduleNext', 'stageRemarks', 'closeReason', 'reopenReason', 'targetStageId');
        $this->assignTo = $enquiry->assigned_employee_id;
        $this->followUpAssignee = $enquiry->assigned_employee_id ?? Auth::user()->employee?->id;
        $this->followUpDueAt = now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');
        $this->followUpType = 'CALL';
        $this->followUpId = $modal === 'complete-follow-up' ? $id : null;
        $this->targetStageId = $modal === 'stage' ? $id : null;
        $this->modal = $modal;
    }

    public function closeModal(): void
    {
        $this->modal = null;
    }

    public function assign(AssignEnquiry $assign): void
    {
        $this->authorize('enquiries.assign');
        $this->validate([
            'assignTo' => ['nullable', Rule::exists('employees', 'id')],
            'assignReason' => ['required', 'string', 'min:3', 'max:500'],
        ], attributes: ['assignReason' => __('reason')]);

        if ($this->attempt(fn () => $assign->handle(Auth::user(), $this->enquiry(), $this->assignTo ? Employee::query()->find($this->assignTo) : null, $this->assignReason))) {
            $this->modal = null;
            $this->toast(__('Assignment updated.'));
        }
    }

    public function scheduleFollowUp(ScheduleFollowUp $schedule): void
    {
        $this->authorize('follow_ups.manage');
        $this->validateFollowUp();

        if ($this->attempt(fn () => $schedule->handle(Auth::user(), $this->enquiry(), Employee::query()->findOrFail($this->followUpAssignee),
            $this->followUpType, CarbonImmutable::parse($this->followUpDueAt), $this->followUpPurpose))) {
            $this->modal = null;
            $this->toast(__('Follow-up scheduled.'));
        }
    }

    public function completeFollowUp(CompleteFollowUp $complete): void
    {
        $this->authorize('follow_ups.manage');
        $this->validate(['followUpOutcome' => ['required', 'string', 'min:3', 'max:1000']], attributes: ['followUpOutcome' => __('outcome')]);

        if ($this->scheduleNext) {
            $this->validateFollowUp(withAssignee: false);
        }

        $followUp = FollowUp::query()->where('followable_type', (new Enquiry)->getMorphClass())->where('followable_id', $this->enquiryId)->findOrFail($this->followUpId);
        $next = $this->scheduleNext ? ['type_code' => $this->followUpType, 'due_at' => CarbonImmutable::parse($this->followUpDueAt), 'purpose' => $this->followUpPurpose] : null;

        if ($this->attempt(fn () => $complete->handle(Auth::user(), $followUp, $this->followUpOutcome, $next))) {
            $this->modal = null;
            $this->toast(__('Follow-up completed.'));
        }
    }

    public function moveStage(MoveEnquiryStage $move): void
    {
        $this->authorize('pipeline.move');
        $this->validate([
            'targetStageId' => ['required', Rule::exists('workflow_stages', 'id')],
            'stageRemarks' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['targetStageId' => __('stage')]);

        $stage = WorkflowStage::query()->findOrFail($this->targetStageId);

        if ($this->attempt(fn () => $move->handle(Auth::user(), $this->enquiry(), $stage, $this->stageRemarks ?: null, $this->closeReason ?: null))) {
            $this->modal = null;
            $this->toast(__('Moved to :stage.', ['stage' => $stage->name]));
        }
    }

    public function reopen(ReopenEnquiry $reopen): void
    {
        $this->authorize('enquiries.reopen');
        $this->validate(['reopenReason' => ['required', 'string', 'min:5', 'max:1000']], attributes: ['reopenReason' => __('reason')]);

        if ($this->attempt(fn () => $reopen->handle(Auth::user(), $this->enquiry(), $this->reopenReason))) {
            $this->modal = null;
            $this->toast(__('Enquiry reopened.'));
        }
    }

    public function requestReopen(RequestReopen $request): void
    {
        $this->authorize('enquiries.reopen_request');
        $this->validate(['reopenReason' => ['required', 'string', 'min:5', 'max:1000']], attributes: ['reopenReason' => __('reason')]);

        if ($this->attempt(fn () => $request->handle(Auth::user(), $this->enquiry(), $this->reopenReason))) {
            $this->modal = null;
            $this->toast(__('Reopen request sent for approval.'));
        }
    }

    public function render(WorkflowService $workflow): mixed
    {
        $enquiry = Enquiry::query()->with([
            'farmer.village.tehsil.district', 'branch', 'village', 'assignee', 'creatorEmployee', 'claimedBy', 'validationStage', 'pipelineStage',
            'requirements.brand', 'requirements.product', 'requirements.variant', 'exchangeTractor', 'attachments',
            'callAttempts.employee', 'callAttempts.outcome', 'assignments.fromEmployee', 'assignments.toEmployee', 'assignments.assignedBy',
            'followUps.assignee', 'reopenRequests.requester', 'reopenRequests.decider',
            'statusHistory.fromStage', 'statusHistory.toStage', 'statusHistory.user', 'statusHistory.definition',
        ])->findOrFail($this->enquiryId);

        $user = Auth::user();
        $stageOptions = $enquiry->pipelineStage && ! $enquiry->isClosed() && $user->can('pipeline.move')
            ? $workflow->availableTargets($enquiry->pipelineStage, $user)
            : collect();

        return view('livewire.crm.enquiries.show', [
            'enquiry' => $enquiry,
            'timeline' => $this->timeline($enquiry),
            'stageOptions' => $stageOptions,
            'targetStage' => $this->targetStageId ? $stageOptions->firstWhere('id', $this->targetStageId) : null,
            'closeReasons' => LookupValue::options(LookupValue::CLOSE_REASON),
            'followUpTypes' => LookupValue::options(LookupValue::FOLLOW_UP_TYPE),
            'employees' => Employee::query()->active()->whereHas('departments', fn ($query) => $query->whereIn('code', ['SALES', 'TELECALLING']))->orderBy('name')->pluck('name', 'id'),
            'hasPendingReopen' => $enquiry->reopenRequests->contains('status', ApprovalStatus::Pending),
            'sourceLabel' => LookupValue::label(LookupValue::ENQUIRY_SOURCE, $enquiry->source_code),
            'closeReasonLabel' => LookupValue::label(LookupValue::CLOSE_REASON, $enquiry->close_reason_code),
        ])->title($enquiry->enquiry_no);
    }

    private function enquiry(): Enquiry
    {
        return Enquiry::query()->with(['validationStage', 'pipelineStage'])->findOrFail($this->enquiryId);
    }

    private function validateFollowUp(bool $withAssignee = true): void
    {
        $this->validate([
            'followUpType' => ['required', Rule::in(LookupValue::options(LookupValue::FOLLOW_UP_TYPE)->keys())],
            'followUpDueAt' => ['required', 'date', 'after:now'],
            'followUpPurpose' => ['required', 'string', 'min:3', 'max:500'],
            ...($withAssignee ? ['followUpAssignee' => ['required', Rule::exists('employees', 'id')->where('is_active', true)]] : []),
        ], attributes: ['followUpDueAt' => __('due time'), 'followUpPurpose' => __('purpose'), 'followUpAssignee' => __('assignee')]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function timeline(Enquiry $enquiry): Collection
    {
        $items = collect();

        foreach ($enquiry->statusHistory as $history) {
            $isCreation = ($history->meta['event'] ?? null) === 'created';
            $items->push([
                'at' => $history->created_at,
                'title' => $isCreation ? __('Enquiry created') : ($history->from_stage_id ? __(':from → :to', ['from' => $history->fromStage->name, 'to' => $history->toStage->name]) : __('Entered :stage', ['stage' => $history->toStage->name])),
                'body' => $history->remarks,
                'actor' => $history->user?->name,
                'tone' => $isCreation ? 'brand' : $history->toStage->color,
                'icon' => $isCreation ? 'plus' : 'flow',
            ]);
        }

        foreach ($enquiry->callAttempts as $call) {
            $items->push([
                'at' => $call->called_at,
                'title' => __('Call: :outcome', ['outcome' => $call->outcome->name]).($call->duration_seconds ? ' · '.gmdate('i:s', $call->duration_seconds) : ''),
                'body' => collect([$call->remarks, $call->next_callback_at ? __('Callback :time', ['time' => $call->next_callback_at->format('d M, H:i')]) : null])->filter()->implode("\n"),
                'actor' => $call->employee?->name,
                'tone' => 'sky',
                'icon' => 'phone',
            ]);
        }

        foreach ($enquiry->assignments as $assignment) {
            $items->push([
                'at' => $assignment->created_at,
                'title' => __('Assigned to :name', ['name' => $assignment->toEmployee?->name ?? __('nobody')]).($assignment->fromEmployee ? ' '.__('(from :name)', ['name' => $assignment->fromEmployee->name]) : ''),
                'body' => $assignment->reason,
                'actor' => $assignment->assignedBy?->name,
                'tone' => 'violet',
                'icon' => 'user',
            ]);
        }

        foreach ($enquiry->followUps as $followUp) {
            if ($followUp->status !== FollowUpStatus::Pending) {
                $items->push([
                    'at' => $followUp->completed_at,
                    'title' => __('Follow-up :status: :purpose', ['status' => mb_strtolower($followUp->status->label()), 'purpose' => $followUp->purpose]),
                    'body' => $followUp->outcome,
                    'actor' => $followUp->assignee->name,
                    'tone' => $followUp->status === FollowUpStatus::Completed ? 'green' : 'slate',
                    'icon' => 'calendar',
                ]);
            }
        }

        foreach ($enquiry->reopenRequests as $request) {
            $items->push([
                'at' => $request->created_at,
                'title' => __('Reopen requested — :status', ['status' => $request->status->label()]),
                'body' => collect([$request->reason, $request->decision_remarks ? __('Decision: :remarks', ['remarks' => $request->decision_remarks]) : null])->filter()->implode("\n"),
                'actor' => $request->requester->name.($request->decider ? ' → '.$request->decider->name : ''),
                'tone' => $request->status->tone(),
                'icon' => 'refresh',
            ]);
        }

        return $items->sortByDesc('at')->values();
    }
}
