<?php

namespace App\Policies;

use App\Models\User;

class TVClubSocialPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->status && ($actor->isTVClub() || $actor->isSuperAdmin());
    }

    public function view(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function manageAccounts(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function manageContent(User $actor): bool
    {
        return $actor->status && $actor->isTVClub();
    }

    public function publish(User $actor): bool
    {
        return $this->manageContent($actor);
    }

    public function export(User $actor): bool
    {
        return $this->viewAny($actor);
    }
}
