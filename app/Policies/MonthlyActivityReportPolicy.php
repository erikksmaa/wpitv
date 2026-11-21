<?php

namespace App\Policies;

use App\Models\MonthlyActivityReport;
use App\Models\User;

class MonthlyActivityReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction() || $user->isAdminNews());
    }

    public function view(User $user, MonthlyActivityReport $report): bool
    {
        return ($user->status && $user->isSuperAdmin()) || $this->create($user, $report->division);
    }

    public function create(User $user, string $division): bool
    {
        return $user->status && match ($division) {
            'production' => $user->isAdminProduction(),
            'news' => $user->isAdminNews(),
            default => false,
        };
    }

    public function update(User $user, MonthlyActivityReport $report): bool
    {
        return $this->create($user, $report->division);
    }

    public function submit(User $user, MonthlyActivityReport $report): bool
    {
        return $this->update($user, $report);
    }

    public function review(User $user, MonthlyActivityReport $report): bool
    {
        return $user->status && $user->isSuperAdmin();
    }

    public function delete(User $user, MonthlyActivityReport $report): bool
    {
        return false;
    }
}
