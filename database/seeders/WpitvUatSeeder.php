<?php

namespace Database\Seeders;

use App\Models\EquipmentAuditEvent;
use App\Models\EquipmentLoan;
use App\Models\InventoryCategory;
use App\Models\InventoryUnit;
use App\Models\LiveStream;
use App\Models\MonthlyActivityReport;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use App\Models\WpitvAgenda;
use App\Services\EquipmentLoanCheckout;
use App\Services\EquipmentLoanLoss;
use App\Services\EquipmentLoanReturn;
use App\Services\EquipmentPhotos;
use App\Services\LiveStreamLifecycle;
use App\Services\MonthlyActivityCandidates;
use App\Services\MonthlyActivityLifecycle;
use App\Support\MonthlyActivityRules;
use App\Support\TestingDatabaseGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\Support\EquipmentConcurrencyGuard;
use Throwable;

class WpitvUatSeeder extends Seeder
{
    public const MARKER = 'UAT-WPITV-V1';

    public const ACCOUNTS = [
        'uat_wpitv_v1_admin' => User::ROLE_SUPER_ADMIN,
        'uat_wpitv_v1_production' => User::ROLE_PRODUCTION,
        'uat_wpitv_v1_news' => User::ROLE_NEWS,
        'uat_wpitv_v1_tvclub' => User::ROLE_TVCLUB,
    ];

    private array $stagedPhotos = [];

    private function syntheticPhoto(string $table): array
    {
        $photo = app(EquipmentPhotos::class)->stage(UploadedFile::fake()->image('uat.png', 32, 32));
        $this->stagedPhotos[$table][] = $photo;

        return $photo;
    }

    public function run(): void
    {
        $this->assertIsolated();
        $lock = DB::selectOne('SELECT GET_LOCK(?, 5) AS acquired', [self::MARKER]);
        if ((int) $lock->acquired !== 1) {
            throw new RuntimeException('Paket UAT sedang diproses.');
        }

        try {
            DB::transaction(function (): void {
                $existing = User::with('role')->whereIn('username', array_keys(self::ACCOUNTS))->get();
                if ($existing->isNotEmpty()) {
                    if ($existing->count() !== count(self::ACCOUNTS) || $existing->contains(
                        fn (User $user) => $user->name !== self::MARKER.' '.$user->username
                            || $user->role?->name !== self::ACCOUNTS[$user->username],
                    )) {
                        throw new RuntimeException('Akun penanda UAT bentrok atau paket tidak lengkap.');
                    }
                    $this->assertComplete($existing->modelKeys());

                    return;
                }
                $password = getenv('WPITV_UAT_PASSWORD');
                if ($password === false || strlen($password) < 16 || strlen($password) > 72) {
                    throw new RuntimeException('Atur WPITV_UAT_PASSWORD dengan 16–72 byte untuk akun sintetis baru.');
                }
                $actors = [];
                foreach (self::ACCOUNTS as $username => $roleName) {
                    $role = Role::firstOrCreate(['name' => $roleName], ['description' => 'Role kanonis untuk UAT terisolasi']);
                    $actors[$roleName] = User::forceCreate([
                        'username' => $username, 'name' => self::MARKER.' '.$username,
                        'role_id' => $role->id, 'password' => $password, 'status' => true,
                    ]);
                }
                $production = $actors[User::ROLE_PRODUCTION];
                $now = CarbonImmutable::now('Asia/Jakarta')->startOfMinute();
                $months = array_map(fn (int $offset) => $now->startOfMonth()->subMonths($offset), [3, 2, 1]);
                $program = Program::create(['name' => self::MARKER.' Studio Kampus', 'slug' => 'uat-wpitv-v1-studio', 'status' => true]);
                $agendas = $this->seedLive($production, $program, $now, $months);
                $this->seedLoans($production, $now);
                $this->seedMonthly($actors, $months, $agendas);
                $this->assertComplete(array_map(fn (User $user) => $user->id, $actors));
            });
        } catch (Throwable $error) {
            // Domain and SQL failures may contain private fixture or credential bindings.
            throw new RuntimeException('Paket UAT dibatalkan ('.class_basename($error).').');
        } finally {
            try {
                foreach ($this->stagedPhotos as $table => $photos) {
                    app(EquipmentPhotos::class)->discardUnused($table, $photos);
                }
            } finally {
                $this->stagedPhotos = [];
                DB::selectOne('SELECT RELEASE_LOCK(?)', [self::MARKER]);
            }
        }
    }

