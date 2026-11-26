<?php

namespace App\Services\Equipment;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class EquipmentReports
{
    public const CONDITIONS = ['good' => 'Baik', 'minor_damage' => 'Rusak ringan', 'major_damage' => 'Rusak berat'];
    public const STATUS = ['active' => 'Aktif', 'maintenance' => 'Dalam perawatan', 'lost' => 'Hilang', 'retired' => 'Nonaktif permanen'];
    public const PROGRESS = ['active' => 'Aktif', 'partially_resolved' => 'Selesai sebagian', 'closed' => 'Selesai', 'voided' => 'Dibatalkan', 'integrity_error' => 'Perlu pemeriksaan'];
    public const BORROWERS = ['student' => 'Mahasiswa', 'lecturer' => 'Dosen', 'staff' => 'Staf'];
    public const STOCK = ['total' => 'Total unit', 'available' => 'Tersedia', 'borrowed' => 'Dipinjam', 'maintenance' => 'Dalam perawatan', 'lost' => 'Hilang', 'retired' => 'Nonaktif permanen'];
    public const LOAN_COUNTS = ['transactions' => 'Transaksi', 'issued' => 'Unit dipinjam', 'returned' => 'Unit kembali', 'missing' => 'Unit hilang'];
    public const INVENTORY_COLUMNS = ['category_name' => 'Kategori', 'model_name' => 'Peralatan', 'model_code' => 'Kode peralatan', 'physical_condition' => 'Kondisi', 'operational_status' => 'Status unit'] + self::STOCK;
    public const LOAN_COLUMNS = ['loan_number' => 'Nomor transaksi', 'borrowed_at' => 'Dipinjam (WIB)', 'borrower_type' => 'Jenis peminjam', 'due_date' => 'Jatuh tempo (WIB)', 'progress' => 'Status transaksi', 'issued' => 'Unit dipinjam', 'open_units' => 'Masih dipinjam', 'returned' => 'Unit kembali', 'missing' => 'Unit hilang', 'overdue' => 'Masih terlambat', 'late_units' => 'Unit selesai terlambat'];

