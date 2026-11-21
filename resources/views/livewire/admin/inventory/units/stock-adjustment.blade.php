<section class="border rounded p-3 mb-4" aria-labelledby="stock-heading">
    <h5 id="stock-heading">Kelola stok barang</h5>
    <p>{{ $parent->name }} · {{ $parent->inventory_code }}</p>
    <div class="row g-2 mb-3">
        @foreach(\App\Services\EquipmentStock::LABELS as $key => $label)
            <div class="col-6 col-md-2"><x-equipment-status :status="$key"/> <strong>{{ $counts[$key] }}</strong></div>
        @endforeach
    </div>
    @unless($hasCover)
        <p class="alert alert-info">Untuk menambah stok, simpan foto utama pada
            <a href="{{ route('admin.inventory.units.edit', $parent) }}">unit induk {{ $parent->inventory_code }}</a> terlebih dahulu.</p>
    @endunless
    @if(session('stock_success'))<p class="alert alert-success" role="status">{{ session('stock_success') }} Muat ulang sebelum mengedit identitas unit.</p>@endif
    <form wire:submit="save" data-admin-confirm-text="Ya, simpan penyesuaian" class="row g-2">
        <div class="col-md-4"><label for="stock-action">Penyesuaian</label><select class="form-select" id="stock-action" wire:model="action"><option value="add">Tambah stok</option><option value="archive">Nonaktifkan unit tersedia</option></select></div>
        <div class="col-md-2"><label for="stock-count">Jumlah unit</label><input class="form-control" id="stock-count" type="number" min="1" max="50" wire:model="quantity" required></div>
        <div class="col-md-6"><label for="stock-reason">Alasan penyesuaian</label><input class="form-control" id="stock-reason" maxlength="1000" wire:model="reason" required></div>
        @foreach($errors->all() as $error)<p class="text-danger" role="alert">{{ $error }}</p>@endforeach
        <div><button class="btn btn-primary" wire:loading.attr="disabled">Simpan penyesuaian stok</button></div>
    </form>
</section>
