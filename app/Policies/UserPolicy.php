<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     * Only Super Admin can view user management.
     */
    public function viewAny(User $user): bool
    {
        return $user->status && $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view the user.
     * Only Super Admin can view user details.
     */
    public function view(User $user, User $model): bool
    {
        return $user->status && $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can create users.
     * Only Super Admin can create users.
     */
    public function create(User $user): bool
    {
        return $user->status && $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can update the user.
     * Only Super Admin can update users.
     */
    public function update(User $user, User $model): bool
    {
        return $user->status && $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can delete the user.
     * Only Super Admin can delete users.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->status && $user->isSuperAdmin();
    }
}
