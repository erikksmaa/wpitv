<?php

namespace App\Livewire\Admin\Inventory\Units;

use App\Exceptions\EquipmentOperationException;
use App\Livewire\Concerns\HandlesEquipmentErrors;
use App\Models\EquipmentAuditEvent;
use App\Models\InventoryCategory;
use App\Models\InventoryUnit;
use App\Services\EquipmentPhotos;
use App\Services\EquipmentQuantity;
use App\Services\EquipmentStructure;
use App\Services\InventoryCode;
use App\Support\EquipmentErrors;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
class Form extends Component
{
    use WithFileUploads {
        _startUpload as private beginFileUpload;
        _finishUpload as private finishFileUpload;
    }

    private function authorizeUpload(string $name): void
    {
        $unit = $this->unitId ? InventoryUnit::findOrFail($this->unitId) : null;
        $this->authorize($unit ? 'update' : 'create', $unit ?? InventoryUnit::class);
        abort_unless($name === 'photo', 403);
    }

    #[Renderless]
    public function _startUpload($name, $fileInfo, $isMultiple)
    {
        $this->authorizeUpload($name);
        abort_if($isMultiple, 422);
        $this->beginFileUpload($name, $fileInfo, false);
    }

    public function _finishUpload($name, $tmpPath, $isMultiple, $append = true)
    {
        $this->authorizeUpload($name);
        abort_if($isMultiple, 422);
        $this->finishFileUpload($name, $tmpPath, false, $append);
    }

    public $photo;

    use AuthorizesRequests;
    use HandlesEquipmentErrors;

    #[Locked]
    public ?int $unitId = null;

    #[Locked]
    public int $expectedVersion = 0;

    #[Locked]
    public bool $itemMode = false;

    public string $inventory_code = '';

    public string $name = '';

    public ?int $category_id = null;

    public string $brand = '';

    public string $model = '';

    public string $serial_number = '';

    public string $storage_location = '';

    public string $condition = 'good';

    public string $notes = '';

    public bool $is_active = true;

    public int $quantity = 1;

    public string $reason = '';

    private const FIELDS = ['inventory_code', 'name', 'category_id', 'brand', 'model', 'serial_number', 'storage_location', 'condition', 'notes', 'is_active'];

    public function mount(?InventoryUnit $unit = null): void
    {
        $this->authorize($unit?->exists ? 'update' : 'create', $unit?->exists ? $unit : InventoryUnit::class);
        if ($unit?->exists) {
            $this->unitId = $unit->id;
            $this->expectedVersion = $unit->version;
            foreach (self::FIELDS as $field) {
                $this->{$field} = $unit->{$field} ?? '';
            }
            if (request()->boolean('barang')) {
                abort_unless($unit->item_id === $unit->id, 404);
                $item = $unit->item()->firstOrFail();
                $this->itemMode = true;
                $this->expectedVersion = $item->version;
                $this->inventory_code = $item->item_code;
                foreach (['name', 'brand', 'model', 'notes', 'category_id', 'is_active'] as $field) {
                    $this->{$field} = $item->{$field} ?? '';
                }
            }
        }
    }

