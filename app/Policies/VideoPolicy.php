<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Video;

class VideoPolicy
{
    /**
     * Determine whether the user can view any videos.
     */
    public function viewAny(User $user): bool
    {
        return $user->canPublishVideos();
    }

    /**
     * Determine whether the user can view the video.
     */
    public function view(User $user, Video $video): bool
    {
        return $user->canPublishVideos();
    }

    /**
     * Determine whether the user can create videos.
     * Super Admin, Admin Production, and Admin News can create videos.
     */
    public function create(User $user): bool
    {
        return $user->canPublishVideos();
    }

    /**
     * Determine whether the user can update the video.
     * Super Admin, Admin Production, and Admin News can update videos.
     */
    public function update(User $user, Video $video): bool
    {
        return $user->canPublishVideos();
    }

    /**
     * Determine whether the user can delete the video.
     * Only Super Admin and Admin Production can delete videos.
     */
    public function delete(User $user, Video $video): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }
}
