<?php

namespace App\Livewire\Admin\Dashboard;

use App\Services\AdminDashboardMetrics;
use App\Services\TVClubSocialDashboard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class Index extends Component
{
    public string $socialMonth = '';

    public function mount(): void
    {
        $this->socialMonth = now('Asia/Jakarta')->format('Y-m');
    }

    public function render(AdminDashboardMetrics $metrics, TVClubSocialDashboard $social)
    {
        Gate::authorize('access-admin');
        $user = Auth::user();
        $dashboard = [];
        $dashboardAvailable = true;

        try {
            $dashboard = $metrics->get($user);
            $dashboard['social'] = $social->get($user, $this->socialMonth);
        } catch (\Throwable $exception) {
            $dashboardAvailable = false;
            Log::error('Admin dashboard metrics failed to load.', ['exception' => $exception]);
        }

        return view('livewire.admin.dashboard.index', [
            'dashboard' => $dashboard,
            'dashboardAvailable' => $dashboardAvailable,
            'user' => $user,
        ])->layout('layouts.admin', [
            'title' => 'Dasbor Admin - WPiTV',
        ]);
    }
}
