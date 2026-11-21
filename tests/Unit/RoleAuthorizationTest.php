<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureRecentPasswordConfirmation;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\Admin\Videos\Form;
use App\Models;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminNavigation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Pure in-memory actors: these tests never create records or start DB transactions. */
class RoleAuthorizationTest extends TestCase
{
    public static function matrix(): iterable
    {
        $modules = [
            Models\Program::class => ['VCED', 'VCED', '', 'VCED'],
            Models\Video::class => ['VCED', 'VCED', 'VCE', ''],
            Models\News::class => ['VCED', '', 'VCED', ''],
            Models\Coverage::class => ['VCED', 'VCED', '', ''],
            Models\TVClubGeneration::class => ['VCED', 'VCED', '', 'VCED'],
            Models\TVClubMember::class => ['VCED', 'VCED', '', 'VCED'],
            Models\TVClubGroup::class => ['VCED', 'VCED', '', 'VCED'],
            Models\TvClubOrganizationPosition::class => ['VCED', 'VCED', '', 'VCED'],
            Models\WpitvOrganizationPosition::class => ['VCED', 'VCED', '', ''],
            Models\WpitvAgenda::class => ['VCED', 'VCED', '', ''],
            User::class => ['VCED', '', '', ''],
            Role::class => ['VCED', '', '', ''],
        ];
        foreach ($modules as $model => $permissions) {
            foreach (User::ADMIN_ROLES as $index => $role) {
                yield "$role $model active" => [$role, true, $model, $permissions[$index]];
                yield "$role $model inactive" => [$role, false, $model, ''];
            }
            yield "unknown $model" => ['Unrecognized role', true, $model, ''];
        }
    }

    #[DataProvider('matrix')]
    public function test_complete_policy_matrix(string $role, bool $active, string $model, string $permissions): void
    {
        $gate = Gate::forUser($this->actor($role, $active));
        foreach (['viewAny' => 'V', 'view' => 'V', 'create' => 'C', 'update' => 'E', 'delete' => 'D'] as $ability => $letter) {
            $subject = in_array($ability, ['viewAny', 'create'], true) ? $model : new $model;
            $this->assertSame(str_contains($permissions, $letter), $gate->allows($ability, $subject), "$role $model $ability");
        }
        if (in_array($model, [Models\TVClubGroup::class, Models\TvClubOrganizationPosition::class], true)) {
            $this->assertSame(str_contains($permissions, 'E'), $gate->allows('assignMember', $model));
        }
        if ($model === Models\Coverage::class) {
            $this->assertSame(str_contains($permissions, 'E'), $gate->allows('updateStatus', $model));
        }
    }

    public function test_navigation_contains_only_each_roles_operational_modules(): void
    {
        foreach (User::ADMIN_ROLES as $role) {
            $sections = app(AdminNavigation::class)->sections($this->actor($role));
            $links = collect($sections)->flatMap(fn ($section) => $section['links'])->keyBy('route');
            $this->assertSame($role === User::ROLE_SUPER_ADMIN, $links->has('admin.users.index'));
            $this->assertSame($role === User::ROLE_SUPER_ADMIN, $links->has('admin.roles.index'));
            $this->assertSame($role !== User::ROLE_TVCLUB, $links->has('admin.monthly-reports.index'));
            $this->assertSame(in_array($role, [User::ROLE_SUPER_ADMIN, User::ROLE_TVCLUB], true), $links->has('admin.tvclub.social-reports.index'));
            if ($role === User::ROLE_TVCLUB) {
                $this->assertSame('Program Acara', $sections[0]['title']);
                $this->assertSame('Portal TV Club', $sections[1]['parent']);
                $this->assertSame(
                    ['admin.programs.index', 'admin.tvclub.generations.index', 'admin.tvclub.members.index', 'admin.tvclub.groups.index',
                        'admin.tvclub.social-accounts.index', 'admin.tvclub.social-posts.index', 'admin.tvclub.social-reports.index'],
                    $links->keys()->all(),
                );
            }
            if ($role === User::ROLE_NEWS) {
                $this->assertSame('admin.news.index', $sections[0]['links'][0]['route']);
                // Phase 4C gives News a separate entry point for its own monthly reports.
                $this->assertSame(['admin.news.index', 'admin.videos.index', 'admin.monthly-reports.index'], $links->keys()->all());
            }
            if ($role === User::ROLE_PRODUCTION) {
                $this->assertFalse($links->has('admin.news.index'));
            }
            $this->assertFalse($links->contains(fn ($link) => array_key_exists('read_only', $link)));
        }
    }

