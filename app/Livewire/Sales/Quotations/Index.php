<?php

namespace App\Livewire\Sales\Quotations;

use App\Enums\QuotationStatus;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Quotations')]
class Index extends Component
{
    use WithDataTable;

    /** @var list<string> */
    protected array $sortable = ['quotation_no', 'net_amount', 'valid_until', 'created_at'];

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('quotations.view');
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render(): mixed
    {
        $query = Quotation::query()->visibleTo(Auth::user())
            ->with(['enquiry:id,enquiry_no,farmer_id', 'farmer:id,name,mobile', 'preparedBy:id,name'])
            ->when(QuotationStatus::tryFrom($this->status), fn (Builder $query, QuotationStatus $status) => $query->where('status', $status))
            ->when($this->status === '', fn (Builder $query) => $query->where('status', '!=', QuotationStatus::Superseded))
            ->when($this->searchTerm(), fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->where('quotation_no', 'like', $term)
                ->orWhereHas('farmer', fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('mobile', 'like', $term))));

        return view('livewire.sales.quotations.index', [
            'quotations' => $this->applySorting($query, 'id', 'desc')->paginate($this->perPage),
            'statuses' => QuotationStatus::cases(),
            'pendingApproval' => Quotation::query()->visibleTo(Auth::user())->where('status', QuotationStatus::PendingApproval)->count(),
        ]);
    }
}
