<?php

namespace App\Services;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Credential verification shared by web and API logins (SRS v6.1 §2):
 * lockout after repeated failures, inactive-account refusal and a login
 * history row for every attempt.
 */
class AuthenticationService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @throws ValidationException
     */
    public function attempt(string $identifier, string $password, Request $request, string $channel = 'web'): User
    {
        $identifier = trim($identifier);
        $user = $this->findUser($identifier);

        if ($user?->isLocked()) {
            $this->history($user, $identifier, LoginHistory::EVENT_LOCKED, $channel, $request);

            throw ValidationException::withMessages([
                'email' => __('This account is locked after repeated failed attempts. Try again after :time.', [
                    'time' => $user->locked_until->format('H:i'),
                ]),
            ]);
        }

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->registerFailure($user, $identifier, $channel, $request);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->is_active) {
            $this->history($user, $identifier, LoginHistory::EVENT_INACTIVE, $channel, $request);

            throw ValidationException::withMessages(['email' => __('This account has been deactivated. Contact your administrator.')]);
        }

        $user->forceFill([
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        $this->history($user, $identifier, LoginHistory::EVENT_SUCCESS, $channel, $request);
        $this->audit->record('login', 'auth', $user, newValues: ['channel' => $channel], userId: $user->id);

        return $user;
    }

    public function recordLogout(User $user, Request $request, string $channel = 'web'): void
    {
        $this->history($user, $user->email, LoginHistory::EVENT_LOGOUT, $channel, $request);
        $this->audit->record('logout', 'auth', $user, newValues: ['channel' => $channel], userId: $user->id);
    }

    private function findUser(string $identifier): ?User
    {
        $column = str_contains($identifier, '@') ? 'email' : 'mobile';

        return User::query()->where($column, $column === 'email' ? Str::lower($identifier) : $identifier)->first();
    }

    private function registerFailure(?User $user, string $identifier, string $channel, Request $request): void
    {
        if ($user !== null) {
            $failures = $user->failed_login_count + 1;
            $lock = $failures >= config('erp.security.max_failed_logins');

            $user->forceFill([
                'failed_login_count' => $lock ? 0 : $failures,
                'locked_until' => $lock ? now()->addMinutes(config('erp.security.lockout_minutes')) : $user->locked_until,
            ])->saveQuietly();

            if ($lock) {
                $this->audit->record('account_locked', 'auth', $user, newValues: ['locked_until' => $user->locked_until->toIso8601String()], userId: $user->id);
            }
        }

        $this->history($user, $identifier, LoginHistory::EVENT_FAILED, $channel, $request);
    }

    private function history(?User $user, string $identifier, string $event, string $channel, Request $request): void
    {
        LoginHistory::create([
            'user_id' => $user?->id,
            'identifier' => Str::limit($identifier, 250, ''),
            'event' => $event,
            'channel' => $channel,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
        ]);
    }
}
