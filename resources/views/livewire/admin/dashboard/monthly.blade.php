@php
    $monthly = $dashboard['monthly'];
    $rules = \App\Support\MonthlyActivityRules::class;
@endphp
<section class="card dashboard-panel mb-3" aria-labelledby="monthly-dashboard-title">
    <div class="card-body">
        <h2 class="h5" id="monthly-dashboard-title">Laporan Kegiatan Bulanan</h2>
        @if ($monthly['unavailable'] ?? false)
            <p class="mb-0">Ringkasan laporan belum dapat dimuat.</p>
        @else
            <p>{{ $monthly['month']->locale('id')->translatedFormat('F Y') }} · WIB</p>
            <div class="row g-2">
                @foreach ($monthly['divisions'] as $division)
                    @php($report = $monthly['current']->get($division))
                    <div class="col-12 col-sm">
                        <div class="border rounded p-3 h-100">
                            <h3 class="h6">{{ $rules::DIVISION_LABELS[$division] }}</h3>
                            @if ($report)
                                <span class="badge bg-{{ $rules::STATUS_BADGES[$report->status] }}">{{ $rules::STATUS_LABELS[$report->status] }}</span>
                                <a class="d-inline-block ms-2" href="{{ route('admin.monthly-reports.show', $report->id) }}">{{ !$monthly['review'] && $rules::editable($report->status) ? 'Lanjutkan Laporan' : 'Lihat Laporan' }}</a>
                            @else
                                <p class="mb-0">Belum ada laporan bulan ini.</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($monthly['review'])
                <h3 class="h6 mt-3">Menunggu Review ({{ $monthly['waiting'] }})</h3>
                <ul class="list-unstyled mb-2">
                    @forelse ($monthly['latest'] as $pending)
                        <li><a href="{{ route('admin.monthly-reports.show', $pending->id) }}">{{ $rules::DIVISION_LABELS[$pending->division] }} · {{ $pending->period_start->locale('id')->translatedFormat('F Y') }}</a></li>
                    @empty
                        <li>Antrean review kosong.</li>
                    @endforelse
                </ul>
                <a href="{{ route('admin.monthly-reports.index', ['status' => 'submitted']) }}">Buka Antrean Review</a>
            @else
                <a class="d-inline-block mt-2" href="{{ route('admin.monthly-reports.index') }}">Buka Laporan Bulanan</a>
            @endif
        @endif
    </div>
</section>
