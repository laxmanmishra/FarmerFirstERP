<?php

namespace App\Livewire\Fulfilment\Finance;

use App\Livewire\Concerns\WithDataTable;
use App\Models\FinanceFile;
use App\Models\Financer;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Retail & Finance work queue (SRS §57, §72) plus the financer master tab.
 */
class Index extends Component
{
    use WithDataTable;

    /** @var list<string> */
    protected array $sortable = ['file_no', 'loan_amount', 'updated_at'];

    #[Url(except: 'files')]
    public string $tab = 'files';

    #[Url(except: '')]
    public string $stage = '';

    #[Url(except: '')]
    public string $financer = '';

    #[Url(except: false)]
    public bool $mine = false;

    #[Url(except: false)]
    public bool $open = true;

    public function mount(): void
    {
        $this->authorize('finance.view');
        $this->tab = $this->tab === 'financers' && Auth::user()->can('finance.configure') ? 'financers' : 'files';
    }

    public function updated(string $property): void
    {
        if ($property === 'tab') {
            $this->tab = $this->tab === 'financers' && Auth::user()->can('finance.configure') ? 'financers' : 'files';
        }

        $this->resetPage();
    }

    public function render(): mixed
    {
        $user = Auth::user();
        $files = $this->tab === 'files' ? FinanceFile::query()->visibleTo($user)
            ->with(['stage', 'financer:id,name', 'responsible:id,name', 'order:id,order_no,customer_id,expected_delivery_date,cancelled_at', 'order.customer:id,name,mobile'])
            ->withCount(['queries as open_queries' => fn (Builder $query) => $query->where('status', '!=', 'resolved')])
            ->when($this->open, fn (Builder $query) => $query->whereHas('stage', fn (Builder $query) => $query->where('is_final', false)))
            ->when(ctype_digit($this->stage), fn (Builder $query) => $query->where('stage_id', $this->stage))
            ->when(ctype_digit($this->financer), fn (Builder $query) => $query->where('financer_id', $this->financer))
            ->when($this->mine, fn (Builder $query) => $query->where('responsible_employee_id', $user->employee?->id ?? 0))
            ->when($this->searchTerm(), fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->where('file_no', 'like', $term)
                ->orWhereHas('order', fn (Builder $query) => $query->where('order_no', 'like', $term)
                    ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('mobile', 'like', $term)))))
            : null;

        return view('livewire.fulfilment.finance.index', [
            'files' => $files ? $this->applySorting($files, 'updated_at')->paginate($this->perPage) : null,
            'stages' => WorkflowStage::query()->ofDefinition(WorkflowDefinition::FINANCE)->orderBy('sequence')->get(),
            'financers' => Financer::query()->orderBy('name')->pluck('name', 'id'),
        ])->title(__('Retail & Finance'));
    }
}
