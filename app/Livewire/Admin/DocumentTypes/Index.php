<?php

namespace App\Livewire\Admin\DocumentTypes;

use App\Enums\DocumentLevel;
use App\Enums\DocumentSensitivity;
use App\Enums\TaskCondition;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Department;
use App\Models\DocumentRequirementRule;
use App\Models\DocumentType;
use App\Models\FulfilmentTaskType;
use App\Models\SystemSetting;
use App\Services\DocumentRequirementService;
use BackedEnum;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Document Type Master, requirement rules and fulfilment task types (SRS §187, §199, §23).
 * Changes apply to orders booked afterwards; existing requirements keep their history.
 */
#[Title('Document Configuration')]
class Index extends Component
{
    use InteractsWithUi;

    #[Url(except: 'types')]
    public string $tab = 'types';

    /** 'type' | 'rule' | 'task'; false/null when closed. */
    public mixed $drawer = null;

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $autoLink = true;

    public function mount(): void
    {
        $this->authorize('documents.configure');
        $this->tab = in_array($this->tab, ['types', 'rules', 'tasks', 'options'], true) ? $this->tab : 'types';
        $this->autoLink = (bool) SystemSetting::get(DocumentRequirementService::AUTO_LINK_SETTING, true);
    }

    public function edit(string $kind, ?int $id = null): void
    {
        $this->authorize('documents.configure');
        abort_unless(in_array($kind, ['type', 'rule', 'task'], true), 404);

        $this->resetValidation();
        $this->editingId = $id;
        $this->form = match ($kind) {
            'type' => $id ? DocumentType::query()->findOrFail($id)->only(['code', 'name', 'category', 'level', 'is_reusable', 'expiry_applicable', 'verification_required',
                'verification_permission', 'sensitivity', 'allowed_extensions', 'max_size_kb', 'default_department_id', 'sort_order', 'is_active'])
                : ['code' => '', 'name' => '', 'category' => 'other', 'level' => 'order', 'is_reusable' => false, 'expiry_applicable' => false, 'verification_required' => true,
                    'verification_permission' => 'documents.verify', 'sensitivity' => 'normal', 'allowed_extensions' => 'pdf,jpg,jpeg,png', 'max_size_kb' => 5120,
                    'default_department_id' => null, 'sort_order' => 0, 'is_active' => true],
            'rule' => $id ? DocumentRequirementRule::query()->findOrFail($id)->only(['document_type_id', 'department_id', 'fulfilment_task_type_id', 'blocks_delivery', 'due_offset_days', 'is_active'])
                : ['document_type_id' => null, 'department_id' => null, 'fulfilment_task_type_id' => null, 'blocks_delivery' => false, 'due_offset_days' => null, 'is_active' => true],
            'task' => $id ? FulfilmentTaskType::query()->findOrFail($id)->only(['code', 'name', 'department_id', 'condition', 'blocks_delivery', 'update_permission', 'sort_order', 'is_active'])
                : ['code' => '', 'name' => '', 'department_id' => null, 'condition' => 'always', 'blocks_delivery' => true, 'update_permission' => '', 'sort_order' => 0, 'is_active' => true],
        };
        $this->form = array_map(fn ($value) => $value instanceof BackedEnum ? $value->value : $value, $this->form);
        $this->drawer = $kind;
    }

    public function saveType(): void
    {
        $this->authorize('documents.configure');
        $record = $this->editingId ? DocumentType::query()->findOrFail($this->editingId) : new DocumentType;

        $validated = $this->validate([
            'form.code' => [$record->exists ? 'nullable' : 'required', 'string', 'max:40', 'regex:/^[A-Z0-9_]+$/', Rule::unique('document_types', 'code')->ignore($record->id)],
            'form.name' => ['required', 'string', 'max:100'],
            'form.category' => ['required', Rule::in(DocumentType::CATEGORIES)],
            'form.level' => ['required', Rule::enum(DocumentLevel::class)],
            'form.is_reusable' => ['boolean'],
            'form.expiry_applicable' => ['boolean'],
            'form.verification_required' => ['boolean'],
            'form.verification_permission' => ['required', Rule::in($this->permissionNames())],
            'form.sensitivity' => ['required', Rule::enum(DocumentSensitivity::class)],
            'form.allowed_extensions' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(,[a-z0-9]+)*$/'],
            'form.max_size_kb' => ['required', 'integer', 'between:50,20480'],
            'form.default_department_id' => ['nullable', 'exists:departments,id'],
            'form.sort_order' => ['required', 'integer', 'between:0,9999'],
            'form.is_active' => ['boolean'],
        ], ['form.allowed_extensions.regex' => __('List extensions in lower case separated by commas, e.g. pdf,jpg,png.')])['form'];

        if ($record->exists) {
            unset($validated['code']); // codes are referenced by rules and history
        }

        $record->fill($validated)->save();
        $this->closeWith(__('Document type saved.'));
    }

