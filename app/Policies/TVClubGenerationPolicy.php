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
        return $user->canManageProgramsAndTVClub();
    }

    /**
     * Determine whether the user can view the generation.
     */
    public function view(User $user, TVClubGeneration $generation): bool
    {
        return $user->canManageProgramsAndTVClub();
    }

    /**
     * Determine whether the user can create generations.
     * Super Admin, Admin Production, and TVClub can create generations.
     */
    public function create(User $user): bool
    {
        return $user->canManageProgramsAndTVClub();
    }

    /**
     * Determine whether the user can update the generation.
     * Super Admin, Admin Production, and TVClub can update generations.
     */
    public function update(User $user, TVClubGeneration $generation): bool
    {
        return $user->canManageProgramsAndTVClub();
    }

    /**
     * Determine whether the user can delete the generation.
     * Super Admin, Admin Production, and TVClub can delete generations.
     */
    public function delete(User $user, TVClubGeneration $generation): bool
    {
        return $user->canManageProgramsAndTVClub();
    }
}
