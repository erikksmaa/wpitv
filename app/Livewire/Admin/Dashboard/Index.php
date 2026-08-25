<?php

namespace App\Livewire\Admin\Dashboard;

use App\Services\AdminDashboardMetrics;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class Index extends Component
{
    public function render(AdminDashboardMetrics $metrics)
    {
        $dashboard = [];
        $dashboardAvailable = true;

        try {
            $dashboard = $metrics->get();
        } catch (\Throwable $exception) {
            $dashboardAvailable = false;
            Log::error('Admin dashboard metrics failed to load.', ['exception' => $exception]);
        }

        return view('livewire.admin.dashboard.index', [
            'dashboard' => $dashboard,
            'dashboardAvailable' => $dashboardAvailable,
            'user' => Auth::user(),
        ])->layout('layouts.admin', [
            'title' => 'Dasbor Admin - WPiTV',
        ]);
    }
}
