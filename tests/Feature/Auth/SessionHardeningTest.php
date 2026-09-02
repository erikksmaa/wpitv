<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\EnsureAdminSessionIsValid;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SessionHardeningTest extends TestCase
{
    use DatabaseTransactions;

    public static function adminRoles(): iterable
    {
        foreach (User::ADMIN_ROLES as $role) {
            yield $role => [$role];
        }
    }

    public function test_login_initializes_lifecycle_and_native_revocation_metadata_and_regenerates_session(): void
    {
        $user = $this->makeUser(User::ROLE_SUPER_ADMIN);
        $this->get('/login');
        $oldId = session()->getId();

        $response = $this->post('/login', ['username' => $user->username, 'password' => 'password']);

        $response->assertRedirect(route('admin.dashboard', absolute: false));
        $this->assertNotSame($oldId, session()->getId());
        $response->assertSessionHas(EnsureAdminSessionIsValid::STARTED_AT);
        $response->assertSessionHas(EnsureAdminSessionIsValid::LAST_ACTIVITY_AT);
        $response->assertSessionHas('password_hash_web');
        $this->assertIsInt(session(EnsureAdminSessionIsValid::STARTED_AT));
        $this->assertSame(session(EnsureAdminSessionIsValid::STARTED_AT), session(EnsureAdminSessionIsValid::LAST_ACTIVITY_AT));
    }

    public function test_login_is_generic_throttled_and_never_creates_remember_cookie(): void
    {
        $user = $this->makeUser(User::ROLE_SUPER_ADMIN);
        $response = $this->post('/login', [
            'username' => $user->username, 'password' => 'password', 'remember' => '1',
        ]);
        $response->assertRedirect();
        $this->assertFalse(Auth::guard('web')->viaRemember());
        $recaller = Auth::guard('web')->getRecallerName();
        $this->assertNull(collect($response->headers->getCookies())->firstWhere(fn ($cookie) => $cookie->getName() === $recaller));
        $this->get('/login')->assertDontSee('Remember me')->assertDontSee('name="remember"', false);

        $this->clearBrowser(newSession: true);
        $unknown = 'missing_'.str()->random(12);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $failure = $this->post('/login', ['username' => $unknown, 'password' => 'wrong']);
            $failure->assertSessionHasErrors('username');
            $this->assertSame(trans('auth.failed'), session('errors')->first('username'));
        }
        $this->post('/login', ['username' => $unknown, 'password' => 'wrong'])
            ->assertSessionHasErrors('username');
        $this->assertNotSame(trans('auth.failed'), session('errors')->first('username'));
    }

    #[DataProvider('adminRoles')]
    public function test_idle_timeout_applies_equally_to_every_role(string $role): void
    {
        $this->actingAs($this->makeUser($role));
        $this->travel(30)->minutes();
        $this->get(route('admin.dashboard'))->assertOk();
        $this->travel(30)->minutes();
        $this->travel(1)->seconds();

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->assertStringContainsString('tidak ada aktivitas selama 30 menit', session('errors')->first('username'));
    }

    public function test_activity_refreshes_idle_window_without_refreshing_absolute_start(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_PRODUCTION));
        $startedAt = session(EnsureAdminSessionIsValid::STARTED_AT);
        $this->travel(20)->minutes();
        $this->get(route('password.confirm'))->assertOk();
        $this->assertSame($startedAt, session(EnsureAdminSessionIsValid::STARTED_AT));
        $this->assertSame(now()->timestamp, session(EnsureAdminSessionIsValid::LAST_ACTIVITY_AT));
        $this->travel(20)->minutes();
        $this->get(route('password.confirm'))->assertOk();
        $this->assertAuthenticated();
    }

    public function test_absolute_lifetime_expires_even_after_recent_activity(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_NEWS));
        for ($request = 0; $request < 16; $request++) {
            $this->travel(29)->minutes();
            $this->get(route('password.confirm'))->assertOk();
        }
        $this->travel(16)->minutes();

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_missing_lifecycle_metadata_fails_closed(): void
    {
        $user = $this->makeUser(User::ROLE_TVCLUB);
        $this->actingAs($user);
        session()->forget([EnsureAdminSessionIsValid::STARTED_AT, EnsureAdminSessionIsValid::LAST_ACTIVITY_AT]);

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_idle_livewire_snapshot_cannot_mutate_program(): void
    {
        $user = $this->makeUser(User::ROLE_TVCLUB);
        $program = Program::create(['name' => 'Session Original', 'slug' => 'session-original-'.str()->uuid(), 'status' => true]);
        $snapshot = $this->snapshot($this->actingAs($user)->get(route('admin.programs.edit', $program)));
        $this->travel(31)->minutes();

        $this->updateSnapshot($snapshot, 'save', ['name' => 'Session Mutated'])
            ->assertRedirect(route('login'));
        $this->assertSame('Session Original', $program->refresh()->name);
        $this->assertGuest();
    }

    public function test_self_password_change_keeps_current_http_session_and_revokes_other_session(): void
    {
        $user = $this->makeUser(User::ROLE_SUPER_ADMIN);
        $sessionA = $this->loginBrowser($user);
        $sessionB = $this->loginBrowser($user);

        $this->useBrowser($sessionA)->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors();
        $this->useBrowser($sessionA)->get(route('admin.dashboard'))->assertOk();
        $this->useBrowser($sessionB)->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
        $this->clearBrowser(newSession: true);
        $this->post('/login', ['username' => $user->username, 'password' => 'password'])->assertSessionHasErrors('username');
        $this->clearBrowser(newSession: true);
        $this->post('/login', ['username' => $user->username, 'password' => 'new-password'])->assertRedirect();
    }

    public function test_normal_logout_only_ends_current_browser_session(): void
    {
        $user = $this->makeUser(User::ROLE_PRODUCTION);
        $sessionA = $this->loginBrowser($user);
        $sessionB = $this->loginBrowser($user);

        $this->useBrowser($sessionA)->post(route('logout'))->assertRedirect('/');
        $this->useBrowser($sessionA)->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->useBrowser($sessionB)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_super_admin_password_reset_revokes_target_but_not_super_admin_session(): void
    {
        $super = $this->makeUser(User::ROLE_SUPER_ADMIN);
        $target = $this->makeUser(User::ROLE_TVCLUB);
        $superSession = $this->loginBrowser($super);
        $targetSession = $this->loginBrowser($target);

        $snapshot = $this->snapshot($this->useBrowser($superSession)->get(route('admin.users.edit', $target)));
        $this->updateSnapshot($snapshot, 'save', [
            'password' => 'admin-reset-password',
            'password_confirmation' => 'admin-reset-password',
        ], $superSession)->assertOk();

        $this->useBrowser($targetSession)->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->useBrowser($superSession)->get(route('admin.dashboard'))->assertOk();
        $this->assertTrue(Hash::check('admin-reset-password', $target->refresh()->password));
    }

    public function test_confirm_password_is_throttled_and_success_clears_failures(): void
    {
        $user = $this->makeUser(User::ROLE_SUPER_ADMIN);
        $this->actingAs($user);
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post('/confirm-password', ['password' => 'wrong'])->assertSessionHasErrors('password');
        }
        $this->post('/confirm-password', ['password' => 'password'])->assertSessionHasNoErrors();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/confirm-password', ['password' => 'wrong'])->assertSessionHasErrors('password');
        }
        $this->post('/confirm-password', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertNotSame(trans('auth.password'), session('errors')->first('password'));
    }

    public function test_identity_and_access_routes_do_not_require_recent_password_confirmation(): void
    {
        $user = $this->makeUser(User::ROLE_SUPER_ADMIN);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => null]);
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.roles.index'))->assertOk();
        $this->assertNotContains('password.recent', app('router')->getRoutes()->getByName('admin.users.index')->gatherMiddleware());
        $this->assertNotContains('password.recent', app('router')->getRoutes()->getByName('admin.roles.index')->gatherMiddleware());
    }

    public function test_stale_password_confirmation_does_not_block_super_admin_livewire_user_save(): void
    {
        $super = $this->makeUser(User::ROLE_SUPER_ADMIN);
        $target = $this->makeUser(User::ROLE_PRODUCTION);
        $newsRole = Role::where('name', User::ROLE_NEWS)->firstOrFail();
        $superSession = $this->loginBrowser($super);
        $snapshot = $this->snapshot($this->useBrowser($superSession)->get(route('admin.users.edit', $target)));

        session()->put('auth.password_confirmed_at', time() - config('auth.password_timeout') - 1);
        session()->save();

        $this->updateSnapshot($snapshot, 'save', [
            'role_id' => $newsRole->id,
            'status' => false,
            'password' => 'should-not-be-saved',
            'password_confirmation' => 'should-not-be-saved',
        ], $superSession)->assertOk();
        $target->refresh();
        $this->assertSame($newsRole->id, $target->role_id);
        $this->assertFalse($target->status);
        $this->assertTrue(Hash::check('should-not-be-saved', $target->password));
    }

    public function test_stale_password_confirmation_does_not_block_super_admin_livewire_role_create(): void
    {
        $super = $this->makeUser(User::ROLE_SUPER_ADMIN);
        $superSession = $this->loginBrowser($super);
        $snapshot = $this->snapshot($this->useBrowser($superSession)->get(route('admin.roles.create')));
        session()->put('auth.password_confirmed_at', null);
        session()->save();
        $name = 'Role no confirm '.Str::random(8);

        $this->updateSnapshot($snapshot, 'save', [
            'name' => $name,
            'description' => 'Created without recent password confirmation.',
        ], $superSession)->assertOk();

        $this->assertDatabaseHas('roles', ['name' => $name]);
    }

    public function test_password_update_wrong_current_password_is_throttled(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_TVCLUB));
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->put('/password', [
                'current_password' => 'wrong', 'password' => 'new-password', 'password_confirmation' => 'new-password',
            ])->assertSessionHasErrorsIn('updatePassword', 'current_password');
        }
        $this->put('/password', [
            'current_password' => 'wrong', 'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertSessionHasErrorsIn('updatePassword', 'current_password');
        $this->assertNotEmpty(session('errors')->getBag('updatePassword')->first('current_password'));
    }

    public function test_logout_removes_lifecycle_metadata_and_invalidates_session(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_NEWS));
        $oldId = session()->getId();
        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNull(session(EnsureAdminSessionIsValid::STARTED_AT));
        $this->assertNull(session(EnsureAdminSessionIsValid::LAST_ACTIVITY_AT));
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role)->firstOrFail()->id,
            'status' => true,
            'username' => str()->lower(str_replace(' ', '_', $role)).'_'.str()->random(10),
            'password' => Hash::make('password'),
        ]);
    }

    private function loginBrowser(User $user): string
    {
        $this->clearBrowser(newSession: true);
        $response = $this->post('/login', ['username' => $user->username, 'password' => 'password']);
        $response->assertRedirect(route('admin.dashboard', absolute: false));
        $this->assertNotEmpty(session()->getId());

        return session()->getId();
    }

    private function useBrowser(string $encryptedSessionCookie): static
    {
        $this->clearBrowser();

        return $this->withCookie(config('session.cookie'), $encryptedSessionCookie);
    }

    private function clearBrowser(bool $newSession = false): void
    {
        Auth::forgetGuards();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        if ($newSession) {
            session()->save();
            session()->setId(Str::random(40));
            session()->start();
            session()->flush();
        }
    }

    private function snapshot(TestResponse $response): string
    {
        $response->assertOk();
        preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        return htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE);
    }

    private function updateSnapshot(string $snapshot, string $method, array $updates = [], ?string $cookie = null): TestResponse
    {
        if ($cookie !== null) {
            $this->useBrowser($cookie);
        }

        return $this->withCredentials()->postJson(route('default-livewire.update'), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => (object) $updates,
                'calls' => [['path' => '', 'method' => $method, 'params' => []]],
            ]],
        ], ['X-Livewire' => 'true']);
    }
}
