<?php

namespace App\Livewire\Sales\Orders;

use App\Enums\RequirementState;
use App\Livewire\Concerns\WithDataTable;
use App\Models\DocumentRequirement;
use App\Models\Order;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Orders / bookings with fulfilment and documentation progress (SRS §15, §23).
 */
class Index extends Component
{
    use WithDataTable;

    /** @var list<string> */
    protected array $sortable = ['order_no', 'order_value', 'expected_delivery_date', 'order_date'];

    #[Url(except: '')]
    public string $stage = '';

    public function mount(): void
    {
        $this->authorize('orders.view');
    }

    public function updatedStage(): void
    {
        $this->resetPage();
    }

    public function render(): mixed
    {
        $applicable = [RequirementState::Required, RequirementState::Conditional, RequirementState::Waived];

        $query = Order::query()->visibleTo(Auth::user())
            ->with(['stage', 'customer:id,name,customer_no,mobile', 'primarySalesman:id,name'])
            ->withCount([
                'documentRequirements as documents_needed' => fn (Builder $query) => $query->whereIn('requirement_state', [RequirementState::Required, RequirementState::Conditional]),
                'documentRequirements as documents_open' => fn (Builder $query) => $query->unsatisfied(),
            ])
            ->addSelect([
                'tasks_needed' => $this->taskCount($applicable),
                'tasks_done' => $this->taskCount($applicable, completed: true),
            ])
            ->when(ctype_digit($this->stage), fn (Builder $query) => $query->where('stage_id', $this->stage))
            ->when($this->searchTerm(), fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->where('order_no', 'like', $term)
                ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('mobile', 'like', $term)->orWhere('customer_no', 'like', $term))));

        return view('livewire.sales.orders.index', [
            'orders' => $this->applySorting($query)->paginate($this->perPage),
            'stages' => WorkflowStage::query()->ofDefinition(WorkflowDefinition::ORDER)->orderBy('sequence')->get(),
            'blocking' => DocumentRequirement::query()->visibleTo(Auth::user())->blockingDelivery()->count(),
        ])->title(__('Orders'));
    }

    /**
     * @param  list<RequirementState>  $states
     */
    private function taskCount(array $states, bool $completed = false): QueryBuilder
    {
        return DB::table('fulfilment_tasks')
            ->join('fulfilments', 'fulfilments.id', '=', 'fulfilment_tasks.fulfilment_id')
            ->when($completed, fn ($query) => $query->join('workflow_stages', 'workflow_stages.id', '=', 'fulfilment_tasks.stage_id')->where('workflow_stages.is_completion', true))
            ->whereColumn('fulfilments.order_id', 'orders.id')
            ->whereIn('fulfilment_tasks.requirement_state', array_map(fn (RequirementState $state) => $state->value, $states))
            ->selectRaw('count(*)');
    }
}