    public function filters(string $kind, array $input): array
    {
        $enum = fn (array $labels) => ['nullable', 'string', Rule::in(array_keys($labels))];
        $rules = $kind === 'inventory' ? [
            'category' => ['nullable', 'integer', 'exists:equipment_categories,id'],
            'model' => ['nullable', 'integer', Rule::exists('equipment_models', 'id')->when(filter_var($input['category'] ?? null, FILTER_VALIDATE_INT) !== false, fn ($r) => $r->where('category_id', $input['category']))],
            'condition' => $enum(self::CONDITIONS), 'status' => $enum(self::STATUS),
            'search' => ['nullable', 'string', 'max:100'],
        ] : [
            'period' => ['nullable', 'string', Rule::in(['checkout', 'return'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => array_filter(['nullable', 'date_format:Y-m-d', !empty($input['from']) ? 'after_or_equal:from' : null]),
            'borrower' => $enum(self::BORROWERS), 'progress' => $enum(self::PROGRESS),
            'late' => ['nullable', 'string', Rule::in(['overdue', 'resolved_late', 'none'])],
        ];
        return array_filter(EquipmentValidation::validate($input, $rules), fn ($value) => $value !== null && $value !== '');
    }

    private function stockQuery(array $filters): Builder
    {
        $query = DB::table('equipment_units as u')->join('equipment_models as m', 'm.id', '=', 'u.model_id')
            ->join('equipment_categories as c', 'c.id', '=', 'm.category_id')
            ->leftJoin('equipment_loan_units as allocation', 'allocation.open_unit_id', '=', 'u.id');
        foreach (['category' => 'c.id', 'model' => 'm.id', 'condition' => 'u.physical_condition', 'status' => 'u.operational_status'] as $key => $column) {
            if (isset($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }
        if (isset($filters['search'])) {
            $term = '%'.trim($filters['search']).'%';
            $query->where(fn ($q) => $q->where('c.name', 'like', $term)->orWhere('m.name', 'like', $term)
                ->orWhere('m.model_code', 'like', $term)->orWhere('u.asset_code', 'like', $term)->orWhere('u.serial_number', 'like', $term));
        }
        return $query;
    }

    private function stockCounts(Builder $query): Builder
    {
        return $query->selectRaw("COUNT(*) AS total, COUNT(allocation.open_unit_id) AS borrowed,
            COUNT(CASE WHEN allocation.open_unit_id IS NULL AND u.operational_status='active' AND u.physical_condition='good' AND m.is_active=1 THEN 1 END) AS available,
            COUNT(CASE WHEN u.operational_status='maintenance' THEN 1 END) AS maintenance,
            COUNT(CASE WHEN u.operational_status='lost' THEN 1 END) AS lost,
            COUNT(CASE WHEN u.operational_status='retired' THEN 1 END) AS retired");
    }

    public function stock(array $filters = []): object
    {
        return $this->stockCounts($this->stockQuery($filters))->first();
    }

    public function inventory(array $filters): Builder
    {
        return $this->stockCounts($this->stockQuery($filters))
            ->addSelect('c.id as category_id', 'c.name as category_name', 'm.id as model_id', 'm.name as model_name', 'm.model_code', 'u.physical_condition', 'u.operational_status')
            ->groupBy('c.id', 'c.name', 'm.id', 'm.name', 'm.model_code', 'u.physical_condition', 'u.operational_status')
            ->orderBy('c.name')->orderBy('m.name')->orderBy('m.id')->orderBy('u.physical_condition')->orderBy('u.operational_status');
    }

    public function loans(array $filters): Builder
    {
        $units = DB::table('equipment_loan_units')->select('loan_id')->groupBy('loan_id')
            ->selectRaw(LoanRules::progressSql()." as progress, SUM(outcome!='voided') as issued, SUM(outcome='on_loan') as open_units,
                SUM(outcome='returned') as returned, SUM(outcome='lost') as missing, SUM(CASE WHEN is_late=1 THEN 1 ELSE 0 END) as late_units");
        $loans = DB::table('equipment_loans as l')->leftJoinSub($units, 'p', 'p.loan_id', '=', 'l.id')
            ->select('l.id', 'l.loan_number', 'l.borrowed_at', 'l.due_date', 'l.borrower_type')
            ->selectRaw("COALESCE(p.progress,'integrity_error') as progress, COALESCE(p.issued,0) as issued, COALESCE(p.open_units,0) as open_units,
                COALESCE(p.returned,0) as returned, COALESCE(p.missing,0) as missing, COALESCE(p.late_units,0) as late_units,
                (COALESCE(p.open_units,0)>0 AND l.due_date < ?) as overdue", [now('Asia/Jakarta')->toDateString()]);
        $returnPeriod = ($filters['period'] ?? 'checkout') === 'return';
        $receipts = DB::table('equipment_returns as r')->join('equipment_loan_units as ru', 'ru.return_id', '=', 'r.id')
            ->where('ru.outcome', 'returned')->select('r.loan_id')
            ->selectRaw('COUNT(*) as period_returns, MAX(r.returned_at) as latest_return, SUM(CASE WHEN ru.is_late=1 THEN 1 ELSE 0 END) as period_late_returns')
            ->groupBy('r.loan_id');
        foreach (['from', 'until'] as $key) {
            if (isset($filters[$key])) {
                $day = CarbonImmutable::parse($filters[$key], 'Asia/Jakarta')->startOfDay();
                ($returnPeriod ? $receipts : $loans)->where($returnPeriod ? 'r.returned_at' : 'l.borrowed_at', $key === 'from' ? '>=' : '<', ($key === 'until' ? $day->addDay() : $day)->utc());
            }
        }
        if ($returnPeriod) {
            $loans->joinSub($receipts, 'receipts', 'receipts.loan_id', '=', 'l.id')
                ->addSelect('receipts.period_returns', 'receipts.latest_return', 'receipts.period_late_returns');
        }
        $query = DB::query()->fromSub($loans, 'cohort');
        foreach (['progress' => 'progress', 'borrower' => 'borrower_type'] as $key => $column) {
            if (isset($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }
        $lateColumn = $returnPeriod ? 'period_late_returns' : 'late_units';
        match ($filters['late'] ?? '') {
            'overdue' => $query->where('overdue', 1),
            'resolved_late' => $query->where($lateColumn, '>', 0),
            'none' => $query->where('overdue', 0)->where($lateColumn, 0),
            default => null,
        };
        return $query->orderByDesc($returnPeriod ? 'latest_return' : 'borrowed_at')->orderByDesc('id');
    }

    public function loanCounts(Builder $loans, bool $returnPeriod = false): object
    {
        $returned = $returnPeriod ? 'period_returns' : 'returned';
        return (clone $loans)->reorder()->selectRaw("COUNT(*) as transactions, COALESCE(SUM(issued),0) as issued, COALESCE(SUM($returned),0) as returned, COALESCE(SUM(missing),0) as missing")->first();
    }

    public static function columns(string $section, array $filters): array
    {
        if ($section === 'inventory') {
            return self::INVENTORY_COLUMNS;
        }
        return ($filters['period'] ?? '') === 'return'
            ? self::LOAN_COLUMNS + ['latest_return' => 'Retur terakhir dalam periode (WIB)', 'period_returns' => 'Kembali dalam periode', 'period_late_returns' => 'Retur terlambat dalam periode']
            : self::LOAN_COLUMNS;
    }

    public function inventorySummary(): array
    {
        $units = $this->stockQuery([]);
        return [
            'counts' => $this->stock(),
            'conditions' => (clone $units)->select('u.physical_condition as label')->selectRaw('COUNT(*) as total')->groupBy('u.physical_condition')->get(),
            'categories' => (clone $units)->select('c.name as label')->selectRaw('COUNT(*) as total')->groupBy('c.id','c.name')->orderByDesc('total')->orderBy('c.id')->paginate(10, ['*'], 'categoryPage'),
        ];
    }

    public static function display(string $key, mixed $value): string
    {
        return match ($key) {
            'physical_condition' => self::CONDITIONS[$value] ?? (string) $value,
            'operational_status' => self::STATUS[$value] ?? (string) $value,
            'borrower_type' => self::BORROWERS[$value] ?? (string) $value,
            'progress' => self::PROGRESS[$value] ?? (string) $value,
            'due_date' => $value ? CarbonImmutable::parse((string) $value, 'Asia/Jakarta')->format('d/m/Y') : '—',
            'borrowed_at', 'latest_return' => LoanRules::wib($value),
            'overdue' => $value ? 'Ya' : 'Tidak',
            default => (string) $value,
        };
    }
}
