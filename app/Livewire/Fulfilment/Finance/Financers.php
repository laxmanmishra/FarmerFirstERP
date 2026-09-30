<?php

namespace App\Livewire\Fulfilment\Finance;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Financer;
use App\Models\FinancerContact;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Financer and financer-contact master (SRS §65–66). Deactivated, never deleted.
 */
class Financers extends Component
{
    use InteractsWithUi;

    /** 'financer' | 'contact'; false/null when closed. */
    public mixed $drawer = null;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $financerId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->authorize('finance.configure');
    }

    public function editFinancer(?int $id = null): void
    {
        $this->authorize('finance.configure');
        $this->resetValidation();
        $this->editingId = $id;
        $this->form = $id ? Financer::query()->findOrFail($id)->only(['code', 'name', 'type', 'is_active']) : ['code' => '', 'name' => '', 'type' => 'bank', 'is_active' => true];
        $this->drawer = 'financer';
    }

    public function saveFinancer(): void
    {
        $this->authorize('finance.configure');
        $record = $this->editingId ? Financer::query()->findOrFail($this->editingId) : new Financer;

        $validated = $this->validate([
            'form.code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_\-]+$/', Rule::unique('financers', 'code')->ignore($record->id)],
            'form.name' => ['required', 'string', 'max:150'],
            'form.type' => ['required', Rule::in(array_keys(Financer::TYPES))],
            'form.is_active' => ['boolean'],
        ])['form'];

        $record->fill($validated)->save();
        $this->drawer = null;
        $this->toast(__('Financer saved.'));
    }

    public function editContact(int $financerId, ?int $id = null): void
    {
        $this->authorize('finance.configure');
        $this->resetValidation();
        $this->financerId = Financer::query()->findOrFail($financerId)->id;
        $this->editingId = $id;
        $this->form = $id ? FinancerContact::query()->where('financer_id', $financerId)->findOrFail($id)->only(['name', 'designation', 'mobile', 'email', 'area', 'is_active'])
            : ['name' => '', 'designation' => '', 'mobile' => '', 'email' => '', 'area' => '', 'is_active' => true];
        $this->drawer = 'contact';
    }

    public function saveContact(): void
    {
        $this->authorize('finance.configure');

        $validated = $this->validate([
            'form.name' => ['required', 'string', 'max:150'],
            'form.designation' => ['nullable', 'string', 'max:100'],
            'form.mobile' => ['required', 'digits:10'],
            'form.email' => ['nullable', 'email:rfc', 'max:255'],
            'form.area' => ['nullable', 'string', 'max:150'],
            'form.is_active' => ['boolean'],
        ])['form'];

        $record = $this->editingId ? FinancerContact::query()->where('financer_id', $this->financerId)->findOrFail($this->editingId) : new FinancerContact(['financer_id' => $this->financerId]);
        $record->fill(array_map(fn ($value) => $value === '' ? null : $value, $validated))->save();
        $this->drawer = null;
        $this->toast(__('Contact saved.'));
    }

    public function render(): mixed
    {
        return view('livewire.fulfilment.finance.financers', [
            'financers' => Financer::query()->with('contacts')->orderBy('name')->get(),
        ]);
    }
}
