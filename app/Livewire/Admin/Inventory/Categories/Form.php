<?php

namespace App\Livewire\Admin\Inventory\Categories;

use App\Exceptions\EquipmentOperationException;
use App\Livewire\Concerns\HandlesEquipmentErrors;
use App\Models\EquipmentAuditEvent;
use App\Models\InventoryCategory;
use App\Support\EquipmentErrors;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.admin')]
class Form extends Component
{
    use AuthorizesRequests;
    use HandlesEquipmentErrors;

    #[Locked]
    public ?int $categoryId = null;

    #[Locked]
    public ?string $originalUpdatedAt = null;

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    public string $reason = '';

    public function mount(?InventoryCategory $category = null): void
    {
        $this->authorize($category?->exists ? 'update' : 'create', $category?->exists ? $category : InventoryCategory::class);
        if ($category?->exists) {
            $this->categoryId = $category->id;
            $this->originalUpdatedAt = $category->getRawOriginal('updated_at');
            $this->name = $category->name;
            $this->description = $category->description ?? '';
            $this->isActive = $category->is_active;
        }
    }

    public function save()
    {
        $this->authorize($this->categoryId ? 'update' : 'create', $this->categoryId ? InventoryCategory::findOrFail($this->categoryId) : InventoryCategory::class);
        $this->name = preg_replace('/\s+/u', ' ', trim($this->name));
        $this->description = trim($this->description);
        $this->reason = trim($this->reason);
        $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('inventory_categories', 'name')->ignore($this->categoryId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'isActive' => ['boolean'],
            'reason' => [$this->categoryId ? 'required' : 'nullable', 'string', 'max:1000'],
        ], [], ['name' => 'nama kategori', 'description' => 'deskripsi', 'reason' => 'alasan perubahan']);

        try {
            DB::transaction(function (): void {
                $category = $this->categoryId ? InventoryCategory::lockForUpdate()->findOrFail($this->categoryId) : new InventoryCategory;
                $this->authorize($category->exists ? 'update' : 'create', $category->exists ? $category : InventoryCategory::class);
                if ($category->exists && $category->getRawOriginal('updated_at') !== $this->originalUpdatedAt) {
                    throw EquipmentOperationException::stale('name');
                }
                if ($category->exists && ! $this->isActive) {
                    $this->authorize('archive', $category);
                }
                $before = $category->exists ? $category->only(['name', 'description', 'is_active']) : null;
                $category->name = $this->name;
                $category->description = $this->description === '' ? null : $this->description;
                $category->is_active = $this->isActive;
                if ($category->exists && ! $category->isDirty()) {
                    return;
                }
                $type = ! $category->exists ? 'created' : (($before['is_active'] && ! $this->isActive) ? 'archived' : 'updated');
                $category->save();
                $event = new EquipmentAuditEvent;
                $event->category_id = $category->id;
                $event->event_type = $type;
                $event->actor_id = auth()->id();
                $event->actor_name = auth()->user()->name;
                $event->reason = $this->reason ?: 'Pembuatan kategori';
                $event->before_data = $before;
                $event->after_data = $category->only(['name', 'description', 'is_active']);
                $event->save();
            }, 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw EquipmentOperationException::duplicate('name');
            }
            throw EquipmentErrors::sanitize($exception);
        }
        session()->flash('success', 'Kategori berhasil disimpan.');

        return redirect()->route('admin.inventory.categories.index');
    }

    public function render()
    {
        $this->authorize($this->categoryId ? 'update' : 'create', $this->categoryId ? InventoryCategory::findOrFail($this->categoryId) : InventoryCategory::class);

        return view('livewire.admin.inventory.categories.form')->title('Kategori Inventaris');
    }
}
