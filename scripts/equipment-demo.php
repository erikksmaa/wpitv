<?php

use App\Models\EquipmentAuditEvent;
use App\Models\InventoryCategory;
use App\Models\InventoryUnit;
use App\Models\User;
use App\Services\EquipmentLoanCheckout;
use App\Services\EquipmentLoanReturn;
use App\Services\EquipmentPhotos;
use App\Services\EquipmentQuantity;
use App\Services\EquipmentStock;
use App\Services\InventoryCode;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EquipmentConcurrencyGuard as Guard;
use Tests\Support\EquipmentLedgerReconciliation;

require dirname(__DIR__).'/vendor/autoload.php';
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
function checkDemo(bool $ok, string $code): void
{
    if (! $ok) {
        throw new RuntimeException($code);
    }
}

function rowMap(): array
{
    $map = [];
    foreach (['equipment_return_photo_items', 'equipment_return_photos', 'inventory_unit_photos', 'equipment_audit_events', 'equipment_loan_items', 'equipment_loans', 'inventory_unit_members', 'inventory_units', 'inventory_categories', 'users'] as $table) {
        $map[$table] = [];
        foreach (DB::table($table)->cursor() as $row) {
            $id = match ($table) {
                'equipment_return_photo_items' => $row->photo_id.':'.$row->loan_item_id,'inventory_unit_members' => $row->unit_id,default => $row->id
            };
            $map[$table][$id] = hash('sha256', serialize($row));
        }
    }

    return $map;
}

function illustration(string $label, string $caption): array
{
    global $files;
    $image = imagecreatetruecolor(500, 160);
    imagefill($image, 0, 0, imagecolorallocate($image, 21, 48, 66));
    $ink = imagecolorallocate($image, 255, 255, 255);
    imagestring($image, 5, 20, 25, 'ILUSTRASI DEMONSTRASI', $ink);
    imagestring($image, 5, 20, 68, $label, $ink);
    imagestring($image, 4, 20, 112, $caption, $ink);
    $tmp = tmpfile();
    try {
        $path = stream_get_meta_data($tmp)['uri'];
        imagepng($image, $path);
        $photo = app(EquipmentPhotos::class)->stage(new UploadedFile($path, 'ilustrasi.png', 'image/png', null, true));
        $files[] = $photo;

        return $photo;
    } finally {
        fclose($tmp);
    }
}

