<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Roles\Edit;
use App\Livewire\Admin\Roles\Index;
use App\Models\AuditLog;
use App\Support\PermissionRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_seeder_registers_every_permission_and_default_role(): void
    {
        $this->assertSame(count(PermissionRegistry::all()), Permission::query()->count());
        $this->assertSame(array_keys(config('erp.roles')), Role::query()->orderBy('id')->pluck('name')->all());
        $this->assertSame(Permission::query()->count(), Role::findByName('Owner')->permissions()->count());
    }

    public function test_seeder_is_additive_and_keeps_admin_grants(): void
    {
        $salesman = Role::findByName('Salesman');
        $salesman->givePermissionTo('reports.sales');

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue($salesman->fresh()->hasPermissionTo('reports.sales'));
    }

    public function test_default_role_matrix_reflects_department_boundaries(): void
    {
        $retail = Role::findByName('Retail Employee');
        $this->assertTrue($retail->hasPermissionTo('finance.update'));
        $this->assertFalse($retail->hasPermissionTo('accounts.clear_payment'));
        $this->assertFalse($retail->hasPermissionTo('finance.configure'));

        $salesman = Role::findByName('Salesman');
        $this->assertTrue($salesman->hasPermissionTo('enquiries.view_own'));
        $this->assertFalse($salesman->hasPermissionTo('enquiries.view_all'));
        $this->assertFalse($salesman->hasPermissionTo('deals.approve'));
        $this->assertFalse($salesman->hasPermissionTo('waivers.approve'));

        $this->assertFalse(Role::findByName('Sales Manager')->hasPermissionTo('waivers.approve_critical'));
    }

    public function test_permission_changes_are_saved_and_audited(): void
    {
        $role = Role::findByName('Telecaller');

        Livewire::actingAs($this->userWithRole('Owner'))->test(Edit::class, ['role' => $role])
            ->set('permissions', ['dashboard.view', 'telecaller.queue'])
            ->call('save')
            ->assertDispatched('toast', type: 'success');

        $this->assertEqualsCanonicalizing(['dashboard.view', 'telecaller.queue'], $role->fresh()->permissions->pluck('name')->all());

        $entry = AuditLog::query()->where('event', 'permissions_changed')->where('auditable_id', $role->id)->firstOrFail();
        $this->assertContains('telecaller.claim', $entry->old_values['revoked']);
    }

    public function test_viewer_cannot_save_permissions(): void
    {
        Livewire::actingAs($this->userWithPermissions(['roles.view']))->test(Edit::class, ['role' => Role::findByName('Telecaller')])
            ->set('permissions', ['dashboard.view'])
            ->call('save')
            ->assertForbidden();
    }

    public function test_unknown_permission_is_rejected(): void
    {
        Livewire::actingAs($this->userWithRole('Owner'))->test(Edit::class, ['role' => Role::findByName('Telecaller')])
            ->set('permissions', ['dashboard.view', 'nuclear.launch'])
            ->call('save')
            ->assertDispatched('toast', type: 'error');

        $this->assertTrue(Role::findByName('Telecaller')->hasPermissionTo('telecaller.claim'));
    }

    public function test_custom_role_can_be_created_from_template_and_deleted_when_unused(): void
    {
        $owner = $this->userWithRole('Owner');

        Livewire::actingAs($owner)->test(Index::class)
            ->call('create')
            ->set('name', 'Field Supervisor')
            ->set('copyFromId', Role::findByName('Salesman')->id)
            ->call('save')
            ->assertRedirect();

        $custom = Role::findByName('Field Supervisor');
        $this->assertTrue($custom->hasPermissionTo('enquiries.create'));

        Livewire::actingAs($owner)->test(Index::class)->call('delete', $custom->id);
        $this->assertNull(Role::query()->where('name', 'Field Supervisor')->first());
    }

    public function test_default_roles_cannot_be_deleted(): void
    {
        Livewire::actingAs($this->userWithRole('Owner'))->test(Index::class)
            ->call('delete', Role::findByName('Salesman')->id)
            ->assertDispatched('toast', type: 'error');

        $this->assertNotNull(Role::query()->where('name', 'Salesman')->first());
    }
}
