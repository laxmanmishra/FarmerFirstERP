<?php

namespace Tests\Feature\Api;

use App\Models\District;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_token_is_issued_with_envelope_and_expiry(): void
    {
        $user = $this->userWithRole('Salesman');

        $this->postJson(route('api.v1.auth.token'), ['email' => $user->email, 'password' => 'password', 'device_name' => 'Pixel 8'])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['success', 'message', 'data' => ['token', 'expires_at'], 'meta', 'request_id'])
            ->assertHeader('X-Request-Id');

        $this->assertDatabaseHas('login_histories', ['user_id' => $user->id, 'channel' => 'api', 'event' => 'success']);
    }

    public function test_invalid_credentials_return_validation_envelope(): void
    {
        $user = $this->userWithRole('Salesman');

        $this->postJson(route('api.v1.auth.token'), ['email' => $user->email, 'password' => 'nope', 'device_name' => 'Pixel'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('type', 'validation_error')
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_api_lockout_uses_the_same_rules_as_web(): void
    {
        config(['erp.security.max_failed_logins' => 2]);
        $user = $this->userWithRole('Salesman');

        foreach (range(1, 2) as $attempt) {
            $this->postJson(route('api.v1.auth.token'), ['email' => $user->email, 'password' => 'nope', 'device_name' => 'x']);
        }

        $this->assertTrue($user->fresh()->isLocked());
    }

    public function test_me_returns_roles_permissions_and_branches(): void
    {
        $user = $this->userWithRole('Salesman');
        $this->employeeFor($user);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('data.roles', ['Salesman'])
            ->assertJsonPath('data.branches.0.code', 'HO')
            ->assertJsonFragment(['enquiries.view_own']);
    }

    public function test_unauthenticated_request_gets_401_envelope(): void
    {
        $this->getJson(route('api.v1.me'))
            ->assertUnauthorized()
            ->assertJsonPath('type', 'unauthenticated');
    }

    public function test_missing_permission_gets_403_envelope(): void
    {
        $token = $this->userWithRole('Salesman')->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.v1.geography.districts'))
            ->assertForbidden()
            ->assertJsonPath('type', 'permission_error');
    }

    public function test_geography_lookups_cascade(): void
    {
        $token = $this->userWithRole('Owner')->createToken('test')->plainTextToken;
        $district = District::query()->where('name', 'Sehore')->firstOrFail();

        $this->withToken($token)->getJson(route('api.v1.geography.tehsils', $district))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Ashta');
    }

    public function test_unknown_record_gets_404_envelope(): void
    {
        $token = $this->userWithRole('Owner')->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.v1.geography.tehsils', 999999))
            ->assertNotFound()
            ->assertJsonPath('type', 'not_found');
    }

    public function test_deactivated_user_token_is_refused(): void
    {
        $user = $this->userWithRole('Salesman');
        $token = $user->createToken('test')->plainTextToken;
        $user->forceFill(['is_active' => false])->save();

        $this->withToken($token)->getJson(route('api.v1.me'))->assertForbidden();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = $this->userWithRole('Salesman');
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson(route('api.v1.auth.logout'))->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }
}