    private function assertIsolated(): void
    {
        $connection = DB::connection();
        if (app()->environment('local') && ! app()->configurationIsCached()
            && $connection->getDriverName() === 'mysql'
            && $connection->getDatabaseName() === 'wpitv'
            && in_array($connection->getConfig('host'), ['localhost', '127.0.0.1'], true)
            && (string) $connection->getConfig('port') === '3306'
            && ! $connection->getConfig('url') && ! $connection->getConfig('unix_socket')
            && ! $connection->getConfig('read') && ! $connection->getConfig('write')) {
            $identity = $connection->selectOne('SELECT DATABASE() AS db, @@foreign_key_checks AS fk, @@unique_checks AS uq');
            if ($identity->db === 'wpitv' && (int) $identity->fk === 1 && (int) $identity->uq === 1) {
                return;
            }
        }
        TestingDatabaseGuard::validateResolved(app()->environment(), $connection->getDatabaseName(), configurationCached: app()->configurationIsCached());
        if ($connection->getDriverName() !== 'mysql' || $connection->getConfig('host') !== '127.0.0.1'
            || (string) $connection->getConfig('port') !== '3306' || $connection->getConfig('username') !== 'wpitv_test_runner'
            || $connection->getConfig('url') || $connection->getConfig('unix_socket')
            || $connection->getConfig('read') || $connection->getConfig('write')) {
            throw new RuntimeException('Seeder UAT membutuhkan koneksi MySQL testing loopback yang disetujui.');
        }
        $identity = $connection->selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS account, @@foreign_key_checks AS fk, @@unique_checks AS uq');
        if ($identity->db !== 'wpitv_testing' || $identity->account !== 'wpitv_test_runner@localhost'
            || (int) $identity->fk !== 1 || (int) $identity->uq !== 1) {
            throw new RuntimeException('Identitas database atau constraint testing tidak sesuai.');
        }
        EquipmentConcurrencyGuard::assertGrants(array_map(fn ($row) => array_values((array) $row)[0], DB::select('SHOW GRANTS')));
    }

    private function assertComplete(array $users): void
    {
        $counts = [
            InventoryCategory::where('name', 'like', self::MARKER.' %')->count(),
            InventoryUnit::where('inventory_code', 'like', self::MARKER.'-%')->count(),
            EquipmentLoan::whereIn('recorded_by', $users)->where('student_number', 'like', self::MARKER.'-%')->count(),
            LiveStream::whereIn('created_by', $users)->count(),
            MonthlyActivityReport::whereIn('created_by', $users)->count(),
        ];
        if ($counts !== [6, 24, 12, 8, 6]) {
            throw new RuntimeException('Paket UAT tidak lengkap. Periksa penanda sebelum menjalankan ulang.');
        }
    }

    /** @return list<WpitvAgenda> */
    private function seedLive(User $actor, Program $program, CarbonImmutable $now, array $months): array
    {
        $service = app(LiveStreamLifecycle::class);
        $agendas = [];
        foreach ([...array_map(fn ($month) => $month->addDays(12), $months), $now->addDays(4), $now->addDays(8)] as $i => $date) {
            $agenda = WpitvAgenda::create([
                'title' => self::MARKER.' '.['Liputan seminar', 'Produksi festival', 'Diskusi kampus', 'Gladi studio', 'Siaran ulang tahun'][$i],
                'type' => 'live', 'program_id' => $program->id, 'agenda_date' => $date->toDateString(),
                'start_time' => '19:00', 'end_time' => '20:00', 'status' => $i === 3 ? 'draft' : 'scheduled',
                'visibility' => 'internal', 'created_by' => $actor->id,
            ]);
            $stream = $service->create($actor, $agenda->id, $i < 3 ? $this->youtubeId($i) : null);
            if ($i < 3) {
                $service->transition($actor, $stream->id, $stream->version, 'ended', 'UAT: simulasi acara selesai');
                $agendas[] = $agenda->refresh();
            }
        }

        foreach (['scheduled', 'ended', 'cancelled'] as $i => $status) {
            $stream = $service->create($actor, null, $status === 'ended' ? $this->youtubeId(5) : null, 'manual', [
                'title' => self::MARKER.' '.['Latihan siaran malam', 'Rekap studio', 'Gladi teknis batal'][$i],
                'program_id' => $i === 2 ? null : $program->id,
                'agenda_date' => ($i === 1 ? $now->subMonthNoOverflow() : $now->addDays(10 + $i))->toDateString(),
                'start_time' => '23:00', 'end_time' => $i === 0 ? '01:00' : null, 'visibility' => 'internal',
            ]);
            if ($status !== 'scheduled') {
                $service->transition($actor, $stream->id, $stream->version, $status, 'UAT: simulasi lifecycle tanpa siaran');
            }
        }

        return $agendas;
    }