    public function saveRule(): void
    {
        $this->authorize('documents.configure');
        $record = $this->editingId ? DocumentRequirementRule::query()->findOrFail($this->editingId) : new DocumentRequirementRule;

        $validated = $this->validate([
            'form.document_type_id' => ['required', 'exists:document_types,id',
                Rule::unique('document_requirement_rules', 'document_type_id')->where('department_id', $this->form['department_id'] ?? null)->ignore($record->id)],
            'form.department_id' => ['required', 'exists:departments,id'],
            'form.fulfilment_task_type_id' => ['nullable', 'exists:fulfilment_task_types,id'],
            'form.blocks_delivery' => ['boolean'],
            'form.due_offset_days' => ['nullable', 'integer', 'between:0,365'],
            'form.is_active' => ['boolean'],
        ], ['form.document_type_id.unique' => __('This department already has a rule for that document type.')], ['form.document_type_id' => __('document type')])['form'];

        $record->fill([...$validated, 'fulfilment_task_type_id' => $validated['fulfilment_task_type_id'] ?: null, 'due_offset_days' => $validated['due_offset_days'] === '' ? null : $validated['due_offset_days']])->save();
        $this->closeWith(__('Requirement rule saved. It applies to orders booked from now on.'));
    }

    public function saveTask(): void
    {
        $this->authorize('documents.configure');
        $record = $this->editingId ? FulfilmentTaskType::query()->findOrFail($this->editingId) : new FulfilmentTaskType;

        $validated = $this->validate([
            'form.code' => [$record->exists ? 'nullable' : 'required', 'string', 'max:40', 'regex:/^[A-Z0-9_]+$/', Rule::unique('fulfilment_task_types', 'code')->ignore($record->id)],
            'form.name' => ['required', 'string', 'max:100'],
            'form.department_id' => ['required', 'exists:departments,id'],
            'form.condition' => ['required', Rule::enum(TaskCondition::class)],
            'form.blocks_delivery' => ['boolean'],
            'form.update_permission' => ['required', Rule::in($this->permissionNames())],
            'form.sort_order' => ['required', 'integer', 'between:0,9999'],
            'form.is_active' => ['boolean'],
        ])['form'];

        if ($record->exists) {
            unset($validated['code']);
        }

        $record->fill($validated)->save();
        $this->closeWith(__('Fulfilment task saved. It applies to orders booked from now on.'));
    }

    public function saveOptions(): void
    {
        $this->authorize('documents.configure');
        SystemSetting::put(DocumentRequirementService::AUTO_LINK_SETTING, $this->autoLink, 'documents', 'Link verified reusable customer documents automatically when an order is booked.');
        $this->toast(__('Options saved.'));
    }

    public function render(): mixed
    {
        return view('livewire.admin.document-types.index', [
            'types' => DocumentType::query()->with('defaultDepartment:id,name')->orderBy('sort_order')->orderBy('name')->get(),
            'rules' => DocumentRequirementRule::query()->with(['documentType:id,name', 'department:id,name', 'taskType:id,name'])->get()
                ->sortBy(fn (DocumentRequirementRule $rule) => $rule->department->name.$rule->documentType->name)->values(),
            'tasks' => FulfilmentTaskType::query()->with('department:id,name')->orderBy('sort_order')->get(),
            'departments' => Department::query()->active()->orderBy('sort_order')->pluck('name', 'id'),
            'typeOptions' => DocumentType::query()->orderBy('name')->pluck('name', 'id'),
            'taskOptions' => FulfilmentTaskType::query()->orderBy('sort_order')->pluck('name', 'id'),
            'permissions' => collect($this->permissionNames())->mapWithKeys(fn (string $name) => [$name => $name]),
        ]);
    }

    /**
     * @return list<string>
     */
    private function permissionNames(): array
    {
        return collect(config('erp.permissions'))
            ->flatMap(fn (array $module, string $key) => array_map(fn (string $action) => "{$key}.{$action}", $module['actions']))
            ->values()->all();
    }

    private function closeWith(string $message): void
    {
        $this->drawer = null;
        $this->toast($message);
    }
}
