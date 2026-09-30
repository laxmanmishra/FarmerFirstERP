<?php

namespace App\Livewire\Admin\Branches;

use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Branch;
use App\Models\Company;
use App\Models\District;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Branches')]
class Index extends Component
{
    use InteractsWithUi, WithDataTable;

    /** @var list<string> */
    protected array $sortable = ['code', 'name'];

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $address = '';

    public ?int $district_id = null;

    public string $phone = '';

    public string $email = '';

    public function mount(): void
    {
        $this->authorize('branches.view');
    }

    public function create(): void
    {
        $this->authorize('branches.manage');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $branchId): void
    {
        $this->authorize('branches.manage');
        $branch = Branch::query()->findOrFail($branchId);

        $this->resetForm();
        $this->editingId = $branch->id;
        $this->code = $branch->code;
        $this->name = $branch->name;
        $this->address = (string) $branch->address;
        $this->district_id = $branch->district_id;
        $this->phone = (string) $branch->phone;
        $this->email = (string) $branch->email;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('branches.manage');

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('branches', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'district_id' => ['nullable', Rule::exists('districts', 'id')],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
        ]);

        $validated = array_map(fn ($value) => $value === '' ? null : $value, $validated);
        $validated['code'] = strtoupper($validated['code']);

        if ($this->editingId) {
            Branch::query()->findOrFail($this->editingId)->update($validated);
        } else {
            Branch::create([...$validated, 'company_id' => Company::current()->id]);
        }

        $this->showForm = false;
        $this->toast($this->editingId ? __('Branch updated.') : __('Branch created.'));
    }

    public function toggleActive(int $branchId): void
    {
        $this->authorize('branches.manage');
        $branch = Branch::query()->findOrFail($branchId);

        if ($branch->is_active && Branch::query()->active()->count() === 1) {
            $this->toast(__('At least one branch must remain active.'), 'error');

            return;
        }

        $branch->update(['is_active' => ! $branch->is_active]);
        $this->toast($branch->is_active ? __('Branch activated.') : __('Branch deactivated.'));
    }

    public function render(): mixed
    {
        $query = Branch::query()->with('district:id,name')->withCount('employees')
            ->when($this->searchTerm(), fn ($query, string $term) => $query->where(fn ($query) => $query->where('name', 'like', $term)->orWhere('code', 'like', $term)));

        return view('livewire.admin.branches.index', [
            'branches' => $this->applySorting($query, 'name', 'asc')->paginate($this->perPage),
            'districts' => District::query()->active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'code', 'name', 'address', 'district_id', 'phone', 'email');
        $this->resetValidation();
    }
}
