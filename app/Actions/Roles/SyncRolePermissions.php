<?php

namespace App\Actions\Roles;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Services\AuditService;
use App\Support\PermissionRegistry;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncRolePermissions
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  list<string>  $permissions
     */
    public function handle(Role $role, array $permissions): void
    {
        if ($role->name === User::SUPER_ADMIN_ROLE) {
            throw new BusinessRuleException(__('The Super Admin role always has every permission and cannot be edited.'), 'super_admin_role');
        }

        $unknown = array_diff($permissions, PermissionRegistry::all());

        if ($unknown !== []) {
            throw new BusinessRuleException(__('Unknown permissions: :list', ['list' => implode(', ', $unknown)]), 'unknown_permission');
        }

        DB::transaction(function () use ($role, $permissions): void {
            $before = $role->permissions()->pluck('name')->sort()->values()->all();
            $role->syncPermissions($permissions);
            $after = collect($permissions)->sort()->values()->all();

            if ($before !== $after) {
                $this->audit->record('permissions_changed', 'roles', $role, [
                    'revoked' => array_values(array_diff($before, $after)),
                ], [
                    'granted' => array_values(array_diff($after, $before)),
                ]);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
