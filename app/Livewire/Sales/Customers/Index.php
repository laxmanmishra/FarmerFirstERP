<?php

namespace App\Livewire\Sales\Customers;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Customers')]
class Index extends Component
{
    use WithDataTable;

    /** @var list<string> */
    protected array $sortable = ['customer_no', 'name', 'customer_since'];

    #[Url(except: false)]
    public bool $duplicatesOnly = false;

    public function mount(): void
    {
        $this->authorize('customers.view');
    }

    public function updatedDuplicatesOnly(): void
    {
        $this->resetPage();
    }

    public function render(): mixed
    {
        $query = Customer::query()->visibleTo(Auth::user())
            ->with(['village.tehsil.district', 'possibleDuplicateOf:id,customer_no'])
            ->withCount(['deals', 'enquiries'])
            ->when($this->duplicatesOnly, fn (Builder $query) => $query->whereNotNull('possible_duplicate_of_id'))
            ->when($this->searchTerm(), fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', $term)->orWhere('customer_no', 'like', $term)
                ->orWhere('mobile', 'like', $term)->orWhere('alternate_mobile', 'like', $term)));

        return view('livewire.sales.customers.index', [
            'customers' => $this->applySorting($query, 'id', 'desc')->paginate($this->perPage),
            'duplicateCount' => Customer::query()->visibleTo(Auth::user())->whereNotNull('possible_duplicate_of_id')->count(),
        ]);
    }
}
