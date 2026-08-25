<?php

namespace App\Policies;

use App\Models\Banner;
use App\Models\User;

class BannerPolicy
{
    /**
     * Determine whether the user can view any banners.
     */
    public function viewAny(User $user): bool
    {
        return (bool) $user->status;
    }

    /**
     * Determine whether the user can view the banner.
     */
    public function view(User $user, Banner $banner): bool
    {
        return (bool) $user->status;
    }

    /**
     * Determine whether the user can create banners.
     * Super Admin and Admin Production can create banners.
     */
    public function create(User $user): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }

    /**
     * Determine whether the user can update the banner.
     * Super Admin and Admin Production can update banners.
     */
    public function update(User $user, Banner $banner): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }

    /**
     * Determine whether the user can delete the banner.
     * Super Admin and Admin Production can delete banners.
     */
    public function delete(User $user, Banner $banner): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }
}
