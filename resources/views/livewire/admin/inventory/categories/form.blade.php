<div class="card">
    <div class="card-header"><h4>{{ $categoryId ? 'Edit Kategori' : 'Tambah Kategori' }}</h4></div>
    <div class="card-body">
        <form wire:submit.prevent="save">
            <div class="mb-3"><label for="category-name" class="form-label">Nama kategori</label>
                <input id="category-name" class="form-control" wire:model="name" maxlength="100" required>
                @error('name') <div class="text-danger">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3"><label for="category-description" class="form-label">Deskripsi</label>
                <textarea id="category-description" class="form-control" wire:model="description" maxlength="1000"></textarea>
                @error('description') <div class="text-danger">{{ $message }}</div> @enderror
            </div>
            <div class="form-check mb-3"><input id="category-active" class="form-check-input" type="checkbox" wire:model="isActive"><label for="category-active" class="form-check-label">Aktif</label></div>
            @error('isActive') <div class="text-danger">{{ $message }}</div> @enderror
            @if($categoryId)
                <p class="text-muted">Nonaktifkan kategori untuk mengarsipkannya. Unit dan riwayat tetap tersimpan.</p>
                <div class="mb-3"><label for="category-reason" class="form-label">Alasan perubahan</label><textarea id="category-reason" class="form-control" wire:model="reason" maxlength="1000" required></textarea>
                @error('reason') <div class="text-danger">{{ $message }}</div> @enderror</div>
            @endif
            <button class="btn btn-primary" type="submit" wire:loading.attr="disabled" wire:target="save">Simpan</button>
            <a class="btn btn-light" href="{{ route('admin.inventory.categories.index') }}">Kembali</a>
        </form>
    </div>
</div>