    public function save()
    {
        $this->authorize($this->unitId ? 'update' : 'create', $this->unitId ? InventoryUnit::findOrFail($this->unitId) : InventoryUnit::class);
        foreach (['inventory_code', 'name', 'brand', 'model', 'serial_number', 'storage_location', 'notes', 'reason'] as $field) {
            $this->{$field} = trim($this->{$field});
        }

        $this->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.($this->unitId ? 1 : 50)],
            'photo' => ['nullable', ! EquipmentPhotos::enabled() ? 'prohibited' : ($this->unitId ? 'nullable' : 'required'), 'file', 'max:5120'],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:inventory_categories,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'condition' => ['required', Rule::in(['good', 'minor_damage', 'major_damage'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
            'reason' => [$this->unitId ? 'required' : 'nullable', 'string', 'max:1000'],
        ]);
        $files = app(EquipmentPhotos::class);
        $photo = $this->photo ? $files->stage($this->photo) : null;
        try {
            DB::transaction(function () use ($photo): void {
                $unit = $this->unitId ? InventoryUnit::lockForUpdate()->findOrFail($this->unitId) : new InventoryUnit;
                $this->authorize($unit->exists ? 'update' : 'create', $unit->exists ? $unit : InventoryUnit::class);
                if ($this->itemMode) {
                    $this->saveItem($unit, $photo);

                    return;
                }
                if ($unit->exists && $unit->version !== $this->expectedVersion) {
                    throw EquipmentOperationException::stale('name');
                }
                // Current reads under the unit lock; future checkout must lock this same unit first.
                if ($unit->exists && $unit->loanItems()->whereNull('returned_at')->lockForUpdate()->first(['id'])) {
                    throw ValidationException::withMessages(['name' => 'Unit sedang dipinjam atau belum kembali. Perubahan diblokir.']);
                }
                if ($unit->exists && $unit->inventory_code !== $this->inventory_code) {
                    throw ValidationException::withMessages(['inventory_code' => 'Kode inventaris yang sudah diterbitkan tidak dapat diubah.']);
                }
                if ($unit->exists && ! $unit->is_active && $this->is_active && $unit->loanItems()->whereNotNull('lost_at')->lockForUpdate()->first(['id'])) {
                    $this->authorize('reactivateRecovered', $unit);
                    throw ValidationException::withMessages(['is_active' => 'Gunakan aksi aktivasi setelah inspeksi pada detail unit.']);
                }
                if ($unit->exists && ! $this->is_active) {
                    $this->authorize('archive', $unit);
                }
                // Keep an existing inactive category, but never assign a new unit into one.
                $category = InventoryCategory::lockForUpdate()->findOrFail($this->category_id);
                if (! $category->is_active && (! $unit->exists || $unit->category_id !== $category->id)) {
                    throw ValidationException::withMessages(['category_id' => 'Pilih kategori aktif.']);
                }
                $creating = ! $unit->exists;
                $this->inventory_code = $unit->exists ? $unit->inventory_code : (string) Str::ulid();
                $this->storage_location = $unit->exists ? $unit->storage_location : '';
                $before = $unit->exists ? $unit->only([...self::FIELDS, 'version']) : null;
                foreach (self::FIELDS as $field) {
                    $unit->{$field} = $this->{$field};
                }
                foreach (['brand', 'model', 'serial_number', 'notes'] as $field) {
                    $unit->{$field} = $this->{$field} === '' ? null : $this->{$field};
                }
                if ($unit->exists && ! $photo && ! $unit->isDirty()) {
                    return;
                }
                $type = ! $unit->exists ? 'created' : (($before['is_active'] && ! $this->is_active) ? 'archived' : 'updated');
                $unit->version = $unit->exists ? $unit->version + 1 : 1;
                $unit->save();
                if ($creating) {
                    InventoryCode::issue($unit);
                    $this->inventory_code = $unit->inventory_code;
                }
                $itemId = null;
                if (EquipmentStructure::installed() && $unit->item_id === null && app(EquipmentQuantity::class)->parentId($unit->id) === $unit->id) {
                    $itemId = EquipmentStructure::syncItem(DB::connection(), (object) $unit->getAttributes());
                }
                $audit = new EquipmentAuditEvent;
                $audit->unit_id = $unit->id;
                $audit->event_type = $type;
                $audit->actor_id = auth()->id();
                $audit->actor_name = auth()->user()->name;
                $audit->reason = $this->reason ?: 'Pembuatan unit inventaris';
                $audit->before_data = $before;
                $audit->after_data = $unit->only([...self::FIELDS, 'version']);
                $audit->save();
                if ($photo) {
                    DB::table('inventory_unit_photos')->insert($photo + ['unit_id' => $unit->id, 'audit_event_id' => $audit->id, 'revision' => $unit->version]);
                    if ($itemId !== null) {
                        DB::table('inventory_item_photos')->insert($photo + ['item_id' => $itemId, 'revision' => $unit->version, 'uploaded_by' => auth()->id()]);
                    }
                }
                if (! $this->unitId && $this->quantity > 1) {
                    app(EquipmentQuantity::class)->adjust(auth()->user(), $unit->id, $unit->version, $this->quantity - 1, 'Penambahan stok awal', true, initial: true);
                }
            }, 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw EquipmentOperationException::duplicate('inventory_code');
            }
            throw EquipmentErrors::sanitize($exception);
        } finally {
            if ($photo) {
                $files->discardUnused($this->itemMode ? 'inventory_item_photos' : 'inventory_unit_photos', [$photo]);
            }
        }
        session()->flash('success', 'Unit inventaris berhasil disimpan.');

        return redirect()->route('admin.inventory.units.index');
    }

