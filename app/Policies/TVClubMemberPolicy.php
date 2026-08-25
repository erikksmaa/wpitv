<?php

namespace App\Policies;

use App\Models\TVClubMember;
use App\Models\User;

class TVClubMemberPolicy
{
    /**
     * Determine whether the user can view any members.
     */
    public function viewAny(User $user): bool
    {
        return (bool) $user->status;
    }

    /**
     * Determine whether the user can view the member.
     */
    public function view(User $user, TVClubMember $member): bool
    {
        return (bool) $user->status;
    }

    /**
     * Determine whether the user can create members.
     * Super Admin and Admin Production can create members.
     */
    public function create(User $user): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }

    /**
     * Determine whether the user can update the member.
     * Super Admin and Admin Production can update members.
     */
    public function update(User $user, TVClubMember $member): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }

    /**
     * Determine whether the user can delete the member.
     * Super Admin and Admin Production can delete members.
     */
    public function delete(User $user, TVClubMember $member): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }
}
