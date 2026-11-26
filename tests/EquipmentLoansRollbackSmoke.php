<?php

// Explicit opt-in only. No migrations/DDL; only new synthetic rows inside an outer rollback.
require dirname(__DIR__).'/vendor/autoload.php';

use App\Http\Controllers\EquipmentReturnPhotoController;
use App\Http\Controllers\EquipmentReportExportController;
use App\Livewire\Admin\EquipmentReports\Index as ReportPage;
use App\Services\Equipment\EquipmentReports;
use Illuminate\Http\Request;
use App\Livewire\Admin\EquipmentLoans\Index;
use App\Livewire\Admin\EquipmentLoans\Show;
use App\Models\Role;
use App\Models\User;
use App\Services\Equipment\InventoryService;
use App\Services\Equipment\LoanService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

if (getenv('WPITV_LOANS_ROLLBACK_SMOKE') !== 'true') {
    throw new RuntimeException('Explicit WPITV_LOANS_ROLLBACK_SMOKE=true required before bootstrap.');
}
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, get_class($e).': '.$e->getMessage().PHP_EOL);
    exit(1);
});
if (! $app->environment('local') || config('database.connections.mysql.database') !== 'wpitv') {
    throw new RuntimeException('Smoke pinned to the inspected local wpitv schema; no fallback target.');
}
$actor = User::with('role')->where('status', 1)->whereHas('role', fn ($q) => $q->where('name', User::ROLE_SUPER_ADMIN))->firstOrFail();
$tables = ['users', 'equipment_categories', 'equipment_models', 'equipment_units', 'equipment_loans', 'equipment_loan_units', 'equipment_returns', 'equipment_return_photos', 'equipment_operation_keys', 'equipment_audit_log'];
$counts = [];
foreach ($tables as $table) {
    $counts[$table] = DB::table($table)->count();
}
$checks = 0;
$check = function (bool $ok, string $label) use (&$checks) {
    if (! $ok) {
        throw new RuntimeException('FAILED: '.$label);
    } echo 'PASS '.$label.PHP_EOL;
    $checks++;
};
$reject = function (callable $callback, string $label) use ($check) {
    try {
        $callback();
    } catch (ValidationException|AuthorizationException $e) {
        $check(true, $label);

        return;
    }
    throw new RuntimeException('Expected rejection: '.$label);
};
$token = fn () => (string) Str::uuid();
$code = 'LOANSMOKE-'.strtoupper(Str::random(8));
$oldDisk = config('filesystems.disks.local');
$oldUploadCleanup = config('livewire.temporary_file_upload.cleanup');
config(['livewire.temporary_file_upload.cleanup' => false]); // The simulated future clock must not expire newly-created test uploads.
$root = storage_path('app/testing/loans-smoke-'.Str::uuid());
config(['filesystems.disks.local.root' => $root]);
Storage::forgetDisk('local');
$loans = app(LoanService::class);
$inventory = app(InventoryService::class);
Carbon::setTestNow(Carbon::parse('2026-11-24 08:00:00', 'UTC'));
DB::beginTransaction();
try {
    $category = $inventory->execute($actor, 'category.create', $token(), ['code' => $code, 'name' => 'Synthetic Original Category', 'description' => '', 'is_active' => true]);
    $model = $inventory->execute($actor, 'model.create', $token(), ['code' => $code, 'name' => 'Synthetic Loan Camera', 'description' => '', 'category_id' => $category, 'is_active' => true, 'quantity' => 8, 'condition' => 'good']);
    $units = DB::table('equipment_units')->where('model_id', $model)->orderBy('id')->pluck('id')->all();
    $base = ['borrower_type' => 'student', 'borrower_name' => 'Synthetic Borrower', 'identity_number' => $code, 'borrower_contact' => '000000000', 'borrower_affiliation' => 'Synthetic unit', 'purpose' => 'Rollback verification', 'due_date' => '2026-11-24'];
    $checkoutToken = $token();
    $payload = $base + ['units' => [$units[0], $units[1]]];
    $loan = $loans->execute($actor, 'loan.checkout', $checkoutToken, $payload);
    $detail = $loans->detail($loan);
    $lu = $detail['loanUnits']->pluck('id')->all();
    $check($detail['loan']->progress === 'active' && $detail['loan']->total_units === 2, 'multi-unit checkout and derived active');
    $check(DB::table('equipment_loan_units')->where('loan_id', $loan)->whereNotNull('open_unit_id')->count() === 2, 'generated active allocation populated');
    $check($loans->execute($actor, 'loan.checkout', $checkoutToken, $payload) === $loan, 'checkout idempotent replay');
    $reject(fn () => $loans->execute($actor, 'loan.checkout', $checkoutToken, array_replace($payload, ['purpose' => 'Changed'])), 'token payload conflict rejected');
    $reject(fn () => $loans->execute($actor, 'loan.checkout', $token(), $base + ['units' => [$units[0]]]), 'double checkout denied');
    $loanB = $loans->execute($actor, 'loan.checkout', $token(), $base + ['units' => [$units[2]]]);
    try {
        DB::table('equipment_loan_units')->insert(['loan_id' => $loanB, 'unit_id' => $units[0], 'category_id_at_checkout' => $category, 'model_id_at_checkout' => $model, 'condition_out' => 'good']);
        throw new RuntimeException('Expected generated uniqueness rejection.');
    } catch (QueryException $e) {
        $check(($e->errorInfo[1] ?? null) === 1062 && str_contains($e->getMessage(), 'uq_eq_loan_units_open'), 'database generated UNIQUE blocks cross-loan allocation');
    }
    $inventory->execute($actor, 'category.update', $token(), ['id' => $category, 'version' => 1, 'name' => 'Synthetic renamed category', 'description' => '', 'is_active' => true, 'reason' => 'Synthetic rename']);
    $check($loans->detail($loan)['loanUnits'][0]->snapshot['category_name'] === 'Synthetic Original Category', 'checkout labels remain historical after master rename');
    $photo = UploadedFile::fake()->image('evidence.png', 64, 64);
    $returnPayload = ['loan_id' => $loan, 'version' => 1, 'items' => [['loan_unit_id' => $lu[0], 'condition' => 'good', 'notes' => '']], 'notes' => 'Synthetic return'];
    $reject(fn () => $loans->execute($actor, 'return.create', $token(), $returnPayload), 'return without per-unit photo denied');
    $returnToken = $token();
    $receipt = $loans->execute($actor, 'return.create', $returnToken, $returnPayload, [$lu[0] => [$photo]]);
    $check($loans->detail($loan)['loan']->progress === 'partially_resolved', 'partial return retains other allocation');
    $returned = DB::table('equipment_loan_units')->where('id', $lu[0])->first();
    $frozen = (array) $returned;
    $check($returned->outcome === 'returned' && $returned->resolution_at !== null && $returned->due_date_at_resolution === '2026-11-24' && ! $returned->is_late, 'actual returned tuple and on-time frozen snapshot');
    $check(DB::table('equipment_loan_units')->where('id', $lu[0])->value('open_unit_id') === null, 'return releases availability automatically');
    $check($loans->execute($actor, 'return.create', $returnToken, $returnPayload, [$lu[0] => [$photo]]) === $receipt, 'return replay preserves receipt and evidence');
    $check(DB::table('equipment_return_photos')->where('return_id', $receipt)->count() === 1, 'return replay creates no duplicate evidence');
    $photoA = DB::table('equipment_return_photos')->where('return_id', $receipt)->first();
    $check(Storage::disk('local')->exists($photoA->storage_path), 'evidence stored privately');
    $reject(fn () => $loans->execute($actor, 'loan.void', $token(), ['loan_id' => $loan, 'version' => 2, 'reason' => 'Synthetic', 'not_handed_over' => true]), 'void after partial return denied');
    $loans->execute($actor, 'loan.extend', $token(), ['loan_id' => $loan, 'version' => 2, 'due_date' => '2026-11-26', 'reason' => 'Synthetic extension']);
    $check((array) DB::table('equipment_loan_units')->where('id', $lu[0])->first() === $frozen, 'extension leaves returned outcome/history immutable');
    $check($loans->detail($loan)['originalDue'] === '2026-11-24', 'original deadline retained in checkout audit');
    $reject(fn () => $loans->execute($actor, 'loan.extend', $token(), ['loan_id' => $loan, 'version' => 3, 'due_date' => '2026-11-25', 'reason' => 'Synthetic invalid']), 'backwards extension denied');
    $reject(fn () => $loans->execute($actor, 'loan.extend', $token(), ['loan_id' => $loan, 'version' => 2, 'due_date' => '2026-11-28', 'reason' => 'Synthetic stale']), 'stale loan write denied');
    $reject(fn () => $loans->execute($actor, 'loan.void', $token(), ['loan_id' => $loanB, 'version' => 1, 'reason' => 'Synthetic', 'not_handed_over' => false]), 'void without non-handover attestation denied');
    $production = User::create(['role_id' => Role::where('name', User::ROLE_PRODUCTION)->value('id'), 'username' => strtolower($code).'-production', 'name' => 'Synthetic Production', 'password' => Hash::make(Str::random(40)), 'status' => true]);
    $reject(fn () => $loans->execute($production, 'loan.void', $token(), ['loan_id' => $loanB, 'version' => 1, 'reason' => 'Synthetic forbidden', 'not_handed_over' => true]), 'Production has no void permission');
    $news = User::create(['role_id' => Role::where('name', User::ROLE_NEWS)->value('id'), 'username' => strtolower($code).'-news', 'name' => 'Synthetic News', 'password' => Hash::make(Str::random(40)), 'status' => true]);
    $reject(fn () => $loans->execute($news, 'loan.checkout', $token(), $base + ['units' => [$units[7]]]), 'unauthorized checkout denied');
    $loans->execute($actor, 'loan.void', $token(), ['loan_id' => $loanB, 'version' => 1, 'reason' => 'Synthetic wrong input', 'not_handed_over' => true]);
    $check($loans->detail($loanB)['loan']->progress === 'voided', 'void all-or-nothing preserves transaction');
    Carbon::setTestNow(Carbon::parse('2026-11-27 00:00:00', 'UTC'));
    $check((bool) $loans->detail($loan)['loan']->overdue, 'overdue derived by WIB date');
    $loans->execute($actor, 'loan.loss', $token(), ['loan_id' => $loan, 'version' => 3, 'reason' => 'Synthetic missing item', 'items' => [['loan_unit_id' => $lu[1], 'notes' => '']]]);
    $lost = (array) DB::table('equipment_loan_units')->where('id', $lu[1])->first();
    $check($lost['outcome'] === 'lost' && $lost['is_late'] === 1 && $lost['due_date_at_resolution'] === '2026-11-26', 'loan loss records late resolution atomically');
    $check($loans->detail($loan)['loan']->progress === 'closed' && ! $loans->detail($loan)['loan']->overdue, 'all resolved progress closed without active overdue');
    $check(DB::table('equipment_units')->where('id', $units[1])->value('operational_status') === 'lost', 'Inventory reflects loan loss');
    $inventory->execute($actor, 'unit.found', $token(), ['id' => $units[1], 'version' => DB::table('equipment_units')->where('id', $units[1])->value('version'), 'reason' => 'Synthetic found']);
    $check((array) DB::table('equipment_loan_units')->where('id', $lu[1])->first() === $lost, 'Inventory recovery preserves historical loan loss');
    $check($loans->execute($actor, 'loan.checkout', $checkoutToken, $payload) === $loan, 'checkout replay remains valid after due date/lifecycle changes');
    $later = array_replace($base, ['due_date' => '2026-11-27']);
    $loanC = $loans->execute($actor, 'loan.checkout', $token(), $later + ['units' => [$units[3], $units[4]]]);
    $luC = $loans->detail($loanC)['loanUnits']->pluck('id')->all();
    $badReturn = ['loan_id' => $loanC, 'version' => 1, 'items' => [['loan_unit_id' => $luC[0], 'condition' => 'major_damage', 'notes' => '']]];
    $reject(fn () => $loans->execute($actor, 'return.create', $token(), $badReturn, [$luC[0] => [$photo]]), 'damaged return requires per-unit notes');
    $component = Livewire::actingAs($actor)->test(Show::class, ['loanId' => $loanC])->assertSee('Synthetic Borrower')->call('begin', 'return.create');
    $component->set('selected', [(string) $luC[0]])->call('save')
        ->assertHasErrors('photos.'.$luC[0])->assertSee('Unggah minimal satu foto untuk unit ini.')
        ->assertSet('operation', 'return.create');
    $component->set('items.'.$luC[0].'.condition', 'major_damage')->set('items.'.$luC[0].'.notes', 'Synthetic damage')
        ->set('photos.'.$luC[0], [UploadedFile::fake()->image('ui-evidence.png', 64, 64)])->call('save')->assertHasNoErrors()->assertSee('Selesai sebagian');
    $check(DB::table('equipment_units')->where('id', $units[3])->value('physical_condition') === 'major_damage', 'Livewire partial return updates Inventory condition');
    $reject(fn () => $loans->execute($actor, 'loan.checkout', $token(), $later + ['units' => [$units[3]]]), 'damaged returned unit not loanable');
    $mismatch = ['loan_id' => $loanC, 'version' => 2, 'items' => [['loan_unit_id' => $lu[0], 'condition' => 'good', 'notes' => '']]];
    $reject(fn () => $loans->execute($actor, 'return.create', $token(), $mismatch, [$lu[0] => [$photo]]), 'cross-loan resolution rejected');
    Auth::setUser($actor);
    $check(app(EquipmentReturnPhotoController::class)->show($loan, $photoA->id)->getStatusCode() === 200, 'authorized scoped private evidence download');
    try {
        app(EquipmentReturnPhotoController::class)->show($loanC, $photoA->id);
        throw new RuntimeException('Expected photo scope denial');
    } catch (HttpExceptionInterface $e) {
        $check($e->getStatusCode() === 404, 'wrong-loan photo access denied');
    }
    Auth::setUser($news);
    $reject(fn () => app(EquipmentReturnPhotoController::class)->show($loan, $photoA->id), 'photo access requires loan and file permissions');
    Livewire::actingAs($news)->test(Index::class)->assertForbidden();
    $check(true, 'Livewire list denies unauthorized role');
    Livewire::actingAs($actor)->test(Index::class)->set('search', $code)->assertSee($loans->detail($loanC)['loan']->loan_number)->set('search', 'NO-MATCH-'.$code)->assertSee('Tidak ada peminjaman yang cocok')->call('resetFilters')->assertSet('search', '');
    Livewire::actingAs($actor)->test(Index::class)->set('search', 'Synthetic Borrower')->assertSee($loans->detail($loanC)['loan']->loan_number)->set('status', 'partially_resolved')->assertSee('Selesai sebagian');
    $check(true, 'Livewire listing search and progress filter render');
    $component = Livewire::actingAs($actor)->test(Show::class, ['loanId' => $loanC])->call('begin', 'loan.loss')->set('selected', [(string) $luC[1]])->set('reason', 'Synthetic final loss')->call('save')->assertHasNoErrors()->assertSee('Selesai');
    $check($loans->detail($loanC)['loan']->progress === 'closed', 'Livewire loss completes remaining unit');
    $loanD = $loans->execute($actor, 'loan.checkout', $token(), $later + ['units' => [$units[5], $units[6]]]);
    $luD = $loans->detail($loanD)['loanUnits']->pluck('id')->all();
    Carbon::setTestNow(Carbon::parse('2026-11-28 00:00:00', 'UTC'));
    $full = ['loan_id' => $loanD, 'version' => 1, 'items' => [
        ['loan_unit_id' => $luD[0], 'condition' => 'good', 'notes' => ''],
        ['loan_unit_id' => $luD[1], 'condition' => 'good', 'notes' => ''],
    ]];
    $fullPhotos = [$luD[0] => [$photo], $luD[1] => [$photo]];
    $reject(fn () => $loans->execute($actor, 'return.create', $token(), $full, $fullPhotos), 'late good-condition return still requires unit notes');
    $reject(fn () => $loans->execute($actor, 'return.create', $token(), $full, [$luD[0] => array_fill(0, 9, $photo), $luD[1] => [$photo]]), 'per-unit photo limit enforced alongside batch limit');
    $full['items'][0]['notes'] = 'Synthetic late receipt one';
    $full['items'][1]['notes'] = 'Synthetic late receipt two';
    $allReceipt = $loans->execute($actor, 'return.create', $token(), $full, $fullPhotos);
    $check($loans->detail($loanD)['loan']->progress === 'closed', 'full return closes all remaining units');
    $check(DB::table('equipment_return_photos')->where('return_id', $allReceipt)->count() === 2, 'full return receipt owns separate evidence per unit');
    $check(DB::table('equipment_loan_units')->where('loan_id', $loanD)->where('is_late', 1)->count() === 2, 'full return freezes WIB late flags');
    $resolved = DB::table('equipment_loan_units')->where('loan_id', $loanD)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    $reject(fn () => $loans->execute($actor, 'return.create', $token(), array_replace($full, ['version' => 2]), $fullPhotos), 'resolved outcomes cannot be returned twice');
    $check(DB::table('equipment_loan_units')->where('loan_id', $loanD)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all() === $resolved, 'failed repeat return preserves resolved history');
    $reports = app(EquipmentReports::class);
    $stock = $reports->stock(['model' => $model]);
    $check((int)$stock->total === 8 && (int)$stock->available === 5 && (int)$stock->maintenance === 1 && (int)$stock->lost === 1 && (int)$stock->borrowed === 0, 'report stock excludes damaged, lost and maintenance');
    $check($reports->inventory(['model'=>$model,'condition'=>'major_damage'])->get()->sum('total') === 1, 'inventory condition and model filters');
    $closed = $reports->loans(['progress'=>'closed','late'=>'resolved_late'])->where('id',$loan)->first();
    $check($closed && (int)$closed->missing === 1 && (int)$closed->returned === 1 && !$closed->overdue, 'historical loss and late resolution survive recovery');
    $check((int)$reports->loans(['progress'=>'voided'])->where('id',$loanB)->first()->issued === 0, 'void counts no issued units');
    $window = ['period'=>'return','from'=>'2026-11-28','until'=>'2026-11-28'];
    $query = $reports->loans($window)->whereIn('id',[$loan,$loanC,$loanD]);
    $summary = $reports->loanCounts($query,true);
    $check((int)$summary->transactions === 1 && (int)$summary->returned === 2 && $query->first()->id === $loanD, 'period uses receipt time and actual returned units');
    $check((int)$query->first()->period_late_returns === 2, 'return period counts only late returns in its own window');
    $onTimeReturnWindow = ['period'=>'return','from'=>'2026-11-24','until'=>'2026-11-24'];
    $check($reports->loans($onTimeReturnWindow)->where('id',$loan)->exists()
        && !$reports->loans($onTimeReturnWindow + ['late'=>'resolved_late'])->where('id',$loan)->exists(), 'later loss does not count as a late return in earlier period');
    $check(!$reports->loans(array_replace($window,['period'=>'checkout']))->where('id',$loanD)->exists(), 'checkout and receipt windows differ');
    // Fixture-only checkout at midnight WIB, still the previous UTC day.
    Carbon::setTestNow(Carbon::parse('2026-11-28 17:00:00','UTC'));
    $boundary = $loans->execute($actor,'loan.checkout',$token(),array_replace($base,['due_date'=>'2026-11-29','units'=>[$units[7]]]));
    $check($reports->loans(['from'=>'2026-11-29','until'=>'2026-11-29'])->where('id',$boundary)->exists()
        && !$reports->loans(['until'=>'2026-11-28'])->where('id',$boundary)->exists(), 'midnight WIB inclusive start and exclusive end');
    $check((int)$reports->stock(['model'=>$model])->borrowed === 1, 'open allocation contributes to KPI');
    $page = Livewire::actingAs($production)->test(ReportPage::class,['section'=>'loans'])->set('draft',$window)->call('apply')->assertHasNoErrors()
        ->assertSee($loans->detail($loanD)['loan']->loan_number)->assertDontSee('Synthetic Borrower')->assertSee('Kembali dalam periode');
    $page->set('draft.until','2026-11-27')->call('apply')->assertHasErrors(['until'])
        ->assertSee('Tanggal akhir tidak boleh sebelum tanggal mulai.')->assertSet('filters', $window)
        ->call('clear')->assertSet('filters', [])->assertHasNoErrors();
    $check(true,'report page validates ranges and excludes borrower identity');
    Livewire::actingAs($actor)->test(ReportPage::class,['section'=>'inventory'])->set('draft',['model'=>$model,'category'=>$category])->call('apply')->assertHasNoErrors()->assertSee($code);
    Livewire::actingAs($actor)->test(ReportPage::class)->assertSee('Laporan Inventaris')->assertDontSee('Aktivitas terbaru');
    Livewire::actingAs($actor)->test(\App\Livewire\Admin\Inventory\Index::class)
        ->assertSee('Ringkasan peralatan')->assertDontSee('Aktivitas terbaru')->assertDontSee('Terlambat atau jatuh tempo dalam 3 hari');
    $check(true,'inventory drill-down and stock summary render');
    $export = app(EquipmentReportExportController::class);
    $capture = function ($response) { ob_start(); $response->sendContent(); return ob_get_clean(); };
    Auth::setUser($production);
    $csv = $capture($export(Request::create('/','GET',$window),'loans',$reports));
    $check(str_contains($csv,'Retur terakhir dalam periode (WIB)') && str_contains($csv,'Retur terlambat dalam periode'), 'CSV matches return-period columns');
    try {
        $export(Request::create('/','GET',['from'=>'2026-11-29','until'=>'2026-11-28']),'loans',$reports);
        throw new RuntimeException('Expected invalid CSV filter rejection');
    } catch (HttpExceptionInterface $exception) {
        $check($exception->getStatusCode() === 422, 'CSV rejects invalid dates without redirect or download');
    }
    $check(str_contains($csv,$loans->detail($loanD)['loan']->loan_number) && !str_contains($csv,$loans->detail($loanC)['loan']->loan_number)
        && !str_contains($csv,'Synthetic Borrower') && !str_contains($csv,$code), 'filtered receipt CSV excludes private fields');
    $inventoryCsv = $capture($export(Request::create('/','GET',['model'=>$model,'condition'=>'major_damage']),'inventory',$reports));
    $check(str_contains($inventoryCsv,'Rusak berat') && !str_contains($inventoryCsv,'Rusak ringan'), 'inventory CSV uses applied filters');
    Livewire::actingAs($news)->test(ReportPage::class)->assertForbidden();
    Auth::setUser($news);
    $reject(fn () => $export(Request::create('/'),'loans',$reports),'report export denies unauthorized role');
    $check(DB::transactionLevel() === 1, 'outer rollback boundary intact');
} finally {
    $fixturePhotos = isset($category) ? DB::table('equipment_return_photos as p')
        ->join('equipment_loan_units as lu','lu.id','=','p.loan_unit_id')
        ->where('lu.category_id_at_checkout',$category)->pluck('p.storage_path') : collect();
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    // Remove only evidence recorded for this fixture category under this run's unique test root.
    $fixtureDirectory = realpath($root.'/equipment/photos');
    foreach ($fixturePhotos as $relativePath) {
        $path = realpath($root.'/'.$relativePath);
        if ($path && $fixtureDirectory && dirname($path) === $fixtureDirectory
            && dirname(dirname($fixtureDirectory)) === realpath($root)
            && dirname(realpath($root)) === realpath(storage_path('app/testing'))) {
            unlink($path);
        }
    }
    config(['filesystems.disks.local' => $oldDisk, 'livewire.temporary_file_upload.cleanup' => $oldUploadCleanup]);
    Storage::forgetDisk('local');
    Carbon::setTestNow();
}
foreach ($counts as $table => $count) {
    $check(DB::table($table)->count() === $count, $table.' unchanged after rollback');
}
echo 'COMPLETE '.$checks.' checks. No synthetic records committed; surrogate AUTO_INCREMENT gaps may remain.'.PHP_EOL;
