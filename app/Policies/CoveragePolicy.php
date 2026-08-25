<?php

namespace App\Policies;

use App\Models\Coverage;
use App\Models\User;

class CoveragePolicy
{
    /**
     * Determine whether the user can view any coverages.
     */
    public function viewAny(User $user): bool
    {
        return (bool) $user->status;
    }

    /**
     * Determine whether the user can view the coverage.
     */
    public function view(User $user, Coverage $coverage): bool
    {
        return (bool) $user->status;
    }

    /**
     * Determine whether the user can create coverages.
     * Super Admin and Admin Production can create coverage requests.
     */
    public function create(User $user): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }

    /**
     * Determine whether the user can update the coverage.
     * Super Admin and Admin Production can update coverage requests.
     */
    public function update(User $user, Coverage $coverage): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }

    /**
     * Determine whether the user can delete the coverage.
     * Super Admin and Admin Production can delete coverage requests.
     */
    public function delete(User $user, Coverage $coverage): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }

    /**
     * Determine whether the user can update the coverage status or add admin notes.
     * Super Admin and Admin Production can process coverage status.
     */
    public function updateStatus(User $user, ?Coverage $coverage = null): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }
}
