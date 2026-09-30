<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues a one-time temporary password, forces a change at next sign-in,
 * clears any lockout and revokes existing sessions and tokens.
 */
class ResetUserPassword
{
    public function __construct(private readonly AuditService $audit) {}

    public function handle(User $user): string
    {
        $temporaryPassword = Str::password(12, symbols: false);

        DB::transaction(function () use ($user, $temporaryPassword): void {
            $user->forceFill([
                'password' => $temporaryPassword,
                'must_change_password' => true,
                'failed_login_count' => 0,
                'locked_until' => null,
                'remember_token' => Str::random(60),
            ])->saveQuietly();

            $user->tokens()->delete();

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }

            $this->audit->record('password_reset', 'users', $user);
        });

        return $temporaryPassword;
    }

    public function unlock(User $user): void
    {
        $user->forceFill(['failed_login_count' => 0, 'locked_until' => null])->saveQuietly();
        $this->audit->record('unlocked', 'users', $user);
    }
}
