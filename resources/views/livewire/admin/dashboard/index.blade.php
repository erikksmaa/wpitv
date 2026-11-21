<div class="operations-dashboard">
    <section class="dashboard-welcome mb-3" aria-labelledby="dashboard-welcome-title">
        <div>
            <span class="dashboard-eyebrow">WPiTV Media Operations</span>
            <h1 id="dashboard-welcome-title">Selamat datang, {{ $user->name }}</h1>
            <p>{{ match ($user->role->name) {
                App\Models\User::ROLE_SUPER_ADMIN => 'Ringkasan sistem dan operasional WPiTV',
                App\Models\User::ROLE_PRODUCTION => 'Operasional produksi dan peliputan',
                App\Models\User::ROLE_NEWS => 'Ringkasan editorial dan berita',
                App\Models\User::ROLE_TVCLUB => 'Operasional TV Club dan Program Acara',
            } }}</p>
        </div>
        <div class="dashboard-welcome-meta">
            <span class="badge bg-primary"><i class="bi bi-shield-check me-1"></i>{{ $user->role->name ?? 'Admin' }}</span>
            <time datetime="{{ now()->toDateString() }}"><i class="bi bi-calendar3 me-1"></i>{{ now()->locale('id')->translatedFormat('l, d F Y') }}</time>
        </div>
    </section>

    @unless($dashboardAvailable)
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>Data dasbor belum dapat dimuat. Silakan muat ulang halaman.
        </div>
    @else
        @include('livewire.admin.dashboard.quick-actions')
        @if(isset($dashboard['monthly'])) @include('livewire.admin.dashboard.monthly') @endif
        @if(isset($dashboard['social'])) @include('livewire.admin.dashboard.social') @endif
        @if(isset($dashboard['live'])) @include('livewire.admin.dashboard.live') @endif
        @if(($user->isSuperAdmin() || $user->isAdminProduction()) && isset($dashboard['equipment']))
            @can('viewAny', App\Models\InventoryUnit::class)
                @can('viewAny', App\Models\EquipmentLoan::class)
                    @include('livewire.admin.dashboard.equipment')
                @endcan
            @endcan
        @endif
        @if(!$user->isSuperAdmin())
            @include('livewire.admin.dashboard.focused')
        @else
        @include('livewire.admin.dashboard.agenda')
        @include('livewire.admin.dashboard.system')
        <section class="row g-3 mb-3" aria-label="Indikator utama">
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-kpi kpi-program h-100">
                    <div class="dashboard-kpi-icon"><i class="bi bi-collection-play"></i></div>
                    <div class="dashboard-kpi-copy"><span>Program</span><strong>{{ number_format($dashboard['kpis']['programs']['total']) }}</strong><small>{{ number_format($dashboard['kpis']['programs']['active']) }} aktif @if($dashboard['kpis']['programs']['without_videos'] > 0) · {{ number_format($dashboard['kpis']['programs']['without_videos']) }} perlu ditinjau @endif</small></div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-kpi kpi-video h-100">
                    <div class="dashboard-kpi-icon"><i class="bi bi-camera-video"></i></div>
                    <div class="dashboard-kpi-copy"><span>Tayangan</span><strong>{{ number_format($dashboard['kpis']['videos']['total']) }}</strong><small>{{ number_format($dashboard['kpis']['videos']['this_month']) }} tayang bulan ini</small></div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-kpi kpi-news h-100">
                    <div class="dashboard-kpi-icon"><i class="bi bi-newspaper"></i></div>
                    <div class="dashboard-kpi-copy"><span>Berita</span><strong>{{ number_format($dashboard['kpis']['news']['total']) }}</strong><small>{{ number_format($dashboard['kpis']['news']['this_month']) }} dibuat bulan ini</small></div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-kpi kpi-coverage h-100">
                    <div class="dashboard-kpi-icon"><i class="bi bi-camera-reels"></i></div>
                    <div class="dashboard-kpi-copy"><span>Peliputan</span><strong>{{ number_format($dashboard['kpis']['coverage']['pending']) }}</strong><small>{{ $dashboard['kpis']['coverage']['pending'] > 0 ? 'pengajuan menunggu tinjauan' : 'tidak ada antrean tinjauan' }}</small></div>
                </article>
            </div>
        </section>
        <section class="row g-3 mb-3" aria-label="Indikator sistem dan sumber daya">
            @foreach([
                ['Anggota aktif', $dashboard['tv_club']['active_members']],
                ['Agenda mendatang', $dashboard['agenda']['count']],
                ['Pengunjung hari ini', $dashboard['traffic']['metrics']['today_visitors']],
                ['Pengguna aktif', $dashboard['system']['active_users']],
            ] as [$label, $value])
                <div class="col-12 col-sm-6 col-xl-3"><article class="dashboard-kpi kpi-program h-100"><div class="dashboard-kpi-copy"><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong></div></article></div>
            @endforeach
        </section>

        <div class="row g-3 mb-3 dashboard-priority-row">
            <div class="col-12 col-xl-7">
                <section class="card dashboard-panel h-100" aria-labelledby="production-title">
                    <div class="card-header dashboard-panel-header">
                        <div><span class="dashboard-section-kicker">Produksi media</span><h2 id="production-title"><i class="bi bi-bar-chart-line me-2"></i>Produksi Konten</h2></div>
                        <span class="badge bg-primary">6 bulan</span>
                    </div>
                    <div class="card-body"><div class="dashboard-chart" wire:ignore><canvas id="productionChart" role="img" aria-label="Grafik produksi tayangan dan berita enam bulan terakhir"></canvas></div></div>
                </section>
            </div>
            <div class="col-12 col-xl-5">
                <section class="card dashboard-panel coverage-panel h-100" aria-labelledby="coverage-title">
                    <div class="card-header dashboard-panel-header">
                        <div><span class="dashboard-section-kicker">Perlu tindakan</span><h2 id="coverage-title"><i class="bi bi-camera-reels me-2"></i>Peliputan</h2></div>
                        <a href="{{ route('admin.peliputan.index') }}" class="btn btn-sm btn-outline-primary">Lihat semua</a>
                    </div>
                    <div class="card-body">
                        <div class="coverage-status-grid mb-3">
                            <div><span class="status-dot warning"></span><small>Menunggu</small><strong>{{ $dashboard['coverage']['counts']['pending'] }}</strong></div>
                            <div><span class="status-dot primary"></span><small>Disetujui</small><strong>{{ $dashboard['coverage']['counts']['approved'] }}</strong></div>
                            <div><span class="status-dot info"></span><small>Berjalan</small><strong>{{ $dashboard['coverage']['counts']['in_progress'] }}</strong></div>
                            <div><span class="status-dot success"></span><small>Selesai</small><strong>{{ $dashboard['coverage']['counts']['completed'] }}</strong></div>
                        </div>
                        <div class="dashboard-list compact-list">
                            @forelse($dashboard['coverage']['latest'] as $coverage)
                                @php
                                    $daysToEvent = (int) today()->diffInDays($coverage->event_date, false);
                                    $isClosed = in_array($coverage->status, [App\Models\Coverage::STATUS_COMPLETED, App\Models\Coverage::STATUS_REJECTED], true);
                                @endphp
                                <div class="dashboard-list-item">
                                    <div class="list-icon warning"><i class="bi bi-calendar-event"></i></div>
                                    <div class="list-copy"><strong>{{ $coverage->event_name }}</strong><span>{{ $coverage->event_date->locale('id')->translatedFormat('d F Y') }}</span></div>
                                    <div class="list-actions">
                                        @if(!$isClosed && $daysToEvent < 0)<span class="badge bg-danger">Lewat</span>
                                        @elseif(!$isClosed && $daysToEvent === 0)<span class="badge bg-warning">Hari ini</span>
                                        @elseif(!$isClosed && $daysToEvent <= 7)<span class="badge bg-warning">H-{{ $daysToEvent }}</span>
                                        @else<span class="badge {{ $coverage->status_badge_class }}">{{ $coverage->status_label }}</span>@endif
                                        @can('update', $coverage)<a href="{{ route('admin.peliputan.edit', $coverage) }}" aria-label="Tinjau {{ $coverage->event_name }}"><i class="bi bi-arrow-right"></i></a>@endcan
                                    </div>
                                </div>
                            @empty
                                <div class="dashboard-empty compact"><i class="bi bi-check2-circle"></i><span>Tidak ada pengajuan peliputan.</span></div>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-xl-8">
                <section class="card dashboard-panel h-100" aria-labelledby="traffic-title">
                    <div class="card-header dashboard-panel-header">
                        <div><span class="dashboard-section-kicker">Performa media</span><h2 id="traffic-title"><i class="bi bi-activity me-2"></i>Traffic 7 Hari Terakhir</h2></div>
                        <a href="{{ route('admin.visitor-statistics.index') }}" class="btn btn-sm btn-outline-primary">Statistik lengkap</a>
                    </div>
                    <div class="card-body"><div class="dashboard-chart traffic-chart" wire:ignore><canvas id="trafficChart" role="img" aria-label="Grafik tayangan dan pengunjung tujuh hari terakhir"></canvas></div></div>
                </section>
            </div>
            <div class="col-12 col-xl-4">
                <section class="card dashboard-panel h-100" aria-labelledby="visitor-summary-title">
                    <div class="card-header dashboard-panel-header"><div><span class="dashboard-section-kicker">Saat ini</span><h2 id="visitor-summary-title"><i class="bi bi-people me-2"></i>Ringkasan Pengunjung</h2></div></div>
                    <div class="card-body visitor-quick-grid">
                        <div><i class="bi bi-person-check"></i><span>Pengunjung hari ini</span><strong>{{ number_format($dashboard['traffic']['metrics']['today_visitors']) }}</strong></div>
                        <div><i class="bi bi-eye"></i><span>Tayangan hari ini</span><strong>{{ number_format($dashboard['traffic']['metrics']['today_hits']) }}</strong></div>
                        <div><i class="bi bi-broadcast"></i><span>Daring sekarang</span><strong>{{ number_format($dashboard['traffic']['metrics']['online_visitors']) }}</strong></div>
                        <div><i class="bi bi-graph-up"></i><span>Total tayangan</span><strong>{{ number_format($dashboard['traffic']['metrics']['total_hits']) }}</strong></div>
                    </div>
                </section>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-lg-6">
                <section class="card dashboard-panel h-100" aria-labelledby="program-ranking-title">
                    <div class="card-header dashboard-panel-header">
                        <div><span class="dashboard-section-kicker">Aktivitas program</span><h2 id="program-ranking-title"><i class="bi bi-collection-play me-2"></i>Program Paling Aktif</h2></div>
                        <a href="{{ route('admin.programs.index') }}" class="btn btn-sm btn-outline-primary">Tinjau program</a>
                    </div>
                    <div class="card-body program-ranking">
                        @php $maxVideos = max(1, (int) ($dashboard['programs']['ranking']->max('videos_count') ?? 1)); @endphp
                        @forelse($dashboard['programs']['ranking'] as $program)
                            <div class="ranking-item"><div><strong>{{ $program->name }}</strong><span>{{ number_format($program->videos_count) }} tayangan</span></div><div class="progress" role="progressbar" aria-label="{{ $program->name }}" aria-valuenow="{{ $program->videos_count }}" aria-valuemin="0" aria-valuemax="{{ $maxVideos }}"><div class="progress-bar" style="width: {{ ($program->videos_count / $maxVideos) * 100 }}%"></div></div></div>
                        @empty
                            <div class="dashboard-empty"><i class="bi bi-collection"></i><span>Belum ada program untuk ditampilkan.</span></div>
                        @endforelse
                    </div>
                </section>
            </div>
            <div class="col-12 col-lg-6">
                <section class="card dashboard-panel h-100" aria-labelledby="content-health-title">
                    <div class="card-header dashboard-panel-header"><div><span class="dashboard-section-kicker">Housekeeping</span><h2 id="content-health-title"><i class="bi bi-heart-pulse me-2"></i>Kesehatan Konten</h2></div></div>
                    <div class="card-body health-list">
                        <a href="{{ route('admin.programs.index') }}"><span class="health-icon warning"><i class="bi bi-tv"></i></span><span><strong>{{ $dashboard['health']['programs_without_videos'] }} program aktif</strong><small>belum memiliki tayangan</small></span><span class="badge bg-warning">Perlu ditinjau</span></a>
                        <a href="{{ route('admin.videos.index') }}"><span class="health-icon primary"><i class="bi bi-link-45deg"></i></span><span><strong>{{ $dashboard['health']['videos_without_program'] }} tayangan</strong><small>belum terhubung ke program</small></span><span class="badge bg-primary">Belum terhubung</span></a>
                        <a href="{{ route('admin.programs.index') }}"><span class="health-icon info"><i class="bi bi-image"></i></span><span><strong>{{ $dashboard['health']['programs_without_image'] }} program</strong><small>menggunakan gambar fallback</small></span><span class="badge bg-info">Fallback</span></a>
                        <a href="{{ route('admin.news.index') }}"><span class="health-icon info"><i class="bi bi-newspaper"></i></span><span><strong>{{ $dashboard['health']['news_without_image'] }} berita</strong><small>menggunakan gambar fallback</small></span><span class="badge bg-info">Fallback</span></a>
                    </div>
                </section>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-lg-6">
                <section class="card dashboard-panel h-100" aria-labelledby="latest-videos-title">
                    <div class="card-header dashboard-panel-header"><div><span class="dashboard-section-kicker">Publikasi</span><h2 id="latest-videos-title"><i class="bi bi-camera-video me-2"></i>Tayangan Terbaru</h2></div><a href="{{ route('admin.videos.index') }}" class="btn btn-sm btn-outline-primary">Lihat semua</a></div>
                    <div class="card-body dashboard-list">
                        @forelse($dashboard['latest']['videos'] as $video)
                            <div class="dashboard-list-item"><img src="{{ $video->thumbnail_url }}" alt="" loading="lazy" class="media-thumb"><div class="list-copy"><strong>{{ $video->title }}</strong><span>{{ $video->program?->name ?? 'Belum terhubung' }} · {{ $video->air_date?->locale('id')->translatedFormat('d F Y') ?? 'Tanggal belum diatur' }}</span></div>@can('update', $video)<a href="{{ route('admin.videos.edit', $video) }}" aria-label="Buka {{ $video->title }}"><i class="bi bi-arrow-right"></i></a>@endcan</div>
                        @empty
                            <div class="dashboard-empty"><i class="bi bi-camera-video-off"></i><span>Belum ada tayangan terbaru.</span></div>
                        @endforelse
                    </div>
                </section>
            </div>
            <div class="col-12 col-lg-6">
                <section class="card dashboard-panel h-100" aria-labelledby="latest-news-title">
                    <div class="card-header dashboard-panel-header"><div><span class="dashboard-section-kicker">Redaksi</span><h2 id="latest-news-title"><i class="bi bi-newspaper me-2"></i>Berita Terbaru</h2></div><a href="{{ route('admin.news.index') }}" class="btn btn-sm btn-outline-primary">Lihat semua</a></div>
                    <div class="card-body dashboard-list">
                        @forelse($dashboard['latest']['news'] as $news)
                            <div class="dashboard-list-item"><div class="list-icon info"><i class="bi bi-file-earmark-text"></i></div><div class="list-copy"><strong>{{ $news->title }}</strong><span>{{ $news->author_display_name }} · {{ $news->created_at->locale('id')->translatedFormat('d F Y') }}</span></div>@can('update', $news)<a href="{{ route('admin.news.edit', $news) }}" aria-label="Buka {{ $news->title }}"><i class="bi bi-arrow-right"></i></a>@endcan</div>
                        @empty
                            <div class="dashboard-empty"><i class="bi bi-newspaper"></i><span>Belum ada berita terbaru.</span></div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>

        <section class="card dashboard-panel mb-3" aria-labelledby="tvclub-title">
            <div class="card-header dashboard-panel-header"><div><span class="dashboard-section-kicker">Sumber daya produksi</span><h2 id="tvclub-title"><i class="bi bi-people me-2"></i>TV Club Production</h2></div><a href="{{ route('admin.tvclub.groups.index') }}" class="btn btn-sm btn-outline-primary">Lihat kelompok</a></div>
            <div class="card-body">
                <div class="tvclub-metrics mb-3">
                    <div><i class="bi bi-calendar2-check"></i><span>Generasi aktif</span><strong>{{ number_format($dashboard['tv_club']['active_generations']) }}</strong></div>
                    <div><i class="bi bi-person-check"></i><span>Anggota aktif</span><strong>{{ number_format($dashboard['tv_club']['active_members']) }}</strong></div>
                    <div><i class="bi bi-people"></i><span>Kelompok produksi</span><strong>{{ number_format($dashboard['tv_club']['groups']) }}</strong></div>
                    <div><i class="bi bi-diagram-3"></i><span>Penugasan kru</span><strong>{{ number_format($dashboard['tv_club']['assignments']) }}</strong></div>
                </div>
                <div class="table-responsive dashboard-table-wrap">
                    <table class="table align-middle mb-0"><thead><tr><th>Kelompok</th><th>Generasi</th><th>Program</th><th class="text-end">Kru</th></tr></thead><tbody>
                        @forelse($dashboard['tv_club']['top_groups'] as $group)
                            <tr><td class="fw-semibold">{{ $group->name }}</td><td>{{ $group->generation?->name ?? '—' }}</td><td>{{ $group->program?->name ?? 'Belum terhubung' }}</td><td class="text-end"><span class="badge bg-primary"><i class="bi bi-people me-1"></i>{{ $group->members_count }}</span></td></tr>
                        @empty
                            <tr><td colspan="4"><div class="dashboard-empty compact"><i class="bi bi-people"></i><span>Belum ada kelompok produksi.</span></div></td></tr>
                        @endforelse
                    </tbody></table>
                </div>
            </div>
        </section>

        <div id="dashboard-chart-data" data-production-labels='@json($dashboard['production']['labels'])' data-production-videos='@json($dashboard['production']['videos'])' data-production-news='@json($dashboard['production']['news'])' data-traffic-labels='@json($dashboard['traffic']['labels'])' data-traffic-hits='@json($dashboard['traffic']['hits'])' data-traffic-visitors='@json($dashboard['traffic']['visitors'])' hidden></div>

        @push('scripts')
            <script src="{{ asset('mazer/extensions/chart.js/chart.umd.js') }}"></script>
        @endpush

        @script
        <script>
            const dashboardData = document.getElementById('dashboard-chart-data');
            if (dashboardData && typeof Chart !== 'undefined') {
                const styles = getComputedStyle(document.documentElement);
                const primary = styles.getPropertyValue('--wp-primary-deep').trim() || '#155DAC';
                const cyan = styles.getPropertyValue('--wp-cyan').trim() || '#1B8CD1';
                const red = styles.getPropertyValue('--wp-danger').trim() || '#D9251D';
                const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
                const grid = dark ? '#24334D' : '#E2E8F0';
                const ticks = dark ? '#94A3B8' : '#64748B';
                const sharedOptions = { responsive: true, maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' }, plugins: { legend: { labels: { color: ticks, usePointStyle: true, boxWidth: 8, font: { family: 'Inter', size: 11 } } }, tooltip: { padding: 10, titleFont: { family: 'Inter' }, bodyFont: { family: 'Inter' } } }, scales: { x: { grid: { display: false }, ticks: { color: ticks, font: { family: 'Inter', size: 11 } } }, y: { beginAtZero: true, grid: { color: grid }, ticks: { color: ticks, precision: 0, font: { family: 'Inter', size: 11 } } } } };
                const productionCanvas = document.getElementById('productionChart');
                if (productionCanvas) {
                    Chart.getChart(productionCanvas)?.destroy();
                    new Chart(productionCanvas, { data: { labels: JSON.parse(dashboardData.dataset.productionLabels), datasets: [ { type: 'bar', label: 'Tayangan', data: JSON.parse(dashboardData.dataset.productionVideos), backgroundColor: 'rgba(21, 93, 172, .78)', borderRadius: 7, maxBarThickness: 34 }, { type: 'line', label: 'Berita', data: JSON.parse(dashboardData.dataset.productionNews), borderColor: red, backgroundColor: red, borderWidth: 2.5, tension: .35, pointRadius: 3, pointBackgroundColor: '#fff', pointBorderWidth: 2 } ] }, options: sharedOptions });
                }
                const trafficCanvas = document.getElementById('trafficChart');
                if (trafficCanvas) {
                    Chart.getChart(trafficCanvas)?.destroy();
                    new Chart(trafficCanvas, { type: 'line', data: { labels: JSON.parse(dashboardData.dataset.trafficLabels), datasets: [ { label: 'Tayangan', data: JSON.parse(dashboardData.dataset.trafficHits), borderColor: primary, backgroundColor: 'rgba(21, 93, 172, .1)', borderWidth: 2.5, fill: true, tension: .35, pointRadius: 3 }, { label: 'Pengunjung', data: JSON.parse(dashboardData.dataset.trafficVisitors), borderColor: cyan, backgroundColor: cyan, borderWidth: 2, tension: .35, pointRadius: 3 } ] }, options: sharedOptions });
                }
            }
        </script>
        @endscript
        @endif
    @endunless
</div>
