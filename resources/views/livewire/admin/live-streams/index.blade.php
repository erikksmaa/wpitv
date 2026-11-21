<div class="live-admin-page">
    <div class="page-heading d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div><h3>Live Streaming</h3><p class="text-muted mb-0">Kelola siaran YouTube dengan jadwal dari Agenda WPiTV.</p></div>
        @if(!$loadError)@can('create', App\Models\LiveStream::class)<a href="{{ route('admin.live-streams.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah Live</a>@endcan @endif
    </div>
    @include('livewire.admin.live-streams.feedback')
    @if(!$loadError)
        <section class="card"><div class="card-body">
            <div class="row g-3 live-filter-grid">
                <div class="col-md-6 col-xl-4"><label for="live-search" class="form-label">Cari judul atau ID YouTube</label><input id="live-search" type="search" wire:model.live.debounce.350ms="search" maxlength="150" class="form-control"></div>
                <div class="col-md-3 col-xl-2"><label for="live-status" class="form-label">Status manual</label><select id="live-status" wire:model.live="status" class="form-select"><option value="all">Semua status</option>@foreach(App\Models\LiveStream::STATUSES as $value)<option value="{{ $value }}">{{ ucfirst($value) }}</option>@endforeach</select></div>
                <div class="col-md-3 col-xl-2"><label for="live-visibility" class="form-label">Visibilitas</label><select id="live-visibility" wire:model.live="visibility" class="form-select"><option value="all">Semua</option><option value="public">Publik</option><option value="internal">Internal</option></select></div>
                <div class="col-md-6 col-xl-4"><label for="live-program" class="form-label">Program</label><select id="live-program" wire:model.live="programId" class="form-select"><option value="">Semua Program</option>@foreach($programs as $program)<option value="{{ $program->id }}">{{ $program->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label for="live-from" class="form-label">Tanggal Agenda dari (WIB)</label><input id="live-from" type="date" wire:model.live="from" class="form-control"></div>
                <div class="col-md-3"><label for="live-to" class="form-label">Sampai tanggal</label><input id="live-to" type="date" wire:model.live="to" class="form-control"></div>
                <div class="col-md-3"><label for="live-sort" class="form-label">Urutkan</label><select id="live-sort" wire:model.live="sort" class="form-select"><option value="schedule">Jadwal</option><option value="title">Judul</option><option value="status">Status</option><option value="updated_at">Perubahan terakhir</option></select></div>
                <div class="col-md-3"><label for="live-direction" class="form-label">Arah urutan</label><select id="live-direction" wire:model.live="direction" class="form-select"><option value="asc">Naik</option><option value="desc">Turun</option></select></div>
                <div class="col-md-3"><label for="live-per-page" class="form-label">Baris per halaman</label><select id="live-per-page" wire:model.live="perPage" class="form-select"><option>10</option><option>25</option><option>50</option></select></div>
                <div class="col-md-3 d-flex align-items-end"><button type="button" wire:click="resetFilters" class="btn btn-light">Reset filter</button></div>
            </div>
        </div></section>
        <section class="card"><div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Acara / Program</th><th>Jadwal WIB</th><th>Status WPiTV</th><th>Visibilitas</th><th>YouTube / arsip</th><th>Aksi</th></tr></thead>
            <tbody>@forelse($streams as $stream)
                <tr wire:key="stream-{{ $stream->id }}"><td><strong>{{ $stream->display_title }}</strong><small class="d-block text-muted">{{ $stream->display_program?->name ?? 'Tanpa Program' }}</small></td>
                    <td>{{ $stream->schedule_date }}<small class="d-block text-muted">{{ $stream->time_label }}</small></td>
                    <td><span class="badge live-status-{{ $stream->status }}">{{ ucfirst($stream->status) }}</span><small class="d-block text-muted">Manual</small></td>
                    <td>{{ $stream->visibility === 'public' ? 'Publik' : 'Internal' }}</td>
                    <td><code>{{ $stream->youtube_id ?? 'Belum ada ID' }}</code><small class="d-block text-muted">{{ $stream->hasValidArchive() ? 'Arsip terhubung' : 'Arsip belum tersedia / perlu ditinjau' }}</small></td>
                    <td><a href="{{ route('admin.live-streams.show', $stream->id) }}" class="btn btn-outline-primary btn-sm">Detail</a></td>
                </tr>
            @empty<tr><td colspan="6" class="text-center p-5"><i class="bi bi-broadcast fs-2 d-block mb-2"></i>Belum ada Live Streaming yang cocok dengan filter.</td></tr>@endforelse</tbody>
        </table></div><div class="card-body">{{ $streams->links() }}</div></section>
    @endif
</div>
