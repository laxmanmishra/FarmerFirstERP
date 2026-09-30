<?php

namespace App\Livewire\Fulfilment\Finance;

use App\Actions\Finance\FinanceFileFlow;
use App\Actions\FollowUps\CompleteFollowUp;
use App\Actions\FollowUps\ScheduleFollowUp;
use App\Actions\Queries\FileQueryFlow;
use App\Enums\FollowUpStatus;
use App\Enums\QueryStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FileQuery;
use App\Models\FinanceFile;
use App\Models\Financer;
use App\Models\FinancerContact;
use App\Models\FollowUp;
use App\Models\LookupValue;
use App\Models\WorkflowStage;
use App\Models\WorkflowStatusHistory;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Finance file workspace (SRS §65–73): loan and DO details, financer status, follow-ups,
 * financer queries, the finance document checklist and history.
 */
class Show extends Component
{
    use InteractsWithUi;

    #[Locked]
    public int $fileId;

    #[Url(except: 'details')]
    public string $tab = 'details';

    /** @var array<string, mixed> */
    public array $details = [];

    /** 'status' | 'assign' | 'follow_up' | 'complete_follow_up' | 'query' | 'update_query'; false/null when closed. */
    public mixed $modal = null;

    #[Locked]
    public ?int $targetId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(FinanceFile $file): void
    {
        $this->authorize('finance.view');
        abort_unless(FinanceFile::query()->visibleTo(Auth::user())->whereKey($file->id)->exists(), 404);

        $this->fileId = $file->id;
        $this->tab = in_array($this->tab, ['details', 'activity', 'documents', 'timeline'], true) ? $this->tab : 'details';
        $this->fillDetails($file);
    }

    public function saveDetails(FinanceFileFlow $flow): void
    {
        $this->authorize('finance.update');

        $validated = $this->validate([
            'details.financer_id' => ['nullable', 'integer', 'exists:financers,id'],
            'details.financer_contact_id' => ['nullable', 'integer', 'exists:financer_contacts,id'],
            'details.sanctioned_amount' => ['nullable', 'numeric', 'min:0'],
            'details.down_payment' => ['nullable', 'numeric', 'min:0'],
            'details.tenure_months' => ['nullable', 'integer', 'between:1,120'],
            'details.interest_rate' => ['nullable', 'numeric', 'between:0,40'],
            'details.emi_amount' => ['nullable', 'numeric', 'min:0'],
            'details.loan_account_no' => ['nullable', 'string', 'max:50'],
            'details.do_number' => ['nullable', 'string', 'max:50'],
            'details.do_date' => ['nullable', 'date'],
            'details.do_amount' => ['nullable', 'numeric', 'min:0'],
            'details.do_valid_until' => ['nullable', 'date'],
            'details.disbursed_amount' => ['nullable', 'numeric', 'min:0'],
            'details.disbursed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'details.remarks' => ['nullable', 'string', 'max:2000'],
        ], attributes: ['details.financer_id' => __('financer'), 'details.do_valid_until' => __('DO valid until')])['details'];

        $values = array_map(fn ($value) => $value === '' ? null : $value, $validated);

        if ($this->attempt(fn () => $flow->updateDetails(Auth::user(), $this->file(), $values))) {
            $this->toast(__('Finance details saved.'));
        }
    }

    public function updatedDetailsFinancerId(): void
    {
        $this->details['financer_contact_id'] = null;
    }

