<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WpitvOrganizationPosition;

class WpitvOrganizationPositionPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->status;
    }

    public function view(User $user, WpitvOrganizationPosition $position): bool
    {
        return (bool) $user->status;
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, WpitvOrganizationPosition $position): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, WpitvOrganizationPosition $position): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }
}
