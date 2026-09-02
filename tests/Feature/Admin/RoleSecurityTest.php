<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Programs\Form as ProgramForm;
use App\Livewire\Admin\Programs\Index as ProgramIndex;
use App\Livewire\Admin\TVClub\Generations\Form;
use App\Livewire\Admin\TVClub\Organization\Index;
use App\Models\Coverage;
use App\Models\News;
use App\Models\Program;
use App\Models\Role;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubMember;
use App\Models\TvClubOrganizationPosition;
use App\Models\User;
use App\Services\AdminDashboardMetrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\IsolatedSecurityTestCase;

class RoleSecurityTest extends IsolatedSecurityTestCase
{
    public static function routeMatrix(): iterable
    {
        $modules = [
            'programs' => [true, true, false, true],
            'videos' => [true, true, true, false],
            'news' => [true, false, true, false],
            'peliputan' => [true, true, false, false],
            'agenda' => [true, true, false, false],
            'tvclub.generations' => [true, true, false, true],
            'tvclub.members' => [true, true, false, true],
            'tvclub.groups' => [true, true, false, true],
            'users' => [true, false, false, false],
            'roles' => [true, false, false, false],
        ];
        foreach (User::ADMIN_ROLES as $index => $role) {
            foreach ($modules as $module => $access) {
                yield "$role $module index" => [$role, "admin.$module.index", $access[$index]];
                yield "$role $module create" => [$role, "admin.$module.create", $access[$index]];
            }
            yield "$role statistics" => [$role, 'admin.visitor-statistics.index', $role === User::ROLE_SUPER_ADMIN];
            yield "$role organization" => [$role, 'admin.organization.index', in_array($role, [User::ROLE_SUPER_ADMIN, User::ROLE_PRODUCTION], true)];
        }
    }

    #[DataProvider('routeMatrix')]
    public function test_direct_url_access(string $role, string $route, bool $allowed): void
    {
        $response = $this->actingAs($this->roleUser($role))->get(route($route));
        $allowed ? $response->assertOk() : $response->assertForbidden();
    }

    public static function roles(): iterable
    {
        foreach (User::ADMIN_ROLES as $role) {
            yield $role => [$role];
        }
    }

    #[DataProvider('roles')]
    public function test_dashboard_queries_and_actions_follow_role(string $role): void
    {
        $user = $this->roleUser($role);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $data = app(AdminDashboardMetrics::class)->get($user);
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();

        if ($role !== User::ROLE_SUPER_ADMIN) {
            $this->assertArrayNotHasKey('system', $data);
            $this->assertNoQueriesForModels($queries, [User::class, Role::class]);
        }
        if ($role === User::ROLE_TVCLUB) {
            $this->assertArrayNotHasKey('coverage', $data);
            $this->assertArrayNotHasKey('agenda', $data);
            $this->assertArrayNotHasKey('latest', $data);
            $this->assertNoQueriesForModels($queries, [Coverage::class, News::class]);
            $this->assertStringNotContainsString('student_number', $queries);
        }
        if ($role === User::ROLE_NEWS) {
            $this->assertArrayNotHasKey('coverage', $data);
            $this->assertArrayNotHasKey('agenda', $data);
            $this->assertArrayNotHasKey('tv_club', $data);
            $this->assertNoQueriesForModels($queries, [Coverage::class, TVClubGeneration::class, TVClubMember::class, TVClubGroup::class]);
        }
        $response = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
        if ($role === User::ROLE_NEWS) {
            $response->assertSee('Meja editorial')->assertSee('Tulis Berita')->assertDontSee('Tambah Program');
        } elseif ($role === User::ROLE_TVCLUB) {
            $response->assertSee('Organisasi TV Club')->assertSee('Tambah Kru')->assertDontSee('Tambah Tayangan');
        } elseif ($role === User::ROLE_PRODUCTION) {
            $response->assertSee('Antrean operasional')->assertSee('Proses Peliputan')->assertDontSee('Tambah Banner')->assertDontSee('Tulis Berita');
        } else {
            $response->assertSee('Kelola Pengguna')->assertSee('Traffic 7 Hari Terakhir');
        }
    }

