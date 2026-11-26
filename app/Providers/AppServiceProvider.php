<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAdminSessionIsValid;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use App\Support\AgendaLiveGuard;
use App\Support\EquipmentPermissions;
use App\Support\TestingDatabaseGuard;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(AgendaLiveGuard::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        TestingDatabaseGuard::assertSafe($this->app);
        foreach (['manage' => 'managers', 'review' => 'reviewers'] as $action => $group) {
            Gate::define('social.'.$action, fn (User $user) => $user->status && $user->canAccessAdmin()
                && in_array((string) $user->id, config('social-media.'.$group), true));
        }
        Gate::define('social.view', fn (User $user) => $user->canAny(['social.manage', 'social.review']));

        foreach (EquipmentPermissions::catalog() as $permission) {
            Gate::define($permission, fn (User $user) => EquipmentPermissions::allows($user, $permission));
        }
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
        Livewire::addPersistentMiddleware([
            EnsureAdminSessionIsValid::class,
            EnsureUserIsActive::class,
            EnsureUserHasRole::class,
        ]);

        Gate::define('access-admin', function (User $user) {
            return $user->canAccessAdmin();
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

        Gate::define('view-visitor-statistics', function (User $user) {
            return $user->status && $user->isSuperAdmin();
        });

        Gate::define('manage-website-settings', function (User $user) {
            return $user->status && $user->isSuperAdmin();
        });
    }
}
