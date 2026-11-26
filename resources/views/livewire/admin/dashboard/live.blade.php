@use('App\Models\LiveStream')
@can('viewAny', LiveStream::class)
<section class="card dashboard-panel h-100" aria-labelledby="dashboard-live-title">
    <div class="card-header dashboard-panel-header">
        <div>
            <span class="dashboard-section-kicker">Siaran langsung · Status manual</span>
            <h2 id="dashboard-live-title"><i class="bi bi-broadcast me-2"></i>Live Streaming</h2>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('create', LiveStream::class)
                <a class="btn btn-sm btn-primary" href="{{ route('admin.live-streams.create') }}"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah Live</a>
            @endcan
            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.live-streams.index') }}">Kelola Live</a>
        </div>
    </div>
    <div class="card-body">
        @unless($dashboard['live']['available'])
            <div class="dashboard-empty compact" role="status">
                <i class="bi bi-exclamation-circle"></i>
                <span>Ringkasan Live belum dapat dimuat. Silakan coba lagi.</span>
            </div>
        @else
            <div class="coverage-status-grid mb-3" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                <div><span class="status-dot success"></span><small>Sedang Live</small><strong>{{ number_format($dashboard['live']['on_air']) }}</strong></div>
                <div><span class="status-dot primary"></span><small>Jadwal mendatang</small><strong>{{ number_format($dashboard['live']['upcoming']) }}</strong></div>
                <div><span class="status-dot warning"></span><small>Tanpa arsip valid</small><strong>{{ number_format($dashboard['live']['missing_archive']) }}</strong></div>
            </div>
            <div class="dashboard-list">
                @forelse($dashboard['live']['latest'] as $stream)
                    <div class="dashboard-list-item">
                        <div class="list-icon {{ $stream->status === 'live' ? 'warning' : 'primary' }}"><i class="bi bi-broadcast"></i></div>
                        <div class="list-copy">
                            <strong>{{ $stream->display_title }}</strong>
                            <span>{{ $stream->display_program?->name ?? 'Tanpa Program' }} · {{ $stream->schedule_date }} · {{ $stream->time_label }}</span>
                        </div>
                        <div class="list-actions">
                            <span class="badge live-status-{{ $stream->status }}">{{ LiveStream::STATUS_LABELS[$stream->status] ?? ucfirst($stream->status) }}</span>
                            <a href="{{ route('admin.live-streams.show', $stream->id) }}" aria-label="Detail {{ $stream->display_title }}"><i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                @empty
                    <div class="dashboard-empty compact"><i class="bi bi-broadcast"></i><span>Belum ada jadwal Live mendatang.</span></div>
                @endforelse
            </div>
        @endunless
    </div>
</section>
@endcan
