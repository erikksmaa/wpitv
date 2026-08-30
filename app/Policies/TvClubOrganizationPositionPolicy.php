<?php

namespace App\Policies;

use App\Models\TvClubOrganizationPosition;
use App\Models\User;

class TvClubOrganizationPositionPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->status;
    }

    public function view(User $user, TvClubOrganizationPosition $position): bool
    {
        return (bool) $user->status;
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, TvClubOrganizationPosition $position): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, TvClubOrganizationPosition $position): bool
    {
        return $this->canManage($user);
    }

    public function assignMember(User $user, ?TvClubOrganizationPosition $position = null): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }
}