    public function test_rendered_sidebar_is_responsibility_oriented_and_has_no_read_only_badges(): void
    {
        $cases = [
            User::ROLE_SUPER_ADMIN => [
                ['Program', 'Tayangan', 'Berita', 'Peliputan Acara', 'Agenda WPiTV', 'Portal TV Club', 'Organisasi WPiTV', 'Statistik Pengunjung', 'Pengguna', 'Peran'],
                [],
            ],
            User::ROLE_PRODUCTION => [
                ['Program', 'Tayangan', 'Peliputan Acara', 'Agenda WPiTV', 'Portal TV Club', 'Organisasi WPiTV'],
                ['Berita', 'Statistik Pengunjung', 'Pengguna', 'Peran'],
            ],
            User::ROLE_NEWS => [
                ['Berita', 'Tayangan'],
                ['Program', 'Peliputan Acara', 'Agenda WPiTV', 'Portal TV Club', 'Organisasi WPiTV', 'Statistik Pengunjung', 'Pengguna', 'Peran'],
            ],
            User::ROLE_TVCLUB => [
                ['Program', 'Portal TV Club'],
                ['Tayangan', 'Berita', 'Peliputan Acara', 'Agenda WPiTV', 'Organisasi WPiTV', 'Statistik Pengunjung', 'Pengguna', 'Peran'],
            ],
        ];

        foreach ($cases as $role => [$visible, $hidden]) {
            Auth::setUser($this->actor($role));
            $html = view('components.admin.role-navigation')->render();
            foreach ($visible as $label) {
                $this->assertStringContainsString($label, $html, "$role should see $label");
            }
            foreach ($hidden as $label) {
                $this->assertStringNotContainsString($label, $html, "$role should not see $label");
            }
            $this->assertStringNotContainsString('· Lihat', $html);
            $this->assertStringNotContainsString('Read Only', $html);
        }
    }

