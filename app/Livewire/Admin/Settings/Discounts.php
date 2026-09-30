<?php

namespace App\Livewire\Admin\Settings;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\DiscountLimit;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * Discount authority per role (SRS v6.1 §4). Quotations above the preparer's
 * limit need approval from someone whose limit covers the discount.
 */
class Discounts extends Component
{
    use InteractsWithUi;

    /** @var array<int, array{max_percent: string, max_amount: string}> */
    public array $limits = [];

    public function mount(): void
    {
        $this->authorize('settings.view');

        $existing = DiscountLimit::query()->get()->keyBy('role_id');

        foreach ($this->roles() as $role) {
            $this->limits[$role->id] = [
                'max_percent' => (string) ($existing[$role->id]->max_percent ?? '0.00'),
                'max_amount' => (string) ($existing[$role->id]->max_amount ?? ''),
            ];
        }
    }

    public function save(): void
    {
        $this->authorize('settings.manage');

        $this->validate([
            'limits.*.max_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'limits.*.max_amount' => ['nullable', 'numeric', 'min:0'],
        ], attributes: ['limits.*.max_percent' => __('maximum %'), 'limits.*.max_amount' => __('maximum amount')]);

        $roleIds = $this->roles()->pluck('id')->all();

        foreach ($this->limits as $roleId => $limit) {
            if (! in_array((int) $roleId, $roleIds, true)) {
                continue;
            }

            $record = DiscountLimit::query()->firstOrNew(['role_id' => $roleId]);
            $record->fill(['max_percent' => $limit['max_percent'], 'max_amount' => $limit['max_amount'] === '' ? null : $limit['max_amount']]);

            if ($record->isDirty() || ! $record->exists) {
                $record->save();
            }
        }

        $this->toast(__('Discount limits saved.'));
    }

    public function render(): mixed
    {
        return view('livewire.admin.settings.discounts', ['roles' => $this->roles(), 'canManage' => auth()->user()->can('settings.manage')]);
    }

    /**
     * @return Collection<int, Role>
     */
    private function roles(): Collection
    {
        return Role::query()->where('name', '!=', User::SUPER_ADMIN_ROLE)->orderBy('name')->get();
    }
}
