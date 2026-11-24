<?php

namespace App\Livewire\Admin\Inventory;

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
#[Title('Inventory Peralatan - WPiTV')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url]
    public string $tab = 'models';

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
        if (in_array($property, ['search', 'category', 'status', 'condition', 'perPage', 'tab'], true)) {
            $this->resetPage();
        }
    }

    public function mount(): void
    {
        Gate::authorize('equipment.inventory.view');
    }

    public function begin(string $operation, int $id = 0): void
    {
        abort_unless(isset(EquipmentPermissions::OPERATIONS[$operation]), 403);
        abort_if(in_array($operation, LoanService::OPERATIONS, true), 403);
        Gate::authorize(EquipmentPermissions::OPERATIONS[$operation]);
        $this->resetValidation();
        $this->photos = [];
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
        $this->validate(['photos' => 'array|max:8', 'photos.*' => 'file|max:4883']);
        if (! str_ends_with($this->operation, '.photo_add') && $this->photos) {
            throw ValidationException::withMessages(['photos' => 'Tambahkan foto melalui tindakan foto sesudah data tersimpan.']);
        }
        $prepared = [];
        foreach ($this->photos as $file) {
            $prepared[] = $photoService->prepare($file);
        }
        try {
            $service->execute(auth()->user(), $this->operation, $this->token,
                $this->form + ['id' => $this->recordId, 'version' => $this->recordVersion], $prepared);
        } catch (ValidationException $e) {
            $messages = [];
            foreach ($e->errors() as $field => $errors) {
                $messages[in_array($field, ['photos', 'action'], true) ? $field : 'form.'.$field] = $errors;
            }
            throw ValidationException::withMessages($messages);
        }
        $this->cancel();
        session()->flash('success', 'Perubahan Inventory berhasil disimpan beserta audit.');
    }

    public function inspect(string $kind, int $id): void
    {
        Gate::authorize('equipment.inventory.view');
        abort_unless(in_array($kind, ['model', 'unit'], true), 404);
        $this->detailKind = $kind;
        $this->detailId = $id;
    }

    public function render(InventoryService $service)
    {
        Gate::authorize('equipment.inventory.view');
        $tab = in_array($this->tab, ['categories', 'models', 'units', 'audit'], true) ? $this->tab : 'models';
        if ($tab === 'audit') {
            Gate::authorize('equipment.audit.view');
        }
        $search = '%'.mb_substr(trim($this->search), 0, 100).'%';
        if ($tab === 'categories') {
            $query = DB::table('equipment_categories')->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('code', 'like', $search));
            if (in_array($this->status, ['1', '0'], true)) {
                $query->where('is_active', $this->status);
            }
            $query->orderBy('name')->orderBy('id');
        } elseif ($tab === 'models') {
            $query = DB::table('equipment_models as m')->join('equipment_categories as c', 'c.id', '=', 'm.category_id')
                ->select('m.*', 'c.name as category_name')->selectRaw('(SELECT COUNT(*) FROM equipment_units u WHERE u.model_id=m.id) as units_count')
                ->selectRaw('(SELECT p.id FROM equipment_model_photos p WHERE p.model_id=m.id AND p.is_cover=1 ORDER BY p.id DESC LIMIT 1) as cover_id')
                ->where(fn ($q) => $q->where('m.name', 'like', $search)->orWhere('m.model_code', 'like', $search));
            if (ctype_digit($this->category)) {
                $query->where('m.category_id', $this->category);
            }
            if (in_array($this->status, ['1', '0'], true)) {
                $query->where('m.is_active', $this->status);
            }
            $query->orderBy('m.name')->orderBy('m.id');
        } elseif ($tab === 'units') {
            // Drill-down from the grouped inventory report.
            $query = $service->units()->where(fn ($q) => $q->where('u.asset_code', 'like', $search)->orWhere('u.serial_number', 'like', $search)
                ->orWhere('m.name', 'like', $search)->orWhere('m.model_code', 'like', $search)->orWhere('c.name', 'like', $search));
            if (ctype_digit($this->model)) {
                $query->where('u.model_id', $this->model);
            }
            if (ctype_digit($this->category)) {
                $query->where('m.category_id', $this->category);
            }
            if (in_array($this->status, ['active', 'maintenance', 'lost', 'retired'], true)) {
                $query->where('u.operational_status', $this->status);
            }
            if ($this->status === 'on_loan') {
                $query->whereExists(fn ($q) => $q->selectRaw('1')->from('equipment_loan_units as allocation')->whereColumn('allocation.unit_id', 'u.id')->where('allocation.outcome', 'on_loan'));
            }
            if (in_array($this->condition, ['good', 'minor_damage', 'major_damage'], true)) {
                $query->where('u.physical_condition', $this->condition);
            }
            $query->orderBy('u.id', 'desc');
        } else {
            $query = DB::table('equipment_audit_log')->where(fn ($q) => $q->where('event_name', 'like', $search)->orWhere('reason', 'like', $search))->orderByDesc('id');
        }
        $gallery = collect();
        if ($this->detailId && Gate::allows('equipment.photo.view')) {
            $gallery = DB::table($this->detailKind === 'model' ? 'equipment_model_photos' : 'equipment_unit_photos')
                ->where($this->detailKind.'_id', $this->detailId)->orderByDesc('id')->limit(40)->get();
        }

        return view('livewire.admin.inventory.index', [
            'activeTab' => $tab, 'rows' => $query->paginate(in_array($this->perPage, [10, 25, 50], true) ? $this->perPage : 10),
            'categories' => DB::table('equipment_categories')->orderBy('name')->get(),
            'stock' => $service->stock(), 'gallery' => $gallery,
        ]);
    }
}
