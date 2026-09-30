<?php

namespace App\Livewire\Crm\Telecaller;

use App\Actions\Telecaller\ClaimEnquiry;
use App\Actions\Telecaller\RecordCallAttempt;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\CallAttempt;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Common validation queue (SRS §10). Telecallers claim an enquiry, call, and record
 * the outcome; every attempt is kept.
 */
#[Title('Telecaller')]
class Index extends Component
{
    use InteractsWithUi, WithDataTable;

    #[Url(except: 'queue')]
    public string $tab = 'queue';

    public bool $showCall = false;

    public ?int $callEnquiryId = null;

    public ?int $outcomeId = null;

    public string $remarks = '';

    public string $durationMinutes = '';

    public string $callbackAt = '';

    public function mount(): void
    {
        $this->authorize('telecaller.queue');
    }

    public function updatedTab(): void
    {
        $this->tab = in_array($this->tab, ['queue', 'callbacks', 'mine', 'done'], true) ? $this->tab : 'queue';
        $this->resetPage();
    }

    public function claim(int $enquiryId, ClaimEnquiry $claim): void
    {
        $this->authorize('telecaller.claim');
        $employee = $this->employee();

        if ($employee === null) {
            return;
        }

        $enquiry = $this->queueQuery()->findOrFail($enquiryId);

        if ($this->attempt(fn () => $claim->handle($employee, $enquiry))) {
            $this->openCall($enquiryId);
        }
    }

    public function openCall(int $enquiryId): void
    {
        $this->authorize('telecaller.validate');
        $enquiry = $this->queueQuery()->findOrFail($enquiryId);

        if ($enquiry->claimed_by_employee_id !== $this->employee()?->id) {
            $this->toast(__('Claim the enquiry before recording a call.'), 'error');

            return;
        }

        $this->reset('outcomeId', 'remarks', 'durationMinutes', 'callbackAt');
        $this->resetValidation();
        $this->callEnquiryId = $enquiryId;
        $this->callbackAt = now()->addHours(2)->startOfHour()->format('Y-m-d\TH:i');
        $this->showCall = true;
    }

    public function release(int $enquiryId, ClaimEnquiry $claim): void
    {
        if ($employee = $this->employee()) {
            $claim->release($employee, $this->queueQuery()->findOrFail($enquiryId));
            $this->showCall = false;
            $this->toast(__('Returned to the queue.'), 'info');
        }
    }

    public function recordCall(RecordCallAttempt $record): void
    {
        $this->authorize('telecaller.validate');

        $this->validate([
            'outcomeId' => ['required', Rule::exists('workflow_stages', 'id')],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'durationMinutes' => ['nullable', 'numeric', 'min:0', 'max:180'],
            'callbackAt' => ['nullable', 'date'],
        ], attributes: ['outcomeId' => __('outcome')]);

        $outcome = WorkflowStage::query()->findOrFail($this->outcomeId);
        $enquiry = Enquiry::query()->with(['validationStage', 'village'])->findOrFail($this->callEnquiryId);
        $callback = $outcome->is_final || $this->callbackAt === '' ? null : CarbonImmutable::parse($this->callbackAt);

        $attempt = $this->attempt(fn () => $record->handle(
            Auth::user(),
            $enquiry,
            $outcome,
            $this->remarks ?: null,
            $this->durationMinutes === '' ? null : (int) round((float) $this->durationMinutes * 60),
            $callback,
        ));

        if ($attempt) {
            $this->showCall = false;
            $this->toast($outcome->is_completion
                ? __(':no validated and moved to the sales pipeline.', ['no' => $enquiry->enquiry_no])
                : __('Call recorded: :outcome.', ['outcome' => $outcome->name]));
        }
    }

    public function render(): mixed
    {
        $employee = $this->employee();
        $expiredBefore = now()->subMinutes(config('erp.crm.claim_timeout_minutes'));
        $base = fn () => $this->queueQuery();

        $query = $base()
            ->with(['farmer.village.tehsil', 'validationStage', 'claimedBy:id,name', 'assignee:id,name', 'requirements.product', 'requirements.brand'])
            ->withCount('callAttempts')
            ->when($this->searchTerm(), fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->where('enquiry_no', 'like', $term)
                ->orWhereHas('farmer', fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('mobile', 'like', $term))));

        match ($this->tab) {
            'callbacks' => $query->where('callback_at', '>', now())->orderBy('callback_at'),
            'mine' => $query->where('claimed_by_employee_id', $employee?->id)->where('claimed_at', '>=', $expiredBefore)->orderBy('claimed_at'),
            'done' => $query,
            default => $query->where(fn (Builder $query) => $query->whereNull('callback_at')->orWhere('callback_at', '<=', now()))
                ->orderByRaw('case when callback_at is null then 1 else 0 end')->orderBy('callback_at')->orderBy('created_at'),
        };

        if ($this->tab === 'done') {
            $enquiries = CallAttempt::query()
                ->with(['enquiry.farmer.village', 'outcome'])
                ->where('employee_id', $employee?->id)
                ->where('called_at', '>=', today())
                ->latest('called_at')
                ->paginate($this->perPage);
        } else {
            $enquiries = $query->paginate($this->perPage);
        }

        return view('livewire.crm.telecaller.index', [
            'rows' => $enquiries,
            'counts' => [
                'new' => $base()->whereNull('callback_at')->whereDoesntHave('callAttempts')->count(),
                'due' => $base()->whereNotNull('callback_at')->where('callback_at', '<=', now())->count(),
                'scheduled' => $base()->where('callback_at', '>', now())->count(),
                'mine' => $employee ? $base()->where('claimed_by_employee_id', $employee->id)->where('claimed_at', '>=', $expiredBefore)->count() : 0,
                'validatedToday' => $employee ? CallAttempt::query()->where('employee_id', $employee->id)->where('called_at', '>=', today())
                    ->whereHas('outcome', fn (Builder $query) => $query->where('is_completion', true))->count() : 0,
                'callsToday' => $employee ? CallAttempt::query()->where('employee_id', $employee->id)->where('called_at', '>=', today())->count() : 0,
            ],
            'employeeId' => $employee?->id,
            'expiredBefore' => $expiredBefore,
            'callEnquiry' => $this->showCall && $this->callEnquiryId
                ? Enquiry::query()->with(['farmer.village.tehsil.district', 'requirements.product', 'requirements.brand', 'requirements.variant', 'exchangeTractor', 'callAttempts.outcome', 'callAttempts.employee', 'validationStage'])->find($this->callEnquiryId)
                : null,
            'outcomes' => WorkflowStage::query()->ofDefinition(WorkflowDefinition::ENQUIRY_VALIDATION)->active()->where('is_initial', false)->orderBy('sequence')->get(),
            'selectedOutcome' => $this->outcomeId ? WorkflowStage::query()->find($this->outcomeId) : null,
        ]);
    }

    /**
     * Enquiries awaiting validation in the user's permitted branches.
     *
     * @return Builder<Enquiry>
     */
    private function queueQuery(): Builder
    {
        return Enquiry::query()->visibleTo(Auth::user())->whereNull('pipeline_stage_id')->whereNull('closed_at');
    }

    private function employee(): ?Employee
    {
        $employee = Auth::user()->employee;

        if ($employee === null) {
            $this->toast(__('Your login is not linked to an employee record.'), 'error');
        }

        return $employee;
    }
}
