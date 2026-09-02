@php
    if ($user->isTVClub()) {
        $cards = [
            ['Generasi aktif', $dashboard['tv_club']['active_generations'], $dashboard['tv_club']['generation']?->name ?? 'Belum ada generasi aktif', 'bi-calendar-check'],
            ['Anggota TV Club', $dashboard['tv_club']['members'], $dashboard['tv_club']['active_members'].' anggota aktif', 'bi-people'],
            ['Kelompok Produksi', $dashboard['tv_club']['groups'], 'Kelompok pada seluruh generasi', 'bi-camera-reels'],
            ['Posisi Organisasi', $dashboard['tv_club']['positions'], 'Posisi pada seluruh generasi', 'bi-diagram-3'],
        ];
    } elseif ($user->isAdminNews()) {
        $cards = [
            ['Total Berita', $dashboard['kpis']['news']['total'], 'Seluruh artikel tersimpan', 'bi-newspaper'],
            ['Berita Bulan Ini', $dashboard['kpis']['news']['this_month'], 'Berdasarkan tanggal dibuat', 'bi-file-earmark-plus'],
            ['Diperbarui Minggu Ini', $dashboard['kpis']['news']['updated_this_week'], 'Berdasarkan perubahan terakhir', 'bi-pencil-square'],
            ['Tayangan', $dashboard['kpis']['videos']['total'], 'Konteks pendukung editorial', 'bi-camera-video'],
        ];
    } else {
        $cards = [
            ['Program Aktif', $dashboard['kpis']['programs']['active'], $dashboard['kpis']['programs']['total'].' total program', 'bi-collection-play'],
            ['Tayangan', $dashboard['kpis']['videos']['total'], $dashboard['kpis']['videos']['this_month'].' tayang bulan ini', 'bi-camera-video'],
            ['Agenda Mendatang', $dashboard['agenda']['count'], 'Agenda berstatus terjadwal', 'bi-calendar-event'],
            ['Peliputan Menunggu', $dashboard['coverage']['counts']['pending'], $dashboard['coverage']['counts']['in_progress'].' sedang berjalan', 'bi-camera-reels'],
            ['Kelompok Produksi', $dashboard['kpis']['groups'], 'Sumber daya produksi', 'bi-people'],
        ];
    }
@endphp
<section class="row g-3 mb-3" aria-label="Indikator sesuai peran">
    @foreach($cards as [$label, $value, $description, $icon])
        <div class="col-12 col-sm-6 {{ $user->isAdminProduction() ? 'col-xl' : 'col-xl-3' }}"><article class="dashboard-kpi kpi-program h-100"><div class="dashboard-kpi-icon"><i class="bi {{ $icon }}"></i></div><div class="dashboard-kpi-copy"><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong><small>{{ $description }}</small></div></article></div>
    @endforeach
</section>

@if($user->isAdminNews())
    <section class="card dashboard-panel mb-3" aria-labelledby="editorial-title">
        <div class="card-header dashboard-panel-header"><div><span class="dashboard-section-kicker">Meja editorial</span><h2 id="editorial-title"><i class="bi bi-newspaper me-2"></i>Berita Terakhir Diperbarui</h2></div><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.news.index') }}">Lihat berita</a></div>
        <div class="card-body dashboard-list">
            @forelse($dashboard['latest']['news'] as $news)
                <div class="dashboard-list-item"><div class="list-icon info"><i class="bi bi-file-earmark-text"></i></div><div class="list-copy"><strong>{{ $news->title }}</strong><span>{{ $news->author_name ?: 'Redaksi WPiTV' }} · {{ $news->updated_at?->locale('id')->translatedFormat('d M Y H:i') ?? 'Waktu belum tercatat' }}</span></div>@can('update', $news)<a href="{{ route('admin.news.edit', $news) }}" aria-label="Edit {{ $news->title }}"><i class="bi bi-arrow-right"></i></a>@endcan</div>
            @empty
                <div class="dashboard-empty"><i class="bi bi-newspaper"></i><span>Belum ada berita. Mulai dengan menulis artikel pertama.</span></div>
            @endforelse
        </div>
    </section>