    public function test_tvclub_can_create_edit_and_delete_any_program(): void
    {
        $user = $this->roleUser(User::ROLE_TVCLUB);
        $slug = 'rbac-'.str()->uuid();
        Livewire::actingAs($user)->test(ProgramForm::class)
            ->set('name', 'RBAC Program')->set('slug', $slug)->call('save')->assertHasNoErrors();
        $program = Program::where('slug', $slug)->firstOrFail();
        Livewire::actingAs($user)->test(ProgramForm::class, ['program' => $program])
            ->set('name', 'RBAC Updated')->call('save')->assertHasNoErrors();
        $this->assertSame('RBAC Updated', $program->refresh()->name);
        Livewire::actingAs($user)->test(ProgramIndex::class)->call('delete', $program->id)->assertHasNoErrors();
        $this->assertDatabaseMissing((new Program)->getTable(), ['id' => $program->id]);
    }

    public function test_stale_video_save_is_denied_after_production_becomes_tvclub_over_http(): void
    {
        $user = $this->roleUser(User::ROLE_PRODUCTION);
        $snapshot = $this->snapshot($this->actingAs($user)->get(route('admin.videos.create')));
        User::whereKey($user->id)->update(['role_id' => Role::where('name', User::ROLE_TVCLUB)->value('id')]);
        $this->updateSnapshot($snapshot, 'save')->assertForbidden();
        $this->get(route('admin.videos.create'))->assertForbidden();
    }

