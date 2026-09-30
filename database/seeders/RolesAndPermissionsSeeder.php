<?php

namespace Database\Seeders;

use App\Support\PermissionRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Syncs the permission registry and default roles. Additive: permissions
 * granted later by administrators are never revoked by re-running it.
 * Uses bulk inserts because it runs for every test case.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        $now = now();

        Permission::query()->insertOrIgnore(array_map(
            fn (string $name): array => ['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            PermissionRegistry::all(),
        ));

        Role::query()->insertOrIgnore(array_map(
            fn (string $name): array => ['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            array_keys(config('erp.roles')),
        ));

        $permissionIds = Permission::query()->where('guard_name', 'web')->pluck('id', 'name');
        $roleIds = Role::query()->where('guard_name', 'web')->pluck('id', 'name');
        $grants = [];

        foreach (config('erp.roles') as $roleName => $patterns) {
            foreach ($patterns === [] ? [] : PermissionRegistry::expand($patterns) as $permission) {
                $grants[] = [
                    $registrar->pivotPermission => $permissionIds[$permission],
                    $registrar->pivotRole => $roleIds[$roleName],
                ];
            }
        }

        foreach (array_chunk($grants, 500) as $chunk) {
            DB::table(config('permission.table_names.role_has_permissions'))->insertOrIgnore($chunk);
        }

        $registrar->forgetCachedPermissions();
    }
}
