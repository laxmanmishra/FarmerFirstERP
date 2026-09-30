<?php

namespace App\Livewire\Admin\Departments;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Departments and designations share one screen with tabs.
 */
#[Title('Departments & Designations')]
class Index extends Component
{
    use InteractsWithUi;

    #[Url(except: 'departments')]
    public string $tab = 'departments';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public bool $is_operational = false;

    public int $sort_order = 0;

    public function mount(): void
    {
        $this->authorize('departments.view');
        $this->tab = in_array($this->tab, ['departments', 'designations'], true) ? $this->tab : 'departments';
    }

    public function updatedTab(): void
    {
        $this->tab = in_array($this->tab, ['departments', 'designations'], true) ? $this->tab : 'departments';
        $this->showForm = false;
    }

    public function create(): void
    {
        $this->authorize('departments.manage');
        $this->resetForm();
        $this->sort_order = (int) Department::query()->max('sort_order') + 10;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('departments.manage');
        $this->resetForm();

        $record = $this->modelClass()::query()->findOrFail($id);
        $this->editingId = $record->id;
        $this->code = $record->code;
        $this->name = $record->name;

        if ($record instanceof Department) {
            $this->description = (string) $record->description;
            $this->is_operational = $record->is_operational;
            $this->sort_order = $record->sort_order;
        }

        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('departments.manage');
        $table = (new ($this->modelClass()))->getTable();

        $rules = [
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique($table, 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:100', Rule::unique($table, 'name')->ignore($this->editingId)],
        ];

        if ($table === 'departments') {
            $rules += [
                'description' => ['nullable', 'string', 'max:255'],
                'is_operational' => ['boolean'],
                'sort_order' => ['integer', 'min:0', 'max:65000'],
            ];
        }

        $validated = $this->validate($rules);
        $validated['code'] = strtoupper($validated['code']);

        if (array_key_exists('description', $validated)) {
            $validated['description'] = $validated['description'] ?: null;
        }

        $this->editingId
            ? $this->modelClass()::query()->findOrFail($this->editingId)->update($validated)
            : $this->modelClass()::create($validated);

        $this->showForm = false;
        $this->toast(__('Saved.'));
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('departments.manage');
        $record = $this->modelClass()::query()->findOrFail($id);
        $record->update(['is_active' => ! $record->is_active]);

        $this->toast($record->is_active ? __('Activated.') : __('Deactivated. Existing assignments are kept for history.'));
    }

    public function render(): mixed
    {
        return view('livewire.admin.departments.index', [
            'departments' => $this->tab === 'departments' ? Department::query()->withCount('employees')->orderBy('sort_order')->get() : collect(),
            'designations' => $this->tab === 'designations' ? Designation::query()->withCount('employees')->orderBy('name')->get() : collect(),
        ]);
    }

    /**
     * @return class-string<Department|Designation>
     */
    private function modelClass(): string
    {
        return $this->tab === 'designations' ? Designation::class : Department::class;
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'code', 'name', 'description', 'is_operational', 'sort_order');
        $this->resetValidation();
    }
}