    public function test_tvclub_can_manage_generations_members_groups_and_organization_assignments(): void
    {
        $user = $this->roleUser(User::ROLE_TVCLUB);
        $year = collect(range(2000, 2100))->diff(TVClubGeneration::pluck('year'))->first();
        $this->assertNotNull($year, 'A free generation year is required in the isolated fixture database.');
        Livewire::actingAs($user)->test(Form::class)
            ->set('name', 'RBAC Generation')->set('year', $year)->call('save')->assertHasNoErrors();
        $generation = TVClubGeneration::where('year', $year)->firstOrFail();
        Livewire::actingAs($user)->test(Form::class, ['generation' => $generation])
            ->set('name', 'RBAC Generation Updated')->call('save')->assertHasNoErrors();
        $this->assertSame('RBAC Generation Updated', $generation->refresh()->name);

        $number = 'RBAC-'.str()->random(15);
        Livewire::actingAs($user)->test(\App\Livewire\Admin\TVClub\Members\Form::class)
            ->set('generation_id', $generation->id)->set('student_number', $number)
            ->set('name', 'RBAC Member')->call('save')->assertHasNoErrors();
        $member = TVClubMember::where('student_number', $number)->firstOrFail();
        Livewire::actingAs($user)->test(\App\Livewire\Admin\TVClub\Members\Form::class, ['member' => $member])
            ->set('name', 'RBAC Member Updated')->call('save')->assertHasNoErrors();
        $this->assertSame('RBAC Member Updated', $member->refresh()->name);

        $program = Program::create(['name' => 'Other operator program', 'slug' => 'rbac-'.str()->uuid(), 'status' => true]);
        Livewire::actingAs($user)->test(\App\Livewire\Admin\TVClub\Groups\Form::class)
            ->set('generation_id', $generation->id)->set('program_id', $program->id)
            ->set('name', 'RBAC Group')->set('selectedMemberId', $member->id)
            ->call('addMember')->call('save')->assertHasNoErrors();
        $group = TVClubGroup::where('generation_id', $generation->id)->where('name', 'RBAC Group')->firstOrFail();
        $this->assertTrue($group->members()->whereKey($member->id)->exists());
        Livewire::actingAs($user)->test(\App\Livewire\Admin\TVClub\Groups\Form::class, ['group' => $group])
            ->call('updateMemberRole', $member->id, 'Sutradara')->call('save')->assertHasNoErrors();
        $this->assertSame('Sutradara', $group->members()->firstOrFail()->pivot->role);

        $organization = Livewire::actingAs($user)->test(Index::class, ['generation' => $generation]);
        $organization->set('name', 'RBAC Position')->call('savePosition')->assertHasNoErrors();
        $position = TvClubOrganizationPosition::where('generation_id', $generation->id)->firstOrFail();
        $organization->call('editPosition', $position->id)->set('name', 'RBAC Position Updated')->call('savePosition')->assertHasNoErrors();
        $this->assertSame('RBAC Position Updated', $position->refresh()->name);
        $organization->call('openMemberForm', $position->id)->set('member_id', $member->id)->call('addMember')->assertHasNoErrors();
        $assignment = $position->assignments()->firstOrFail();
        $this->assertSame($member->id, $assignment->member_id);
        $organization->call('removeMember', $position->id, $assignment->id)->call('deletePosition', $position->id)->assertHasNoErrors();
        $this->assertDatabaseMissing('tvclub_organization_positions', ['id' => $position->id]);

        Livewire::actingAs($user)->test(\App\Livewire\Admin\TVClub\Groups\Form::class, ['group' => $group])
            ->call('removeMember', $member->id)->call('save')->assertHasNoErrors();
        $this->assertSame(0, $group->members()->count());
        Livewire::actingAs($user)->test(\App\Livewire\Admin\TVClub\Groups\Index::class)->call('delete', $group->id)->assertHasNoErrors();
        Livewire::actingAs($user)->test(\App\Livewire\Admin\TVClub\Members\Index::class)->call('delete', $member->id)->assertHasNoErrors();
        Livewire::actingAs($user)->test(\App\Livewire\Admin\TVClub\Generations\Index::class)->call('delete', $generation->id)->assertHasNoErrors();
        $this->assertDatabaseMissing('tvclub_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('tvclub_members', ['id' => $member->id]);
        $this->assertDatabaseMissing('tvclub_generations', ['id' => $generation->id]);
    }

    public function test_stale_program_save_is_denied_after_production_becomes_news_over_http(): void
    {
        $user = $this->roleUser(User::ROLE_PRODUCTION);
        $snapshot = $this->snapshot($this->actingAs($user)->get(route('admin.programs.create')));
        User::whereKey($user->id)->update(['role_id' => Role::where('name', User::ROLE_NEWS)->value('id')]);
        $this->updateSnapshot($snapshot, 'save')->assertForbidden();
    }

    public function test_stale_system_component_is_denied_after_super_admin_revocation_over_http(): void
    {
        $user = $this->roleUser(User::ROLE_SUPER_ADMIN);
        $snapshot = $this->snapshot($this->actingAs($user)->get(route('admin.users.index')));
        User::whereKey($user->id)->update(['role_id' => Role::where('name', User::ROLE_TVCLUB)->value('id')]);
        $this->updateSnapshot($snapshot)->assertForbidden();
    }

    #[DataProvider('roles')]
    public function test_inactive_role_cannot_replay_dashboard_snapshot_over_http(string $role): void
    {
        $user = $this->roleUser($role);
        $snapshot = $this->snapshot($this->actingAs($user)->get(route('admin.dashboard')));
        User::whereKey($user->id)->update(['status' => false]);
        $this->updateSnapshot($snapshot)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    #[DataProvider('roles')]
    public function test_inactive_role_is_denied_get_and_password_update_but_can_logout(string $role): void
    {
        $user = $this->roleUser($role, false);
        $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->actingAs($user)->get(route('password.confirm'))->assertRedirect(route('login'));
        $this->actingAs($user)->post(route('password.confirm'), [])->assertRedirect(route('login'));
        $this->actingAs($user)->put(route('password.update'), [])->assertRedirect(route('login'));
        $this->actingAs($user)->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    #[DataProvider('roles')]
    public function test_inactive_role_cannot_save_an_open_form_over_http(string $role): void
    {
        $user = $this->roleUser($role);
        $route = $role === User::ROLE_NEWS ? 'admin.news.create' : 'admin.programs.create';
        $snapshot = $this->snapshot($this->actingAs($user)->get(route($route)));
        User::whereKey($user->id)->update(['status' => false]);
        $this->updateSnapshot($snapshot, 'save')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_unknown_role_cannot_enter_admin_or_replay_livewire(): void
    {
        $user = $this->roleUser(User::ROLE_PRODUCTION);
        $snapshot = $this->snapshot($this->actingAs($user)->get(route('admin.dashboard')));
        $unknown = Role::create(['name' => 'Unrecognized role', 'description' => 'Test']);
        User::whereKey($user->id)->update(['role_id' => $unknown->id]);
        $this->updateSnapshot($snapshot)->assertForbidden();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.videos.create'))->assertForbidden();
    }

    private function assertNoQueriesForModels(string $queries, array $models): void
    {
        foreach ($models as $model) {
            $table = preg_quote((new $model)->getTable(), '/');
            $this->assertDoesNotMatchRegularExpression('/\b(?:from|join)\s+[`"]?'.$table.'[`"]?\b/i', $queries);
        }
    }

    private function snapshot(TestResponse $response): string
    {
        $response->assertOk();
        preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'Expected a signed Livewire snapshot from the real GET route.');

        return htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE);
    }

    private function updateSnapshot(string $snapshot, string $method = '$refresh'): TestResponse
    {
        return $this->postJson(route('default-livewire.update'), [
            'components' => [[
                'snapshot' => $snapshot, 'updates' => (object) [],
                'calls' => [['path' => '', 'method' => $method, 'params' => []]],
            ]],
        ], ['X-Livewire' => 'true']);
    }
}