    public function test_tvclub_quick_actions_do_not_offer_read_only_mutations(): void
    {
        $user = $this->actor(User::ROLE_TVCLUB);
        Auth::setUser($user);
        $html = view('livewire.admin.dashboard.quick-actions', ['user' => $user, 'dashboard' => []])->render();
        foreach (['Tambah Program', 'Tambah Generasi', 'Tambah Kru', 'Tambah Kelompok Produksi', 'Kelola Struktur Organisasi'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        foreach (['Tambah Tayangan', 'Tulis Berita', 'Tambah Banner', 'Proses Peliputan', 'Kelola Pengguna'] as $label) {
            $this->assertStringNotContainsString($label, $html);
        }
    }

    public function test_quick_actions_for_existing_roles_preserve_their_responsibilities(): void
    {
        $cases = [
            User::ROLE_SUPER_ADMIN => [['Tambah Program', 'Tulis Berita', 'Tambah Tayangan', 'Tambah Agenda', 'Proses Peliputan', 'Tambah Generasi', 'Kelola Pengguna'], []],
            User::ROLE_PRODUCTION => [['Tambah Program', 'Tambah Tayangan', 'Tambah Agenda', 'Proses Peliputan', 'Tambah Generasi'], ['Tambah Banner', 'Tulis Berita', 'Kelola Pengguna']],
            User::ROLE_NEWS => [['Tulis Berita', 'Tambah Tayangan'], ['Tambah Program', 'Tambah Agenda', 'Tambah Banner', 'Proses Peliputan', 'Kelola Pengguna']],
        ];
        foreach ($cases as $role => [$allowed, $denied]) {
            $user = $this->actor($role);
            Auth::setUser($user);
            $html = view('livewire.admin.dashboard.quick-actions', ['user' => $user, 'dashboard' => []])->render();
            foreach ($allowed as $label) {
                $this->assertStringContainsString($label, $html, $role);
            }
            foreach ($denied as $label) {
                $this->assertStringNotContainsString($label, $html, $role);
            }
        }
    }

    public function test_focused_dashboards_render_safe_empty_states_without_database_fixtures(): void
    {
        $dashboard = [
            'kpis' => [
                'programs' => ['active' => 0, 'total' => 0],
                'videos' => ['total' => 0, 'this_month' => 0],
                'news' => ['total' => 0, 'this_month' => 0, 'updated_this_week' => 0],
                'groups' => 0,
            ],
            'agenda' => ['count' => 0, 'latest' => collect()],
            'tv_club' => [
                'active_generations' => 0, 'generation' => null, 'members' => 0,
                'active_members' => 0, 'groups' => 0, 'positions' => 0,
                'recent_members' => collect(), 'top_groups' => collect(),
            ],
        ];
        foreach ([User::ROLE_PRODUCTION, User::ROLE_NEWS, User::ROLE_TVCLUB] as $role) {
            $user = $this->actor($role);
            Auth::setUser($user);
            $data = $dashboard;
            if ($role === User::ROLE_TVCLUB) {
                $data['latest'] = ['videos' => collect()];
                $data['programs'] = ['recent' => collect()];
            } else {
                $data['coverage'] = ['counts' => array_fill_keys(['pending', 'approved', 'in_progress', 'completed'], 0)];
                $data['latest'] = $role === User::ROLE_NEWS ? ['news' => collect()] : ['videos' => collect()];
                if ($role === User::ROLE_PRODUCTION) {
                    $data['coverage']['latest'] = collect();
                    $data['programs'] = ['recent' => collect()];
                }
            }
            $html = view('livewire.admin.dashboard.focused', ['user' => $user, 'dashboard' => $data])->render();
            $this->assertStringContainsString(match ($role) {
                User::ROLE_PRODUCTION => 'Belum ada pengajuan peliputan.',
                User::ROLE_NEWS => 'Belum ada berita.',
                User::ROLE_TVCLUB => 'Belum ada generasi aktif.',
            }, $html);
        }
    }

    public static function deniedForms(): iterable
    {
        foreach (['Videos', 'News', 'Peliputan', 'Agenda', 'Users', 'Roles'] as $module) {
            yield "TVClub $module" => [User::ROLE_TVCLUB, true, "App\\Livewire\\Admin\\$module\\Form"];
        }
        foreach (['Programs', 'TVClub\\Generations', 'TVClub\\Members', 'TVClub\\Groups'] as $module) {
            yield "News $module" => [User::ROLE_NEWS, true, "App\\Livewire\\Admin\\$module\\Form"];
        }
        yield 'unknown video' => ['Unknown', true, Form::class];
        foreach (User::ADMIN_ROLES as $role) {
            yield "inactive $role" => [$role, false, \App\Livewire\Admin\Programs\Form::class];
        }
    }

    #[DataProvider('deniedForms')]
    public function test_denied_save_reauthorizes_before_validation_or_writes(string $role, bool $active, string $component): void
    {
        Auth::setUser($this->actor($role, $active));
        $this->expectException(AuthorizationException::class);
        app()->call([new $component, 'save']);
    }

    #[DataProvider('deniedForms')]
    public function test_denied_render_reauthorizes_before_form_queries(string $role, bool $active, string $component): void
    {
        Auth::setUser($this->actor($role, $active));
        $this->expectException(AuthorizationException::class);
        (new $component)->render();
    }

    public function test_livewire_persists_current_status_and_role_middleware(): void
    {
        $middleware = Livewire::getPersistentMiddleware();
        $this->assertContains(EnsureUserIsActive::class, $middleware);
        $this->assertContains(EnsureUserHasRole::class, $middleware);
        $this->assertNotContains(EnsureRecentPasswordConfirmation::class, $middleware);
        $routes = app('router')->getRoutes();
        $this->assertContains('active', $routes->getByName('password.update')->gatherMiddleware());
        $this->assertContains('active', $routes->getByName('password.confirm')->gatherMiddleware());
        $this->assertNotContains('active', $routes->getByName('logout')->gatherMiddleware());
    }

    #[DataProvider('deniedForms')]
    public function test_denied_edit_save_reauthorizes_before_validation_or_writes(string $role, bool $active, string $component): void
    {
        Auth::setUser($this->actor($role, $active));
        $form = $this->editForm($component);
        $this->expectException(AuthorizationException::class);
        app()->call([$form, 'save']);
    }

    #[DataProvider('deniedForms')]
    public function test_denied_edit_render_reauthorizes_before_queries(string $role, bool $active, string $component): void
    {
        Auth::setUser($this->actor($role, $active));
        $form = $this->editForm($component);
        $this->expectException(AuthorizationException::class);
        $form->render();
    }

    public function test_every_admin_route_keeps_current_status_and_recognized_role_checks(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'admin/')) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware, $route->uri());
            $this->assertContains('active', $middleware, $route->uri());
            $this->assertContains('role:'.implode(',', User::ADMIN_ROLES), $middleware, $route->uri());
        }
    }

    private function editForm(string $component): Component
    {
        $form = new $component;
        // Model-only fixture: mark it persisted without inserting or looking up a row.
        foreach ((new \ReflectionClass($form))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $type = $property->getType();
            if (! $type instanceof \ReflectionNamedType || $type->isBuiltin()
                || ! is_subclass_of($type->getName(), Model::class)) {
                continue;
            }
            $modelClass = $type->getName();
            $model = new $modelClass;
            $model->exists = true;
            $property->setValue($form, $model);

            return $form;
        }
        $this->fail('Expected a typed model property on '.$component);
    }

    public function test_admin_gate_rejects_inactive_unknown_and_missing_roles(): void
    {
        foreach (User::ADMIN_ROLES as $role) {
            $this->assertTrue(Gate::forUser($this->actor($role))->allows('access-admin'));
            $this->assertFalse(Gate::forUser($this->actor($role, false))->allows('access-admin'));
        }
        $this->assertFalse(Gate::forUser($this->actor('Unknown'))->allows('access-admin'));
        $user = (new User(['status' => true]))->setRelation('role', null);
        $this->assertFalse(Gate::forUser($user)->allows('access-admin'));
    }

    private function actor(string $role, bool $active = true): User
    {
        return (new User(['name' => 'Authorization test', 'status' => $active]))
            ->setRelation('role', new Role(['name' => $role]));
    }
}
