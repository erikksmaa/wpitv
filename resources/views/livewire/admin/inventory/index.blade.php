@use('App\Livewire\Admin\Inventory\InventoryActivity', 'Activity')
@use('App\Services\Equipment\LoanRules')
<div class="inventory-page" x-on:equipment-feedback.window="window.WPiAlert?.toast($event.detail.message, $event.detail.type)">
    <style>
        .inventory-dialog[open] { display:block; position:fixed; border:0; background:transparent; padding:0; max-height:100dvh; max-width:100vw; margin:0; }
        .inventory-dialog::backdrop { background:rgba(8,16,32,.65); }
        .inventory-dialog .modal-dialog { pointer-events:auto; }
        .inventory-actions { position:relative; }
        .inventory-actions summary { list-style:none; }
        .inventory-actions summary::-webkit-details-marker { display:none; }
        .inventory-actions .dropdown-menu { position:absolute; right:0; top:100%; z-index:25; min-width:12rem; max-width:calc(100vw - 2rem); }
        .inventory-actions .dropdown-item { white-space:normal; }
        .unit-photo-gallery { width:220px; max-width:100%; }
        .unit-lightbox { position:fixed; inset:0; margin:auto; padding:0; width:fit-content; max-width:calc(100vw - 24px); max-height:94dvh; border:1px solid #ffffff30; border-radius:16px; color:#fff; background:#151b26; box-shadow:0 16px 60px #0008; }
        .unit-lightbox::backdrop { background:rgba(10,15,24,.84); backdrop-filter:blur(5px); }
        .unit-lightbox-image { display:block; margin:auto; max-width:min(900px,100%); max-height:min(74dvh,calc(94dvh - 70px)); object-fit:contain; }
        .unit-lightbox-bar { display:flex; align-items:center; gap:6px; padding:10px 12px; }
        .unit-lightbox-bar strong { flex:1; min-width:0; overflow-wrap:anywhere; }
        .unit-lightbox-bar small { color:#d1dae9; white-space:nowrap; }
        .unit-lightbox-bar button { flex:none; width:36px; height:36px; border-radius:50%; border:1px solid #ffffff35; color:white; background:#ffffff0d; }
        .unit-lightbox-bar button:hover,.unit-lightbox-bar button:focus-visible { background:#39465c; }
        .unit-photo-stage { background:#17202c; }
        .unit-photo-open { display:block; position:relative; background:transparent; }
        .unit-photo-image { width:100%; aspect-ratio:16/10; object-fit:contain; transition:transform .2s ease; }
        .unit-photo-open:hover .unit-photo-image { transform:scale(1.04); }
        .unit-photo-zoom { position:absolute; bottom:8px; left:50%; transform:translateX(-50%); opacity:0; transition:opacity .2s; font-size:.65rem; }
        .unit-photo-open:hover .unit-photo-zoom,.unit-photo-open:focus-visible .unit-photo-zoom { opacity:1; }
        .unit-photo-prev,.unit-photo-next { position:absolute; top:50%; transform:translateY(-50%); padding:4px 7px; font-size:.75rem; }
        .unit-photo-prev { left:6px; }
        .unit-photo-next { right:6px; }
        .unit-photo-count { position:absolute; top:6px; right:6px; pointer-events:none; font-size:.65rem; }
        .unit-photo-thumb { border:2px solid transparent; background:transparent; }
        @media (prefers-reduced-motion:reduce) { .unit-photo-image,.unit-photo-zoom { transition:none; } }
    </style>
    @php($heading = ['categories'=>'Kategori','models'=>'Peralatan','audit'=>'Riwayat Aktivitas'][$tab])
    <div class="page-heading d-flex flex-wrap gap-3 justify-content-between align-items-end">
        <div>
            <nav aria-label="Breadcrumb"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li class="breadcrumb-item"><a href="{{ route('admin.inventory.equipment') }}">Inventaris</a></li><li class="breadcrumb-item active" aria-current="page">{{ $heading }}</li></ol></nav>
            <h1 class="h3 mb-0">{{ $heading }}</h1>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if($tab === 'categories')
                @can('equipment.category.create')<button type="button" class="btn btn-primary" wire:click="begin('category.create')"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah kategori</button>@endcan
            @elseif($tab === 'models')
                @can('equipment.model.create')<button type="button" class="btn btn-primary" wire:click="begin('model.create')" @disabled(!$categories->contains('is_active', 1))><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah peralatan</button>@endcan
            @endif
        </div>
    </div>
    @if($summary)
        @include('livewire.admin.inventory.summary', $summary)
    @endif
    @if($tab === 'models' && !$categories->contains('is_active', 1))
        <div class="alert alert-light-warning border d-flex align-items-center gap-2 py-2" role="status">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <span>Buat atau aktifkan <a href="{{ route('admin.inventory.categories') }}">kategori</a> sebelum menambahkan peralatan.</span>
        </div>
    @endif
    @if($model && $tab === 'models')
        <div class="alert alert-light-primary border d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
            <span class="small">Menampilkan peralatan terpilih dari laporan{{ $unitStatus ? ' · Status unit: '.\App\Services\Equipment\EquipmentReports::STATUS[$unitStatus] : '' }}{{ $unitCondition ? ' · Kondisi: '.\App\Services\Equipment\EquipmentReports::CONDITIONS[$unitCondition] : '' }}.</span>
            <a href="{{ route('admin.inventory.equipment') }}" class="btn btn-sm btn-outline-primary">Tampilkan semua peralatan</a>
        </div>
    @endif

    <section class="card"><div class="card-body pt-3">
        <div class="row g-2 align-items-end mb-3">
            <div class="col-sm-6 col-lg-4"><label for="eq-search" class="form-label small mb-1">Pencarian</label><input id="eq-search" wire:model.live.debounce.300ms="search" maxlength="100" class="form-control" placeholder="{{ $tab === 'audit' ? 'Nama pengguna, aktivitas, atau alasan' : 'Nama, kode, atau serial…' }}"></div>
            @if($tab === 'models')
                <div class="col-sm-6 col-lg-3"><label for="eq-category-filter" class="form-label small mb-1">Kategori</label><select id="eq-category-filter" wire:model.live="category" class="form-select"><option value="">Semua kategori</option>@foreach($categories as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div>
            @endif
            @if($tab !== 'audit')
                @unless($model && $tab === 'models')
                    <div class="col-sm-6 col-lg-2"><label for="eq-status-filter" class="form-label small mb-1">Status</label><select id="eq-status-filter" wire:model.live="status" class="form-select"><option value="">Semua status</option><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
                @endunless
            @else
                <div class="col-sm-6 col-lg-3"><label for="activity" class="form-label small mb-1">Aktivitas</label><select id="activity" wire:model.live="activity" class="form-select"><option value="">Semua aktivitas</option>@foreach(Activity::LABELS as $event => $label)<option value="{{ $event }}">{{ $label }}</option>@endforeach</select></div>
                <div class="col-6 col-lg-2"><label for="activity-from" class="form-label small mb-1">Dari (WIB)</label><input id="activity-from" type="date" class="form-control" wire:model.live="from"></div>
                <div class="col-6 col-lg-2"><label for="activity-until" class="form-label small mb-1">Sampai (WIB)</label><input id="activity-until" type="date" class="form-control" wire:model.live="until" min="{{ $from ?: null }}"></div>
            @endif
            <div class="col-auto"><label for="eq-page-size" class="form-label small mb-1">Per halaman</label><select id="eq-page-size" wire:model.live="perPage" class="form-select">@foreach([10,25,50] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach</select></div>
            <div class="col-auto"><button type="button" class="btn btn-outline-secondary" wire:click="resetFilters">Reset filter</button></div>
        </div>
        @if($tab === 'audit' && $from && $until && $from > $until)
            <p class="text-danger" role="alert">Tanggal sampai harus sama dengan atau sesudah tanggal mulai.</p>
        @endif
        <p wire:loading.delay class="small text-muted" role="status">Memuat data…</p>

        @if($tab === 'categories')
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Kode</th>
                            <th scope="col">Nama kategori</th>
                            <th scope="col">Deskripsi</th>
                            <th scope="col">Status</th>
                            @can('equipment.category.update')<th scope="col">Tindakan</th>@endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr wire:key="inventory-categories-{{ $row->id }}">
                                <td>{{ $row->code }}</td>
                                <th scope="row" class="fw-semibold text-break">{{ $row->name }}</th>
                                <td class="text-break">{{ $row->description ?: '—' }}</td>
                                <td><span class="badge {{ $row->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $row->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                @can('equipment.category.update')
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="begin('category.update', {{ $row->id }})" aria-label="Edit kategori {{ $row->name }}">
                                            <i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit
                                        </button>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr><td colspan="{{ auth()->user()->can('equipment.category.update') ? 5 : 4 }}" class="text-center text-muted py-3">{{ $search || $status !== '' ? 'Tidak ada kategori yang cocok dengan filter.' : 'Belum ada kategori.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
        @forelse($rows as $row)
            <div class="border-top py-3" wire:key="inventory-{{ $tab }}-{{ $row->id }}">
                @if($tab === 'models')
                    <div class="row g-3 align-items-start">
                        <div class="col-12 col-md-5 d-flex gap-3 align-items-start">
                            @if($row->cover_id && auth()->user()->can('equipment.photo.view'))
                                <img src="{{ route('admin.inventory.photos',['kind'=>'model','photo'=>$row->cover_id]) }}" width="56" height="56" alt="" class="object-fit-cover rounded flex-shrink-0" loading="lazy">
                            @else
                                <span class="rounded border p-3 flex-shrink-0"><i class="bi bi-box-seam" aria-hidden="true"></i></span>
                            @endif
                            <div class="text-break"><h2 class="h6 mb-1">{{ $row->name }}</h2><div class="small text-muted">{{ $row->category_name }} · {{ $row->model_code }}</div><span class="badge {{ $row->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $row->is_active ? 'Aktif' : 'Nonaktif' }}</span></div>
                        </div>
                        <dl class="col-12 col-md mb-0 d-flex flex-wrap gap-3 gap-xl-4">
                            <div><dt class="small text-muted">Total unit</dt><dd class="fw-semibold mb-0">{{ $row->total ?? 0 }}</dd></div>
                            <div><dt class="small text-muted">Tersedia</dt><dd class="fw-semibold mb-0">{{ $row->is_active ? ($row->ready ?? 0) : 0 }}</dd></div>
                            <div><dt class="small text-muted">Dipinjam</dt><dd class="fw-semibold mb-0">{{ $row->borrowed ?? 0 }}</dd></div>
                        </dl>
                        <div class="col-12 col-md-auto d-flex gap-2 align-items-start">
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="toggleUnits({{ $row->id }})" aria-expanded="{{ $expanded === $row->id ? 'true' : 'false' }}" aria-controls="unit-list-{{ $row->id }}">{{ $expanded === $row->id ? 'Tutup detail unit' : 'Detail unit' }} <i class="bi bi-chevron-{{ $expanded === $row->id ? 'up' : 'down' }}" aria-hidden="true"></i></button>
                            @include('livewire.admin.inventory.actions', ['actions'=>['model.update'=>['Edit peralatan','pencil'],'stock.add'=>['Tambah stok','plus-lg'],'model.photo_add'=>['Unggah foto','camera'],'gallery'=>['Galeri foto','images']],'name'=>$row->name,'id'=>$row->id,'kind'=>'model'])
                        </div>
                    </div>
                    @if($detailKind === 'model' && $detailId === $row->id && $gallery)
                        @include('livewire.admin.inventory.gallery', ['name'=>$row->name])
                    @endif
                    @if($expanded === $row->id && $units)
                        @include('livewire.admin.inventory.units')
                    @endif
                @else
                    <div class="d-flex align-items-center gap-3">
                        <span class="d-inline-flex align-items-center justify-content-center rounded border p-2 lh-1 flex-shrink-0">
                            <i class="bi bi-clock-history" aria-hidden="true"></i>
                        </span>
                        <div class="row g-2 align-items-center flex-grow-1" style="min-width:0">
                            <div class="col-12 col-md text-break">
                                <h2 class="h6 mb-1">{{ Activity::LABELS[$row->event_name] ?? 'Mencatat aktivitas peralatan' }}</h2>
                                <div>{{ Activity::subject($row) }}</div>
                                <div class="small text-muted">Alasan · {{ Activity::reason($row->reason) }}</div>
                                <div class="small text-muted">{{ $row->actor_name_snapshot }}</div>
                            </div>
                            <div class="col-12 col-md-auto small text-muted text-md-end">{{ LoanRules::wib($row->occurred_at) }}</div>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-center text-muted py-4 mb-0">{{ $search || $category || $model || $activity || $from || $until || $status !== '' ? 'Tidak ada data yang cocok dengan filter.' : ($tab === 'audit' ? 'Belum ada aktivitas.' : 'Belum ada peralatan.') }}</p>
        @endforelse
        @endif
        {{ $rows->links() }}
    </div></section>

    @include('livewire.admin.inventory.form')
</div>