@endif

@if($user->isTVClub())
    <section class="card dashboard-panel mb-3" aria-labelledby="club-organization-title">
        <div class="card-header dashboard-panel-header"><div><span class="dashboard-section-kicker">Generasi aktif terbaru</span><h2 id="club-organization-title"><i class="bi bi-diagram-3 me-2"></i>Organisasi TV Club</h2></div></div>
        <div class="card-body">
            @if($dashboard['tv_club']['generation'])
                <p class="fw-semibold">{{ $dashboard['tv_club']['generation']->name }}</p>
                <div class="tvclub-metrics"><div><span>Posisi terisi anggota aktif</span><strong>{{ $dashboard['tv_club']['filled_positions'] }}</strong></div><div><span>Posisi belum terisi anggota aktif</span><strong>{{ $dashboard['tv_club']['vacant_positions'] }}</strong></div><div><span>Program aktif</span><strong>{{ $dashboard['kpis']['programs']['active'] }}</strong></div><div><span>Total program</span><strong>{{ $dashboard['kpis']['programs']['total'] }}</strong></div></div>
            @else
                <div class="dashboard-empty compact"><i class="bi bi-calendar-plus"></i><span>Belum ada generasi aktif. Tambahkan atau aktifkan generasi untuk mengelola strukturnya.</span></div>
                <p class="mb-0 mt-2">{{ $dashboard['kpis']['programs']['active'] }} program aktif dari {{ $dashboard['kpis']['programs']['total'] }} program.</p>
            @endif
        </div>
    </section>
@endif

@if(isset($dashboard['agenda']))
    @include('livewire.admin.dashboard.agenda')
@endif

@if(isset($dashboard['coverage']))
    <section class="card dashboard-panel mb-3" aria-labelledby="role-coverage-title">
        <div class="card-header dashboard-panel-header"><div><span class="dashboard-section-kicker">Antrean operasional</span><h2 id="role-coverage-title"><i class="bi bi-camera-reels me-2"></i>Ringkasan Peliputan</h2></div><a href="{{ route('admin.peliputan.index') }}" class="btn btn-sm btn-outline-primary">Buka peliputan</a></div>
        <div class="card-body">
            <div class="coverage-status-grid">
                @foreach(['pending' => 'Menunggu', 'approved' => 'Disetujui', 'in_progress' => 'Berjalan', 'completed' => 'Selesai'] as $status => $label)<div><small>{{ $label }}</small><strong>{{ $dashboard['coverage']['counts'][$status] }}</strong></div>@endforeach
            </div>
            @if(isset($dashboard['coverage']['latest']))
                <div class="dashboard-list mt-3">
                    @forelse($dashboard['coverage']['latest'] as $coverage)
                        <div class="dashboard-list-item"><div class="list-copy"><strong>{{ $coverage->event_name }}</strong><span>{{ $coverage->event_date->locale('id')->translatedFormat('d M Y') }} · {{ $coverage->status_label }}</span></div>@can('update', $coverage)<a href="{{ route('admin.peliputan.edit', $coverage) }}" aria-label="Tinjau {{ $coverage->event_name }}"><i class="bi bi-arrow-right"></i></a>@endcan</div>
                    @empty
                        <div class="dashboard-empty compact"><i class="bi bi-check-circle"></i><span>Belum ada pengajuan peliputan.</span></div>
                    @endforelse
                </div>
            @endif
        </div>
    </section>
@endif

