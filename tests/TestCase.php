<?php

namespace Tests;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Roles, permissions, organisation, geography and number series.
     */
    protected function seedReferenceData(): void
    {
        $this->seed(ReferenceDataSeeder::class);
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    /**
     * A user holding exactly the given permissions through a dedicated role.
     *
     * @param  list<string>  $permissions
     */
    protected function userWithPermissions(array $permissions, array $attributes = []): User
    {
        $role = Role::create(['name' => 'Test role '.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(array_map(fn (string $name) => Permission::findByName($name, 'web'), $permissions));

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    protected function superAdmin(): User
    {
        return $this->userWithRole(User::SUPER_ADMIN_ROLE);
    }

    protected function headOffice(): Branch
    {
        return Branch::query()->where('code', 'HO')->firstOrFail();
    }

    protected function employeeFor(User $user, array $attributes = []): Employee
    {
        $employee = Employee::factory()->for($user)->create($attributes);
        $employee->branches()->attach($this->headOffice()->id, ['is_primary' => true]);

        return $employee;
    }
}
