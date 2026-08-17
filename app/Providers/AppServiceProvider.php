<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::define('access-admin', function (\App\Models\User $user) {
            return $user->status && $user->role !== null;
        });

        \Illuminate\Support\Facades\Gate::define('manage-production', function (\App\Models\User $user) {
            return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
        });

        \Illuminate\Support\Facades\Gate::define('manage-news', function (\App\Models\User $user) {
            return $user->status && ($user->isSuperAdmin() || $user->isAdminNews());
        });

        \Illuminate\Support\Facades\Gate::define('manage-users', function (\App\Models\User $user) {
            return $user->status && $user->isSuperAdmin();
        });
    }
}
