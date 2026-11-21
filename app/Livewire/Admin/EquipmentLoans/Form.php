<?php

namespace App\Livewire\Admin\EquipmentLoans;

use App\Exceptions\EquipmentOperationException;
use App\Livewire\Concerns\HandlesEquipmentErrors;
use App\Models\EquipmentLoan;
use App\Services\EquipmentLoanCheckout;
use App\Services\EquipmentQuantity;
use App\Services\EquipmentStock;
use App\Support\InputBounds;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.admin')]
class Form extends Component
{
    use AuthorizesRequests, HandlesEquipmentErrors;

    private const EMPTY_ROW = ['product' => '', 'quantity' => 1, 'mode' => 'auto', 'units' => [], 'filter' => ''];

    #[Locked]
    public string $submissionToken;

    #[Locked]
    public array $allocation = [];

    public array $rows = [];

    public array $data = ['borrower_name' => '', 'student_number' => '', 'study_program' => '', 'whatsapp_number' => '', 'purpose' => '', 'notes' => '', 'borrowed_at' => '', 'due_at' => ''];

    public string $search = '';

    public function mount(): void
    {
        $this->addRow();
        $this->submissionToken = (string) Str::uuid();
        $this->data['borrowed_at'] = now('Asia/Jakarta')->toDateString();
        $this->data['due_at'] = now('Asia/Jakarta')->addDay()->toDateString();
    }

    public function addRow(): void
    {
        $this->authorize('create', EquipmentLoan::class);
        if (count($this->rows) < 50) {
            $this->rows[] = self::EMPTY_ROW;
        }
        $this->allocation = [];
    }

    public function removeRow(int $index): void
    {
        $this->authorize('create', EquipmentLoan::class);
        unset($this->rows[$index]);
        $this->allocation = [];
        $this->resetValidation();
    }

    public function updated($property): void
    {
        $this->authorize('create', EquipmentLoan::class);
        $this->allocation = [];
        foreach ($this->rows as $key => $row) {
            if (! is_int($key) || $key < 0 || ! is_array($row) || count($this->rows) > 50) {
                abort(422);
            }
            foreach (['product', 'quantity', 'mode', 'filter'] as $field) {
                if (! is_scalar($row[$field] ?? null) && ($row[$field] ?? null) !== null) {
                    abort(422);
                }
            }
        }
        if (preg_match('/^rows\.(\d+)\.(product|mode)$/D', $property, $match)) {
            $this->rows[$match[1]]['units'] = [];
            $this->rows[$match[1]]['filter'] = '';
        }
    }

    public function edit(): void
    {
        $this->authorize('create', EquipmentLoan::class);
        $this->allocation = [];
        $this->resetValidation();
    }

    public function review(EquipmentQuantity $stock, EquipmentLoanCheckout $checkout): void
    {
        $this->edit();
        $this->validate([
            'rows' => 'required|array|min:1|max:50',
            'rows.*' => 'array:product,quantity,mode,units,filter',
            'rows.*.product' => 'required|integer|min:1|distinct',
            'rows.*.quantity' => 'required|integer|between:1,50',
            'rows.*.mode' => 'required|in:auto,manual',
            'rows.*.units' => 'present|array|max:50',
            'rows.*.units.*' => 'integer|min:1',
        ], ['rows.*.product.distinct' => 'Barang ini sudah ada. Ubah jumlah pada satu baris saja.']);
        if (array_sum(array_column($this->rows, 'quantity')) > 50) {
            throw ValidationException::withMessages(['rows' => 'Maksimal 50 unit per transaksi.']);
        }

        $allocation = [];
        foreach ($this->rows as $index => $row) {
            $product = (int) $row['product'];
            if ($stock->parentId($product) !== $product) {
                throw ValidationException::withMessages(["rows.$index.product" => 'Pilih barang induk yang valid.']);
            }
            $quantity = (int) $row['quantity'];
            $available = $stock->available($product);
            if ($row['mode'] === 'manual') {
                if (count(array_unique($row['units'])) !== $quantity || count($row['units']) !== $quantity) {
                    throw ValidationException::withMessages(["rows.$index.units" => 'Jumlah unit berbeda yang dipilih harus sesuai jumlah pinjaman.']);
                }
                $available->whereIn('inventory_units.id', $row['units']);
            }
            $units = $available->limit($quantity)->get();
            if ($units->count() !== $quantity) {
                throw ValidationException::withMessages(["rows.$index.quantity" => 'Stok tidak cukup atau unit pilihan tidak tersedia untuk barang ini.']);
            }
            foreach ($units as $unit) {
                $allocation[$index][] = ['unit_id' => $unit->id, 'version' => $unit->version, 'code' => $unit->inventory_code];
            }
        }
        $checkout->canonicalPayload([...$this->data, 'items' => array_merge(...array_values($allocation))]);
        $this->allocation = $allocation;
    }

