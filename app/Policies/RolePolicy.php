<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    /**
     * Determine whether the user can view any roles.
     * Only Super Admin can view role management.
     */
    public function viewAny(User $user): bool
    {
        return $user->status && $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view the role.
     * Only Super Admin can view role details.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->status && $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can create roles.
     * Only Super Admin can create roles.
     */
    public function create(User $user): bool
    {
        return $user->status && $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can update the role.
     * Only Super Admin can update roles.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->status && $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can delete the role.
     * Only Super Admin can delete roles.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->status && $user->isSuperAdmin();
    }
}
