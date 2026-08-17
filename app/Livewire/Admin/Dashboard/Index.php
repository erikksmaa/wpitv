<?php

namespace App\Livewire\Admin\Dashboard;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Role;
use App\Models\Program;
use App\Models\Video;
use App\Models\News;
use App\Models\Banner;
use App\Models\Coverage;

class Index extends Component
{
    public function render()
    {
        $dbConnected = false;
        $dbMessage = '';
        $counts = [];

        try {
            DB::connection()->getPdo();
            $dbConnected = true;
            $dbMessage = 'Terhubung ke database MySQL wpitv';

            $counts = [
                'users' => User::count(),
                'roles' => Role::count(),
                'programs' => Program::count(),
                'videos' => Video::count(),
                'news' => News::count(),
                'banners' => Banner::count(),
                'coverage' => Coverage::count(),
            ];
        } catch (\Exception $e) {
            $dbConnected = false;
            $dbMessage = 'Gagal terhubung: ' . $e->getMessage();
        }

        return view('livewire.admin.dashboard.index', [
            'dbConnected' => $dbConnected,
            'dbMessage' => $dbMessage,
            'counts' => $counts,
            'user' => Auth::user(),
        ])->layout('layouts.admin', ['title' => 'Admin Dashboard - WPiTV', 'header' => 'Dashboard Overview']);
    }
}
