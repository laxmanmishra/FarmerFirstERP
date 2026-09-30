<?php

namespace App\Actions\Users;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates or updates a login account and (optionally) its roles.
 */
class SaveUser
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{name: string, email: string, mobile: ?string}  $attributes
     * @param  list<string>|null  $roles  null leaves roles untouched
     * @return array{user: User, temporary_password: ?string}
     */
    public function handle(User $actor, array $attributes, ?array $roles, ?User $user = null): array
    {
        if ($roles !== null && in_array(User::SUPER_ADMIN_ROLE, $roles, true) && ! $actor->isSuperAdmin()) {
            throw new BusinessRuleException(__('Only a Super Admin can grant the Super Admin role.'), 'super_admin_grant');
        }

        if ($roles !== null && $user !== null && $actor->is($user)) {
            throw new BusinessRuleException(__('You cannot change your own roles.'), 'self_role_change');
        }

        return DB::transaction(function () use ($actor, $attributes, $roles, $user): array {
            $temporaryPassword = null;

            if ($user === null) {
                $temporaryPassword = Str::password(12, symbols: false);
                $user = User::create([
                    ...$attributes,
                    'password' => $temporaryPassword,
                    'must_change_password' => true,
                    'current_branch_id' => $actor->current_branch_id,
                ]);
            } else {
                $user->update($attributes);
            }

            if ($roles !== null) {
                $before = $user->getRoleNames()->sort()->values()->all();
                $user->syncRoles($roles);
                $after = $user->getRoleNames()->sort()->values()->all();

                if ($before !== $after) {
                    $this->audit->record('roles_changed', 'users', $user, ['roles' => $before], ['roles' => $after]);
                }
            }

            return ['user' => $user, 'temporary_password' => $temporaryPassword];
        });
    }
}
