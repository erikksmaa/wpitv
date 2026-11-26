<?php

namespace App\Livewire\Admin\Inventory;

use App\Services\Equipment\EquipmentReports;
use App\Services\Equipment\EquipmentValidation;
use Illuminate\Database\QueryException;
use App\Services\Equipment\InventoryPhotos;
use App\Services\Equipment\InventoryService;
use App\Services\Equipment\LoanService;
use App\Support\EquipmentPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Inventaris Peralatan - WPiTV')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Locked]
    public string $tab = 'models';

    #[Url]
    public string $activity = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $until = '';

    #[Locked]
    public int $expanded = 0;

    #[Locked]
    public string $target = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $model = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $condition = '';

    public string $unitStatus = '';

    public string $unitCondition = '';

    public int $perPage = 10;

    #[Locked]
    public string $operation = '';

    #[Locked]
    public string $token = '';

    #[Locked]
    public int $recordId = 0;

    #[Locked]
    public int $recordVersion = 0;

    public array $form = [];

    public array $photos = [];

    #[Locked]
    public string $detailKind = '';

    #[Locked]
    public int $detailId = 0;

    public function updated($property): void
    {
        if (in_array($property, ['search', 'category', 'status', 'condition', 'unitStatus', 'unitCondition', 'perPage', 'activity', 'from', 'until', 'model'], true)) {
            $this->resetPage();
            $this->resetPage('unitsPage');
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'category', 'status', 'condition', 'unitStatus', 'unitCondition', 'model', 'activity', 'from', 'until');
        $this->resetPage();
        $this->resetPage('unitsPage');
    }

    public function mount(string $section = 'models'): void
    {
        Gate::authorize('equipment.inventory.view');
        $this->tab = in_array($section, ['categories', 'models', 'audit'], true) ? $section : 'models';
        if ($this->tab === 'audit') {
            Gate::authorize('equipment.audit.view');
        }
        if ($this->tab === 'models' && ctype_digit($this->model)) {
            $this->expanded = (int) $this->model;
            if (isset(EquipmentReports::STATUS[$this->status])) {
                $this->unitStatus = $this->status;
                $this->status = '';
            }
            if (isset(EquipmentReports::CONDITIONS[$this->condition])) {
                $this->unitCondition = $this->condition;
                $this->condition = '';
            }
        }
    }

    public function begin(string $operation, int $id = 0): void
    {
        abort_unless(isset(EquipmentPermissions::OPERATIONS[$operation]), 403);
        abort_if(in_array($operation, LoanService::OPERATIONS, true), 403);
        Gate::authorize(EquipmentPermissions::OPERATIONS[$operation]);
        $this->resetValidation();
        $this->photos = [];
        $this->target = '';
        $this->operation = $operation;
        $this->recordId = $id;
        $this->recordVersion = 0;
        $this->token = (string) Str::uuid();
        $this->form = ['name' => '', 'code' => '', 'description' => '', 'is_active' => true, 'category_id' => '', 'quantity' => 1, 'condition' => 'good', 'serial_number' => '', 'reason' => ''];
        if ($id) {
            $table = str_starts_with($operation, 'category.') ? 'equipment_categories'
                : (str_starts_with($operation, 'unit.') ? 'equipment_units' : 'equipment_models');
            $row = DB::table($table)->where('id', $id)->first();
            abort_unless($row, 404);
            $this->target = $row->name ?? $row->asset_code;
            $this->recordVersion = $row->version;
            $this->form = array_replace($this->form, array_intersect_key((array) $row, $this->form));
            $this->form['condition'] = $row->physical_condition ?? 'good';
            $this->form['code'] = $row->model_code ?? $row->code ?? '';
            $this->form['is_active'] = (bool) ($row->is_active ?? true);
        }
    }

    public function cancel(): void
    {
        $this->operation = '';
        $this->photos = [];
        $this->form = [];
        $this->resetValidation();
    }

    public function save(InventoryService $service, InventoryPhotos $photoService): void
    {
        Gate::authorize(EquipmentPermissions::OPERATIONS[$this->operation] ?? 'equipment.unknown');
        $this->resetValidation();
        EquipmentValidation::validate(['photos' => $this->photos], ['photos' => 'array|max:8', 'photos.*' => 'file|max:4883']);
        if (! str_ends_with($this->operation, '.photo_add') && $this->photos) {
            throw ValidationException::withMessages(['photos' => 'Tambahkan foto melalui tindakan foto sesudah data tersimpan.']);
        }
        $prepared = [];
        foreach ($this->photos as $file) {
            $prepared[] = $photoService->prepare($file);
        }
        $payload = ['id' => $this->recordId, 'version' => $this->recordVersion] + $this->form;
        if (isset($payload['code']) && is_string($payload['code'])) {
            $payload['code'] = strtoupper(trim($payload['code']));
        }
        try {
            $service->execute(auth()->user(), $this->operation, $this->token, $payload, $prepared);
        } catch (ValidationException $e) {
            $messages = [];
            foreach ($e->errors() as $field => $errors) {
                $key = match (true) {
                    $field === 'photos', $field === 'action' => $field,
                    in_array($field, ['id', 'version', 'token'], true) => 'action',
                    $field === 'code' && !str_ends_with($this->operation, '.create') => 'action',
                    default => 'form.'.$field,
                };
                $messages[$key] = $errors;
            }
            throw ValidationException::withMessages($messages);
        } catch (QueryException $error) {
            report($error);
            $this->addError('action', 'Data gagal disimpan. Silakan coba lagi.');
            return;
        }
        $message = match ($this->operation) {
            'category.create' => 'Kategori berhasil ditambahkan.',
            'category.update' => 'Kategori berhasil diperbarui.',
            'model.create' => 'Peralatan berhasil ditambahkan.',
            'model.update' => 'Peralatan berhasil diperbarui.',
            'stock.add' => 'Stok berhasil ditambahkan.',
            'unit.condition' => 'Kondisi dan serial unit berhasil diperbarui.',
            'unit.maintenance_start' => 'Unit masuk perawatan.',
            'unit.maintenance_finish' => 'Perawatan berhasil diselesaikan.',
            'unit.lost' => 'Unit ditandai hilang.',
            'unit.found' => 'Unit ditemukan dan masuk perawatan.',
            'unit.retire' => 'Unit berhasil dinonaktifkan permanen.',
            default => 'Foto berhasil ditambahkan.',
        };
        $this->cancel();
        $this->dispatch('equipment-feedback', message: $message, type: 'success');
    }

    public function inspect(string $kind, int $id): void
    {
        Gate::authorize('equipment.inventory.view');
        Gate::authorize('equipment.photo.view');
        abort_unless(in_array($kind, ['model', 'unit'], true), 404);
        $this->resetPage('galleryPage');
        $this->detailId = $this->detailKind === $kind && $this->detailId === $id ? 0 : $id;
        $this->detailKind = $kind;
    }

    public function toggleUnits(int $id): void
    {
        Gate::authorize('equipment.inventory.view');
        $this->expanded = $this->expanded === $id ? 0 : $id;
        $this->detailId = 0;
        $this->resetPage('unitsPage');
    }

    public function render(InventoryService $service, EquipmentReports $reports)
    {
        Gate::authorize('equipment.inventory.view');
        $search = '%'.mb_substr(trim($this->search), 0, 100).'%';
        $pageSize = in_array($this->perPage, [10, 25, 50], true) ? $this->perPage : 10;
        $categories = collect();
        $units = $gallery = null;
        $unitPhotos = collect();
        $summary = $this->tab === 'models' && Gate::allows('equipment.report.view')
            ? $reports->inventorySummary()
            : null;

        if ($this->tab === 'audit') {
            Gate::authorize('equipment.audit.view');
            $rows = InventoryActivity::query($search, $this->activity, $this->from, $this->until)->paginate($pageSize);
        } elseif ($this->tab === 'categories') {
            $rows = DB::table('equipment_categories')
                ->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('code', 'like', $search))
                ->when(in_array($this->status, ['0', '1'], true), fn ($q) => $q->where('is_active', $this->status))
                ->orderBy('name')->orderBy('id')->paginate($pageSize);
        } else {
            $categories = DB::table('equipment_categories')->orderBy('name')->get();
            $stock = DB::table('equipment_units as u')
                ->leftJoin('equipment_loan_units as l', 'l.open_unit_id', '=', 'u.id')
                ->select('u.model_id')->selectRaw("COUNT(*) as total, COUNT(l.open_unit_id) as borrowed,
                    SUM(l.open_unit_id IS NULL AND u.operational_status='active' AND u.physical_condition='good') as ready")
                ->groupBy('u.model_id');
            $query = DB::table('equipment_models as m')->join('equipment_categories as c', 'c.id', '=', 'm.category_id')
                ->leftJoinSub($stock, 's', 's.model_id', '=', 'm.id')
                ->select('m.*', 'c.name as category_name', 's.total', 's.borrowed', 's.ready')
                ->selectRaw('(SELECT p.id FROM equipment_model_photos p WHERE p.model_id=m.id AND p.is_cover=1 ORDER BY p.id DESC LIMIT 1) as cover_id')
                ->where(fn ($q) => $q->where('m.name', 'like', $search)->orWhere('m.model_code', 'like', $search)->orWhere('c.name', 'like', $search)
                    ->orWhereExists(fn ($u) => $u->selectRaw('1')->from('equipment_units as asset')->whereColumn('asset.model_id', 'm.id')
                        ->where(fn ($match) => $match->where('asset.asset_code', 'like', $search)->orWhere('asset.serial_number', 'like', $search))))
                ->when(ctype_digit($this->category), fn ($q) => $q->where('m.category_id', $this->category))
                ->when(ctype_digit($this->model), fn ($q) => $q->where('m.id', $this->model))
                ->when(in_array($this->status, ['0', '1'], true), fn ($q) => $q->where('m.is_active', $this->status));
            $rows = $query->orderBy('m.name')->orderBy('m.id')->paginate($pageSize);
            if ($rows->contains('id', $this->expanded)) {
                $activeUnitStatus = isset(EquipmentReports::STATUS[$this->unitStatus]) ? $this->unitStatus : (isset(EquipmentReports::STATUS[$this->status]) ? $this->status : '');
                $activeUnitCondition = isset(EquipmentReports::CONDITIONS[$this->unitCondition]) ? $this->unitCondition : (isset(EquipmentReports::CONDITIONS[$this->condition]) ? $this->condition : '');
                $units = $service->units()->where('u.model_id', $this->expanded)
                    ->when($activeUnitStatus !== '', fn ($q) => $q->where('u.operational_status', $activeUnitStatus))
                    ->when($activeUnitCondition !== '', fn ($q) => $q->where('u.physical_condition', $activeUnitCondition))
                    ->when(ctype_digit($this->model), fn ($q) => $q->where(fn ($fields) => $fields->where('u.asset_code', 'like', $search)
                        ->orWhere('u.serial_number', 'like', $search)->orWhere('m.name', 'like', $search)
                        ->orWhere('m.model_code', 'like', $search)->orWhere('c.name', 'like', $search)))
                    ->orderBy('u.unit_no')->paginate(10, ['*'], 'unitsPage');
                if (Gate::allows('equipment.photo.view')) {
                    $unitPhotos = DB::table('equipment_unit_photos')
                        ->whereIn('unit_id', $units->pluck('id'))
                        ->orderByDesc('id')->get(['id', 'unit_id'])->groupBy('unit_id');
                }
            }
            $galleryVisible = $this->detailKind === 'model' ? $rows->contains('id', $this->detailId) : $units?->contains('id', $this->detailId);
            if ($this->detailId && $galleryVisible && Gate::allows('equipment.photo.view')) {
                $gallery = DB::table($this->detailKind === 'model' ? 'equipment_model_photos' : 'equipment_unit_photos')
                    ->where($this->detailKind.'_id', $this->detailId)->orderByDesc('id')->paginate(12, ['id', 'created_at'], 'galleryPage');
            }
        }

        return view('livewire.admin.inventory.index', compact('rows', 'categories', 'units', 'gallery', 'summary', 'unitPhotos'));
    }
}
