<?php

namespace App\Livewire\Admin\Settings;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\LookupValue;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Configurable lists (enquiry sources, close reasons, follow-up types). Codes are
 * stored on records and cannot change; names can be renamed and values deactivated.
 */
class Lists extends Component
{
    use InteractsWithUi;

    public string $type = LookupValue::ENQUIRY_SOURCE;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public bool $showForm = false;

    public function mount(): void
    {
        $this->authorize('settings.view');
    }

    public function updatedType(): void
    {
        $this->type = array_key_exists($this->type, LookupValue::TYPES) ? $this->type : LookupValue::ENQUIRY_SOURCE;
        $this->showForm = false;
    }

    public function create(): void
    {
        $this->authorize('settings.manage');
        $this->reset('editingId', 'code', 'name');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('settings.manage');
        $value = LookupValue::query()->where('type', $this->type)->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $value->id;
        $this->code = $value->code;
        $this->name = $value->name;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('settings.manage');

        $this->validate([
            'code' => $this->editingId ? [] : ['required', 'string', 'max:50', 'regex:/^[A-Z][A-Z0-9_]*$/', Rule::unique('lookup_values', 'code')->where('type', $this->type)],
            'name' => ['required', 'string', 'max:150'],
        ], ['code.regex' => __('Use UPPER_CASE letters, digits and underscores.')]);

        if ($this->editingId) {
            LookupValue::query()->where('type', $this->type)->findOrFail($this->editingId)->update(['name' => $this->name]);
        } else {
            LookupValue::create([
                'type' => $this->type,
                'code' => $this->code,
                'name' => $this->name,
                'sort_order' => (int) LookupValue::query()->where('type', $this->type)->max('sort_order') + 10,
            ]);
        }

        $this->showForm = false;
        $this->toast(__('Saved.'));
    }

    public function toggle(int $id): void
    {
        $this->authorize('settings.manage');
        $value = LookupValue::query()->where('type', $this->type)->findOrFail($id);

        if ($value->is_active && LookupValue::query()->where('type', $this->type)->active()->count() === 1) {
            $this->toast(__('Keep at least one active value.'), 'error');

            return;
        }

        $value->update(['is_active' => ! $value->is_active]);
    }

    public function render(): mixed
    {
        return view('livewire.admin.settings.lists', [
            'values' => LookupValue::query()->where('type', $this->type)->orderBy('sort_order')->orderBy('name')->get(),
            'types' => LookupValue::TYPES,
            'canManage' => auth()->user()->can('settings.manage'),
        ]);
    }
}
