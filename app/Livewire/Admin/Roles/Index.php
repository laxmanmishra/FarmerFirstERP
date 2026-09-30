<?php

namespace App\Livewire\Admin\Roles;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Title('Roles & Permissions')]
class Index extends Component
{
    use InteractsWithUi;

    public bool $showForm = false;

    public string $name = '';

    public ?int $copyFromId = null;

    public function mount(): void
    {
        $this->authorize('roles.view');
    }

    public function create(): void
    {
        $this->authorize('roles.manage');
        $this->reset('name', 'copyFromId');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(AuditService $audit): mixed
    {
        $this->authorize('roles.manage');

        $this->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'copyFromId' => ['nullable', Rule::exists('roles', 'id')],
        ]);

        $role = Role::create(['name' => trim($this->name), 'guard_name' => 'web']);

        if ($this->copyFromId && ($source = Role::query()->find($this->copyFromId)) && $source->name !== User::SUPER_ADMIN_ROLE) {
            $role->syncPermissions($source->permissions);
        }

        $audit->record('created', 'roles', $role, newValues: ['name' => $role->name, 'copied_from' => $source->name ?? null]);

        return $this->redirectRoute('admin.roles.edit', $role, navigate: true);
    }

    public function delete(int $roleId, AuditService $audit): void
    {
        $this->authorize('roles.manage');
        $role = Role::query()->withCount('users')->findOrFail($roleId);

        if (array_key_exists($role->name, config('erp.roles'))) {
            $this->toast(__('Default roles cannot be deleted.'), 'error');

            return;
        }

        if ($role->users_count > 0) {
            $this->toast(__('Remove this role from its :count users before deleting it.', ['count' => $role->users_count]), 'error');

            return;
        }

        $audit->record('deleted', 'roles', $role, ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->all()]);
        $role->delete();
        $this->toast(__('Role deleted.'));
    }

    public function render(): mixed
    {
        return view('livewire.admin.roles.index', [
            'roles' => Role::query()->withCount(['users', 'permissions'])->orderBy('name')->get(),
            'defaultRoles' => array_keys(config('erp.roles')),
        ]);
    }
}