    private function saveItem(InventoryUnit $unit, ?array $photo): void
    {
        abort_unless($unit->item_id === $unit->id, 404);
        $item = $unit->item()->lockForUpdate()->firstOrFail();
        if ($item->version !== $this->expectedVersion) {
            throw EquipmentOperationException::stale('name');
        }
        $category = InventoryCategory::lockForUpdate()->findOrFail($this->category_id);
        if (! $category->is_active && $item->category_id !== $category->id) {
            throw ValidationException::withMessages(['category_id' => 'Pilih kategori aktif.']);
        }
        if ($item->item_code !== $this->inventory_code) {
            throw ValidationException::withMessages(['inventory_code' => 'Kode barang tidak dapat diubah.']);
        }
        if (! $this->is_active && $item->is_active) {
            $this->authorize('archive', $unit);
        }
        $before = $item->getAttributes();
        $item->forceFill([
            'category_id' => $category->id, 'name' => $this->name,
            'brand' => $this->brand ?: null, 'model' => $this->model ?: null, 'notes' => $this->notes ?: null,
            'is_active' => $this->is_active,
        ]);
        if (! $photo && ! $item->isDirty()) {
            return;
        }
        $item->version++;
        $item->save();
        EquipmentAuditEvent::forceCreate([
            'unit_id' => $unit->id, 'event_type' => 'updated', 'actor_id' => auth()->id(),
            'actor_name' => auth()->user()->name, 'reason' => $this->reason,
            'before_data' => $before, 'after_data' => $item->getAttributes() + ['operation' => 'item_updated'],
        ]);
        if ($photo) {
            $photos = DB::table('inventory_item_photos');
            $revision = 1 + (int) $photos->where('item_id', $item->id)->max('revision');
            DB::table('inventory_item_photos')->insert($photo + ['item_id' => $item->id, 'revision' => $revision, 'uploaded_by' => auth()->id()]);
        }
    }

    public function clearPhoto(): void
    {
        $this->authorizeUpload('photo');
        $this->reset('photo');
        $this->resetValidation('photo');
    }

    public function render()
    {
        $unit = $this->unitId ? InventoryUnit::findOrFail($this->unitId) : null;
        $this->authorize($unit ? 'update' : 'create', $unit ?? InventoryUnit::class);
        $currentCategory = $this->itemMode ? $unit->item()->value('category_id') : $unit?->category_id;
        $categories = InventoryCategory::where(function ($q) use ($currentCategory) {
            $q->where('is_active', true);
            if ($currentCategory !== null) {
                $q->orWhere('id', $currentCategory);
            }
        })->orderBy('name')->get(['id', 'name', 'is_active']);

        $parentId = $unit ? app(EquipmentQuantity::class)->parentId($unit->id) : null;

        $cover = $this->itemMode ? app(EquipmentQuantity::class)->photos([$unit->id])->get($unit->id) : null;

        return view('livewire.admin.inventory.units.form', compact('categories', 'parentId', 'cover'))->title('Form Unit Inventaris');
    }
}
