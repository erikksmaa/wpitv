<div class="org-admin-page">
    @if(session('success'))<div class="alert alert-success d-none" data-admin-flash>{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger d-none" data-admin-flash>{{ session('error') }}</div>@endif

    <div class="org-admin-hero">
        <div><span class="org-admin-eyebrow">WPiTV · Organisasi</span><h1>Struktur Organisasi WPiTV</h1><p>Kelola posisi, susunan hierarki, dan personel organisasi WPiTV.</p></div>
        @can('create', App\Models\WpitvOrganizationPosition::class)
            <button type="button" class="btn btn-primary" wire:click="createPosition"><i class="bi bi-plus-lg me-1"></i> Tambah Posisi</button>
        @endcan
    </div>

    <section class="card org-admin-preview" aria-labelledby="wpitvOrgPreviewTitle">
        <div class="card-header"><div><span>Pratinjau Otomatis</span><h2 id="wpitvOrgPreviewTitle">Susunan Hierarki</h2></div><span class="badge bg-light-primary text-primary">{{ $positions->count() }} posisi</span></div>
        <div class="card-body">
            @if($tree)
                <div class="org-admin-tree-scroll"><ul class="org-admin-tree org-admin-tree--roots">
                    @foreach($tree as $node)<x-admin.organization-tree-node :node="$node" :can-manage="auth()->user()->can('create', App\Models\WpitvOrganizationPosition::class)" />@endforeach
                </ul></div>
            @else
                <div class="org-admin-empty"><i class="bi bi-diagram-3"></i><h3>Struktur belum disusun</h3><p>Tambahkan posisi utama untuk mulai membangun hierarki WPiTV.</p></div>
            @endif
        </div>
    </section>

    <section class="card org-admin-list" aria-labelledby="wpitvOrgListTitle">
        <div class="card-header"><div><span>Pengelolaan Data</span><h2 id="wpitvOrgListTitle">Daftar Posisi</h2></div></div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Posisi</th><th>Pengisi</th><th>Atasan</th><th>Urutan</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            @forelse($positions as $position)
                <tr wire:key="wpitv-position-{{ $position->id }}">
                    <td><strong>{{ $position->name }}</strong></td>
                    <td>@if($position->user)<span class="org-admin-person"><b>{{ $position->user->initials }}</b><span>{{ $position->user->name }}<small>{{ '@'.$position->user->username }}</small></span></span>@else<span class="text-muted">Belum ditetapkan</span>@endif</td>
                    <td>{{ $positions->firstWhere('id', $position->parent_id)?->name ?? 'Posisi Utama / Root' }}</td>
                    <td>{{ $position->sort_order }}</td>
                    <td><span class="badge {{ $position->status ? 'bg-light-success text-success' : 'bg-light-warning text-warning' }}">{{ $position->status ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="text-end">@can('update', $position)<button class="btn btn-sm btn-outline-primary" wire:click="editPosition({{ $position->id }})" aria-label="Edit {{ $position->name }}"><i class="bi bi-pencil"></i></button>@endcan @can('delete', $position)<button class="btn btn-sm btn-outline-danger" wire:click="deletePosition({{ $position->id }})" data-admin-confirm="Apakah Anda yakin ingin menghapus posisi ini?" aria-label="Hapus {{ $position->name }}"><i class="bi bi-trash"></i></button>@endcan</td>
                </tr>
            @empty<tr><td colspan="6"><div class="org-admin-empty org-admin-empty--compact">Belum ada posisi organisasi.</div></td></tr>@endforelse
            </tbody>
        </table></div>
    </section>

    @if($showForm)
        <div class="modal fade show d-block org-admin-modal" role="dialog" aria-modal="true" aria-labelledby="wpitvPositionFormTitle" wire:key="wpitv-position-form">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <div class="modal-header"><div><span>Struktur WPiTV</span><h2 id="wpitvPositionFormTitle">{{ $positionId ? 'Edit Posisi' : 'Tambah Posisi' }}</h2></div><button type="button" class="btn-close" wire:click="closeForm" aria-label="Tutup"></button></div>
                <form wire:submit="savePosition"><div class="modal-body">
                    <div class="mb-3"><label for="wpi-position-name" class="form-label">Nama Posisi</label><input id="wpi-position-name" class="form-control @error('name') is-invalid @enderror" wire:model="name" maxlength="100">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="mb-3"><label for="wpi-position-user" class="form-label">Pengisi Posisi</label><select id="wpi-position-user" class="form-select @error('user_id') is-invalid @enderror" wire:model="user_id"><option value="">— Belum ditetapkan —</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ '@'.$user->username }})</option>@endforeach</select>@error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="mb-3"><label for="wpi-position-parent" class="form-label">Parent / Atasan</label><select id="wpi-position-parent" class="form-select @error('parent_id') is-invalid @enderror" wire:model="parent_id"><option value="">— Posisi Utama / Root —</option>@foreach($positions as $candidate)@if($candidate->id !== $positionId)<option value="{{ $candidate->id }}">{{ $candidate->name }}{{ $candidate->status ? '' : ' · Nonaktif' }}</option>@endif @endforeach</select>@error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="row g-3"><div class="col-6"><label for="wpi-position-sort" class="form-label">Urutan</label><input id="wpi-position-sort" type="number" min="0" class="form-control @error('sort_order') is-invalid @enderror" wire:model="sort_order">@error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-6"><label for="wpi-position-status" class="form-label">Status</label><select id="wpi-position-status" class="form-select" wire:model="status"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div></div>
                </div><div class="modal-footer"><button type="button" class="btn btn-light" wire:click="closeForm">Batal</button><button class="btn btn-primary" type="submit">{{ $positionId ? 'Simpan Perubahan' : 'Tambah Posisi' }}</button></div></form>
            </div></div>
        </div>
    @endif
</div>
