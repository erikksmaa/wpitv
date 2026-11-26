<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Equipment\EquipmentReports;
use App\Services\Equipment\InventoryPhotos;
use App\Services\Equipment\InventoryService;
use App\Services\Equipment\LoanService;
use App\Support\EquipmentPermissions;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class EquipmentDemoSeeder extends Seeder
{
    private const CATALOG = [
        'KAMERA' => ['Kamera', [
            ['EOS-700D', 'Canon EOS 700D', 'Kamera DSLR untuk dokumentasi dan latihan produksi.', 4],
            ['EOS-80D', 'Canon EOS 80D', 'Kamera DSLR untuk wawancara dan produksi profil.', 3],
            ['ALPHA-A6400', 'Sony Alpha A6400', 'Kamera ringkas untuk liputan bergerak.', 4],
            ['HXR-NX100', 'Sony HXR-NX100', 'Kamera video untuk peliputan acara kampus.', 3],
            ['OSMO-POCKET-3', 'DJI Osmo Pocket 3', 'Kamera genggam untuk konten pendek.', 2],
        ]],
        'AUDIO' => ['Audio', [
            ['WIRELESS-GO-II', 'Rode Wireless GO II', 'Mikrofon nirkabel untuk wawancara.', 4],
            ['BY-M1', 'Boya BY-M1', 'Mikrofon lavalier berkabel.', 3],
            ['ZOOM-H1N', 'Zoom H1n', 'Perekam audio portabel untuk liputan.', 2],
            ['SHURE-SM58', 'Shure SM58', 'Mikrofon genggam untuk narasi dan acara.', 4],
        ]],
        'CAHAYA' => ['Pencahayaan', [
            ['GODOX-SL60W', 'Godox SL60W', 'Lampu utama untuk produksi studio.', 3],
            ['GODOX-LEDP260C', 'Godox LEDP260C', 'Panel lampu portabel untuk wawancara.', 2],
        ]],
        'PENYANGGA' => ['Tripod dan Stabilizer', [
            ['DJI-RS3-MINI', 'DJI RS 3 Mini', 'Stabilizer untuk pengambilan gambar bergerak.', 2],
            ['ECO-193A', 'Takara ECO-193A', 'Tripod untuk dokumentasi kegiatan.', 4],
            ['COMPACT-ACTION', 'Manfrotto Compact Action', 'Tripod ringkas untuk produksi lapangan.', 3],
        ]],
        'STREAMING' => ['Peralatan Streaming', [
            ['CAM-LINK-4K', 'Elgato Cam Link 4K', 'Perangkat tangkap video untuk siaran.', 3],
            ['ATEM-MINI-PRO', 'Blackmagic ATEM Mini Pro', 'Pengalih sumber video untuk siaran langsung.', 2],
            ['MARS-400S-PRO', 'Hollyland Mars 400S Pro', 'Perangkat pengiriman video nirkabel.', 2],
        ]],
        'AKSESORI' => ['Aksesori Produksi', [
            ['SD-EXTREME-128', 'SanDisk Extreme Pro 128GB', 'Kartu memori untuk rekaman lapangan.', 5],
            ['EF-50-STM', 'Canon EF 50mm f/1.8 STM', 'Lensa untuk potret dan wawancara.', 3],
            ['EFS-18-55', 'Canon EF-S 18-55mm', 'Lensa zoom untuk kegiatan kampus.', 3],
        ]],
    ];

    private const SCENARIOS = [
        'on_time', 'late_return', 'loss', 'on_time', 'late_return', 'void', 'on_time', 'loss', 'late_return',
        'partial', 'partial', 'partial', 'overdue', 'overdue', 'overdue', 'active', 'active', 'active', 'active', 'active',
    ];

    private const BORROWERS = ['Aditya Pratama', 'Muhammad Rizky Ramadhan', 'Fajar Nugroho', 'Nabila Putri', 'Dinda Maharani', 'Raka Saputra', 'Siti Nur Aisyah', 'Ahmad Fauzan'];

    private const PURPOSES = [
        'Dokumentasi seminar kampus', 'Produksi video profil program studi', 'Peliputan kegiatan mahasiswa',
        'Siaran langsung wisuda', 'Pembuatan konten media sosial', 'Produksi berita kampus',
        'Dokumentasi kegiatan UKM', 'Perekaman podcast', 'Praktikum multimedia', 'Dokumentasi penerimaan mahasiswa baru',
    ];

    private array $writtenPaths = [];

    public function run(InventoryService $inventory, LoanService $loans, InventoryPhotos $photos, EquipmentReports $reports): void
    {
        $connection = DB::connection();
        if (! app()->environment('local') || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'wpitv'
            || ! in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new \RuntimeException('Seeder Equipment hanya untuk database MySQL wpitv di komputer lokal.');
        }
        if ((int) DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', ['wpitv-equipment-demo'])->acquired !== 1) {
            throw new \RuntimeException('Seeder Equipment sedang berjalan.');
        }
        $clock = Carbon::getTestNow();
        try {
            if (DB::table('equipment_operation_keys')->where('token', $this->token('category:KAMERA'))->exists()) {
                $this->command->info('Paket Equipment sudah ada. Memverifikasi tanpa menulis ulang.');
                $this->verify($reports);

                return;
            }

            $actor = User::with('role')->where('status', 1)->whereHas('role', fn ($q) => $q->where('name', User::ROLE_SUPER_ADMIN))->orderBy('id')->firstOrFail();
            foreach (array_unique(EquipmentPermissions::OPERATIONS) as $permission) {
                if ($actor->cannot($permission)) {
                    throw new \RuntimeException('Akun pelaku tidak memiliki izin Equipment yang lengkap.');
                }
            }
            $today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
            DB::transaction(function () use ($inventory, $loans, $photos, $reports, $actor, $today) {
                $unitIds = $this->catalog($inventory, $photos, $actor, $today->subDays(110));
                $this->loans($loans, $actor, $today, $unitIds);
                Carbon::setTestNow($today->addHours(9));
                $this->verify($reports, true);
            });
        } catch (\Throwable $error) {
            foreach ($this->writtenPaths as $path) {
                try {
                    $referenced = false;
                    foreach (['equipment_model_photos', 'equipment_unit_photos', 'equipment_return_photos'] as $table) {
                        $referenced = $referenced || DB::table($table)->where('storage_path', $path)->exists();
                    }
                    if (! $referenced) {
                        Storage::disk('local')->delete($path);
                    }
                } catch (\Throwable $cleanupError) {
                    report($cleanupError);
                }
            }
            throw $error;
        } finally {
            Carbon::setTestNow($clock);
            DB::selectOne('SELECT RELEASE_LOCK(?)', ['wpitv-equipment-demo']);
        }
    }

    private function catalog(InventoryService $inventory, InventoryPhotos $photos, User $actor, CarbonImmutable $date): array
    {
        Carbon::setTestNow($date->addHours(9));
        $pool = $spares = [];
        foreach (self::CATALOG as $code => [$name, $models]) {
            $category = $inventory->execute($actor, 'category.create', $this->token('category:'.$code), [
                'name' => $name, 'code' => $code, 'description' => 'Perlengkapan produksi untuk '.mb_strtolower($name).'.', 'is_active' => true,
            ]);
            foreach ($models as [$modelCode, $modelName, $description, $quantity]) {
                $model = $inventory->execute($actor, 'model.create', $this->token('model:'.$modelCode), [
                    'name' => $modelName, 'code' => $modelCode, 'description' => $description, 'is_active' => true, 'category_id' => $category,
                    'quantity' => $quantity - 1, 'condition' => 'good', 'reason' => 'Penambahan peralatan produksi.',
                ]);
                $this->inventoryAction($inventory, $actor, 'stock.add', 'equipment_models', $model, [
                    'quantity' => 1, 'condition' => 'good', 'reason' => 'Penambahan stok untuk kebutuhan peliputan.',
                ]);
                $units = DB::table('equipment_units')->where('model_id', $model)->orderBy('unit_no')->pluck('id')->all();
                $pool[] = $units[0];
                $pool[] = $units[1];
                array_push($spares, ...array_slice($units, 2));
                foreach (['model' => [$model, $modelCode], 'unit' => [$units[0], $modelCode.'-001']] as $kind => [$id, $label]) {
                    $image = $this->image($label);
                    try {
                        $photoId = $this->inventoryAction($inventory, $actor, $kind.'.photo_add', 'equipment_'.$kind.'s', $id, [
                            'reason' => 'Ilustrasi lokal untuk referensi inventaris.',
                        ], [$photos->prepare($image)]);
                        $this->writtenPaths[] = DB::table('equipment_'.$kind.'_photos')->where('id', $photoId)->value('storage_path');
                    } finally {
                        unlink($image->getPathname());
                    }
                }
            }
        }
        foreach (['minor_damage', 'major_damage'] as $index => $condition) {
            $id = $spares[$index];
            $this->inventoryAction($inventory, $actor, 'unit.condition', 'equipment_units', $id, [
                'condition' => $condition, 'serial_number' => '', 'reason' => $index ? 'Dudukan mengalami kerusakan setelah peliputan.' : 'Pemeriksaan menemukan gangguan pada kelengkapan unit.',
            ]);
            $this->inventoryAction($inventory, $actor, 'unit.maintenance_start', 'equipment_units', $id, ['reason' => 'Unit menunggu pemeriksaan teknisi.']);
        }
        $this->inventoryAction($inventory, $actor, 'unit.retire', 'equipment_units', $spares[2], ['reason' => 'Unit tidak layak digunakan kembali setelah pemeriksaan.']);

        return $pool;
    }

    private function inventoryAction(InventoryService $service, User $actor, string $op, string $table, int $id, array $input, array $photos = []): int
    {
        return $service->execute($actor, $op, $this->token($op.':'.$id), $input + [
            'id' => $id, 'version' => DB::table($table)->where('id', $id)->value('version'),
        ], $photos);
    }

    private function loans(LoanService $service, User $actor, CarbonImmutable $today, array $unitIds): void
    {
        foreach (self::SCENARIOS as $index => $scenario) {
            $checkout = $today->subDays(match ($scenario) {
                'active' => 19 - $index, 'overdue' => 26 - $index, 'partial' => 30 - $index,
                default => 100 - 8 * $index,
            });
            $due = $scenario === 'active' || $scenario === 'partial' ? $today->addDays(2 + $index % 4) : $checkout->addDays(3);
            Carbon::setTestNow($checkout->addHours(8));
            [$borrower, $affiliation, $identifier] = match ($index % 3) {
                0 => ['student', 'Teknik Informatika', 'MHS'],
                1 => ['lecturer', 'Sistem Informasi', 'DSN'],
                2 => ['staff', 'Unit Kegiatan Kampus', 'STF'],
            };
            $loan = $service->execute($actor, 'loan.checkout', $this->token('checkout:'.$index), [
                'borrower_type' => $borrower, 'borrower_name' => self::BORROWERS[$index % count(self::BORROWERS)],
                'identity_number' => $identifier.'-'.(27001 + $index), 'borrower_contact' => 'produksi.'.($index + 1).'@example.invalid',
                'borrower_affiliation' => $affiliation, 'purpose' => self::PURPOSES[$index % count(self::PURPOSES)],
                'due_date' => $due->toDateString(), 'units' => array_slice($unitIds, $index * 2, 2),
            ]);
            if (in_array($index, [0, 4, 15], true)) {
                Carbon::setTestNow($checkout->addDay()->addHours(9));
                $due = $due->addDays(2);
                $this->loanAction($service, $actor, 'loan.extend', $index, $loan, ['due_date' => $due->toDateString(), 'reason' => 'Perpanjangan karena jadwal produksi berubah.']);
            }
            if (in_array($scenario, ['active', 'overdue'], true)) {
                continue;
            }
            if ($scenario === 'void') {
                Carbon::setTestNow($checkout->addHours(10));
                $this->loanAction($service, $actor, 'loan.void', $index, $loan, ['not_handed_over' => true, 'reason' => 'Jadwal kegiatan dibatalkan sebelum peralatan diserahkan.']);

                continue;
            }

            $detailIds = DB::table('equipment_loan_units')->where('loan_id', $loan)->orderBy('id')->pluck('id')->all();
            $returned = in_array($scenario, ['partial', 'loss'], true) ? [$detailIds[0]] : $detailIds;
            $returnDate = $scenario === 'late_return' ? $due->addDays(2) : $checkout->addDays(2);
            Carbon::setTestNow($returnDate->addHours(13));
            $uploads = $items = [];
            try {
                foreach ($returned as $id) {
                    $uploads[$id] = [$this->image('Bukti pengembalian')];
                    $items[] = ['loan_unit_id' => $id, 'condition' => 'good', 'notes' => $scenario === 'late_return' ? 'Pengembalian tertunda karena perubahan jadwal kegiatan.' : ''];
                }
                $receipt = $this->loanAction($service, $actor, 'return.create', $index, $loan, [
                    'items' => $items, 'notes' => 'Peralatan diterima dan diperiksa setelah kegiatan. Foto merupakan ilustrasi lokal.',
                ], $uploads);
                array_push($this->writtenPaths, ...DB::table('equipment_return_photos')->where('return_id', $receipt)->pluck('storage_path')->all());
            } finally {
                foreach ($uploads as [$image]) {
                    unlink($image->getPathname());
                }
            }
            if ($scenario === 'loss') {
                Carbon::setTestNow($due->addDay()->addHours(11));
                $this->loanAction($service, $actor, 'loan.loss', $index, $loan, [
                    'items' => [['loan_unit_id' => $detailIds[1], 'notes' => 'Unit belum ditemukan setelah pemeriksaan lokasi kegiatan.']],
                    'reason' => 'Unit belum ditemukan setelah penelusuran dan konfirmasi kepada penanggung jawab kegiatan.',
                ]);
            }
        }
    }

    private function loanAction(LoanService $service, User $actor, string $op, int $index, int $loan, array $input, array $uploads = []): int
    {
        return $service->execute($actor, $op, $this->token($op.':'.$index), $input + [
            'loan_id' => $loan, 'version' => DB::table('equipment_loans')->where('id', $loan)->value('version'),
        ], $uploads);
    }

    private function image(string $label): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'wpi-equipment-');
        if ($path === false) {
            throw new \RuntimeException('Tidak dapat membuat file foto sementara.');
        }
        $image = imagecreatetruecolor(640, 360);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 38, 64));
        $ink = imagecolorallocate($image, 223, 234, 249);
        $accent = imagecolorallocate($image, 87, 161, 218);
        imagefilledrectangle($image, 48, 49, 591, 52, $accent);
        imagestring($image, 5, 48, 91, 'WPITV  /  ILUSTRASI PERALATAN', $ink);
        imagestring($image, 5, 48, 153, strtoupper($label), $ink);
        imagestring($image, 4, 48, 271, 'Gambar ilustrasi, bukan dokumentasi asli.', $accent);
        try {
            if (! imagepng($image, $path)) {
                throw new \RuntimeException('Tidak dapat membuat ilustrasi foto.');
            }
        } catch (\Throwable $error) {
            unlink($path);
            throw $error;
        }

        return new UploadedFile($path, 'ilustrasi-wpitv.png', 'image/png', null, true);
    }

    private function token(string $key): string
    {
        $hex = hash('sha256', 'wpitv/equipment/catalog-v1/'.$key);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-4'.substr($hex, 13, 3).'-a'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }

    private function verify(EquipmentReports $reports, bool $checkInitialState = false): void
    {
        $categoryIds = DB::table('equipment_operation_keys')->whereIn('token', array_map(fn ($code) => $this->token('category:'.$code), array_keys(self::CATALOG)))->pluck('result_id');
        $modelIds = DB::table('equipment_models')->whereIn('category_id', $categoryIds)->pluck('id');
        $unitIds = DB::table('equipment_units')->whereIn('model_id', $modelIds)->pluck('id');
        $loanIds = DB::table('equipment_operation_keys')->whereIn('token', array_map(fn ($i) => $this->token('checkout:'.$i), array_keys(self::SCENARIOS)))->pluck('result_id');
        $details = DB::table('equipment_loan_units')->whereIn('loan_id', $loanIds);
        $returned = (clone $details)->where('outcome', 'returned');
        $receipts = DB::table('equipment_return_photos')->whereIn('loan_unit_id', (clone $returned)->select('id'));
        $photos = [
            DB::table('equipment_model_photos')->whereIn('model_id', $modelIds),
            DB::table('equipment_unit_photos')->whereIn('unit_id', $unitIds),
            $receipts,
        ];
        $summary = $reports->loanCounts($reports->loans([])->whereIn('id', $loanIds));
        $stock = $reports->stock();
        $duplicate = DB::table('equipment_loan_units')->where('outcome', 'on_loan')->select('unit_id')->groupBy('unit_id')->havingRaw('COUNT(*) > 1')->exists();
        $missingEvidence = (clone $returned)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('equipment_return_photos as p')->whereColumn('p.loan_unit_id', 'equipment_loan_units.id')->whereColumn('p.return_id', 'equipment_loan_units.return_id'))->exists();
        if ($categoryIds->count() !== 6 || $modelIds->count() < 20 || $unitIds->count() < 50 || $loanIds->count() !== 20
            || $details->count() !== 40 || $summary->transactions != 20 || $duplicate || $missingEvidence
            || $stock->borrowed != DB::table('equipment_loan_units')->whereNotNull('open_unit_id')->count()) {
            throw new \RuntimeException('Verifikasi integritas paket Equipment gagal.');
        }
        if ($checkInitialState) {
            $rows = $reports->loans([])->whereIn('id', $loanIds)->get();
            if ($rows->where('progress', 'active')->where('overdue', 0)->count() !== 5
                || $rows->where('progress', 'active')->where('overdue', 1)->count() !== 3
                || $rows->where('progress', 'partially_resolved')->count() !== 3
                || $rows->where('progress', 'closed')->where('missing', 0)->count() !== 6
                || $rows->where('missing', 1)->count() !== 2 || $rows->where('progress', 'voided')->count() !== 1
                || DB::table('equipment_audit_log')->whereIn('loan_id', $loanIds)->where('event_name', 'loan.extended')->count() !== 3) {
                throw new \RuntimeException('Distribusi transaksi Equipment tidak sesuai rencana.');
            }
        }
        $photoCounts = [];
        foreach ($photos as $query) {
            $photoCounts[] = $query->count();
            foreach ($query->select('storage_path', 'sha256')->cursor() as $photo) {
                $disk = Storage::disk('local');
                if (! $disk->exists($photo->storage_path) || hash('sha256', $disk->get($photo->storage_path)) !== $photo->sha256) {
                    throw new \RuntimeException('File atau checksum foto Equipment tidak valid.');
                }
            }
        }
        $this->command->info(sprintf('%d kategori, %d peralatan, %d unit fisik, %d transaksi, %d detail unit, %d foto peralatan, %d foto unit, %d foto pengembalian.',
            $categoryIds->count(), $modelIds->count(), $unitIds->count(), $loanIds->count(), $details->count(), ...$photoCounts));
    }
}
