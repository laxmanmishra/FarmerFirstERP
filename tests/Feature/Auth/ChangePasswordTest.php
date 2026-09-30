<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\ChangePassword;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_user_changes_password_and_clears_forced_change_flag(): void
    {
        $user = $this->userWithRole('Salesman', ['must_change_password' => true]);
        $user->createToken('phone');

        Livewire::actingAs($user)->test(ChangePassword::class)
            ->set('current_password', 'password')
            ->set('password', 'NewSecret123')
            ->set('password_confirmation', 'NewSecret123')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecret123', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $user->id, 'event' => 'password_changed']);
    }

    public function test_current_password_must_be_correct(): void
    {
        $user = $this->userWithRole('Salesman');

        Livewire::actingAs($user)->test(ChangePassword::class)
            ->set('current_password', 'not-it')
            ->set('password', 'NewSecret123')
            ->set('password_confirmation', 'NewSecret123')
            ->call('save')
            ->assertHasErrors('current_password');
    }

    public function test_new_password_must_differ_and_meet_policy(): void
    {
        $user = $this->userWithRole('Salesman');

        Livewire::actingAs($user)->test(ChangePassword::class)
            ->set('current_password', 'password')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('save')
            ->assertHasErrors('password');

        Livewire::actingAs($user)->test(ChangePassword::class)
            ->set('current_password', 'password')
            ->set('password', 'short')
            ->set('password_confirmation', 'short')
            ->call('save')
            ->assertHasErrors('password');
    }

    public function test_password_is_never_written_to_the_audit_log(): void
    {
        $user = $this->userWithRole('Salesman');

        Livewire::actingAs($user)->test(ChangePassword::class)
            ->set('current_password', 'password')
            ->set('password', 'NewSecret123')
            ->set('password_confirmation', 'NewSecret123')
            ->call('save');

        foreach (AuditLog::query()->where('auditable_id', $user->id)->get() as $entry) {
            $this->assertArrayNotHasKey('password', $entry->new_values ?? []);
            $this->assertArrayNotHasKey('password', $entry->old_values ?? []);
        }
    }
}
