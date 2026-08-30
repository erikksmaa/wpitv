<div class="org-admin-page">
    @if(session('success'))<div class="alert alert-success d-none" data-admin-flash>{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger d-none" data-admin-flash>{{ session('error') }}</div>@endif

    <nav class="org-admin-back"><a href="{{ route('admin.tvclub.generations.index') }}"><i class="bi bi-arrow-left"></i> Kembali ke Generasi</a></nav>
    <div class="org-admin-hero org-admin-hero--tvclub">
        <div><span class="org-admin-eyebrow">TV Club · {{ $generation->year }}</span><h1>Struktur Organisasi TV Club</h1><p>{{ $generation->name }} · Kelola hierarki organisasi dan anggota TV Club untuk generasi ini.</p></div>
        @can('create', App\Models\TvClubOrganizationPosition::class)<button type="button" class="btn btn-primary" wire:click="createPosition"><i class="bi bi-plus-lg me-1"></i> Tambah Posisi</button>@endcan
    </div>

    <section class="card org-admin-preview" aria-labelledby="tvOrgPreviewTitle">
        <div class="card-header"><div><span>Pratinjau Otomatis</span><h2 id="tvOrgPreviewTitle">Hierarki {{ $generation->name }}</h2></div><span class="badge bg-light-primary text-primary">{{ $positions->count() }} posisi</span></div>
        <div class="card-body">
            @if($tree)
                <div class="org-admin-tree-scroll"><ul class="org-admin-tree org-admin-tree--roots">
                    @foreach($tree as $node)<x-admin.organization-tree-node :node="$node" tv-club :can-manage="auth()->user()->can('create', App\Models\TvClubOrganizationPosition::class)" />@endforeach
                </ul></div>
            @else
                <div class="org-admin-empty"><i class="bi bi-people"></i><h3>Struktur generasi belum disusun</h3><p>Tambahkan posisi utama, lalu tempatkan anggota dari {{ $generation->name }}.</p></div>
            @endif
        </div>
    </section>

    <section class="card org-admin-list" aria-labelledby="tvOrgListTitle">
        <div class="card-header"><div><span>Pengelolaan Data</span><h2 id="tvOrgListTitle">Posisi &amp; Anggota</h2></div><span class="text-muted small">Anggota hanya berasal dari generasi ini</span></div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Posisi</th><th>Atasan</th><th>Anggota</th><th>Urutan</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            @forelse($positions as $position)
                <tr wire:key="tv-position-{{ $position->id }}">
                    <td><strong>{{ $position->name }}</strong></td>
                    <td>{{ $positions->firstWhere('id', $position->parent_id)?->name ?? 'Posisi Utama / Root' }}</td>
                    <td><div class="org-admin-assignees">@forelse($position->assignments as $assignment)<span><b>{{ $assignment->member->initials }}</b><span>{{ $assignment->member->name }}<small>{{ $assignment->member->student_number }}</small></span>@can('assignMember', $position)<button wire:click="removeMember({{ $position->id }}, {{ $assignment->id }})" data-admin-confirm="Apakah Anda yakin ingin menghapus anggota dari posisi ini?" aria-label="Hapus {{ $assignment->member->name }} dari posisi"><i class="bi bi-x"></i></button>@endcan</span>@empty<em>Belum ada anggota</em>@endforelse</div></td>
                    <td>{{ $position->sort_order }}</td>
                    <td><span class="badge {{ $position->status ? 'bg-light-success text-success' : 'bg-light-warning text-warning' }}">{{ $position->status ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="text-end"><div class="btn-group btn-group-sm">@can('assignMember', $position)<button class="btn btn-outline-success" wire:click="openMemberForm({{ $position->id }})" aria-label="Tambah anggota ke {{ $position->name }}"><i class="bi bi-person-plus"></i></button>@endcan @can('update', $position)<button class="btn btn-outline-primary" wire:click="editPosition({{ $position->id }})" aria-label="Edit {{ $position->name }}"><i class="bi bi-pencil"></i></button>@endcan @can('delete', $position)<button class="btn btn-outline-danger" wire:click="deletePosition({{ $position->id }})" data-admin-confirm="Apakah Anda yakin ingin menghapus posisi ini?" aria-label="Hapus {{ $position->name }}"><i class="bi bi-trash"></i></button>@endcan</div></td>
                </tr>
            @empty<tr><td colspan="6"><div class="org-admin-empty org-admin-empty--compact">Belum ada posisi organisasi pada generasi ini.</div></td></tr>@endforelse
            </tbody>
        </table></div>
    </section>

    @if($showPositionForm)
        <div class="modal fade show d-block org-admin-modal" role="dialog" aria-modal="true" aria-labelledby="tvPositionFormTitle" wire:key="tv-position-form">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <div class="modal-header"><div><span>{{ $generation->name }}</span><h2 id="tvPositionFormTitle">{{ $positionId ? 'Edit Posisi' : 'Tambah Posisi' }}</h2></div><button type="button" class="btn-close" wire:click="closePositionForm" aria-label="Tutup"></button></div>
                <form wire:submit="savePosition"><div class="modal-body">
                    <div class="mb-3"><label for="tv-position-name" class="form-label">Nama Posisi</label><input id="tv-position-name" class="form-control @error('name') is-invalid @enderror" wire:model="name" maxlength="100">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="mb-3"><label for="tv-position-parent" class="form-label">Parent / Atasan</label><select id="tv-position-parent" class="form-select @error('parent_id') is-invalid @enderror" wire:model="parent_id"><option value="">— Posisi Utama / Root —</option>@foreach($positions as $candidate)@unless(in_array($candidate->id, $invalidParentIds, true))<option value="{{ $candidate->id }}">{{ $candidate->name }}{{ $candidate->status ? '' : ' · Nonaktif' }}</option>@endunless @endforeach</select>@error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="row g-3"><div class="col-6"><label for="tv-position-sort" class="form-label">Urutan</label><input id="tv-position-sort" type="number" min="0" class="form-control @error('sort_order') is-invalid @enderror" wire:model="sort_order">@error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-6"><label for="tv-position-status" class="form-label">Status</label><select id="tv-position-status" class="form-select" wire:model="status"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div></div>
                </div><div class="modal-footer"><button type="button" class="btn btn-light" wire:click="closePositionForm">Batal</button><button class="btn btn-primary" type="submit">{{ $positionId ? 'Simpan Perubahan' : 'Tambah Posisi' }}</button></div></form>
            </div></div>
        </div>
    @endif

    @if($showMemberForm)
        @php($memberPosition = $positions->firstWhere('id', $memberPositionId))
        <div class="modal fade show d-block org-admin-modal" role="dialog" aria-modal="true" aria-labelledby="tvMemberFormTitle" wire:key="tv-member-form">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <div class="modal-header"><div><span>Penempatan Anggota</span><h2 id="tvMemberFormTitle">{{ $memberPosition?->name }}</h2></div><button type="button" class="btn-close" wire:click="closeMemberForm" aria-label="Tutup"></button></div>
                <form wire:submit="addMember"><div class="modal-body">
                    <div class="org-admin-context"><i class="bi bi-shield-check"></i><span>Hanya menampilkan anggota <strong>{{ $generation->name }}</strong>.</span></div>
                    <div class="mb-3"><label for="tv-org-member" class="form-label">Anggota</label><select id="tv-org-member" class="form-select @error('member_id') is-invalid @enderror" wire:model="member_id"><option value="">— Pilih Anggota —</option>@foreach($availableMembers as $member)<option value="{{ $member->id }}">{{ $member->name }} · {{ $member->student_number }}{{ $member->status ? '' : ' · Nonaktif' }}</option>@endforeach</select>@error('member_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div><label for="tv-member-sort" class="form-label">Urutan Anggota</label><input id="tv-member-sort" type="number" min="0" class="form-control @error('member_sort_order') is-invalid @enderror" wire:model="member_sort_order">@error('member_sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                </div><div class="modal-footer"><button type="button" class="btn btn-light" wire:click="closeMemberForm">Batal</button><button class="btn btn-primary" type="submit">Tambah Anggota</button></div></form>
            </div></div>
        </div>
    @endif
</div>