    public function open(string $modal, ?int $targetId = null): void
    {
        abort_unless(in_array($modal, ['status', 'assign', 'follow_up', 'complete_follow_up', 'query', 'update_query'], true), 404);

        $this->resetValidation();
        $this->targetId = $targetId;
        $this->form = match ($modal) {
            'status' => ['stage_id' => '', 'remarks' => ''],
            'assign' => ['employee_id' => (string) ($this->file()->responsible_employee_id ?? '')],
            'follow_up' => ['type_code' => 'CALL', 'due_at' => now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i'), 'purpose' => '', 'employee_id' => (string) (Auth::user()->employee?->id ?? '')],
            'complete_follow_up' => ['outcome' => ''],
            'query' => ['party' => $this->file()->financer?->name ?? '', 'subject' => '', 'description' => '', 'due_date' => '', 'employee_id' => ''],
            'update_query' => ['status' => '', 'response' => ''],
        };
        $this->modal = $modal;
    }

    public function saveStatus(FinanceFileFlow $flow, WorkflowService $workflow): void
    {
        $file = $this->file();
        $targets = $this->targets($file, $workflow);

        $this->validate(['form.stage_id' => ['required', Rule::in($targets->pluck('id')->map(fn (int $id) => (string) $id)->all())], 'form.remarks' => ['nullable', 'string', 'max:1000']],
            attributes: ['form.stage_id' => __('status')]);

        $stage = $targets->firstWhere('id', (int) $this->form['stage_id']);

        if ($this->attempt(fn () => $flow->move(Auth::user(), $file, $stage, $this->form['remarks'] ?: null))) {
            $this->modal = null;
            $this->toast(__('Status set to :stage.', ['stage' => $stage->name]));
        }
    }

    public function saveAssignment(FinanceFileFlow $flow): void
    {
        $this->validate(['form.employee_id' => ['nullable', 'integer', 'exists:employees,id']]);
        $employee = $this->form['employee_id'] !== '' ? Employee::query()->find($this->form['employee_id']) : null;

        if ($this->attempt(fn () => $flow->assign(Auth::user(), $this->file(), $employee))) {
            $this->modal = null;
            $this->toast(__('Responsible employee updated.'));
        }
    }

    public function saveFollowUp(ScheduleFollowUp $schedule): void
    {
        $this->authorize('finance.update');
        $this->validate([
            'form.type_code' => ['required', Rule::in(LookupValue::options(LookupValue::FOLLOW_UP_TYPE)->keys()->all())],
            'form.due_at' => ['required', 'date', 'after:now'],
            'form.purpose' => ['required', 'string', 'max:500'],
            'form.employee_id' => ['required', 'integer', 'exists:employees,id'],
        ], attributes: ['form.due_at' => __('due time'), 'form.employee_id' => __('employee')]);

        $employee = Employee::query()->findOrFail($this->form['employee_id']);

        if ($this->attempt(fn () => $schedule->forRecord(Auth::user(), $this->file(), $employee, $this->form['type_code'], Carbon::parse($this->form['due_at']), $this->form['purpose']))) {
            $this->modal = null;
            $this->toast(__('Follow-up scheduled.'));
        }
    }

    public function completeFollowUp(CompleteFollowUp $complete): void
    {
        $this->validate(['form.outcome' => ['required', 'string', 'max:1000']]);
        $followUp = FollowUp::query()->with(['followable', 'assignee'])
            ->where('followable_type', (new FinanceFile)->getMorphClass())->where('followable_id', $this->fileId)->findOrFail($this->targetId);

        if ($this->attempt(fn () => $complete->handle(Auth::user(), $followUp, $this->form['outcome']))) {
            $this->modal = null;
            $this->toast(__('Follow-up completed.'));
        }
    }

    public function saveQuery(FileQueryFlow $flow): void
    {
        $this->validate([
            'form.party' => ['required', 'string', 'max:150'],
            'form.subject' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'form.employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ], attributes: ['form.party' => __('raised by'), 'form.subject' => __('subject')]);

        $assignee = $this->form['employee_id'] !== '' ? Employee::query()->find($this->form['employee_id']) : null;

        if ($this->attempt(fn () => $flow->raise(Auth::user(), $this->file(), 'finance.update', $this->form['party'], $this->form['subject'],
            $this->form['description'] ?: null, $this->form['due_date'] ? Carbon::parse($this->form['due_date']) : null, $assignee))) {
            $this->modal = null;
            $this->toast(__('Query recorded.'));
        }
    }

    public function updateQuery(FileQueryFlow $flow): void
    {
        $query = FileQuery::query()->where('queryable_type', (new FinanceFile)->getMorphClass())->where('queryable_id', $this->fileId)->findOrFail($this->targetId);
        $this->validate(['form.status' => ['required', Rule::in(array_map(fn (QueryStatus $status) => $status->value, $query->status->next()))], 'form.response' => ['nullable', 'string', 'max:2000']],
            attributes: ['form.status' => __('status')]);

        if ($this->attempt(fn () => $flow->update(Auth::user(), $query, 'finance.update', QueryStatus::from($this->form['status']), $this->form['response'] ?: null))) {
            $this->modal = null;
            $this->toast(__('Query updated.'));
        }
    }

    public function render(WorkflowService $workflow): mixed
    {
        $file = FinanceFile::query()->with([
            'stage.definition', 'financer', 'contact', 'responsible', 'branch',
            'order' => fn ($query) => $query->with(['customer.village.tehsil.district', 'primarySalesman:id,name', 'stage']),
            'followUps' => fn ($query) => $query->with(['assignee:id,name', 'completedBy:id,name'])->latest('due_at'),
            'queries.assignee:id,name', 'queries.resolver:id,name',
        ])->findOrFail($this->fileId);
        $user = Auth::user();
        $editable = $user->can('finance.update') && ! $file->stage->is_final && ! $file->order->isCancelled();
        $retail = Department::query()->where('code', 'RETAIL_FINANCE')->value('id');
        $currentQuery = $this->modal === 'update_query' ? $file->queries->firstWhere('id', $this->targetId) : null;

        return view('livewire.fulfilment.finance.show', [
            'file' => $file,
            'editable' => $editable,
            'financers' => Financer::query()->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $file->financer_id))->orderBy('name')->pluck('name', 'id'),
            'contacts' => ($this->details['financer_id'] ?? null)
                ? FinancerContact::query()->where('financer_id', $this->details['financer_id'])->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $file->financer_contact_id))->orderBy('name')->pluck('name', 'id')
                : collect(),
            'targets' => $this->modal === 'status' ? $this->targets($file, $workflow) : collect(),
            'employees' => in_array($this->modal, ['assign', 'follow_up', 'query'], true)
                ? Employee::query()->where('is_active', true)->whereHas('departments', fn ($query) => $query->whereKey($retail))->orderBy('name')->pluck('name', 'id')
                : collect(),
            'followUpTypes' => LookupValue::options(LookupValue::FOLLOW_UP_TYPE),
            'currentQuery' => $currentQuery,
            'pendingFollowUps' => $file->followUps->where('status', FollowUpStatus::Pending)->count(),
            'openQueries' => $file->queries->filter(fn (FileQuery $query) => $query->status->isOpen())->count(),
            'departmentId' => $retail,
            'timeline' => $this->tab === 'timeline' ? $this->timeline($file) : [],
        ])->title($file->file_no);
    }

    /**
     * @return Collection<int, WorkflowStage>
     */
    private function targets(FinanceFile $file, WorkflowService $workflow): Collection
    {
        return $workflow->availableTargets($file->stage, Auth::user())->reject(fn (WorkflowStage $stage) => $stage->is_system)->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function timeline(FinanceFile $file): array
    {
        return WorkflowStatusHistory::query()->with(['fromStage', 'toStage', 'user:id,name'])
            ->where('subject_type', $file->getMorphClass())->where('subject_id', $file->id)
            ->latest('id')->get()
            ->map(fn (WorkflowStatusHistory $history) => [
                'at' => $history->created_at,
                'title' => ($history->fromStage ? $history->fromStage->name.' → ' : '').$history->toStage->name,
                'body' => $history->remarks,
                'actor' => $history->user?->name,
                'tone' => $history->toStage->color,
                'icon' => 'banknotes',
            ])->all();
    }

    private function file(): FinanceFile
    {
        return FinanceFile::query()->with(['stage.definition', 'order', 'task', 'financer', 'branch'])->findOrFail($this->fileId);
    }

    private function fillDetails(FinanceFile $file): void
    {
        $this->details = collect($file->only(['financer_id', 'financer_contact_id', 'sanctioned_amount', 'down_payment', 'tenure_months', 'interest_rate', 'emi_amount',
            'loan_account_no', 'do_number', 'do_amount', 'disbursed_amount', 'remarks']))->map(fn ($value) => $value ?? '')->all()
            + ['do_date' => $file->do_date?->toDateString() ?? '', 'do_valid_until' => $file->do_valid_until?->toDateString() ?? '', 'disbursed_on' => $file->disbursed_on?->toDateString() ?? ''];
    }
}
