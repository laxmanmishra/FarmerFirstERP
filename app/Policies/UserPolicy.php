<?php

namespace App\Policies;

use App\Models\User;

/**
 * Account administration. Permission checks plus guards against
 * self-lockout and privilege escalation towards Super Admin accounts.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.create');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can('users.update') && $this->mayManage($actor, $target);
    }

    public function assignRoles(User $actor, ?User $target = null): bool
    {
        return $actor->can('users.assign_roles') && ($target === null || ($actor->isNot($target) && $this->mayManage($actor, $target)));
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $actor->can('users.deactivate') && $actor->isNot($target) && $this->mayManage($actor, $target);
    }

    public function resetPassword(User $actor, User $target): bool
    {
        return $actor->can('users.reset_password') && $actor->isNot($target) && $this->mayManage($actor, $target);
    }

    /**
     * Only a Super Admin may manage another Super Admin account.
     */
    private function mayManage(User $actor, User $target): bool
    {
        return ! $target->isSuperAdmin() || $actor->isSuperAdmin();
    }
}
