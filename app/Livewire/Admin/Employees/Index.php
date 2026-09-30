<?php

namespace App\Livewire\Admin\Employees;

use App\Actions\Employees\SaveEmployee;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Employees')]
class Index extends Component
{
    use InteractsWithUi, WithDataTable;

    /** @var list<string> */
    protected array $sortable = ['employee_code', 'name', 'date_of_joining'];

    #[Url(except: '')]
    public string $department = '';

    #[Url(except: '')]
    public string $branch = '';

    #[Url(except: 'active')]
    public string $status = 'active';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $mobile = '';

    public string $email = '';

    public ?int $designation_id = null;

    public ?int $reports_to_id = null;

    public ?int $user_id = null;

    public ?string $date_of_joining = null;

    /** @var list<int> */
    public array $department_ids = [];

    public ?int $primary_department_id = null;

    /** @var list<int> */
    public array $branch_ids = [];

    public ?int $primary_branch_id = null;

    public function mount(): void
    {
        $this->authorize('employees.view');
    }

    public function create(): void
    {
        $this->authorize('employees.create');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $employeeId): void
    {
        $this->authorize('employees.update');
        $employee = Employee::query()->with(['departments', 'branches'])->findOrFail($employeeId);

        $this->resetForm();
        $this->editingId = $employee->id;
        $this->fill($employee->only(['name', 'designation_id', 'reports_to_id', 'user_id']));
        $this->mobile = (string) $employee->mobile;
        $this->email = (string) $employee->email;
        $this->date_of_joining = $employee->date_of_joining?->toDateString();
        $this->department_ids = $employee->departments->pluck('id')->all();
        $this->primary_department_id = $employee->departments->firstWhere('pivot.is_primary', true)?->id;
        $this->branch_ids = $employee->branches->pluck('id')->all();
        $this->primary_branch_id = $employee->branches->firstWhere('pivot.is_primary', true)?->id;
        $this->showForm = true;
    }

    public function save(SaveEmployee $saveEmployee): void
    {
        $this->authorize($this->editingId ? 'employees.update' : 'employees.create');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', 'digits:10'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'designation_id' => ['nullable', Rule::exists('designations', 'id')],
            'reports_to_id' => ['nullable', Rule::exists('employees', 'id'), Rule::notIn(array_filter([$this->editingId]))],
            'user_id' => ['nullable', Rule::exists('users', 'id'), Rule::unique('employees', 'user_id')->ignore($this->editingId)],
            'date_of_joining' => ['nullable', 'date', 'before_or_equal:today'],
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer', Rule::exists('departments', 'id')],
            'primary_department_id' => ['nullable', 'integer'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer', Rule::exists('branches', 'id')],
            'primary_branch_id' => ['nullable', 'integer'],
        ], attributes: ['department_ids' => __('departments'), 'branch_ids' => __('branches'), 'user_id' => __('login account')]);

        $employee = $this->attempt(fn () => $saveEmployee->handle(
            [
                'name' => $validated['name'],
                'mobile' => $validated['mobile'] ?: null,
                'email' => $validated['email'] ?: null,
                'designation_id' => $validated['designation_id'],
                'reports_to_id' => $validated['reports_to_id'],
                'user_id' => $validated['user_id'],
                'date_of_joining' => $validated['date_of_joining'] ?: null,
            ],
            $validated['department_ids'],
            $validated['primary_department_id'],
            $validated['branch_ids'],
            $validated['primary_branch_id'],
            $this->editingId ? Employee::query()->findOrFail($this->editingId) : null,
        ));

        if ($employee === null) {
            return;
        }

        $this->showForm = false;
        $this->toast($this->editingId ? __('Employee updated.') : __('Employee :code created.', ['code' => $employee->employee_code]));
    }

    public function toggleActive(int $employeeId): void
    {
        $this->authorize('employees.deactivate');
        $employee = Employee::query()->findOrFail($employeeId);
        $employee->update(['is_active' => ! $employee->is_active]);

        $this->toast($employee->is_active ? __('Employee reactivated.') : __('Employee deactivated. Their login account is unchanged.'));
    }

    public function render(): mixed
    {
        $query = Employee::query()
            ->with(['designation:id,name', 'manager:id,name', 'departments:id,name', 'branches:id,name', 'user:id,email,is_active'])
            ->when($this->searchTerm(), fn ($query, string $term) => $query->where(fn ($query) => $query
                ->where('name', 'like', $term)->orWhere('employee_code', 'like', $term)->orWhere('mobile', 'like', $term)))
            ->when($this->department !== '', fn ($query) => $query->whereHas('departments', fn ($query) => $query->whereKey($this->department)))
            ->when($this->branch !== '', fn ($query) => $query->whereHas('branches', fn ($query) => $query->whereKey($this->branch)))
            ->when($this->status !== '', fn ($query) => $query->where('is_active', $this->status === 'active'));

        return view('livewire.admin.employees.index', [
            'employees' => $this->applySorting($query, 'name', 'asc')->paginate($this->perPage),
            'departments' => Department::query()->active()->orderBy('sort_order')->get(['id', 'name']),
            'branches' => Branch::query()->active()->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::query()->active()->orderBy('name')->pluck('name', 'id'),
            'managers' => Employee::query()->active()->when($this->editingId, fn ($query) => $query->whereKeyNot($this->editingId))->orderBy('name')->pluck('name', 'id'),
            'linkableUsers' => User::query()
                ->where(fn ($query) => $query->whereDoesntHave('employee')->orWhereHas('employee', fn ($query) => $query->whereKey($this->editingId)))
                ->orderBy('name')->get(['id', 'name', 'email'])
                ->mapWithKeys(fn (User $user) => [$user->id => "{$user->name} ({$user->email})"]),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'mobile', 'email', 'designation_id', 'reports_to_id', 'user_id', 'date_of_joining',
            'department_ids', 'primary_department_id', 'branch_ids', 'primary_branch_id');
        $this->resetValidation();
    }
}
