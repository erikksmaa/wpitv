<?php

use App\Livewire\Public\Home as PublicHome;
use App\Livewire\Admin\Dashboard\Index as AdminDashboard;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/', PublicHome::class)->name('public.home');

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth', 'active', 'role:Super Admin,Admin Production,Admin News'])->group(function () {
    Route::get('/', AdminDashboard::class)->name('admin.index');
    Route::get('/dashboard', AdminDashboard::class)->name('admin.dashboard');
});

require __DIR__.'/auth.php';
