<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminNavigation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\IsolatedSecurityTestCase;

class BannerRetirementTest extends IsolatedSecurityTestCase
{
    public static function roles(): iterable
    {
        foreach (User::ADMIN_ROLES as $role) {
            yield $role => [$role];
        }
    }

    public function test_retired_routes_are_absent_and_return_404_for_guests(): void
    {
        foreach (['index', 'create', 'edit'] as $action) {
            $this->assertFalse(Route::has('admin.banners.'.$action));
        }
        foreach (['/admin/banners', '/admin/banners/create', '/admin/banners/1/edit'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    #[DataProvider('roles')]
    public function test_all_roles_have_no_banner_routes_navigation_actions_or_queries(string $role): void
    {
        $user = $this->roleUser($role);
        $this->actingAs($user);
        foreach (['/admin/banners', '/admin/banners/create', '/admin/banners/1/edit'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->assertStringNotContainsString('banner', strtolower(json_encode(app(AdminNavigation::class)->sections($user))));
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->get(route('admin.dashboard'))->assertOk()
                ->assertDontSee('/admin/banners')
                ->assertDontSee('Tambah Banner')
                ->assertDontSee('Kelola Banner');
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('ms_banner', strtolower($query['query']));
        }
    }
}
