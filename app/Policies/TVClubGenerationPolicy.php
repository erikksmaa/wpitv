<?php

namespace App\Policies;

use App\Models\TVClubGeneration;
use App\Models\User;

class TVClubGenerationPolicy
{
    /**
     * Determine whether the user can view any generations.
     */
    public function viewAny(User $user): bool
    {
        return (bool) $user->status;
    }

    /**
     * Determine whether the user can view the generation.
     */
    public function view(User $user, TVClubGeneration $generation): bool
    {
        return (bool) $user->status;
    }

    /**
     * Determine whether the user can create generations.
     * Super Admin and Admin Production can create generations.
     */
    public function create(User $user): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }

    /**
     * Determine whether the user can update the generation.
     * Super Admin and Admin Production can update generations.
     */
    public function update(User $user, TVClubGeneration $generation): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }

    /**
     * Determine whether the user can delete the generation.
     * Super Admin and Admin Production can delete generations.
     */
    public function delete(User $user, TVClubGeneration $generation): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }
}
