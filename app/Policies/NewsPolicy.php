<?php

namespace App\Policies;

use App\Models\News;
use App\Models\User;

class NewsPolicy
{
    /**
     * Determine whether the user can view any news.
     */
    public function viewAny(User $user): bool
    {
        return (bool) $user->status;
    }

    /**
     * Determine whether the user can view the news.
     */
    public function view(User $user, News $news): bool
    {
        return (bool) $user->status;
    }

    /**
     * Determine whether the user can create news.
     * Super Admin and Admin News can create news.
     */
    public function create(User $user): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminNews());
    }

    /**
     * Determine whether the user can update the news.
     * Super Admin and Admin News can update news.
     */
    public function update(User $user, News $news): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminNews());
    }

    /**
     * Determine whether the user can delete the news.
     * Super Admin and Admin News can delete news.
     */
    public function delete(User $user, News $news): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminNews());
    }
}
