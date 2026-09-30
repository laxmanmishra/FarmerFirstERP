<?php

namespace App\Actions\Users;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Activates or deactivates an account. Deactivation revokes API tokens and
 * database sessions immediately (forced logout, SRS v6.1 §2).
 */
class SetUserActiveState
{
    public function __construct(private readonly AuditService $audit) {}

    public function handle(User $actor, User $user, bool $active, string $reason): void
    {
        if (! $active && $actor->is($user)) {
            throw new BusinessRuleException(__('You cannot deactivate your own account.'), 'self_deactivation');
        }

        DB::transaction(function () use ($user, $active, $reason): void {
            $user->forceFill(['is_active' => $active])->saveQuietly();

            if (! $active) {
                $user->tokens()->delete();

                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                }
            }

            $this->audit->record($active ? 'activated' : 'deactivated', 'users', $user, ['is_active' => ! $active], ['is_active' => $active], $reason);
        });
    }
}
