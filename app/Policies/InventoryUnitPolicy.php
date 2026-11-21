<?php

namespace App\Policies;

use App\Models\InventoryUnit;
use App\Models\User;

class InventoryUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }

    public function view(User $user, InventoryUnit $category): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, InventoryUnit $category): bool
    {
        return $this->viewAny($user);
    }

    public function archive(User $user, InventoryUnit $category): bool
    {
        return $this->viewAny($user);
    }

    public function reactivateRecovered(User $user, InventoryUnit $unit): bool
    {
        return $user->status && $user->isSuperAdmin();
    }

    // Operational units are archived so their audit trail is retained.
    public function delete(User $user, InventoryUnit $category): bool
    {
        return false;
    }
}
