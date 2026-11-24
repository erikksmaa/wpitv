<?php

namespace App\Livewire\Admin\Dashboard;

use App\Services\AdminDashboardMetrics;
use App\Services\Equipment\EquipmentReports;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class Index extends Component
{
    public function render(AdminDashboardMetrics $metrics)
    {
        Gate::authorize('access-admin');
        $user = Auth::user();
        $dashboard = [];
        $dashboardAvailable = true;

        try {
            $dashboard = $metrics->get($user);
        } catch (\Throwable $exception) {
            $dashboardAvailable = false;
            Log::error('Admin dashboard metrics failed to load.', ['exception' => $exception]);
        }

        $equipment = null;
        if (Gate::allows('equipment.report.view')) {
            try {
                $equipment = app(EquipmentReports::class)->stock();
            } catch (\Illuminate\Database\QueryException $error) {
                report($error);
            }
        }

        return view('livewire.admin.dashboard.index', [
            'equipment' => $equipment,
            'dashboard' => $dashboard,
            'dashboardAvailable' => $dashboardAvailable,
            'user' => $user,
        ])->layout('layouts.admin', [
            'title' => 'Dasbor Admin - WPiTV',
        ]);
    }
}
