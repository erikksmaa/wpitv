<?php

namespace App\Policies;

use App\Models\InventoryCategory;
use App\Models\User;

class InventoryCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }

    public function view(User $user, InventoryCategory $category): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, InventoryCategory $category): bool
    {
        return $this->viewAny($user);
    }

    public function archive(User $user, InventoryCategory $category): bool
    {
        return $this->viewAny($user);
    }

    // Operational categories are archived so their audit trail is retained.
    public function delete(User $user, InventoryCategory $category): bool
    {
        return false;
    }
}