@if(isset($dashboard['programs']['recent']))
    <section class="card dashboard-panel mb-3" aria-labelledby="role-programs-title">
        <div class="card-header dashboard-panel-header"><div><span class="dashboard-section-kicker">Program Acara</span><h2 id="role-programs-title"><i class="bi bi-collection-play me-2"></i>Program Terakhir Diperbarui</h2></div><a href="{{ route('admin.programs.index') }}" class="btn btn-sm btn-outline-primary">Lihat program</a></div>
        <div class="card-body dashboard-list">
            @forelse($dashboard['programs']['recent'] as $program)
                <div class="dashboard-list-item"><div class="list-copy"><strong>{{ $program->name }}</strong><span>{{ $program->status ? 'Aktif' : 'Nonaktif' }} · {{ $program->tv_club_groups_count }} kelompok produksi</span></div>@can('update', $program)<a href="{{ route('admin.programs.edit', $program) }}" aria-label="Edit {{ $program->name }}"><i class="bi bi-arrow-right"></i></a>@endcan</div>
            @empty
                <div class="dashboard-empty compact"><i class="bi bi-collection"></i><span>Belum ada program acara.</span></div>
            @endforelse
        </div>
    </section>
@endif

@if($user->isTVClub())
    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-6"><section class="card dashboard-panel h-100" aria-labelledby="club-crew-title">
            <div class="card-header dashboard-panel-header"><div><span class="dashboard-section-kicker">Sumber daya</span><h2 id="club-crew-title"><i class="bi bi-people me-2"></i>Kru Aktif Terbaru</h2></div></div>
            <div class="card-body dashboard-list">
                @forelse($dashboard['tv_club']['recent_members'] as $member)<div class="dashboard-list-item"><div class="list-copy"><strong>{{ $member->name }}</strong><span>{{ $member->generation?->name ?? 'Generasi belum tersedia' }}</span></div></div>@empty<div class="dashboard-empty compact"><span>Belum ada kru aktif.</span></div>@endforelse
            </div>
        </section></div>
        <div class="col-12 col-lg-6"><section class="card dashboard-panel h-100" aria-labelledby="club-groups-title">
            <div class="card-header dashboard-panel-header"><div><span class="dashboard-section-kicker">Tim produksi</span><h2 id="club-groups-title"><i class="bi bi-camera-reels me-2"></i>Kelompok Produksi</h2></div></div>
            <div class="card-body dashboard-list">
                @forelse($dashboard['tv_club']['top_groups'] as $group)<div class="dashboard-list-item"><div class="list-copy"><strong>{{ $group->name }}</strong><span>{{ $group->program?->name ?? 'Program belum tersedia' }} · {{ $group->members_count }} kru</span></div></div>@empty<div class="dashboard-empty compact"><span>Belum ada kelompok produksi.</span></div>@endforelse
            </div>
        </section></div>
    </div>
@endif

@if(isset($dashboard['latest']['videos']))
    <section class="card dashboard-panel mb-3" aria-labelledby="role-videos-title">
        <div class="card-header dashboard-panel-header"><div><span class="dashboard-section-kicker">{{ $user->isAdminNews() ? 'Meja editorial' : 'Hasil produksi' }}</span><h2 id="role-videos-title"><i class="bi bi-camera-video me-2"></i>Tayangan Terbaru</h2></div><a href="{{ route('admin.videos.index') }}" class="btn btn-sm btn-outline-primary">Buka tayangan</a></div>
        <div class="card-body dashboard-list">
            @forelse($dashboard['latest']['videos'] as $video)<div class="dashboard-list-item"><div class="list-copy"><strong>{{ $video->title }}</strong><span>{{ $video->program?->name ?? 'Belum terhubung ke program' }} · {{ $video->air_date?->format('d M Y') ?? 'Tanggal belum diatur' }}</span></div>@can('update', $video)<a href="{{ route('admin.videos.edit', $video) }}" aria-label="Edit {{ $video->title }}"><i class="bi bi-arrow-right"></i></a>@endcan</div>@empty<div class="dashboard-empty compact"><span>Belum ada tayangan terbaru.</span></div>@endforelse
        </div>
    </section>
@endif
