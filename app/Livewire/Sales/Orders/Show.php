<?php

namespace App\Livewire\Sales\Orders;

use App\Actions\Fulfilment\FulfilmentTaskFlow;
use App\Actions\Orders\CancelOrder;
use App\Enums\RequirementState;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Employee;
use App\Models\FulfilmentTask;
use App\Models\Order;
use App\Models\WorkflowStatusHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Order 360: commercial snapshot, parallel department tasks, document checklist, history.
 */
class Show extends Component
{
    use InteractsWithUi;

    #[Locked]
    public int $orderId;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    /** 'status', 'assign', 'requirement', 'cancel'; false/null when closed (bound to the modal). */
    public mixed $modal = null;

    #[Locked]
    public ?int $taskId = null;

    /** @var array{stage_id: string, remarks: string, employee_id: string, state: string, reason: string} */
    public array $form = ['stage_id' => '', 'remarks' => '', 'employee_id' => '', 'state' => '', 'reason' => ''];

    public function mount(Order $order): void
    {
        $this->authorize('orders.view');
        abort_unless(Order::query()->visibleTo(Auth::user())->whereKey($order->id)->exists(), 404);

        $this->orderId = $order->id;
        $this->tab = in_array($this->tab, ['overview', 'fulfilment', 'documents', 'timeline'], true) ? $this->tab : 'overview';
    }

    public function openTask(int $taskId, string $mode): void
    {
        abort_unless(in_array($mode, ['status', 'assign', 'requirement'], true), 404);
        $task = $this->task($taskId);

        $this->resetValidation();
        $this->taskId = $task->id;
        $this->form = [
            'stage_id' => '', 'remarks' => '', 'reason' => '',
            'employee_id' => (string) ($task->responsible_employee_id ?? ''),
            'state' => $task->requirement_state === RequirementState::NotRequired ? RequirementState::Required->value : RequirementState::NotRequired->value,
        ];
        $this->modal = $mode;
    }

