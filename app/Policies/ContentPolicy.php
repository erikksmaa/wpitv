<?php

namespace App\Policies;

use App\Models\User;

class ContentPolicy
{
    public function viewAdmin(User $user): bool
    {
        return $user->status && $user->role !== null;
    }

    public function manageProduction(User $user): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }

    public function manageNews(User $user): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminNews());
    }

    public function manageUsers(User $user): bool
    {
        return $user->status && $user->isSuperAdmin();
    }
}
