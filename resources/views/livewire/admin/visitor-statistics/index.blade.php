<div>
    <!-- Top Action & Refresh Toolbar -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h4 class="mb-1 text-dark fw-bold"><i class="bi bi-graph-up text-primary me-2"></i>Statistik Pengunjung</h4>
            <p class="text-muted small mb-0">Ringkasan lalu lintas website.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" wire:click="refreshStats" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-arrow-clockwise" wire:loading.class="spinner-border spinner-border-sm"></i>
                <span>Perbarui</span>
            </button>
        </div>
    </div>

    <!-- 5 Key Statistic Metric Cards -->
    <div class="row g-2 mb-3">
        <!-- Card 1: Total Hits -->
        <div class="col-12 col-sm-6 col-xl">
            <div class="card metric-card metric-primary border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small fw-semibold">Total Tayangan</span>
                        <div class="metric-icon">
                            <i class="bi bi-eye-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1">{{ number_format($overviewStats['total_hits']) }}</h3>
                    <p class="small mb-0">Semua waktu</p>
                </div>
            </div>
        </div>

        <!-- Card 2: Visitor Logs -->
        <div class="col-12 col-sm-6 col-xl">
            <div class="card metric-card metric-cyan border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small fw-semibold">Total Pengunjung</span>
                        <div class="metric-icon">
                            <i class="bi bi-people-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1">{{ number_format($overviewStats['total_visitors']) }}</h3>
                    <p class="small mb-0">Semua waktu</p>
                </div>
            </div>
        </div>

        <!-- Card 3: Today's Hits -->
        <div class="col-12 col-sm-6 col-xl">
            <div class="card metric-card metric-success border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small fw-semibold">Tayangan Hari Ini</span>
                        <div class="metric-icon">
                            <i class="bi bi-calendar-check-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1">{{ number_format($overviewStats['today_hits']) }}</h3>
                    <p class="small mb-0">Hari ini</p>
                </div>
            </div>
        </div>

        <!-- Card 4: Today's Visitors -->
        <div class="col-12 col-sm-6 col-xl">
            <div class="card metric-card metric-orange border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small fw-semibold">Pengunjung Hari Ini</span>
                        <div class="metric-icon">
                            <i class="bi bi-person-check-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1">{{ number_format($overviewStats['today_visitors']) }}</h3>
                    <p class="small mb-0">Hari ini</p>
                </div>
            </div>
        </div>

        <!-- Card 5: Online Now (with 30s light auto-polling) -->
        <div class="col-12 col-sm-6 col-xl" wire:poll.30s="refreshStats">
            <div class="card metric-card metric-red border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small fw-bold">
                            <i class="bi bi-circle-fill me-1 small animate-pulse"></i> Sedang Daring
                        </span>
                        <div class="metric-icon">
                            <i class="bi bi-broadcast fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1">{{ number_format($overviewStats['online_visitors']) }}</h3>
                    <p class="small mb-0">5 menit terakhir</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Period Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="row g-3 align-items-center justify-content-between">
                <div class="col-12 col-lg-7">
                    <div class="d-flex flex-wrap align-items-center gap-2 admin-filter">
                        <span class="fw-semibold text-dark small me-1"><i class="bi bi-funnel me-1"></i>Periode:</span>
                        <div class="btn-group btn-group-sm flex-wrap" role="group">
                            <button type="button" 
                                    wire:click="$set('period', 'today')" 
                                    class="btn {{ $period === 'today' ? 'btn-primary' : 'btn-outline-secondary' }}">
                                Hari Ini
                            </button>
                            <button type="button" 
                                    wire:click="$set('period', '7_days')" 
                                    class="btn {{ $period === '7_days' ? 'btn-primary' : 'btn-outline-secondary' }}">
                                7 Hari
                            </button>
                            <button type="button" 
                                    wire:click="$set('period', '14_days')" 
                                    class="btn {{ $period === '14_days' ? 'btn-primary' : 'btn-outline-secondary' }}">
                                14 Hari
                            </button>
                            <button type="button" 
                                    wire:click="$set('period', '30_days')" 
                                    class="btn {{ $period === '30_days' ? 'btn-primary' : 'btn-outline-secondary' }}">
                                30 Hari
                            </button>
                            <button type="button" 
                                    wire:click="$set('period', 'this_month')" 
                                    class="btn {{ $period === 'this_month' ? 'btn-primary' : 'btn-outline-secondary' }}">
                                Bulan Ini
                            </button>
                            <button type="button" 
                                    wire:click="$set('period', 'custom')" 
                                    class="btn {{ $period === 'custom' ? 'btn-primary' : 'btn-outline-secondary' }}">
                                Rentang Khusus
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-5">
                    @if($period === 'custom')
                        <div class="d-flex align-items-center gap-2 admin-filter">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light">Mulai</span>
                                <input type="date" wire:model.live="startDate" class="form-control form-control-sm">
                            </div>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light">Selesai</span>
                                <input type="date" wire:model.live="endDate" class="form-control form-control-sm">
                            </div>
                        </div>
                    @else
                        <div class="text-lg-end text-muted small">
                            <i class="bi bi-calendar3 me-1"></i> Rentang: <strong>{{ $summary['start_date'] }}</strong> s.d. <strong>{{ $summary['end_date'] }}</strong>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Loading State Indicator -->
            <div wire:loading wire:target="period,startDate,endDate,refreshStats" class="mt-2 text-primary small">
                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                Memuat statistik...
            </div>
        </div>
    </div>

    <div id="visitor-chart-data"
         class="d-none"
         data-labels='@json($chartLabels)'
         data-hits='@json($chartHits)'
         data-visitors='@json($chartVisitors)'></div>

    <!-- Grafik ringkasan lalu lintas dan pengunjung -->
    <div class="row g-4 mb-4">
        <!-- Chart 1: Traffic Overview (Hits Trend) -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0 text-primary">
                            <i class="bi bi-graph-up me-2"></i>Tayangan Harian
                        </h6>
                        <span class="badge bg-primary bg-opacity-10 text-primary">
                            {{ number_format($summary['total_hits']) }} tayangan
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    @if(count($chartLabels) > 0)
                        <div wire:ignore style="position: relative; height: 230px; width: 100%;">
                            <canvas id="hitsChart"></canvas>
                        </div>
                    @else
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-bar-chart fs-1 d-block mb-2 text-secondary"></i>
                            <p class="small mb-0">Belum ada data pengunjung untuk periode ini.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Chart 2: Visitor Overview (Unique Visitors Trend) -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0 text-info">
                            <i class="bi bi-people me-2"></i>Pengunjung Harian
                        </h6>
                        <span class="badge bg-info bg-opacity-10 text-info">
                            {{ number_format($summary['total_visitors']) }} pengunjung
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    @if(count($chartLabels) > 0)
                        <div wire:ignore style="position: relative; height: 230px; width: 100%;">
                            <canvas id="visitorsChart"></canvas>
                        </div>
                    @else
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-bar-chart fs-1 d-block mb-2 text-secondary"></i>
                            <p class="small mb-0">Belum ada data pengunjung untuk periode ini.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Period Traffic Summary Metrics -->
    <div class="card period-summary-card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <h6 class="card-title mb-0 text-dark fw-bold">
                <i class="bi bi-clipboard-data me-2 text-primary"></i>Ringkasan Periode
            </h6>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <!-- Highest Traffic Day -->
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="period-summary-tile is-highest">
                        <span class="period-summary-icon"><i class="bi bi-graph-up-arrow"></i></span>
                        <div class="period-summary-content">
                            <span class="period-summary-label">Hari tersibuk</span>
                            @if($summary['highest_day'])
                                <strong class="period-summary-value">{{ $summary['highest_day']['date'] }}</strong>
                                <span class="period-summary-detail"><strong>{{ number_format($summary['highest_day']['hits']) }}</strong> tayangan · {{ number_format($summary['highest_day']['visitors']) }} pengunjung</span>
                            @else
                                <strong class="period-summary-value">Belum ada data</strong>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Lowest Traffic Day -->
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="period-summary-tile is-lowest">
                        <span class="period-summary-icon"><i class="bi bi-graph-down-arrow"></i></span>
                        <div class="period-summary-content">
                            <span class="period-summary-label">Hari paling sepi</span>
                            @if($summary['lowest_day'])
                                <strong class="period-summary-value">{{ $summary['lowest_day']['date'] }}</strong>
                                <span class="period-summary-detail"><strong>{{ number_format($summary['lowest_day']['hits']) }}</strong> tayangan · {{ number_format($summary['lowest_day']['visitors']) }} pengunjung</span>
                            @else
                                <strong class="period-summary-value">Belum ada data</strong>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Average Daily Hits -->
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="period-summary-tile is-hits">
                        <span class="period-summary-icon"><i class="bi bi-eye"></i></span>
                        <div class="period-summary-content">
                            <span class="period-summary-label">Rata-rata tayangan</span>
                            <strong class="period-summary-value is-number">{{ number_format($summary['avg_daily_hits']) }}</strong>
                            <span class="period-summary-detail">tayangan per hari</span>
                        </div>
                    </div>
                </div>

                <!-- Average Daily Visitors -->
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="period-summary-tile is-visitors">
                        <span class="period-summary-icon"><i class="bi bi-people"></i></span>
                        <div class="period-summary-content">
                            <span class="period-summary-label">Rata-rata pengunjung</span>
                            <strong class="period-summary-value is-number">{{ number_format($summary['avg_daily_visitors']) }}</strong>
                            <span class="period-summary-detail">pengunjung per hari</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Traffic Table Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h6 class="card-title mb-0 text-primary"><i class="bi bi-table me-2"></i>Rincian Harian</h6>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">Tampilkan:</span>
                    <select wire:model.live="perPage" class="form-select form-select-sm bg-light" style="width: 110px;">
                        <option value="10">10 / hal</option>
                        <option value="25">25 / hal</option>
                        <option value="50">50 / hal</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 60px;">#</th>
                            <th scope="col">Tanggal</th>
                            <th scope="col" class="text-center" style="width: 200px;">Pengunjung</th>
                            <th scope="col" class="text-center" style="width: 200px;">Tayangan</th>
                            <th scope="col" class="text-end">Tayangan/Pengunjung</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dailyTraffic as $index => $row)
                            <tr wire:key="daily-{{ $row->tanggal }}">
                                <td class="text-muted small fw-semibold">
                                    {{ $dailyTraffic->firstItem() + $index }}
                                </td>
                                <td>
                                    <div class="fw-bold text-dark text-nowrap">
                                        <i class="bi bi-calendar-event text-primary me-2"></i>
                                        {{ \Carbon\Carbon::parse($row->tanggal)->locale('id')->translatedFormat('d M Y') }}
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info bg-opacity-10 text-info">
                                        <i class="bi bi-people me-1"></i> {{ number_format($row->visitors) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success bg-opacity-10 text-success">
                                        <i class="bi bi-eye me-1"></i> {{ number_format($row->total_hits) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="fw-semibold text-dark">
                                        {{ $row->visitors > 0 ? number_format($row->total_hits / $row->visitors, 1) : '-' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary"></i>
                                        <h6>Belum ada data pengunjung untuk periode ini.</h6>
                                        <p class="small mb-0">Coba pilih rentang tanggal atau periode lain.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 pt-3 border-top gap-2">
                <div class="text-muted small">
                    {{ $dailyTraffic->firstItem() ?? 0 }}–{{ $dailyTraffic->lastItem() ?? 0 }} dari {{ $dailyTraffic->total() }} hari
                </div>
                <div>
                    {{ $dailyTraffic->links() }}
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('mazer/extensions/chart.js/chart.umd.js') }}"></script>
    @endpush

    <!-- Chart.js Script Integration -->
    @script
    <script>
        window.wpitvVisitorCharts = window.wpitvVisitorCharts || { hits: null, visitors: null };

        function renderVisitorCharts() {
            const dataElement = document.getElementById('visitor-chart-data');

            if (typeof Chart === 'undefined' || !dataElement) {
                return;
            }

            const chartLabels = JSON.parse(dataElement.dataset.labels || '[]');
            const chartHits = JSON.parse(dataElement.dataset.hits || '[]');
            const chartVisitors = JSON.parse(dataElement.dataset.visitors || '[]');
            const hitsColor = getComputedStyle(document.documentElement).getPropertyValue('--wp-primary-deep').trim() || '#155DAC';
            const visitorsColor = '#1B8CD1';
            const gridColor = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? '#24334D' : '#E2E8F0';
            const tickColor = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? '#94A3B8' : '#64748B';

            // 1. Render Hits Chart
            const hitsCanvas = document.getElementById('hitsChart');
            if (hitsCanvas && chartLabels.length > 0) {
                Chart.getChart(hitsCanvas)?.destroy();

                window.wpitvVisitorCharts.hits = new Chart(hitsCanvas, {
                    type: 'line',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            label: 'Tayangan Halaman',
                            data: chartHits,
                            borderColor: hitsColor,
                            backgroundColor: 'rgba(21, 93, 172, 0.12)',
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: hitsColor,
                            pointBorderWidth: 2.5,
                            borderWidth: 2.5,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.parsed.y + ' tayangan';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: gridColor },
                                ticks: { color: tickColor, precision: 0 }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: tickColor }
                            }
                        }
                    }
                });
            }

            // 2. Render Visitors Chart
            const visitorsCanvas = document.getElementById('visitorsChart');
            if (visitorsCanvas && chartLabels.length > 0) {
                Chart.getChart(visitorsCanvas)?.destroy();

                window.wpitvVisitorCharts.visitors = new Chart(visitorsCanvas, {
                    type: 'bar',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            label: 'Pengunjung Unik',
                            data: chartVisitors,
                            backgroundColor: visitorsColor,
                            borderRadius: 7,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.parsed.y + ' pengunjung';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: gridColor },
                                ticks: { color: tickColor, precision: 0 }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: tickColor }
                            }
                        }
                    }
                });
            }
        }

        $wire.$hook('commit', ({ succeed }) => {
            succeed(() => requestAnimationFrame(renderVisitorCharts));
        });

        requestAnimationFrame(renderVisitorCharts);
    </script>
    @endscript
</div>
