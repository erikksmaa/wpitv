<?php

namespace App\Policies;

use App\Models\TVClubGroup;
use App\Models\User;

class TVClubGroupPolicy
{
    /**
     * Determine whether the user can view any groups.
     */
    public function viewAny(User $user): bool
    {
        return $user->canManageProgramsAndTVClub();
    }

    /**
     * Determine whether the user can view the group.
     */
    public function view(User $user, TVClubGroup $group): bool
    {
        return $user->canManageProgramsAndTVClub();
    }

    /**
     * Determine whether the user can create groups.
     * Super Admin, Admin Production, and TVClub can create groups.
     */
    public function create(User $user): bool
    {
        return $user->canManageProgramsAndTVClub();
    }

    /**
     * Determine whether the user can update the group.
     * Super Admin, Admin Production, and TVClub can update groups.
     */
    public function update(User $user, TVClubGroup $group): bool
    {
        return $user->canManageProgramsAndTVClub();
    }

    /**
     * Determine whether the user can delete the group.
     * Super Admin, Admin Production, and TVClub can delete groups.
     */
    public function delete(User $user, TVClubGroup $group): bool
    {
        return $user->canManageProgramsAndTVClub();
    }

    /**
     * Determine whether the user can assign or remove members for the group.
     * Super Admin, Admin Production, and TVClub can manage group member assignments.
     */
    public function assignMember(User $user, ?TVClubGroup $group = null): bool
    {
        return $user->canManageProgramsAndTVClub();
    }
}
