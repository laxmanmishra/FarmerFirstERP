<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Every administration screen is refused without its permission and renders with it.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function screens(): array
    {
        return [
            'users' => ['admin.users.index', 'users.view'],
            'employees' => ['admin.employees.index', 'employees.view'],
            'roles' => ['admin.roles.index', 'roles.view'],
            'departments' => ['admin.departments.index', 'departments.view'],
            'branches' => ['admin.branches.index', 'branches.view'],
            'geography' => ['admin.geography.index', 'geography.view'],
            'settings' => ['admin.settings.index', 'settings.view'],
            'numbering only' => ['admin.settings.index', 'number_series.manage'],
            'audit logs' => ['admin.audit-logs.index', 'audit.view'],
            'dashboard' => ['dashboard', 'dashboard.view'],
        ];
    }

    #[DataProvider('screens')]
    public function test_screen_is_forbidden_without_permission(string $route, string $permission): void
    {
        $user = $this->userWithPermissions([$permission === 'dashboard.view' ? 'farmers.view' : 'dashboard.view']);

        $this->actingAs($user)->get(route($route))->assertForbidden();
    }

    #[DataProvider('screens')]
    public function test_screen_renders_with_permission(string $route, string $permission): void
    {
        $user = $this->userWithPermissions([$permission]);

        $this->actingAs($user)->get(route($route))->assertOk();
    }

    public function test_super_admin_can_open_every_screen(): void
    {
        $admin = $this->superAdmin();

        foreach (self::screens() as [$route]) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }

        $this->actingAs($admin)->get(route('admin.roles.edit', Role::findByName('Salesman')))->assertOk();
    }

    public function test_super_admin_role_has_no_editable_permission_screen(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('admin.roles.edit', Role::findByName('Super Admin')))
            ->assertNotFound();
    }

    public function test_sidebar_only_lists_permitted_modules(): void
    {
        $user = $this->userWithPermissions(['dashboard.view', 'geography.view']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('admin.geography.index'), false)
            ->assertDontSee(route('admin.users.index'), false)
            ->assertDontSee(route('admin.audit-logs.index'), false);
    }

    public function test_salesman_sees_no_administration_menu(): void
    {
        $this->actingAs($this->userWithRole('Salesman'))->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Administration');
    }
}