    private function youtubeId(int $index): string
    {
        return 'UATWPITV'.str_pad((string) $index, 3, '0', STR_PAD_LEFT);
    }

    private function seedLoans(User $actor, CarbonImmutable $now): void
    {
        $catalogue = [
            'Kamera' => ['Kamera mirrorless', 'Kamera video', 'Kamera aksi', 'Kamera dokumentasi'],
            'Audio' => ['Mikrofon nirkabel', 'Mikrofon shotgun', 'Perekam audio', 'Mixer portabel'],
            'Pencahayaan' => ['Panel LED', 'Lampu COB', 'Lampu tabung', 'Ring light'],
            'Tripod & Stabilizer' => ['Tripod video', 'Monopod', 'Gimbal kamera', 'Tripod mini'],
            'Perangkat Streaming' => ['Capture card', 'Switcher video', 'Encoder', 'Monitor preview'],
            'Aksesori Produksi' => ['Teleprompter', 'Kabel SDI', 'Adaptor daya', 'Baterai cadangan'],
        ];
        $photosEnabled = EquipmentPhotos::enabled();
        $units = [];
        foreach ($catalogue as $category => $names) {
            $group = InventoryCategory::forceCreate(['name' => self::MARKER.' '.$category, 'description' => 'Peralatan studio dan liputan sintetis', 'is_active' => true]);
            foreach ($names as $name) {
                $index = count($units);
                $units[] = InventoryUnit::forceCreate([
                    'category_id' => $group->id, 'inventory_code' => sprintf('%s-%02d', self::MARKER, $index + 1),
                    'name' => $name, 'storage_location' => 'Lemari studio UAT '.(1 + intdiv($index, 4)),
                    'condition' => match ($index) {
                        20 => 'minor_damage', 21 => 'major_damage', default => 'good'
                    },
                    'is_active' => $index !== 23, 'version' => 1,
                ]);
                if ($photosEnabled) {
                    $unit = $units[$index];
                    $event = EquipmentAuditEvent::forceCreate(['unit_id' => $unit->id, 'event_type' => 'created', 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'reason' => 'Pembuatan unit sintetis UAT']);
                    DB::table('inventory_unit_photos')->insert($this->syntheticPhoto('inventory_unit_photos') + ['unit_id' => $unit->id, 'audit_event_id' => $event->id, 'revision' => 1]);
                }
            }
        }

        $checkout = app(EquipmentLoanCheckout::class);
        $return = app(EquipmentLoanReturn::class);
        $nextUnit = 0;
        foreach (['returned', 'returned', 'returned', 'returned', 'returned', 'returned', 'active', 'active', 'overdue', 'partial', 'partial', 'lost'] as $index => $scenario) {
            $borrowed = $scenario === 'returned' ? $now->subMonthsNoOverflow(1 + intdiv($index, 2))->subDays(4) : $now->subDays($scenario === 'overdue' ? 5 : 2);
            $due = $scenario === 'overdue' ? $now->subDays(2) : ($scenario === 'returned' ? $borrowed->addDays(2) : $now->addDays(4));
            $selection = array_slice($units, $nextUnit, $scenario === 'partial' ? 2 : 1);
            $nextUnit += count($selection);
            $loan = $checkout->checkoutInTransaction($actor, $this->token('checkout', $index), [
                'borrower_name' => 'Peminjam Sintetis '.($index + 1), 'student_number' => self::MARKER.'-'.($index + 1),
                'study_program' => 'Produksi Media', 'whatsapp_number' => '620000000001',
                'purpose' => 'UAT: dokumentasi kegiatan kampus dan latihan studio',
                'borrowed_at' => $borrowed->format('Y-m-d\TH:i'), 'due_at' => $due->format('Y-m-d\TH:i'),
                'items' => array_map(fn ($unit) => ['unit_id' => $unit->id, 'version' => $unit->version], $selection),
            ]);
            $item = $loan->items()->orderBy('id')->firstOrFail();
            $header = ['loan_id' => $loan->id, 'expected_version' => $loan->version];
            if (in_array($scenario, ['returned', 'partial'], true)) {
                $return->recordInTransaction($actor, $this->token('return', $index), [...$header, 'items' => [[
                    'item_id' => $item->id, 'returned_at' => $borrowed->addDays($index === 3 ? 3 : 1)->format('Y-m-d\TH:i'),
                    'condition_in' => match ($index) {
                        4 => 'minor_damage', 5 => 'major_damage', default => 'good'
                    },
                    'return_notes' => 'UAT: catatan pemeriksaan dan pengembalian sintetis',
                ]]], $photosEnabled ? [$item->id => $this->syntheticPhoto('equipment_return_photos')] : []);
            } elseif ($scenario === 'lost') {
                app(EquipmentLoanLoss::class)->recordInTransaction($actor, $this->token('loss', $index), [...$header, 'items' => [[
                    'item_id' => $item->id, 'lost_at' => $borrowed->addDay()->format('Y-m-d\TH:i'), 'loss_notes' => 'UAT: perangkat belum ditemukan setelah liputan',
                ]]]);
            }
        }
    }

