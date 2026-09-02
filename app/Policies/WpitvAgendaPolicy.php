<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WpitvAgenda;

class WpitvAgendaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function view(User $user, WpitvAgenda $agenda): bool
    {
        return $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, WpitvAgenda $agenda): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, WpitvAgenda $agenda): bool
    {
        return $this->manage($user);
    }

    private function manage(User $user): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }
}
