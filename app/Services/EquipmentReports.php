<?php

namespace App\Services;

use App\Models\EquipmentAuditEvent;
use App\Models\EquipmentLoan;
use App\Models\EquipmentLoanItem;
use App\Models\InventoryCategory;
use App\Models\InventoryUnit;
use App\Models\User;
use App\Support\EquipmentErrors;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EquipmentReports
{
    public const MAX_EXPORT_ROWS = 10000;

    public const CONDITIONS = ['good' => 'Baik', 'minor_damage' => 'Rusak ringan', 'major_damage' => 'Rusak berat'];

    public const LOAN_COLUMNS = [
        'loan_code' => 'Kode transaksi', 'inventory_code' => 'Kode unit saat pinjam', 'name' => 'Nama unit saat pinjam',
        'brand' => 'Merek saat pinjam', 'model' => 'Model saat pinjam', 'serial_number' => 'Nomor seri saat pinjam',
        'category_out' => 'Kategori saat pinjam', 'category_current' => 'Kategori saat ini',
        'condition_current' => 'Kondisi saat ini', 'condition_out' => 'Kondisi keluar', 'condition_in' => 'Kondisi masuk',
        'borrowed_at' => 'Dipinjam WIB', 'initial_due_at' => 'Jatuh tempo awal WIB', 'due_at' => 'Jatuh tempo terkini WIB',
        'due_at_on_return' => 'Jatuh tempo saat kembali WIB', 'returned_at' => 'Kembali WIB', 'lost_at' => 'Kehilangan WIB',
        'loan_status' => 'Status transaksi', 'item_status' => 'Status unit transaksi', 'late' => 'Unit terlambat',
        'extension_count' => 'Perpanjangan transaksi sepanjang histori', 'late_extension_count' => 'Perpanjangan setelah terlambat sepanjang histori',
    ];

    public const INVENTORY_COLUMNS = [
        'inventory_code' => 'Kode unit', 'name' => 'Nama unit', 'brand' => 'Merek', 'model' => 'Model',
        'serial_number' => 'Nomor seri', 'storage_location' => 'Lokasi penyimpanan', 'category_current' => 'Kategori saat ini',
        'condition_current' => 'Kondisi saat ini', 'active' => 'Aktif', 'availability' => 'Ketersediaan', 'loan_count' => 'Jumlah histori peminjaman',
    ];

    public function normalizeFilters(array $input): array
    {
        $defaults = [
            'period' => 'this_month', 'startDate' => '', 'endDate' => '', 'dateBasis' => 'borrowed_at',
            'categoryId' => '', 'condition' => 'all', 'status' => 'all',
        ];
        $data = array_replace($defaults, array_intersect_key($input, $defaults));
        Validator::make($data, [
            'period' => ['required', Rule::in(['today', '7_days', '30_days', 'this_month', 'custom'])],
            'startDate' => ['nullable', 'date_format:Y-m-d'], 'endDate' => ['nullable', 'date_format:Y-m-d'],
            'dateBasis' => ['required', Rule::in(['borrowed_at', 'returned_at'])],
            'categoryId' => ['nullable', 'integer', 'min:1'],
            'condition' => ['required', Rule::in(['all', ...array_keys(self::CONDITIONS)])],
            'status' => ['required', Rule::in(['all', 'active', 'partially_returned', 'returned', 'overdue', 'unresolved_loss'])],
        ])->validate();
        $today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
        if ($data['period'] === 'custom') {
            Validator::make($data, ['startDate' => ['required', 'date_format:Y-m-d'], 'endDate' => ['required', 'date_format:Y-m-d']])->validate();
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $data['startDate'], 'Asia/Jakarta');
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $data['endDate'], 'Asia/Jakarta');
        } else {
            $start = match ($data['period']) {
                'today' => $today,
                '7_days' => $today->subDays(6),
                '30_days' => $today->subDays(29),
                default => $today->startOfMonth(),
            };
            $end = $today;
        }
        if ($start->gt($end) || $end->gt($today) || $start->diffInDays($end) > 365) {
            throw ValidationException::withMessages(['startDate' => 'Rentang harus berurutan, maksimal 366 hari, dan tidak melewati hari ini.']);
        }

        return [
            'period' => 'custom', 'startDate' => $start->toDateString(), 'endDate' => $end->toDateString(),
            'dateBasis' => $data['dateBasis'], 'categoryId' => $data['categoryId'] === '' || $data['categoryId'] === null ? '' : (string) (int) $data['categoryId'],
            'condition' => $data['condition'], 'status' => $data['status'],
        ];
    }

    private function authorize(User $actor, string $ability): User
    {
        $fresh = User::with('role')->useWritePdo()->findOrFail($actor->id);
        Gate::forUser($fresh)->authorize($ability, EquipmentLoan::class);

        return $fresh;
    }

    private function inventoryQuery(array $filters): Builder
    {
        return InventoryUnit::query()
            ->when($filters['categoryId'] !== '', fn ($q) => $q->where('category_id', (int) $filters['categoryId']))
            ->when($filters['condition'] !== 'all', fn ($q) => $q->where('condition', $filters['condition']));
    }

    private function itemsQuery(array $filters, CarbonImmutable $asOf): Builder
    {
        $start = CarbonImmutable::parse($filters['startDate'], 'Asia/Jakarta')->startOfDay()->utc()->format('Y-m-d H:i:s.u');
        $end = CarbonImmutable::parse($filters['endDate'], 'Asia/Jakarta')->addDay()->startOfDay()->utc()->format('Y-m-d H:i:s.u');
        $query = EquipmentLoanItem::query()->whereHas('unit', function ($q) use ($filters) {
            $q->when($filters['categoryId'] !== '', fn ($q) => $q->where('category_id', (int) $filters['categoryId']))
                ->when($filters['condition'] !== 'all', fn ($q) => $q->where('condition', $filters['condition']));
        });
        if ($filters['dateBasis'] === 'returned_at') {
            // Half-open UTC bounds preserve the entire selected WIB end date.
            $query->whereNotNull('returned_at')->where('returned_at', '>=', $start)->where('returned_at', '<', $end);
        } else {
            $query->whereHas('loan', fn ($q) => $q->where('borrowed_at', '>=', $start)->where('borrowed_at', '<', $end));
        }
        $query->whereHas('loan', function ($q) use ($filters, $asOf) {
            switch ($filters['status']) {
                case 'active':
                    $q->whereHas('items')->whereDoesntHave('items', fn ($q) => $q->whereNotNull('returned_at'));
                    break;
                case 'partially_returned':
                    $q->whereHas('items', fn ($q) => $q->whereNull('returned_at'))->whereHas('items', fn ($q) => $q->whereNotNull('returned_at'));
                    break;
                case 'returned':
                    $q->whereHas('items')->whereDoesntHave('items', fn ($q) => $q->whereNull('returned_at'));
                    break;
                case 'overdue':
                    $q->where('due_at', '<', $asOf->format('Y-m-d H:i:s.u'))->whereHas('items', fn ($q) => $q->whereNull('returned_at'));
                    break;
                case 'unresolved_loss':
                    $q->whereHas('items', fn ($q) => $q->whereNull('returned_at')->whereNotNull('lost_at'));
                    break;
            }
        });

        return $query;
    }

    private function loadItems(Builder $query): Builder
    {
        // Do not even select borrower identifiers, contact details or free-text notes.
        return $query->select(['equipment_loan_items.id', 'loan_id', 'inventory_unit_id', 'unit_snapshot', 'condition_out', 'returned_at', 'condition_in', 'due_at_on_return', 'lost_at'])
            ->with([
                'loan' => fn ($q) => $q->select(['id', 'loan_code', 'borrowed_at', 'initial_due_at', 'due_at'])
                    ->withCount([
                        'items', 'items as open_items_count' => fn ($q) => $q->whereNull('returned_at'),
                        'auditEvents as extension_count' => fn ($q) => $q->where('event_type', 'extended'),
                        'auditEvents as late_extension_count' => fn ($q) => $q->where('event_type', 'extended')->where('after_data->was_overdue', true),
                    ]),
                'unit' => fn ($q) => $q->select(['id', 'category_id', 'condition'])->with('category:id,name'),
            ]);
    }

    private function loadInventory(Builder $query): Builder
    {
        return $query->select(['inventory_units.id', 'category_id', 'inventory_code', 'name', 'brand', 'model', 'serial_number', 'storage_location', 'condition', 'is_active'])
            ->with('category:id,name')->withCount('loanItems')
            ->withExists([
                'loanItems as is_borrowed' => fn ($q) => $q->whereNull('returned_at'),
                'loanItems as unresolved_loss' => fn ($q) => $q->whereNull('returned_at')->whereNotNull('lost_at'),
            ]);
    }

    public function page(User $actor, array $input, int $perPage): array
    {
        return EquipmentErrors::run(fn () => $this->performPage($actor, $input, $perPage));
    }

    private function performPage(User $actor, array $input, int $perPage): array
    {
        $this->authorize($actor, 'viewReport');
        $filters = $this->normalizeFilters($input);
        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;
        $asOf = CarbonImmutable::now('UTC');
        $items = $this->itemsQuery($filters, $asOf);
        $inventory = $this->inventoryQuery($filters);
        $summary = [
            'units' => (clone $items)->distinct()->count('inventory_unit_id'),
            'loans' => (clone $items)->distinct()->count('loan_id'),
            'rows' => (clone $items)->count(),
            'returns' => (clone $items)->whereNotNull('returned_at')->count(),
            'late' => (clone $items)->where(function ($q) use ($asOf) {
                $q->where(fn ($q) => $q->whereNotNull('returned_at')->whereColumn('returned_at', '>', 'due_at_on_return'))
                    ->orWhere(fn ($q) => $q->whereNull('returned_at')->whereHas('loan', fn ($q) => $q->where('due_at', '<', $asOf->format('Y-m-d H:i:s.u'))));
            })->count(),
            'losses' => (clone $items)->whereNotNull('lost_at')->count(),
            'unresolved_losses' => (clone $items)->whereNull('returned_at')->whereNotNull('lost_at')->count(),
            // Count each extension once even when a loan contributes many rows.
            'extensions' => EquipmentAuditEvent::where('event_type', 'extended')->whereIn('loan_id', (clone $items)->select('equipment_loan_items.loan_id'))->count(),
            'late_extensions' => EquipmentAuditEvent::where('event_type', 'extended')->where('after_data->was_overdue', true)
                ->whereIn('loan_id', (clone $items)->select('equipment_loan_items.loan_id'))->count(),
        ];
        $stock = [
            'total' => (clone $inventory)->count(),
            'available' => (clone $inventory)->where('is_active', true)->where('condition', 'good')->whereDoesntHave('loanItems', fn ($q) => $q->whereNull('returned_at'))->count(),
            'borrowed' => (clone $inventory)->whereHas('loanItems', fn ($q) => $q->whereNull('returned_at'))->count(),
            'damaged' => (clone $inventory)->where('condition', '!=', 'good')->count(),
        ];
        $loanRows = $this->loadItems(clone $items)->orderByDesc('equipment_loan_items.id')->paginate($perPage, ['*'], 'loansPage')
            ->through(fn ($item) => $this->loanRow($item, $asOf));
        $inventoryRows = $this->loadInventory(clone $inventory)->orderBy('inventory_code')->orderBy('inventory_units.id')->paginate($perPage, ['*'], 'inventoryPage')
            ->through(fn ($unit) => $this->inventoryRow($unit));

        return [
            'filters' => $filters, 'asOf' => $asOf, 'summary' => $summary, 'stock' => $stock,
            'loanRows' => $loanRows, 'inventoryRows' => $inventoryRows,
            'categories' => InventoryCategory::orderBy('name')->get(['id', 'name']),
        ];
    }

    private function date(?CarbonImmutable $value, string $format = 'd/m/Y H:i:s'): string
    {
        return $value?->timezone('Asia/Jakarta')->format($format) ?? '-';
    }

    public function loanRow(EquipmentLoanItem $item, CarbonImmutable $asOf): array
    {
        $loan = $item->loan;
        $snapshot = $item->unit_snapshot;
        $late = $item->returned_at !== null
            ? ($item->due_at_on_return !== null && $item->returned_at->gt($item->due_at_on_return))
            : $loan->due_at->lt($asOf);

        return [
            'loan_code' => $loan->loan_code, 'inventory_code' => $snapshot['inventory_code'] ?? '-', 'name' => $snapshot['name'] ?? '-',
            'brand' => $snapshot['brand'] ?? '-', 'model' => $snapshot['model'] ?? '-', 'serial_number' => $snapshot['serial_number'] ?? '-',
            'category_out' => $snapshot['category'] ?? '-', 'category_current' => $item->unit->category?->name ?? '-',
            'condition_current' => self::CONDITIONS[$item->unit->condition] ?? '-',
            'condition_out' => self::CONDITIONS[$item->condition_out] ?? '-', 'condition_in' => self::CONDITIONS[$item->condition_in] ?? '-',
            'borrowed_at' => $this->date($loan->borrowed_at, 'd/m/Y'), 'initial_due_at' => $this->date($loan->initial_due_at, 'd/m/Y'), 'due_at' => $this->date($loan->due_at, 'd/m/Y'),
            'due_at_on_return' => $this->date($item->due_at_on_return, 'd/m/Y'), 'returned_at' => $this->date($item->returned_at), 'lost_at' => $this->date($item->lost_at),
            'loan_status' => (int) $loan->open_items_count === 0 ? 'Selesai' : ((int) $loan->open_items_count < (int) $loan->items_count ? 'Kembali sebagian' : 'Aktif'),
            'item_status' => $item->returned_at !== null ? ($item->lost_at !== null ? 'Ditemukan dan kembali' : 'Kembali') : ($item->lost_at !== null ? 'Hilang belum kembali' : 'Belum kembali'),
            'late' => $late ? 'Ya' : 'Tidak', 'extension_count' => (int) $loan->extension_count, 'late_extension_count' => (int) $loan->late_extension_count,
        ];
    }

    public function inventoryRow(InventoryUnit $unit): array
    {
        $availability = $unit->unresolved_loss ? 'Hilang belum kembali' : ($unit->is_borrowed ? 'Belum kembali' : (! $unit->is_active ? 'Nonaktif' : ($unit->condition !== 'good' ? 'Tidak layak dipinjam' : 'Tersedia')));

        return [
            'inventory_code' => $unit->inventory_code, 'name' => $unit->name, 'brand' => $unit->brand ?? '-', 'model' => $unit->model ?? '-',
            'serial_number' => $unit->serial_number ?? '-', 'storage_location' => $unit->storage_location,
            'category_current' => $unit->category?->name ?? '-', 'condition_current' => self::CONDITIONS[$unit->condition] ?? '-',
            'active' => $unit->is_active ? 'Ya' : 'Tidak', 'availability' => $availability, 'loan_count' => (int) $unit->loan_items_count,
        ];
    }

    public static function csvCell(string|int|float|null $value): string
    {
        $text = (string) $value;
        // Include whitespace, BOM and invisible/control characters before a
        // formula marker; quote the cell, not just the CSV delimiter enclosure.
        $dangerous = preg_match('/\A(?:[\p{Z}\p{C}\s]*[=+@-]|[\p{C}])/u', $text);
        // Invalid UTF-8 must also fail closed rather than bypass the check.
        if ($dangerous !== 0) {
            return "'".$text;
        }

        return $text;
    }

    /** @param resource $stream */
    private function writeCsv($stream, array $row): void
    {
        if (fputcsv($stream, array_map(self::csvCell(...), $row), ',', '"', '', "\r\n") === false) {
            throw new RuntimeException('CSV generation failed.');
        }
    }

    public function download(User $actor, array $input, string $type): StreamedResponse
    {
        return EquipmentErrors::run(fn () => $this->performDownload($actor, $input, $type));
    }

    private function performDownload(User $actor, array $input, string $type): StreamedResponse
    {
        $actor = $this->authorize($actor, 'exportReport');
        Validator::make(['type' => $type], ['type' => ['required', Rule::in(['loans', 'inventory'])]])->validate();
        $filters = $this->normalizeFilters($input);
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Report export requires its own transaction.');
        }
        // Generate into a private bounded-memory spool before sending any bytes.
        // A query/write/audit failure must not produce an unaudited download.
        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Cannot prepare CSV.');
        }
        $asOf = CarbonImmutable::now('UTC');
        try {
            DB::transaction(function () use ($actor, $filters, $type, $stream, $asOf): void {
                $actor = $this->authorize($actor, 'exportReport');
                // A retried audit deadlock must regenerate, not append the CSV.
                if (! ftruncate($stream, 0) || ! rewind($stream) || fwrite($stream, "\xEF\xBB\xBF") !== 3) {
                    throw new RuntimeException('Cannot initialize CSV.');
                }
                $effectiveFilters = $type === 'loans' ? $filters : array_intersect_key($filters, array_flip(['categoryId', 'condition']));
                $this->writeCsv($stream, ['Laporan', $type === 'loans' ? 'Peminjaman per unit' : 'Inventaris terkini']);
                $this->writeCsv($stream, ['Zona waktu', 'Asia/Jakarta', 'Data per', $this->date($asOf)]);
                if ($type === 'loans') {
                    $this->writeCsv($stream, ['Dasar tanggal', $filters['dateBasis'], 'Mulai WIB', $filters['startDate'], 'Sampai WIB', $filters['endDate'], 'Status transaksi', $filters['status']]);
                } else {
                    $this->writeCsv($stream, ['Cakupan', 'Inventaris terkini; tanpa filter tanggal atau status transaksi']);
                }
                $this->writeCsv($stream, ['Kategori saat ini ID', $filters['categoryId'] ?: 'Semua', 'Kondisi saat ini', $filters['condition']]);
                $this->writeCsv($stream, ['Privasi', 'Tanpa identitas peminjam, kontak, atau catatan bebas']);
                $this->writeCsv($stream, []);
                $columns = $type === 'loans' ? self::LOAN_COLUMNS : self::INVENTORY_COLUMNS;
                $this->writeCsv($stream, array_values($columns));
                $query = $type === 'loans' ? $this->loadItems($this->itemsQuery($filters, $asOf)) : $this->loadInventory($this->inventoryQuery($filters));
                $idColumn = $type === 'loans' ? 'equipment_loan_items.id' : 'inventory_units.id';
                $rows = 0;
                foreach ($query->lazyById(200, $idColumn, 'id') as $record) {
                    if ($rows >= self::MAX_EXPORT_ROWS) {
                        throw ValidationException::withMessages(['export' => $type === 'loans'
                            ? 'Ekspor dibatasi 10.000 baris. Persempit filter atau periode.'
                            : 'Ekspor dibatasi 10.000 baris. Persempit kategori atau kondisi unit.']);
                    }
                    $row = $type === 'loans' ? $this->loanRow($record, $asOf) : $this->inventoryRow($record);
                    $this->writeCsv($stream, array_values($row));
                    $rows++;
                }
                $audit = new EquipmentAuditEvent;
                $audit->event_type = 'report_exported';
                $audit->actor_id = $actor->id;
                $audit->actor_name = $actor->name;
                $audit->reason = 'Pembuatan CSV laporan internal';
                $audit->after_data = [
                    'report_type' => $type, 'filters' => $effectiveFilters, 'row_count' => $rows,
                    'data_as_of' => $asOf->format('Y-m-d H:i:s.u'), 'timezone' => 'Asia/Jakarta',
                    'status' => 'generated', 'pii_included' => false,
                ];
                $audit->created_at = CarbonImmutable::now('UTC');
                $audit->save(); // No target FK/token is required for report_exported.
            }, 3);
            if (! rewind($stream)) {
                throw new RuntimeException('Cannot read prepared CSV.');
            }

            return response()->streamDownload(function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            }, 'wpitv-'.$type.'-'.$asOf->timezone('Asia/Jakarta')->format('Ymd-His').'.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache', 'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (Throwable $exception) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw EquipmentErrors::sanitize($exception);
        }
    }
}
