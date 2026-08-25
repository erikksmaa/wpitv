<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        Gate::define('access-admin', function (User $user) {
            return $user->status && $user->role !== null;
        });

        Gate::define('manage-production', function (User $user) {
            return $user->status && ($user->isSuperAdmin() || $user->isAdminProduction());
        });

        Gate::define('manage-news', function (User $user) {
            return $user->status && ($user->isSuperAdmin() || $user->isAdminNews());
        });

        Gate::define('manage-users', function (User $user) {
            return $user->status && $user->isSuperAdmin();
        });
    }
}
