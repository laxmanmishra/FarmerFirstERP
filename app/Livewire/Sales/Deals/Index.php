<?php

namespace App\Livewire\Sales\Deals;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Deal;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Deals list. The same component serves the approval queue (`$approvals = true`).
 */
class Index extends Component
{
    use WithDataTable;

    /** @var list<string> */
    protected array $sortable = ['deal_no', 'deal_value', 'expected_delivery_date', 'submitted_at', 'created_at'];

    #[Locked]
    public bool $approvals = false;

    #[Url(except: '')]
    public string $stage = '';

    public function mount(bool $approvals = false): void
    {
        $this->approvals = $approvals || request()->routeIs('sales.deal-approvals.*');
        $this->authorize($this->approvals ? 'deals.approve' : 'deals.view');
    }

    public function updatedStage(): void
    {
        $this->resetPage();
    }

    public function render(): mixed
    {
        $readyStageId = WorkflowStage::findByCode(WorkflowDefinition::DEAL, Deal::STAGE_READY)->id;

        $query = Deal::query()->visibleTo(Auth::user())
            ->with(['stage', 'customer:id,name,customer_no,mobile', 'primarySalesman:id,name'])
            ->when($this->approvals, fn (Builder $query) => $query->where('stage_id', $readyStageId))
            ->when(! $this->approvals && ctype_digit($this->stage), fn (Builder $query) => $query->where('stage_id', $this->stage))
            ->when($this->searchTerm(), fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->where('deal_no', 'like', $term)
                ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('mobile', 'like', $term)->orWhere('customer_no', 'like', $term))));

        return view('livewire.sales.deals.index', [
            'deals' => $this->applySorting($query, $this->approvals ? 'submitted_at' : 'id', $this->approvals ? 'asc' : 'desc')->paginate($this->perPage),
            'stages' => WorkflowStage::query()->ofDefinition(WorkflowDefinition::DEAL)->orderBy('sequence')->get(),
        ])->title($this->approvals ? __('Deal Approvals') : __('Deals'));
    }
}
