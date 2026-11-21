<?php

namespace App\Policies;

use App\Models\EquipmentLoan;
use App\Models\User;

class EquipmentLoanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function viewReport(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function exportReport(User $user): bool
    {
        return $this->viewReport($user);
    }

    public function view(User $user, EquipmentLoan $loan): bool
    {
        return $this->viewAny($user);
    }

    public function recordReturn(User $user, EquipmentLoan $loan): bool
    {
        // Lifecycle state is checked under the header lock, after receipt replay.
        return $this->viewAny($user);
    }

    public function reportLoss(User $user, EquipmentLoan $loan): bool
    {
        // Replay is permitted before checking lifecycle state under the lock.
        return $this->viewAny($user);
    }

    public function extend(User $user, EquipmentLoan $loan): bool
    {
        // State and version checks follow receipt replay under the header lock.
        return $this->viewAny($user);
    }

    public function correct(User $user, EquipmentLoan $loan): bool
    {
        return $user->status && $user->isSuperAdmin();
    }

    public function update(User $user, EquipmentLoan $loan): bool
    {
        return false;
    }

    public function delete(User $user, EquipmentLoan $loan): bool
    {
        return false;
    }
}
