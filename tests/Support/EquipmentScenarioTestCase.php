<?php

namespace Tests\Support;

use App\Http\Middleware\EnsureAdminSessionIsValid;
use App\Models\EquipmentAuditEvent;
use App\Models\EquipmentLoan;
use App\Models\EquipmentLoanItem;
use App\Models\InventoryCategory;
use App\Models\InventoryUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\EquipmentLoanCheckout;
use App\Services\EquipmentLoanReturn;
use App\Services\EquipmentPhotos;
use App\Services\EquipmentStructure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\EquipmentConcurrencyTestCase;

abstract class EquipmentScenarioTestCase extends EquipmentConcurrencyTestCase
{
    protected string $prefix;

    protected array $actors = [];

    protected array $units = [];

    protected InventoryCategory $category;

    protected array $owned = ['roles' => [], 'users' => [], 'categories' => [], 'units' => [], 'tokens' => []];

    protected array $processes = [];

    private ?string $journal = null;

    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        return $this->withSession([EnsureAdminSessionIsValid::STARTED_AT => time(), EnsureAdminSessionIsValid::LAST_ACTIVITY_AT => time(), 'auth.password_confirmed_at' => time(), 'password_hash_'.($guard ?? 'web') => $user->getAuthPassword()]);
    }

    protected function setUpEquipmentFixtures(): void
    {
        $this->prefix = 'EQCT_'.strtoupper(bin2hex(random_bytes(6)));
        $dir = base_path('storage/app/testing/equipment-runs');
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
        $this->category = $this->categoryFixture(' category');
        for ($i = 0; $i < 3; $i++) {
            $this->units[] = $this->unitFixture();
        }
    }

    protected function actor(string $role = User::ROLE_PRODUCTION): User
    {
        return $this->actors[$role];
    }

    protected function categoryFixture(string $suffix): InventoryCategory
    {
        $category = new InventoryCategory;
        $category->forceFill(['name' => $this->prefix.$suffix, 'description' => 'Synthetic category', 'is_active' => true])->save();
        $this->own('categories', $category->id);

        return $category;
    }

    protected function unitFixture(): InventoryUnit
    {
        $unit = new InventoryUnit;
        $unit->forceFill(['category_id' => $this->category->id, 'inventory_code' => $this->prefix.'-'.strtoupper(bin2hex(random_bytes(3))), 'name' => 'Synthetic camera', 'storage_location' => 'Synthetic shelf', 'condition' => 'good', 'is_active' => true, 'version' => 1])->save();
        $this->own('units', $unit->id);

        return $unit;
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

    protected function input(array $units, bool $late = false): array
    {
        return ['borrower_name' => 'Synthetic borrower '.$this->prefix, 'student_number' => 'SYNSTUDENT-'.substr($this->prefix, 5), 'study_program' => 'Synthetic study', 'whatsapp_number' => '620000000001', 'purpose' => 'Synthetic production', 'notes' => 'SYNTHETIC_PRIVATE_'.$this->prefix,
            'borrowed_at' => now('Asia/Jakarta')->subDays($late ? 2 : 0)->subHour()->format('Y-m-d\TH:i'), 'due_at' => now('Asia/Jakarta')->addDays($late ? -1 : 1)->format('Y-m-d\TH:i'),
            'items' => array_map(fn ($unit) => ['unit_id' => $unit->id, 'version' => $unit->fresh()->version, 'checkout_notes' => null], $units)];
    }

    protected function checkout(array $units, bool $late = false): EquipmentLoan
    {
        return app(EquipmentLoanCheckout::class)->checkout($this->actor(), $this->token(), $this->input($units, $late));
    }

    protected function recordReturn($actor, string $token, array $input)
    {
        return app(EquipmentLoanReturn::class)->record($actor, $token, $input, EquipmentPhotoFixtures::evidence($input));
    }

    protected function returnInput(EquipmentLoan $loan, array $items, string $condition = 'good'): array
    {
        return ['loan_id' => $loan->id, 'expected_version' => $loan->fresh()->version, 'items' => array_map(fn ($item) => ['item_id' => $item->id, 'returned_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i:s'), 'condition_in' => $condition, 'return_notes' => 'Synthetic return / recovery note'], $items)];
    }

    protected function lossInput(EquipmentLoan $loan, array $items): array
    {
        return ['loan_id' => $loan->id, 'expected_version' => $loan->fresh()->version, 'items' => array_map(fn ($item) => ['item_id' => $item->id, 'lost_at' => now('Asia/Jakarta')->subMinute()->format('Y-m-d\TH:i:s'), 'loss_notes' => 'Synthetic loss reason'], $items)];
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
        throw new RuntimeException('EQCT_JOURNAL_WRITE');
    }

    protected function cleanUpEquipmentFixtures(): void
    {
        $users = $this->owned['users'];
        $units = $this->owned['units'];
        $categories = $this->owned['categories'];
        $tokens = $this->owned['tokens'];
        $roles = $this->owned['roles'];
        $loans = EquipmentLoan::whereIn('submission_token', $tokens)->pluck('id')->all();
        $related = EquipmentLoanItem::whereIn('inventory_unit_id', $units)->pluck('loan_id')->unique()->all();
        if (array_diff($related, $loans) !== [] || EquipmentLoanItem::whereIn('loan_id', $loans)->whereNotIn('inventory_unit_id', $units)->exists()) {
            throw new RuntimeException('EQCT_SCOPE');
        }
        foreach (User::whereIn('id', $users)->get() as $row) {
            if (! str_starts_with($row->username, $this->prefix.'_')) {
                throw new RuntimeException('EQCT_USER_SCOPE');
            }
        }
        foreach (InventoryCategory::whereIn('id', $categories)->get() as $row) {
            if (! str_starts_with($row->name, $this->prefix)) {
                throw new RuntimeException('EQCT_CATEGORY_SCOPE');
            }
        }
        foreach (InventoryUnit::whereIn('id', $units)->get() as $row) {
            if (! str_starts_with($row->inventory_code, $this->prefix) && ! (str_starts_with($row->name, $this->prefix.'-') && in_array($row->category_id, $categories, true) && $row->auditEvents()->where('event_type', 'created')->whereIn('actor_id', $users)->exists())) {
                throw new RuntimeException('EQCT_UNIT_SCOPE');
            }
        }
        $items = EquipmentLoanItem::whereIn('loan_id', $loans)->pluck('id')->all();
        $audit = fn () => EquipmentAuditEvent::where(fn ($q) => $q->whereIn('actor_id', $users)->orWhereIn('category_id', $categories)->orWhereIn('unit_id', $units)->orWhereIn('loan_id', $loans)->orWhereIn('loan_item_id', $items));
        foreach ($audit()->get() as $event) {
            foreach (['category_id' => $categories, 'unit_id' => $units, 'loan_id' => $loans, 'loan_item_id' => $items, 'actor_id' => $users] as $field => $ids) {
                if ($event->{$field} !== null && ! in_array($event->{$field}, $ids)) {
                    throw new RuntimeException('EQCT_AUDIT_SCOPE');
                }
            }
        }
        foreach (Role::whereIn('id', $roles)->get() as $role) {
            if (! in_array($role->name, User::ADMIN_ROLES, true) || $role->description !== 'Synthetic canonical role fixture') {
                throw new RuntimeException('EQCT_ROLE_SCOPE');
            }
        }
        $this->primary->transaction(function () use ($audit, $loans, $units, $categories, $users, $roles): void {
            if (EquipmentPhotos::enabled()) {
                $photos = $this->primary->table('equipment_return_photos')->whereIn('receipt_id', $audit()->pluck('id'))->pluck('id');
                $this->primary->table('equipment_return_photo_items')->whereIn('photo_id', $photos)->delete();
                $this->primary->table('equipment_return_photos')->whereIn('id', $photos)->delete();
                $this->primary->table('inventory_unit_photos')->whereIn('unit_id', $units)->delete();
            }
            $members = $this->primary->table('inventory_unit_members')->whereIn('parent_id', $units);
            if ((clone $members)->whereNotIn('unit_id', $units)->exists()) {
                throw new RuntimeException('EQCT_GROUP_SCOPE');
            }
            $members->delete();
            $expanded = EquipmentStructure::installed();
            if ($expanded) {
                $receipts = $this->primary->table('equipment_return_receipts')->whereIn('audit_event_id', $audit()->pluck('id'));
                if ((clone $receipts)->whereNotIn('loan_id', $loans)->exists()) {
                    throw new RuntimeException('EQCT_RECEIPT_SCOPE');
                }
                $details = $this->primary->table('equipment_return_items')->whereIn('receipt_id', (clone $receipts)->pluck('id'));
                if ((clone $details)->whereNotIn('loan_item_id', EquipmentLoanItem::whereIn('loan_id', $loans)->pluck('id'))->exists()) {
                    throw new RuntimeException('EQCT_RETURN_DETAIL_SCOPE');
                }
                $details->delete();
                $receipts->delete();
                if ($this->primary->table('inventory_units')->whereIn('item_id', $units)->whereNotIn('id', $units)->exists()) {
                    throw new RuntimeException('EQCT_ITEM_SCOPE');
                }
                $this->primary->table('inventory_item_photos')->whereIn('item_id', $units)->delete();
            }
            $audit()->delete();
            EquipmentLoanItem::whereIn('loan_id', $loans)->delete();
            EquipmentLoan::whereIn('id', $loans)->delete();
            InventoryUnit::whereIn('id', $units)->delete();
            if ($expanded) {
                $this->primary->table('inventory_items')->whereIn('id', $units)->delete();
            }
            InventoryCategory::whereIn('id', $categories)->delete();
            User::whereIn('id', $users)->delete();
            if (User::whereIn('role_id', $roles)->exists()) {
                throw new RuntimeException('EQCT_ROLE_REFERENCES');
            }
            Role::whereIn('id', $roles)->delete();
        });
        if (EquipmentLoan::whereIn('submission_token', $tokens)->exists() || InventoryUnit::whereIn('id', $units)->exists() || User::whereIn('id', $users)->exists()) {
            throw new RuntimeException('EQCT_RESIDUE');
        }
        if ($this->journal !== null) {
            unlink($this->journal);
            $this->journal = null;
        }
    }

    protected function stopEquipmentWorkers(): void
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

    /** Both workers reach the actual locking-query callback while parent holds the target row. */
    protected function race(array $jobs, string $table, int $id): array
    {
        if (! in_array($table, ['inventory_units', 'equipment_loans', 'inventory_categories'], true)) {
            throw new RuntimeException('EQCT_LOCK_TABLE');
        }
        $directory = storage_path('framework/testing/'.$this->prefix.'-'.bin2hex(random_bytes(4)));
        mkdir($directory, 0700, true);
        $paths = [];
        $this->primary->beginTransaction();
        try {
            $this->primary->table($table)->where('id', $id)->lockForUpdate()->first();
            foreach ($jobs as $i => $job) {
                $base = $directory.'/worker-'.$i;
                file_put_contents($base.'.json', json_encode([...$job, 'go_file' => $base.'.go'], JSON_THROW_ON_ERROR));
                $process = proc_open([PHP_BINARY, base_path('tests/Concurrency/Equipment/worker.php'), $base.'.json'], [0 => ['file', 'NUL', 'r'], 1 => ['file', $base.'.out', 'w'], 2 => ['file', $base.'.err', 'w']], $pipes, base_path(), null, ['bypass_shell' => true, 'create_new_console' => false]);
                if (! is_resource($process)) {
                    throw new RuntimeException('EQCT_SPAWN');
                }
                $this->processes[] = $process;
                $paths[] = $base;
            }
            $ready = array_map(fn ($path) => $this->waitFor($path, 'ready'), $paths);
            $this->assertNotSame((string) $ready[0]['connection_id'], (string) $ready[1]['connection_id']);
            foreach ($paths as $path) {
                file_put_contents($path.'.go', 'go');
            }
            foreach ($paths as $path) {
                $this->waitFor($path, 'lock_attempt');
                $this->assertFalse($this->hasPhase($path, 'result'));
            }
            $this->primary->commit();
            $results = array_map(fn ($path) => $this->waitFor($path, 'result'), $paths);

            return $results;
        } finally {
            if ($this->primary->transactionLevel() > 0) {
                $this->primary->rollBack();
            }
            $deadline = microtime(true) + 3;
            foreach ($this->processes as $process) {
                while (proc_get_status($process)['running'] && microtime(true) < $deadline) {
                    usleep(10000);
                }
            }
            $this->stopEquipmentWorkers();
        }
    }

    private function phases(string $base): array
    {
        clearstatcache();
        $text = is_file($base.'.out') ? file_get_contents($base.'.out') : '';
        $rows = [];
        foreach (explode("\n", trim($text)) as $line) {
            $row = json_decode($line, true);
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function hasPhase(string $base, string $phase): bool
    {
        return in_array($phase, array_column($this->phases($base), 'phase'), true);
    }

    private function waitFor(string $base, string $phase): array
    {
        $deadline = microtime(true) + 15;
        do {
            foreach ($this->phases($base) as $row) {
                if ($row['phase'] === 'fatal') {
                    throw new RuntimeException('EQCT_WORKER_'.$row['code']);
                } if ($row['phase'] === $phase) {
                    return $row;
                }
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        throw new RuntimeException('EQCT_WORKER_TIMEOUT_'.$phase);
    }
}
