<div class="card"><div class="card-header"><h4>{{ $itemMode ? 'Edit Barang Inventaris' : ($unitId ? 'Edit unit fisik' : 'Tambah Barang Inventaris') }}</h4></div><div class="card-body">
@if($unitId)
    <div class="mb-3">
        <span class="font-monospace text-break">{{ $inventory_code }}</span>
        <div><a href="{{ route('admin.inventory.units.show', $parentId) }}#kelola-stok">Kelola jumlah stok di detail barang</a></div>
    </div>
@endif
<form wire:submit.prevent="save">
<fieldset class="border rounded-3 p-3 mb-3"><legend class="float-none w-auto fs-6 px-2"><i class="bi bi-box" aria-hidden="true"></i> Informasi barang</legend><div class="row g-3">
<div class="col-md-6"><label for="unit-name" class="form-label">Nama barang</label><input id="unit-name" class="form-control" wire:model="name" maxlength="150">@error('name')<div class="text-danger">{{ $message }}</div>@enderror</div>
<div class="col-md-6"><label for="unit-brand" class="form-label">Merek</label><input id="unit-brand" class="form-control" wire:model="brand" maxlength="100">@error('brand')<div class="text-danger">{{ $message }}</div>@enderror</div>
<div class="col-md-6"><label for="unit-model" class="form-label">Model</label><input id="unit-model" class="form-control" wire:model="model" maxlength="100">@error('model')<div class="text-danger">{{ $message }}</div>@enderror</div>
<div class="col-md-6"><label for="unit-category" class="form-label">Kategori</label><select id="unit-category" class="form-select" wire:model="category_id"><option value="">Pilih kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}{{ $category->is_active ? '' : ' (nonaktif)' }}</option>@endforeach</select>@error('category_id')<div class="text-danger">{{ $message }}</div>@enderror</div>
<div class="col-md-6"><label for="item-code">Kode barang / unit</label><input id="item-code" class="form-control" readonly value="{{ $unitId ? $inventory_code : 'Otomatis saat disimpan' }}">@error('inventory_code')<small class="text-danger">{{ $message }}</small>@enderror</div>
@if($unitId && !$itemMode)<div class="col-md-6"><label for="unit-serial_number" class="form-label">Nomor seri</label><input id="unit-serial_number" class="form-control" wire:model="serial_number" maxlength="100">@error('serial_number')<div class="text-danger">{{ $message }}</div>@enderror</div>@endif
</div></fieldset>
<fieldset class="border rounded-3 p-3 mb-3"><legend class="float-none w-auto fs-6 px-2"><i class="bi bi-boxes" aria-hidden="true"></i> Stok barang</legend><div class="row g-3">
@unless($unitId)<div class="col-md-6"><label for="stock-quantity">Jumlah stok awal</label><input id="stock-quantity" class="form-control" type="number" min="1" max="50" required wire:model="quantity">@error('quantity')<small class="text-danger">{{ $message }}</small>@enderror</div>@endunless
@unless($itemMode)<div class="col-md-6"><label for="unit-condition" class="form-label">Kondisi</label><select id="unit-condition" class="form-select" wire:model="condition"><option value="good">Baik</option><option value="minor_damage">Rusak ringan</option><option value="major_damage">Rusak berat</option></select>@error('condition')<div class="text-danger">{{ $message }}</div>@enderror</div>@endunless
<div class="col-12"><div class="form-check"><input id="unit-active" class="form-check-input" type="checkbox" wire:model="is_active"><label for="unit-active" class="form-check-label">Aktif</label></div>@error('is_active')<div class="text-danger">{{ $message }}</div>@enderror</div>
</div></fieldset>
<fieldset class="border rounded-3 p-3 mb-3"><legend class="float-none w-auto fs-6 px-2"><i class="bi bi-camera" aria-hidden="true"></i> {{ $unitId && !$itemMode ? 'Foto unit' : 'Foto barang' }}</legend><div class="row g-3">
<div x-data="{ preview: null, clear(){ if(this.preview) URL.revokeObjectURL(this.preview); this.preview=null; this.$refs.file.value=''; }, destroy(){ if(this.preview) URL.revokeObjectURL(this.preview); } }">
    @if($cover)<img width="140" class="rounded d-block mb-2" src="{{ route('admin.inventory.photo', $cover) }}" alt="Foto utama saat ini">@endif
    <label for="unit-photo">Foto {{ $unitId ? '(opsional)' : 'utama (wajib)' }}</label>
    <input id="unit-photo" x-ref="file" type="file" class="form-control" wire:model="photo" accept="image/jpeg,image/png,image/webp"
        @change="if(preview) URL.revokeObjectURL(preview); const file=$event.target.files[0]; preview=file && ['image/jpeg','image/png','image/webp'].includes(file.type) && file.size<=5242880 ? URL.createObjectURL(file) : null">
    <small>JPEG, PNG atau WebP hingga 5 MiB.</small>
    <div wire:loading wire:target="photo" role="status">Mengunggah foto…</div>
    <template x-if="preview"><img :src="preview" alt="Preview foto barang" class="d-block rounded mt-2" width="150"></template>
    <button x-show="preview" type="button" class="btn btn-sm btn-outline-secondary mt-2" wire:loading.attr="disabled" wire:target="photo" @click="clear(); $wire.clearPhoto()">Hapus pilihan foto</button>
    @error('photo')<p class="text-danger">{{ $message }}</p>@enderror @error('photos')<p class="text-danger">{{ $message }}</p>@enderror
</div>
</div></fieldset>
<fieldset class="border rounded-3 p-3 mb-3"><legend class="float-none w-auto fs-6 px-2"><i class="bi bi-pencil" aria-hidden="true"></i> Informasi tambahan</legend><div class="row g-3">
<div class="col-12"><label for="unit-notes" class="form-label">Catatan</label><textarea id="unit-notes" class="form-control" wire:model="notes" maxlength="5000"></textarea>@error('notes')<div class="text-danger">{{ $message }}</div>@enderror</div>
@if($unitId)<div class="col-12"><label for="unit-reason" class="form-label">Alasan perubahan / hasil inspeksi</label><textarea id="unit-reason" class="form-control" wire:model="reason" maxlength="1000" required></textarea>@error('reason')<div class="text-danger">{{ $message }}</div>@enderror<p class="text-muted mt-2">Nonaktifkan untuk mengarsipkan unit. Unit yang belum kembali tidak dapat diubah.</p></div>@endif
</div></fieldset>
<div class="mt-3"><button class="btn btn-primary" type="submit" wire:loading.attr="disabled" wire:target="save">{{ $unitId && !$itemMode ? 'Simpan unit' : 'Simpan barang' }}</button> <a class="btn btn-light" href="{{ route('admin.inventory.units.index') }}">Kembali</a></div></form></div></div>
