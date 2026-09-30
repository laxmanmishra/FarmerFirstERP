<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Users\Index;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_admin_creates_user_with_multiple_roles_and_temporary_password(): void
    {
        $owner = $this->userWithRole('Owner');

        $component = Livewire::actingAs($owner)->test(Index::class)
            ->call('create')
            ->set('name', 'Dwarika Prasad')
            ->set('email', 'Dwarika@FarmerFirst.test')
            ->set('mobile', '9876543210')
            ->set('roles', ['Salesman', 'Telecaller'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showPasswordModal', true);

        $user = User::query()->where('email', 'dwarika@farmerfirst.test')->firstOrFail();
        $this->assertTrue($user->hasAllRoles(['Salesman', 'Telecaller']));
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check($component->get('revealedPassword'), $user->password));
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $user->id, 'module' => 'users', 'event' => 'created', 'user_id' => $owner->id]);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $user->id, 'event' => 'roles_changed']);
    }

    public function test_email_and_mobile_must_be_unique(): void
    {
        $existing = $this->userWithRole('Salesman', ['mobile' => '9876543210']);

        Livewire::actingAs($this->userWithRole('Owner'))->test(Index::class)
            ->call('create')
            ->set('name', 'Someone')
            ->set('email', $existing->email)
            ->set('mobile', '9876543210')
            ->call('save')
            ->assertHasErrors(['email', 'mobile']);
    }

    public function test_only_super_admin_can_grant_super_admin(): void
    {
        $target = $this->userWithRole('Salesman');

        Livewire::actingAs($this->userWithRole('Owner'))->test(Index::class)
            ->call('edit', $target->id)
            ->set('roles', ['Super Admin'])
            ->call('save')
            ->assertDispatched('toast', type: 'error');

        $this->assertFalse($target->fresh()->isSuperAdmin());

        Livewire::actingAs($this->superAdmin())->test(Index::class)
            ->call('edit', $target->id)
            ->set('roles', ['Super Admin'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($target->fresh()->isSuperAdmin());
    }

    public function test_owner_cannot_edit_a_super_admin_account(): void
    {
        $superAdmin = $this->superAdmin();

        Livewire::actingAs($this->userWithRole('Owner'))->test(Index::class)
            ->call('edit', $superAdmin->id)
            ->assertForbidden();
    }

    public function test_user_without_update_permission_cannot_edit(): void
    {
        $target = $this->userWithRole('Salesman');

        Livewire::actingAs($this->userWithPermissions(['users.view']))->test(Index::class)
            ->call('edit', $target->id)
            ->assertForbidden();
    }

    public function test_deactivation_requires_reason_revokes_tokens_and_is_audited(): void
    {
        $owner = $this->userWithRole('Owner');
        $target = $this->userWithRole('Salesman');
        $target->createToken('phone');

        Livewire::actingAs($owner)->test(Index::class)
            ->call('confirmStatusChange', $target->id)
            ->set('statusReason', '')
            ->call('changeStatus')
            ->assertHasErrors('statusReason')
            ->set('statusReason', 'Left the company')
            ->call('changeStatus')
            ->assertHasNoErrors();

        $this->assertFalse($target->fresh()->is_active);
        $this->assertSame(0, $target->tokens()->count());

        $entry = AuditLog::query()->where('event', 'deactivated')->where('auditable_id', $target->id)->firstOrFail();
        $this->assertSame('Left the company', $entry->reason);
        $this->assertSame($owner->id, $entry->user_id);
    }

    public function test_user_cannot_deactivate_own_account_even_as_super_admin(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)->test(Index::class)
            ->call('confirmStatusChange', $admin->id)
            ->set('statusReason', 'Testing self lockout')
            ->call('changeStatus')
            ->assertDispatched('toast', type: 'error');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_password_reset_issues_temporary_password_and_unlocks(): void
    {
        $target = $this->userWithRole('Salesman');
        $target->forceFill(['locked_until' => now()->addHour(), 'failed_login_count' => 3])->save();

        $component = Livewire::actingAs($this->userWithRole('Owner'))->test(Index::class)
            ->call('resetPassword', $target->id)
            ->assertSet('showPasswordModal', true);

        $target->refresh();
        $this->assertTrue($target->must_change_password);
        $this->assertFalse($target->isLocked());
        $this->assertTrue(Hash::check($component->get('revealedPassword'), $target->password));
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $target->id, 'event' => 'password_reset']);
    }

    public function test_list_filters_by_role_and_search(): void
    {
        $this->userWithRole('Salesman', ['name' => 'Dwarika Prasad']);
        $this->userWithRole('Telecaller', ['name' => 'Pooja Sharma']);

        Livewire::actingAs($this->userWithRole('Owner'))->test(Index::class)
            ->set('role', 'Telecaller')
            ->assertSee('Pooja Sharma')
            ->assertDontSee('Dwarika Prasad')
            ->set('role', '')
            ->set('search', 'dwar')
            ->assertSee('Dwarika Prasad')
            ->assertDontSee('Pooja Sharma');
    }
}