$files = [];
try {
    $mode = $argv[1] ?? 'inspect';
    checkDemo(in_array($mode, ['inspect', 'build', 'cleanup'], true), 'MODE');
    config(['database.connections.mysql.timezone' => '+00:00', 'database.connections.mysql.isolation_level' => 'REPEATABLE READ']);
    $db = DB::connection();
    $approval = json_decode(file_get_contents(storage_path('app/testing/approved.json')), true, 512, JSON_THROW_ON_ERROR);
    Guard::assertConnectionConfig($db->getConfig(), $approval);
    $identity = DB::selectOne('SELECT DATABASE() db,CURRENT_USER() actor,@@server_uuid uuid,@@foreign_key_checks fk,@@unique_checks uq');
    checkDemo($app->environment('testing') && ! $app->configurationIsCached() && $identity->db === 'wpitv_testing' && $identity->actor === 'wpitv_test_runner@localhost' && $identity->fk && $identity->uq, 'TARGET');
    Guard::assertGrants(array_map(fn ($r) => array_values((array) $r)[0], DB::select('SHOW GRANTS')));
    $engines = DB::table('information_schema.tables')->where('table_schema', $identity->db)->pluck('ENGINE', 'TABLE_NAME')->all();
    $ddl = [];
    foreach (Guard::approvedTables($approval) as $table) {
        $ddl[$table] = array_values((array) DB::selectOne("SHOW CREATE TABLE \x60$table\x60"))[1];
    }
    Guard::assertSchema($engines, $ddl, $approval);
    echo count($engines).' tables, '.DB::table('migrations')->count()." migrations\n";
    if ($mode === 'inspect') {
        return;
    }
    checkDemo((int) DB::selectOne("SELECT GET_LOCK('equipment_studio_demo',5) acquired")->acquired === 1, 'LOCK');
    $ledger = storage_path('app/testing/equipment-studio-ledger.json');
    $db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->beginTransaction();
    $before = rowMap();
    if (is_file($ledger)) {
        $state = json_decode(file_get_contents($ledger), true, 512, JSON_THROW_ON_ERROR);
        checkDemo($state['server'] === $identity->uuid, 'LEDGER_SERVER');
        if (($state['version'] ?? 1) === 2) {
            $immutable = storage_path('app/testing/equipment-studio-ledger.v1.json');
            checkDemo(is_file($immutable) && hash_file('sha256', $immutable) === $state['source_hash'], 'ARCHIVE_HASH');
            $source = json_decode(file_get_contents($immutable), true, 512, JSON_THROW_ON_ERROR);
            $reconciler = new EquipmentLedgerReconciliation;
            $report = $reconciler->reconcile($db, $source, $state['mapping']);
            checkDemo($report['allowed'] && $report['owned'] === $state['owned'] && $report['derived'] === $state['derived'], 'OWNERSHIP_V2');
            if ($mode === 'cleanup') {
                $reconciler->verifyCleanup($db, $report, $identity->uuid, delete: true);
            }
            $db->commit();
            if ($mode === 'cleanup') {
                // Keep evidence files and the immutable ledger available for recovery.
                unlink($ledger);
                echo "Version 2 rows removed; recovery media preserved.\n";
            } else {
                echo "Version 2 ledger verified.\n";
            }

            return;
        }
        foreach ($state['owned'] as $table => $ids) {
            foreach ($ids as $id => $hash) {
                checkDemo(($before[$table][$id] ?? null) === $hash, 'FIXTURE_CHANGED');
            }
        }
        if ($mode === 'cleanup') {
            $foreignKeys = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('REFERENCED_TABLE_SCHEMA', $identity->db)->whereIn('REFERENCED_TABLE_NAME', array_keys($before))
                ->get(['TABLE_NAME', 'COLUMN_NAME', 'REFERENCED_TABLE_NAME', 'REFERENCED_COLUMN_NAME']);
            foreach ($foreignKeys as $fk) {
                $ids = array_keys($state['owned'][$fk->REFERENCED_TABLE_NAME]);
                if (! $ids) {
                    continue;
                }
                checkDemo($fk->REFERENCED_COLUMN_NAME === 'id', 'UNSUPPORTED_REFERENCE');
                foreach (DB::table($fk->TABLE_NAME)->whereIn($fk->COLUMN_NAME, $ids)->lockForUpdate()->get() as $ref) {
                    $key = match ($fk->TABLE_NAME) {
                        'inventory_unit_members' => $ref->unit_id,'equipment_return_photo_items' => $ref->photo_id.':'.$ref->loan_item_id,default => $ref->id ?? null
                    };
                    checkDemo(isset($state['owned'][$fk->TABLE_NAME][$key]), 'EXTERNAL_REFERENCE');
                }
            }
            foreach ($state['owned'] as $table => $ids) {
                foreach ($ids as $id => $hash) {
                    $query = DB::table($table);
                    if ($table === 'equipment_return_photo_items') {
                        [$photo,$item] = explode(':', $id);
                        $query->where('photo_id', $photo)->where('loan_item_id', $item);
                    } else {
                        $query->where($table === 'inventory_unit_members' ? 'unit_id' : 'id', $id);
                    }
                    checkDemo($query->delete() === 1, 'CLEANUP_COUNT');
                    unset($before[$table][$id]);
                }
            }
            checkDemo(rowMap() == $before, 'NON_FIXTURE_CHANGED');
            $db->commit();
            foreach ($state['files'] as $file) {
                app(EquipmentPhotos::class)->discardUnused($file['table'], [$file]);
            }
            unlink($ledger);
            echo "Cleanup verified\n";

            return;
        }
        $db->commit();
        echo "Existing ledger verified\n";

        return;
    }
    checkDemo($mode === 'build', 'NO_LEDGER');
    $actor = User::forceCreate(['name' => 'Petugas Studio', 'username' => 'studio_fixture_operator', 'password' => Str::random(48), 'role_id' => DB::table('roles')->where('name', User::ROLE_PRODUCTION)->sole()->id, 'status' => true]);
    $catalog = [
        ['Kamera', 'Kamera Sony A6400', 'Sony', 'A6400', 4], ['Kamera', 'Kamera Canon EOS M50', 'Canon', 'EOS M50', 3],
        ['Tripod dan Stabilizer', 'Tripod Manfrotto Compact', 'Manfrotto', 'Compact', 6], ['Audio', 'Mikrofon Wireless BOYA BY-WM4', 'BOYA', 'BY-WM4', 5],
        ['Pencahayaan', 'Lampu LED Godox LEDP260C', 'Godox', 'LEDP260C', 4], ['Perangkat Streaming', 'Capture Card HDMI USB', 'Generic', 'HDMI USB Capture', 5],
        ['Audio', 'Headphone Audio-Technica ATH-M20x', 'Audio-Technica', 'ATH-M20x', 4], ['Kabel dan Konektor', 'Kabel HDMI 3 Meter', 'Generic', 'HDMI 3M', 10],
        ['Baterai dan Daya', 'Baterai Kamera Sony NP-FW50', 'Sony', 'NP-FW50', 8], ['Tripod dan Stabilizer', 'Gimbal DJI Ronin-SC', 'DJI', 'Ronin-SC', 2],
    ];
    $groups = app(EquipmentQuantity::class);
    $roots = [];
    foreach ($catalog as [$category,$name,$brand,$model,$qty]) {
        $aliases = match ($category) {
            'Kamera' => ['Kamera dan Fotografi'],'Audio' => ['Audio dan Mikrofon'],'Tripod dan Stabilizer' => ['Tripod & Stabilizer'],default => []
        };
        $cat = InventoryCategory::whereIn('name', [$category, ...$aliases])->where('is_active', true)->orderBy('id')->first() ?? InventoryCategory::forceCreate(['name' => $category, 'is_active' => true]);
        $unit = InventoryUnit::forceCreate(['inventory_code' => (string) Str::ulid(), 'category_id' => $cat->id, 'name' => $name, 'brand' => $brand, 'model' => $model, 'storage_location' => 'Studio produksi', 'condition' => 'good', 'is_active' => true, 'version' => 1, 'notes' => 'Ilustrasi untuk demonstrasi inventaris.']);
        InventoryCode::issue($unit);
        $event = EquipmentAuditEvent::forceCreate(['unit_id' => $unit->id, 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'event_type' => 'created', 'reason' => 'Pencatatan barang studio']);
        DB::table('inventory_unit_photos')->insert(illustration($name, 'Kartu identifikasi alat') + ['unit_id' => $unit->id, 'revision' => 1, 'audit_event_id' => $event->id]);
        $groups->adjust($actor, $unit->id, 1, $qty - 1, 'Pencatatan stok awal', true, initial: true);
        $roots[] = $unit->fresh();
    }
    $groups->adjust($actor, $roots[8]->id, $roots[8]->version, 1, 'Cadangan disimpan terpisah', false);
    $today = now('Asia/Jakarta')->toImmutable()->startOfDay();
    $plans = [
        ['Dokumentasi seminar kampus', [0 => 2, 2 => 1], 2, false],
        ['Rekaman wawancara narasumber', [3 => 2, 6 => 1], 3, 'partial'],
        ['Produksi video profil program studi', [1 => 1], 3, 'damage'],
        ['Liputan kegiatan organisasi mahasiswa', [9 => 2], -1, false],
        ['Pengambilan gambar kegiatan UKM', [4 => 1], 1, 'full'],
    ];
    foreach ($plans as $i => [$purpose,$wanted,$days,$ret]) {
        $items = [];
        foreach ($wanted as $idx => $qty) {
            $selection = $groups->available($roots[$idx]->id)->limit($qty)->get();
            checkDemo($selection->count() === $qty, 'SHORTAGE');
            foreach ($selection as $unit) {
                $items[] = ['unit_id' => $unit->id, 'version' => $unit->version];
            }
        }
        $loan = app(EquipmentLoanCheckout::class)->checkoutInTransaction($actor, (string) Str::uuid(), ['borrower_name' => ['Laras Mentari', 'Bagas Pramana', 'Nadia Kirana', 'Raka Mahesa', 'Ayu Lestari'][$i], 'student_number' => 'KAMPUS-'.(101 + $i), 'study_program' => 'Ilmu Komunikasi', 'whatsapp_number' => '120255501'.(10 + $i), 'purpose' => $purpose, 'notes' => 'Simulasi kegiatan studio.', 'borrowed_at' => $today->subDays(5)->toDateString(), 'due_at' => $today->addDays($days)->toDateString(), 'items' => $items]);
        if ($ret) {
            $item = $loan->items()->firstOrFail();
            app(EquipmentLoanReturn::class)->recordInTransaction($actor, (string) Str::uuid(), ['loan_id' => $loan->id, 'expected_version' => $loan->version, 'items' => [['item_id' => $item->id, 'returned_at' => $today->subDay()->setTime(14, 30)->format('Y-m-d\TH:i'), 'condition_in' => $ret === 'damage' ? 'minor_damage' : 'good', 'return_notes' => $ret === 'damage' ? 'Pengunci penutup longgar saat pemeriksaan.' : 'Perlengkapan diterima lengkap.']]], [$item->id => illustration($item->unit_snapshot['name'], 'SIMULASI RETUR - bukan foto serah terima')]);
        }
    }
    $stock = app(EquipmentStock::class);
    $unitIds = collect($roots)->flatMap(fn ($root) => $groups->members($root->id)->pluck('inventory_units.id'));
    $counts = $stock->counts($stock->units()->whereIn('inventory_units.id', $unitIds));
    foreach (['total' => 51, 'available' => 42, 'borrowed' => 7, 'damaged' => 1, 'inactive' => 1, 'lost' => 0] as $status => $count) {
        checkDemo((int) $counts[$status] === $count, 'STOCK_BALANCE');
    }
    $after = rowMap();
    $owned = [];
    foreach ($before as $table => $old) {
        checkDemo(! array_diff_assoc($old, $after[$table]), 'HISTORICAL_CHANGE');
        $owned[$table] = array_diff_key($after[$table], $old);
    }
    $media = [];
    foreach (['inventory_unit_photos', 'equipment_return_photos'] as $table) {
        foreach (DB::table($table)->whereIn('id', array_keys($owned[$table]))->pluck('storage_key') as $key) {
            $media[] = ['table' => $table, 'storage_key' => $key];
        }
    }
    $json = json_encode(['server' => $identity->uuid, 'owned' => $owned, 'files' => $media, 'products' => array_map(fn ($r) => $r->id, $roots)], JSON_THROW_ON_ERROR);
    checkDemo(file_put_contents($ledger.'.tmp', $json, LOCK_EX) === strlen($json) && rename($ledger.'.tmp', $ledger), 'LEDGER_WRITE');
    $db->commit();
    echo json_encode(array_map('count', $owned)).PHP_EOL;
} catch (Throwable $e) {
    if (isset($db) && $db->transactionLevel()) {
        $db->rollBack();
    }
    foreach ($files as $photo) {
        if (! DB::table('inventory_unit_photos')->where('storage_key', $photo['storage_key'])->exists()) {
            app(EquipmentPhotos::class)->discardUnused('equipment_return_photos', [$photo]);
        }
    }
    foreach (array_slice($e->getTrace(), 0, 6) as $frame) {
        echo basename($frame['file'] ?? '').':'.($frame['line'] ?? 0).' '.$frame['function'].PHP_EOL;
    }
    fwrite(STDERR, 'Stopped: '.get_class($e).' line '.$e->getLine().' '.($e instanceof RuntimeException && preg_match('/^[A-Z_]+$/', $e->getMessage()) ? $e->getMessage() : '').PHP_EOL);
    exit(1);
}
