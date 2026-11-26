<div class="live-admin-page">
    <div class="page-heading d-flex flex-wrap align-items-end justify-content-between gap-3">
        <div>
            <nav aria-label="Breadcrumb"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li class="breadcrumb-item active" aria-current="page">Live Streaming</li></ol></nav>
            <h1 class="h3 mb-1">Live Streaming</h1>
            <p class="text-muted mb-0">Kelola siaran YouTube secara manual maupun terhubung dengan Agenda WPiTV.</p>
        </div>
        @if(!$loadError)@can('create', App\Models\LiveStream::class)<a href="{{ route('admin.live-streams.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah Live</a>@endcan @endif
    </div>
    @include('livewire.admin.live-streams.feedback')
    @if(!$loadError)
        <section class="card"><div class="card-body pt-3">
            <div class="row g-3 live-filter-grid">
                <div class="col-md-6 col-xl-4"><label for="live-search" class="form-label small mb-1">Cari judul atau ID YouTube</label><input id="live-search" type="search" wire:model.live.debounce.350ms="search" maxlength="150" class="form-control" placeholder="Judul siaran atau ID YouTube…"></div>
                <div class="col-md-3 col-xl-2"><label for="live-status" class="form-label small mb-1">Status siaran</label><select id="live-status" wire:model.live="status" class="form-select"><option value="all">Semua status</option>@foreach(App\Models\LiveStream::STATUS_LABELS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-3 col-xl-2"><label for="live-visibility" class="form-label small mb-1">Visibilitas</label><select id="live-visibility" wire:model.live="visibility" class="form-select"><option value="all">Semua</option><option value="public">Publik</option><option value="internal">Internal</option></select></div>
                <div class="col-md-6 col-xl-4"><label for="live-program" class="form-label small mb-1">Program</label><select id="live-program" wire:model.live="programId" class="form-select"><option value="">Semua Program</option>@foreach($programs as $program)<option value="{{ $program->id }}">{{ $program->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label for="live-from" class="form-label small mb-1">Tanggal mulai (WIB)</label><input id="live-from" type="date" wire:model.live="from" class="form-control"></div>
                <div class="col-md-3"><label for="live-to" class="form-label small mb-1">Sampai tanggal</label><input id="live-to" type="date" wire:model.live="to" class="form-control"></div>
                <div class="col-md-2"><label for="live-sort" class="form-label small mb-1">Urutkan</label><select id="live-sort" wire:model.live="sort" class="form-select"><option value="schedule">Jadwal</option><option value="title">Judul</option><option value="status">Status</option><option value="updated_at">Perubahan terakhir</option></select></div>
                <div class="col-md-2"><label for="live-direction" class="form-label small mb-1">Arah</label><select id="live-direction" wire:model.live="direction" class="form-select"><option value="asc">Naik</option><option value="desc">Turun</option></select></div>
                <div class="col-auto"><label for="live-per-page" class="form-label small mb-1">Per halaman</label><select id="live-per-page" wire:model.live="perPage" class="form-select"><option>10</option><option>25</option><option>50</option></select></div>
                <div class="col-auto d-flex align-items-end"><button type="button" wire:click="resetFilters" class="btn btn-outline-secondary">Reset filter</button></div>
            </div>
        </div></section>
        <section class="card"><div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Acara / Program</th><th>Jadwal WIB</th><th>Status WPiTV</th><th>Visibilitas</th><th>YouTube / Arsip</th><th>Aksi</th></tr></thead>
            <tbody>@forelse($streams as $stream)
                <tr wire:key="stream-{{ $stream->id }}">
                    <td>
                        <strong>{{ $stream->display_title }}</strong>
                        <div class="small text-muted">{{ $stream->display_program?->name ?? 'Tanpa Program' }} · <span class="badge bg-light-secondary text-secondary">{{ $stream->isManual() ? 'Manual' : 'Agenda' }}</span></div>
                    </td>
                    <td>{{ $stream->schedule_date }}<small class="d-block text-muted">{{ $stream->time_label }}</small></td>
                    <td><span class="badge live-status-{{ $stream->status }}">{{ App\Models\LiveStream::STATUS_LABELS[$stream->status] ?? ucfirst($stream->status) }}</span></td>
                    <td><span class="badge {{ $stream->visibility === 'public' ? 'bg-light-primary text-primary' : 'bg-secondary' }}">{{ $stream->visibility === 'public' ? 'Publik' : 'Internal' }}</span></td>
                    <td><code>{{ $stream->youtube_id ?? 'Belum ada ID' }}</code><small class="d-block text-muted">{{ $stream->hasValidArchive() ? 'Arsip terhubung' : ($stream->status === 'ended' ? 'Belum ada arsip' : '—') }}</small></td>
                    <td><a href="{{ route('admin.live-streams.show', $stream->id) }}" class="btn btn-outline-primary btn-sm">Detail</a></td>
                </tr>
            @empty<tr><td colspan="6" class="text-center p-5 text-muted"><i class="bi bi-broadcast fs-2 d-block mb-2" aria-hidden="true"></i>Belum ada Live Streaming yang cocok dengan filter.</td></tr>@endforelse</tbody>
        </table></div><div class="card-body">{{ $streams->links() }}</div></section>
    @endif
</div>