    private function token(string $type, int $index): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, self::MARKER.'/'.$type.'/'.$index)->toString();
    }

    private function seedMonthly(array $actors, array $months, array $agendas): void
    {
        $service = app(MonthlyActivityLifecycle::class)->forNewReportsInTransaction();
        foreach (['production' => User::ROLE_PRODUCTION, 'news' => User::ROLE_NEWS] as $division => $role) {
            $actor = $actors[$role];
            foreach ($months as $index => $month) {
                $report = $service->createDraft($actor, $division, $month->format('Y-m'));
                $agenda = $division === 'production' ? $agendas[$index] : null;
                $context = $agenda ? app(MonthlyActivityCandidates::class)->resolve($actor, $report, 'agenda', $agenda->id) : null;
                $report = $service->saveItem($actor, $report->id, $report->version, null, [
                    'activity_date' => $month->addDays(12)->toDateString(),
                    'title' => self::MARKER.' '.($agenda ? 'Dokumentasi kegiatan kampus' : 'Penyuntingan buletin kampus'),
                    'description' => 'Penyusunan materi dan dokumentasi internal.',
                    'outcome' => 'Materi ditinjau dan disimpan untuk rekap bulanan.',
                    'obstacles' => 'Penjadwalan ulang satu sesi koordinasi.',
                    'follow_up' => 'Tinjau jadwal dan daftar kebutuhan bulan berikutnya.',
                    'contribution' => $agenda ? 'Perekaman dan penyuntingan video' : 'Penulisan naskah dan verifikasi redaksi',
                ], $agenda ? 'agenda' : null, $agenda?->id, $context ? MonthlyActivityRules::snapshotHash($context) : null);
                if ($division === 'news' && $index === 2) {
                    continue;
                }
                $report = $service->submit($actor, $report->id, $report->version);
                $reviewer = $actors[User::ROLE_SUPER_ADMIN];
                if ($index === 0) {
                    $service->approve($reviewer, $report->id, $report->version);
                } elseif ($division === 'news') {
                    $service->requestRevision($reviewer, $report->id, $report->version, 'UAT: lengkapi hasil koordinasi.');
                }
            }
        }
    }
}
