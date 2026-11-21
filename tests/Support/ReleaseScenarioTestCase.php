<?php

namespace Tests\Support;

use App\Http\Middleware\EnsureAdminSessionIsValid;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\ReleaseConcurrencyTestCase;

abstract class ReleaseScenarioTestCase extends ReleaseConcurrencyTestCase
{
    protected string $prefix;

    protected array $actors = [];

    protected array $owned = ['roles' => [], 'users' => [], 'tokens' => []];

    protected array $processes = [];

    private ?string $journal = null;

    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        return $this->withSession([EnsureAdminSessionIsValid::STARTED_AT => time(), EnsureAdminSessionIsValid::LAST_ACTIVITY_AT => time(), 'auth.password_confirmed_at' => time(), 'password_hash_'.($guard ?? 'web') => $user->getAuthPassword()]);
    }

    protected function setUpReleaseFixtures(): void
    {
        $this->prefix = 'RLCT_'.strtoupper(bin2hex(random_bytes(6)));
        $dir = base_path('storage/app/testing/release-runs');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $this->journal = $dir.'/'.$this->prefix.'.json';
        $this->persistOwnership();
        foreach (User::ADMIN_ROLES as $i => $name) {
            $role = Role::where('name', $name)->first();
            if ($role === null) {
                // The isolated baseline may contain schema only. These are the
                // four existing application role names, never new role types.
                $role = new Role;
                $role->forceFill(['name' => $name, 'description' => 'Synthetic canonical role fixture'])->save();
                $this->own('roles', $role->id);
            }
            $actor = new User;
            $actor->forceFill(['role_id' => $role->id, 'username' => $this->prefix.'_'.$i, 'name' => 'Synthetic '.$this->prefix.' '.$i, 'password' => 'Synthetic!9'.bin2hex(random_bytes(16)), 'status' => true]);
            $actor->save();
            $this->actors[$name] = $actor->load('role');
            $this->owned['users'][] = $actor->id;
            $this->persistOwnership();
        }
    }

    protected function actor(string $role = User::ROLE_PRODUCTION): User
    {
        return $this->actors[$role];
    }

    protected function own(string $kind, int $id): void
    {
        if (! in_array($id, $this->owned[$kind], true)) {
            $this->owned[$kind][] = $id;
            $this->persistOwnership();
        }
    }

    protected function token(): string
    {
        $token = (string) Str::uuid();
        $this->rememberToken($token);

        return $token;
    }

    protected function rememberToken(string $token): void
    {
        $this->owned['tokens'][] = $token;
        $this->persistOwnership();
    }

    private function persistOwnership(): void
    {
        if ($this->journal === null) {
            return;
        }
        $temporary = $this->journal.'.tmp';
        file_put_contents($temporary, json_encode(['prefix' => $this->prefix, 'owned' => $this->owned], JSON_THROW_ON_ERROR), LOCK_EX);
        for ($attempt = 0; $attempt < 10; $attempt++) {
            if (@rename($temporary, $this->journal)) {
                return;
            }
            usleep(20000);
        }
        throw new RuntimeException('RLCT_JOURNAL_WRITE');
    }

    protected function cleanUpReleaseFixtures(): void
    {
        $users = $this->owned['users'];
        $roles = $this->owned['roles'];
        foreach (User::whereIn('id', $users)->get() as $row) {
            if (! str_starts_with($row->username, $this->prefix.'_')) {
                throw new RuntimeException('RLCT_USER_SCOPE');
            }
        }
        foreach (Role::whereIn('id', $roles)->get() as $row) {
            if (! in_array($row->name, User::ADMIN_ROLES, true) || $row->description !== 'Synthetic canonical role fixture') {
                throw new RuntimeException('RLCT_ROLE_SCOPE');
            }
        }
        $this->primary->transaction(function () use ($users, $roles) {
            User::whereIn('id', $users)->delete();
            if (User::whereIn('role_id', $roles)->exists()) {
                throw new RuntimeException('RLCT_ROLE_REFERENCES');
            }
            Role::whereIn('id', $roles)->delete();
        });
        if (User::whereIn('id', $users)->exists()) {
            throw new RuntimeException('RLCT_RESIDUE');
        }
        if ($this->journal !== null) {
            unlink($this->journal);
            $this->journal = null;
        }
    }

    protected function stopReleaseWorkers(): void
    {
        foreach ($this->processes as $process) {
            if (is_resource($process)) {
                if (proc_get_status($process)['running']) {
                    proc_terminate($process);
                } proc_close($process);
            }
        }
        $this->processes = [];
    }
}
