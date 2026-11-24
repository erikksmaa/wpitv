<?php

// Explicit manual smoke test against already-provisioned tables. Never migrates or commits.
// All business writes target newly-created synthetic records inside one outer transaction.
require dirname(__DIR__).'/vendor/autoload.php';

use App\Livewire\Admin\Inventory\Index;
use App\Models\User;
use App\Services\Equipment\InventoryService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

if (getenv('WPITV_INVENTORY_ROLLBACK_SMOKE') !== 'true') {
    throw new RuntimeException('Requires explicit WPITV_INVENTORY_ROLLBACK_SMOKE=true. No bootstrap or writes performed.');
}
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, get_class($e).': '.$e->getMessage().PHP_EOL);
    exit(1);
});
if (! $app->environment('local') || config('database.connections.mysql.database') !== 'wpitv') {
    throw new RuntimeException('This manual smoke is pinned to the inspected local wpitv schema.');
}
$actor = User::with('role')->where('status', 1)->whereHas('role', fn ($q) => $q->where('name', User::ROLE_SUPER_ADMIN))->firstOrFail();
$tables = ['equipment_categories', 'equipment_models', 'equipment_units', 'equipment_loans', 'equipment_loan_units', 'equipment_operation_keys', 'equipment_audit_log', 'equipment_model_photos', 'equipment_unit_photos'];
$counts = [];
foreach ($tables as $table) {
    $counts[$table] = DB::table($table)->count();
}
$service = app(InventoryService::class);
$n = 0;
$check = function (bool $ok, string $label) use (&$n) {
    if (! $ok) {
        throw new RuntimeException('FAILED: '.$label);
    }
    echo 'PASS '.$label.PHP_EOL;
    $n++;
};
$reject = function (callable $call, string $label) use ($check) {
    try {
        $call();
    } catch (ValidationException $e) {
        $check(true, $label);

        return;
    }
    throw new RuntimeException('Expected rejection: '.$label);
};
$token = fn () => (string) Str::uuid();
$code = 'SMOKE-'.strtoupper(Str::random(10));
$oldDisk = config('filesystems.disks.local');
$photoRoot = storage_path('app/testing/inventory-smoke-'.Str::uuid());
config(['filesystems.disks.local.root' => $photoRoot]);
Storage::forgetDisk('local');
$photoPaths = [];
DB::beginTransaction();
try {
    $category = $service->execute($actor, 'category.create', $token(), ['code' => $code, 'name' => 'Synthetic Inventory smoke', 'description' => '', 'is_active' => true]);
    $check(DB::table('equipment_categories')->where('id', $category)->value('version') === 1, 'category create');
    $service->execute($actor, 'category.update', $token(), ['id' => $category, 'version' => 1, 'name' => 'Synthetic revised', 'description' => '', 'is_active' => true, 'reason' => 'Synthetic correction']);
    $reject(fn () => $service->execute($actor, 'category.update', $token(), ['id' => $category, 'version' => 1, 'name' => 'Stale', 'description' => '', 'is_active' => true, 'reason' => 'Stale check']), 'stale category denied');
    $modelToken = $token();
    $payload = ['code' => $code, 'name' => 'Synthetic camera', 'category_id' => $category, 'description' => '', 'is_active' => true, 'quantity' => 2, 'condition' => 'good'];
    $model = $service->execute($actor, 'model.create', $modelToken, $payload);
    $check($service->execute($actor, 'model.create', $modelToken, $payload) === $model, 'idempotent model replay');
    $reject(fn () => $service->execute($actor, 'model.create', $modelToken, array_replace($payload, ['quantity' => 3])), 'token payload conflict');
    $check(DB::table('equipment_units')->where('model_id', $model)->count() === 2, 'atomic initial stock');
    $stockToken = $token();
    $stockPayload = ['id' => $model, 'version' => 1, 'quantity' => 2, 'condition' => 'good', 'reason' => 'Synthetic stock'];
    $service->execute($actor, 'stock.add', $stockToken, $stockPayload);
    $service->execute($actor, 'stock.add', $stockToken, $stockPayload);
    $check(DB::table('equipment_models')->where('id', $model)->value('next_unit_no') === 5, 'counter safe; replay creates no extra stock');
    $units = DB::table('equipment_units')->where('model_id', $model)->orderBy('unit_no')->get();
    $check($units->pluck('asset_code')->unique()->count() === 4, 'unique asset codes');
    $unit = $units[0]->id;
    $change = function (string $op, array $extra = []) use ($service, $actor, $token, $unit) {
        return $service->execute($actor, $op, $token(), $extra + ['id' => $unit, 'version' => DB::table('equipment_units')->where('id', $unit)->value('version'), 'reason' => 'Synthetic lifecycle']);
    };
    $change('unit.maintenance_start');
    $change('unit.condition', ['condition' => 'minor_damage', 'serial_number' => 'SYNTHETIC']);
    $reject(fn () => $change('unit.maintenance_finish'), 'damaged unit cannot finish maintenance');
    $change('unit.condition', ['condition' => 'good', 'serial_number' => 'SYNTHETIC']);
    $change('unit.maintenance_finish');
    $change('unit.lost');
    $change('unit.found');
    $check(DB::table('equipment_units')->where('id', $unit)->value('operational_status') === 'maintenance', 'found enters maintenance');
    $change('unit.lost');
    $change('unit.retire');
    $reject(fn () => $change('unit.found'), 'retired is terminal');
    $service->execute($actor, 'stock.add', $token(), ['id' => $model, 'version' => 2, 'quantity' => 1, 'condition' => 'good', 'reason' => 'Synthetic after retire']);
    $check(DB::table('equipment_units')->where('model_id', $model)->max('unit_no') === 5, 'retire does not reuse asset number');
    $loan = DB::table('equipment_loans')->insertGetId(['loan_number' => $code, 'borrower_type' => 'staff', 'borrower_name' => 'Synthetic borrower', 'identity_number' => $code, 'purpose' => 'Rollback smoke', 'borrowed_at' => now(), 'due_date' => now()->addDay()->toDateString(), 'created_by' => $actor->id]);
    DB::table('equipment_loan_units')->insert(['loan_id' => $loan, 'unit_id' => $units[1]->id, 'category_id_at_checkout' => $category, 'model_id_at_checkout' => $model, 'condition_out' => 'good']);
    $reject(fn () => $service->execute($actor, 'unit.retire', $token(), ['id' => $units[1]->id, 'version' => 1, 'reason' => 'Borrowed test']), 'on-loan retire denied');
    $check(! DB::table('equipment_operation_keys')->whereNull('completed_at')->exists(), 'no committed/pending key produced by operations');
    $component = Livewire::actingAs($actor)->test(Index::class)->set('search', $code)->assertSee('Inventory Peralatan')->assertSee('Synthetic camera');
    $component->set('tab', 'categories')->assertSee('Synthetic revised');
    $component->call('begin', 'category.update', $category)
        ->set('form.name', 'Synthetic UI revised')->set('form.reason', 'Synthetic UI edit')
        ->call('save')->assertHasNoErrors()->assertSee('Perubahan Inventory berhasil');
    $check(DB::table('equipment_categories')->where('id', $category)->value('name') === 'Synthetic UI revised', 'Livewire edit persists transactionally');
    $component->set('tab', 'models')->call('begin', 'model.photo_add', $model)
        ->set('photos', [UploadedFile::fake()->image('synthetic.png', 64, 64)])
        ->call('save')->assertHasNoErrors();
    $modelPhoto = DB::table('equipment_model_photos')->where('model_id', $model)->first();
    $photoPaths[] = $modelPhoto->storage_path;
    $check(DB::table('equipment_operation_keys')->where('operation', 'model.photo_add')->where('result_type', 'model_photo')->where('result_id', $modelPhoto->id)->exists(), 'model photo idempotency result references photo');
    $check(Storage::disk('local')->exists($modelPhoto->storage_path), 'Livewire upload stores normalized private model photo');
    $component->call('begin', 'unit.photo_add', $units[2]->id)
        ->set('photos', [UploadedFile::fake()->image('unit.png', 64, 64)])
        ->call('save')->assertHasNoErrors();
    $unitPhoto = DB::table('equipment_unit_photos')->where('unit_id', $units[2]->id)->first();
    $photoPaths[] = $unitPhoto->storage_path;
    $check(DB::table('equipment_operation_keys')->where('operation', 'unit.photo_add')->where('result_type', 'unit_photo')->where('result_id', $unitPhoto->id)->exists(), 'unit photo idempotency result references photo');
    $check(Storage::disk('local')->exists($unitPhoto->storage_path), 'Livewire private unit photo upload');
    $beforePhotoCount = DB::table('equipment_model_photos')->where('model_id', $model)->count();
    $component->call('begin', 'model.photo_add', $model)
        ->set('photos', [UploadedFile::fake()->image('revision.png', 64, 64)])
        ->call('save')->assertHasErrors('form.reason');
    $check(DB::table('equipment_model_photos')->where('model_id', $model)->count() === $beforePhotoCount, 'revision without reason is atomic denial');
    $component->call('cancel');
    $component->call('begin', 'model.photo_add', $model)
        ->set('form.reason', 'Synthetic invalid image')
        ->set('photos', [UploadedFile::fake()->create('fake.jpg', 1, 'image/jpeg')])
        ->call('save')->assertHasErrors('photos');
    $check(DB::table('equipment_model_photos')->where('model_id', $model)->count() === $beforePhotoCount, 'invalid image rejected without photo record');
    $component->call('cancel');

    $component->set('tab', 'units')->assertSee($code.'-001');
    $component->set('tab', 'audit')->set('search', 'Synthetic lifecycle')->assertSee('unit.retired');
    $check(true, 'Livewire renders models/categories/units/audit with filters');
    $check(DB::transactionLevel() === 1, 'outer rollback boundary intact');
} finally {
    if (str_contains($photoRoot, 'inventory-smoke-')) {
        $ownedFiles = Storage::disk('local')->allFiles('equipment/photos');
        Storage::disk('local')->delete($ownedFiles);
    }
    config(['filesystems.disks.local' => $oldDisk]);
    Storage::forgetDisk('local');
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
}
foreach ($counts as $table => $count) {
    $check(DB::table($table)->count() === $count, $table.' count unchanged after rollback');
}
echo 'COMPLETE '.$n.' checks; no fixture records committed. AUTO_INCREMENT gaps may remain.'.PHP_EOL;
