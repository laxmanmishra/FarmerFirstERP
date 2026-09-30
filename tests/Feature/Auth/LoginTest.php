<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Models\AuditLog;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_login_page_renders(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Sign in');
    }

    public function test_user_can_sign_in_with_email_and_is_sent_to_dashboard(): void
    {
        $user = $this->userWithRole('Salesman', ['email' => 'dwarika@farmerfirst.test']);

        Livewire::test(Login::class)
            ->set('email', 'Dwarika@FarmerFirst.test')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('login_histories', ['user_id' => $user->id, 'event' => LoginHistory::EVENT_SUCCESS]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'event' => 'login', 'module' => 'auth']);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_user_can_sign_in_with_mobile(): void
    {
        $user = $this->userWithRole('Salesman', ['mobile' => '9876543210']);

        Livewire::test(Login::class)
            ->set('email', '9876543210')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected_and_recorded(): void
    {
        $user = $this->userWithRole('Salesman');

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email')
            ->assertSet('password', '');

        $this->assertGuest();
        $this->assertSame(1, $user->fresh()->failed_login_count);
        $this->assertDatabaseHas('login_histories', ['user_id' => $user->id, 'event' => LoginHistory::EVENT_FAILED]);
    }

    public function test_unknown_account_gets_the_same_generic_error(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'nobody@farmerfirst.test')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors(['email' => __('auth.failed')]);

        $this->assertDatabaseHas('login_histories', ['user_id' => null, 'identifier' => 'nobody@farmerfirst.test', 'event' => LoginHistory::EVENT_FAILED]);
    }

    public function test_account_locks_after_configured_failures_and_refuses_correct_password(): void
    {
        config(['erp.security.max_failed_logins' => 3, 'erp.security.lockout_minutes' => 15]);
        $user = $this->userWithRole('Salesman');

        foreach (range(1, 3) as $attempt) {
            Livewire::test(Login::class)->set('email', $user->email)->set('password', 'wrong')->call('login');
        }

        $this->assertTrue($user->fresh()->isLocked());
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $user->id, 'event' => 'account_locked']);

        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'password')->call('login')->assertHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('login_histories', ['user_id' => $user->id, 'event' => LoginHistory::EVENT_LOCKED]);
    }

    public function test_lock_expires_after_lockout_period(): void
    {
        $user = $this->userWithRole('Salesman');
        $user->forceFill(['locked_until' => now()->addMinutes(15)])->save();

        $this->travel(16)->minutes();

        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'password')->call('login')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        $user = $this->userWithRole('Salesman', ['is_active' => false]);

        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'password')->call('login')->assertHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('login_histories', ['user_id' => $user->id, 'event' => LoginHistory::EVENT_INACTIVE]);
    }

    public function test_deactivated_user_is_signed_out_on_next_request(): void
    {
        $user = $this->userWithRole('Salesman');
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_with_forced_password_change_is_redirected(): void
    {
        $user = $this->userWithRole('Salesman', ['must_change_password' => true]);

        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'password')->call('login')->assertRedirect(route('password.change'));

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.change'));
        $this->get(route('password.change'))->assertOk();
    }

    public function test_logout_ends_session_and_is_recorded(): void
    {
        $user = $this->userWithRole('Salesman');

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('login_histories', ['user_id' => $user->id, 'event' => LoginHistory::EVENT_LOGOUT]);
        $this->assertSame(1, AuditLog::query()->where('event', 'logout')->where('user_id', $user->id)->count());
    }

    public function test_first_login_sets_working_branch(): void
    {
        $user = $this->userWithRole('Salesman');
        $this->employeeFor($user);

        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'password')->call('login');

        $this->assertSame($this->headOffice()->id, $user->fresh()->current_branch_id);
        $this->assertInstanceOf(User::class, auth()->user());
    }
}
