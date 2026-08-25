<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    /**
     * Determine whether the user can view any programs.
     */
    public function viewAny(User $user): bool
    {
        return (bool) $user->status;
    }

    /**
     * Determine whether the user can view the program.
     */
    public function view(User $user, Program $program): bool
    {
        return (bool) $user->status;
    }

    /**
     * Determine whether the user can create programs.
     */
    public function create(User $user): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }

    /**
     * Determine whether the user can update the program.
     */
    public function update(User $user, Program $program): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }

    /**
     * Determine whether the user can delete the program.
     */
    public function delete(User $user, Program $program): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
    }
}
