<div>
    @if(session('success')) <div class="alert alert-success" role="status">{{ session('success') }}</div> @endif
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2"><h4>Kategori Inventaris</h4>
            @can('create', App\Models\InventoryCategory::class)<a class="btn btn-primary" href="{{ route('admin.inventory.categories.create') }}">Tambah Kategori</a>@endcan
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label for="category-search" class="form-label">Cari kategori</label><input id="category-search" class="form-control" wire:model.live.debounce.300ms="search" maxlength="150"></div>
                <div class="col-md-3"><label for="category-status" class="form-label">Status</label><select id="category-status" class="form-select" wire:model.live="status"><option value="all">Semua</option><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
                <div class="col-md-3"><label for="category-page" class="form-label">Per halaman</label><select id="category-page" class="form-select" wire:model.live="perPage"><option>10</option><option>25</option><option>50</option></select></div>
            </div>
            <div class="table-responsive"><table class="table table-hover align-middle">
                <thead><tr><th><button class="btn btn-link p-0" wire:click="sortBy('name')">Nama</button></th><th>Deskripsi</th><th><button class="btn btn-link p-0" wire:click="sortBy('is_active')">Status</button></th><th><button class="btn btn-link p-0" wire:click="sortBy('created_at')">Dibuat</button></th><th>Barang</th><th>Unit fisik</th><th>Aksi</th></tr></thead>
                <tbody>@forelse($categories as $category)<tr wire:key="category-{{ $category->id }}"><td>{{ $category->name }}</td><td class="text-break">{{ $category->description ?: '—' }}</td><td><span class="badge {{ $category->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td>{{ $category->created_at?->timezone('Asia/Jakarta')->format('d/m/Y') }}</td><td>{{ $category->items_count ?? '—' }}</td><td>{{ $category->item_units_count ?? $category->units_count }}</td><td><a class="btn btn-sm btn-light" href="{{ route('admin.inventory.units.index', ['category' => $category->id]) }}">Lihat barang</a> @can('update', $category)<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.inventory.categories.edit', $category) }}">Edit / Status</a>@endcan</td></tr>@empty<tr><td colspan="7" class="text-center text-muted">Kategori tidak ditemukan.</td></tr>@endforelse</tbody>
            </table></div>
            {{ $categories->links() }}
        </div>
    </div>
</div>
