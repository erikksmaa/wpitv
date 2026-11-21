<section class="card dashboard-panel mb-3" aria-labelledby="dashboard-equipment-title">
    <div class="card-header dashboard-panel-header">
        <div><span class="dashboard-section-kicker">Operasional alat</span><h2 id="dashboard-equipment-title"><i class="bi bi-box-seam me-2"></i>Inventaris & Peminjaman</h2></div>
    </div>
    <div class="card-body">
        @unless($dashboard['equipment']['data_available'])
            <div class="alert alert-warning mb-0" role="status">Ringkasan inventaris dan peminjaman belum dapat dimuat. Silakan muat ulang halaman.</div>
        @else
            <div class="row g-3 mb-3">
                @foreach([
                    ['item_types', 'Jenis barang', 'bi-collection', 'kpi-program'],
                    ['total_units', 'Total unit', 'bi-box-seam', 'kpi-program'],
                    ['available_units', 'Unit tersedia', 'bi-check-circle', 'kpi-video'],
                    ['borrowed_units', 'Unit dipinjam', 'bi-box-arrow-up-right', 'kpi-program'],
                    ['damaged_units', 'Unit rusak', 'bi-tools', 'kpi-coverage'],
                    ['lost_units', 'Unit hilang', 'bi-question-circle', 'kpi-coverage'],
                    ['active_loans', 'Pinjaman aktif', 'bi-clipboard-check', 'kpi-program'],
                    ['overdue_loans', 'Pinjaman terlambat', 'bi-clock-history', 'kpi-coverage'],
                ] as [$key, $label, $icon, $style])
                    <div class="col-12 col-sm-6 col-xl-4">
                        <article class="dashboard-kpi {{ $style }} h-100">
                            <div class="dashboard-kpi-icon"><i class="bi {{ $icon }}"></i></div>
                            <div class="dashboard-kpi-copy"><span>{{ $label }}</span><strong>{{ number_format($dashboard['equipment']['kpis'][$key]) }}</strong></div>
                        </article>
                    </div>
                @endforeach
            </div>
            @if($dashboard['equipment']['total_units'] === 0)
                <div class="dashboard-empty compact mb-3"><i class="bi bi-box"></i><span>Belum ada unit inventaris.</span></div>
            @endif
            <h3 class="fs-6 mt-3">Lima Jatuh Tempo Terdekat</h3>
            <p class="small text-muted">Pinjaman belum selesai, diurutkan dari jatuh tempo paling awal, termasuk yang terlambat. Waktu ditampilkan dalam WIB.</p>
            <div class="dashboard-list">
                @forelse($dashboard['equipment']['due_loans'] as $loan)
                    <div class="dashboard-list-item" wire:key="equipment-due-{{ $loan->id }}">
                        <div class="list-icon primary"><i class="bi bi-calendar-event"></i></div>
                        <div class="list-copy">
                            <strong>{{ $loan->loan_code }}</strong>
                            <span><time datetime="{{ $loan->due_at->toIso8601String() }}">{{ $loan->due_at->timezone('Asia/Jakarta')->format('d/m/Y') }} WIB</time></span>
                        </div>
                        @if($loan->due_at->lt($dashboard['equipment']['as_of']))
                            <span class="badge bg-warning text-dark">Terlambat</span>
                        @endif
                        @can('view', $loan)
                            <a href="{{ route('admin.equipment-loans.show', $loan) }}" aria-label="Detail pinjaman {{ $loan->loan_code }}"><i class="bi bi-arrow-right"></i></a>
                        @endcan
                    </div>
                @empty
                    <div class="dashboard-empty compact"><i class="bi bi-check-circle"></i><span>Tidak ada pinjaman yang menunggu pengembalian.</span></div>
                @endforelse
            </div>
        @endunless
    </div>
</section>
