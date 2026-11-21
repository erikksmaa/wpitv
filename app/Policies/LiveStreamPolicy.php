<?php

namespace App\Policies;

use App\Models\LiveStream;
use App\Models\User;

class LiveStreamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }

    public function view(User $user, LiveStream $stream): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, LiveStream $stream): bool
    {
        return $this->viewAny($user);
    }

    public function changeStatus(User $user, LiveStream $stream): bool
    {
        return $this->viewAny($user);
    }

    public function manageArchive(User $user, LiveStream $stream): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, LiveStream $stream): bool
    {
        return false;
    }
}
