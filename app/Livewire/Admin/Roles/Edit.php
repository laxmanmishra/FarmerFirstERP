<?php

namespace App\Livewire\Admin\Roles;

use App\Actions\Roles\SyncRolePermissions;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\User;
use App\Support\PermissionRegistry;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Edit extends Component
{
    use InteractsWithUi;

    #[Locked]
    public int $roleId;

    /** @var list<string> */
    public array $permissions = [];

    public function mount(Role $role): void
    {
        $this->authorize('roles.view');
        abort_if($role->name === User::SUPER_ADMIN_ROLE, 404);

        $this->roleId = $role->id;
        $this->permissions = $role->permissions()->pluck('name')->all();
    }

    public function toggleModule(string $module): void
    {
        $this->authorize('roles.manage');

        $modulePermissions = PermissionRegistry::expand(["{$module}.*"]);
        $allSelected = array_diff($modulePermissions, $this->permissions) === [];

        $this->permissions = $allSelected
            ? array_values(array_diff($this->permissions, $modulePermissions))
            : array_values(array_unique([...$this->permissions, ...$modulePermissions]));
    }

    public function save(SyncRolePermissions $sync): void
    {
        $this->authorize('roles.manage');

        $role = Role::query()->findOrFail($this->roleId);

        if ($this->attempt(function () use ($sync, $role): bool {
            $sync->handle($role, array_values($this->permissions));

            return true;
        })) {
            $this->toast(__('Permissions for :role saved.', ['role' => $role->name]));
        }
    }

    public function render(): mixed
    {
        $role = Role::query()->withCount('users')->findOrFail($this->roleId);

        return view('livewire.admin.roles.edit', [
            'role' => $role,
            'modules' => PermissionRegistry::modules(),
            'canManage' => auth()->user()->can('roles.manage'),
        ])->title(__('Role: :name', ['name' => $role->name]));
    }
}