    public function save(EquipmentLoanCheckout $checkout)
    {
        $this->authorize('create', EquipmentLoan::class);
        if (! $this->allocation) {
            throw ValidationException::withMessages(['items' => 'Periksa pilihan barang sebelum mengonfirmasi peminjaman.']);
        }
        try {
            $loan = $checkout->checkout(auth()->user(), $this->submissionToken, [...$this->data, 'items' => array_merge(...array_values($this->allocation))]);
        } catch (ValidationException|EquipmentOperationException $e) {
            if ($e instanceof EquipmentOperationException && $e->field !== 'items') {
                throw $e;
            }
            $errors = $e instanceof ValidationException ? $e->errors() : ['items' => $e->getMessage()];
            $stock = app(EquipmentQuantity::class);
            foreach ($this->allocation as $index => $units) {
                $versions = $stock->available((int) $this->rows[$index]['product'])
                    ->whereIn('inventory_units.id', array_column($units, 'unit_id'))->pluck('inventory_units.version', 'inventory_units.id');
                foreach ($units as $unit) {
                    if ((int) $versions->get($unit['unit_id']) !== $unit['version']) {
                        $errors["rows.$index.quantity"] = 'Pilihan unit berubah. Periksa stok dan ulangi review.';
                        break;
                    }
                }
            }
            $this->allocation = [];
            throw ValidationException::withMessages($errors);
        }
        session()->flash('success', 'Peminjaman berhasil dicatat.');

        return redirect()->route('admin.equipment-loans.show', $loan);
    }

    public function render()
    {
        $this->authorize('create', EquipmentLoan::class);
        $search = InputBounds::search($this->search);
        $rows = array_slice($this->rows, 0, 50, true);
        $products = app(EquipmentStock::class)->products()->with('category:id,name');
        $catalog = (clone $products)->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('inventory_code', 'like', '%'.$search.'%')))
            ->orderBy('name')->orderBy('inventory_units.id')->limit(30)->get();
        $chosen = $products->whereIntegerInRaw('inventory_units.id', array_filter(array_column($rows, 'product'), 'is_numeric'))->get()->keyBy('id');
        $stock = app(EquipmentQuantity::class);
        $manual = [];
        $photoIds = $chosen->keys();
        foreach ($rows as $key => $row) {
            if (! is_array($row) || ($row['mode'] ?? '') !== 'manual' || ! $chosen->has((int) ($row['product'] ?? 0)) || $this->allocation) {
                continue;
            }
            $filter = InputBounds::search(is_string($row['filter'] ?? null) ? $row['filter'] : '');
            $manual[$key] = $stock->available((int) $row['product'])->when($filter !== '', fn ($q) => $q->where('inventory_units.inventory_code', 'like', '%'.$filter.'%'))->orderBy('inventory_units.id')->limit(50)->get();
            $photoIds = $photoIds->merge($manual[$key]->pluck('id'));
        }
        $photos = $stock->photos($photoIds->unique());

        return view('livewire.admin.equipment-loans.form', compact('rows', 'catalog', 'chosen', 'manual', 'photos'))->title('Tambah Peminjaman Alat');
    }
}