    public function saveStatus(FulfilmentTaskFlow $flow): void
    {
        $task = $this->task((int) $this->taskId);
        $targets = $flow->targets($task, Auth::user());

        $this->validate([
            'form.stage_id' => ['required', Rule::in($targets->pluck('id')->map(fn (int $id) => (string) $id)->all())],
            'form.remarks' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['form.stage_id' => __('status')]);

        $stage = $targets->firstWhere('id', (int) $this->form['stage_id']);

        if ($this->attempt(fn () => $flow->move(Auth::user(), $task, $stage, $this->form['remarks'] ?: null))) {
            $this->modal = null;
            $this->toast(__(':task moved to :stage.', ['task' => $task->type->name, 'stage' => $stage->name]));
        }
    }

    public function saveAssignment(FulfilmentTaskFlow $flow): void
    {
        $task = $this->task((int) $this->taskId);
        $this->validate(['form.employee_id' => ['nullable', 'integer', 'exists:employees,id']], attributes: ['form.employee_id' => __('employee')]);
        $employee = $this->form['employee_id'] !== '' ? Employee::query()->find($this->form['employee_id']) : null;

        if ($this->attempt(fn () => $flow->assign(Auth::user(), $task, $employee))) {
            $this->modal = null;
            $this->toast($employee ? __(':task assigned to :name.', ['task' => $task->type->name, 'name' => $employee->name]) : __('Assignment cleared.'));
        }
    }

    public function saveRequirement(FulfilmentTaskFlow $flow): void
    {
        $this->authorize('orders.create');
        $task = $this->task((int) $this->taskId);

        $this->validate([
            'form.state' => ['required', Rule::in([RequirementState::Required->value, RequirementState::NotRequired->value, RequirementState::Conditional->value])],
            'form.reason' => ['required', 'string', 'max:1000'],
        ], attributes: ['form.reason' => __('reason')]);

        if ($this->attempt(fn () => $flow->changeRequirement(Auth::user(), $task, RequirementState::from($this->form['state']), $this->form['reason']))) {
            $this->modal = null;
            $this->toast(__('Requirement updated; document checklist refreshed.'));
        }
    }

    public function openCancel(): void
    {
        $this->authorize('orders.cancel');
        $this->resetValidation();
        $this->form['reason'] = '';
        $this->modal = 'cancel';
    }

    public function cancelOrder(CancelOrder $cancel): void
    {
        $this->authorize('orders.cancel');
        $this->validate(['form.reason' => ['required', 'string', 'max:1000']], attributes: ['form.reason' => __('reason')]);

        if ($this->attempt(fn () => $cancel->handle(Auth::user(), Order::query()->findOrFail($this->orderId), $this->form['reason']))) {
            $this->modal = null;
            $this->toast(__('Order cancelled.'));
        }
    }

    #[On('documents-changed')]
    public function refreshCounters(): void
    {
        // Re-render so the header counters follow the checklist.
    }

    public function render(FulfilmentTaskFlow $flow): mixed
    {
        $order = Order::query()->with([
            'stage', 'items', 'customer.village.tehsil.district', 'deal:id,deal_no', 'primarySalesman', 'branch', 'cancelledBy:id,name',
            'fulfilment.tasks' => fn ($query) => $query->with(['type', 'stage.definition', 'department', 'responsible'])->orderBy('id'),
            'documentRequirements' => fn ($query) => $query->with('document.type'),
        ])->findOrFail($this->orderId);

        $user = Auth::user();
        $tasks = $order->fulfilment?->tasks ?? collect();
        $applicable = $order->documentRequirements->filter(fn ($requirement) => in_array($requirement->requirement_state, [RequirementState::Required, RequirementState::Conditional], true));
        $currentTask = $this->taskId ? $tasks->firstWhere('id', $this->taskId) : null;

        return view('livewire.sales.orders.show', [
            'order' => $order,
            'tasks' => $tasks,
            'workable' => $tasks->mapWithKeys(fn (FulfilmentTask $task) => [$task->id => $flow->canWork($user, $task)]),
            'documentsNeeded' => $applicable->count(),
            'documentsSatisfied' => $applicable->filter->isSatisfied()->count(),
            'targets' => $this->modal === 'status' && $currentTask ? $flow->targets($currentTask, $user) : collect(),
            'employees' => $this->modal === 'assign' && $currentTask
                ? Employee::query()->where('is_active', true)->whereHas('departments', fn ($query) => $query->whereKey($currentTask->department_id))->orderBy('name')->pluck('name', 'id')
                : collect(),
            'currentTask' => $currentTask,
            'timeline' => $this->tab === 'timeline' ? $this->timeline($order) : [],
        ])->title($order->order_no);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function timeline(Order $order): array
    {
        $taskNames = $order->fulfilment?->tasks->mapWithKeys(fn (FulfilmentTask $task) => [$task->id => $task->type->name]) ?? collect();

        return WorkflowStatusHistory::query()
            ->with(['fromStage', 'toStage', 'user:id,name'])
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('subject_type', $order->getMorphClass())->where('subject_id', $order->id))
                ->orWhere(fn ($query) => $query->where('subject_type', (new FulfilmentTask)->getMorphClass())->whereIn('subject_id', $taskNames->keys())))
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (WorkflowStatusHistory $history) => [
                'at' => $history->created_at,
                'title' => ($history->subject_type === $order->getMorphClass() ? __('Order') : $taskNames[$history->subject_id]).': '
                    .($history->fromStage ? $history->fromStage->name.' → ' : '').$history->toStage->name,
                'body' => $history->remarks,
                'actor' => $history->user?->name,
                'tone' => $history->toStage->color === 'rose' ? 'rose' : ($history->toStage->is_completion ? 'green' : 'brand'),
                'icon' => $history->subject_type === $order->getMorphClass() ? 'clipboard' : 'check-circle',
            ])->all();
    }

    private function task(int $taskId): FulfilmentTask
    {
        return FulfilmentTask::query()
            ->with(['type', 'stage.definition', 'fulfilment.order.stage', 'department'])
            ->whereHas('fulfilment', fn ($query) => $query->where('order_id', $this->orderId))
            ->findOrFail($taskId);
    }
}
