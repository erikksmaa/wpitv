<div>
    <div class="card">
        <div class="card-header"><h4>Laporan Inventaris & Peminjaman</h4></div>
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="report-period" class="form-label">Periode</label>
                    <select id="report-period" class="form-select" wire:model.live="period">
                        <option value="today">Hari ini</option><option value="7_days">7 hari</option><option value="30_days">30 hari</option>
                        <option value="this_month">Bulan ini</option><option value="custom">Rentang khusus</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="report-basis" class="form-label">Dasar tanggal riwayat</label>
                    <select id="report-basis" class="form-select" wire:model="dateBasis"><option value="borrowed_at">Tanggal pinjam</option><option value="returned_at">Tanggal kembali</option></select>
                </div>
                <div class="col-md-3">
                    <label for="report-category" class="form-label">Kategori unit saat ini</label>
                    <select id="report-category" class="form-select" wire:model="categoryId"><option value="">Semua</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
                </div>
                <div class="col-md-3">
                    <label for="report-condition" class="form-label">Kondisi unit saat ini</label>
                    <select id="report-condition" class="form-select" wire:model="condition"><option value="all">Semua</option><option value="good">Baik</option><option value="minor_damage">Rusak ringan</option><option value="major_damage">Rusak berat</option></select>
                </div>
                <div class="col-md-4">
                    <label for="report-status" class="form-label">Status transaksi saat ini</label>
                    <select id="report-status" class="form-select" wire:model="status">
                        <option value="all">Semua</option><option value="active">Aktif</option><option value="partially_returned">Kembali sebagian</option>
                        <option value="returned">Selesai</option><option value="overdue">Masih terlambat</option><option value="unresolved_loss">Ada alat hilang belum kembali</option>
                    </select>
                </div>
                @if($period === 'custom')
                    <div class="col-md-4"><label for="report-start" class="form-label">Mulai (WIB)</label><input id="report-start" class="form-control" type="date" wire:model="startDate" required></div>
                    <div class="col-md-4"><label for="report-end" class="form-label">Sampai (WIB)</label><input id="report-end" class="form-control" type="date" wire:model="endDate" required></div>
                @endif
                <div class="col-12"><button type="button" class="btn btn-primary" wire:click="applyFilters" wire:loading.attr="disabled">Terapkan Filter</button></div>
            </div>
            <p class="small text-muted mt-3 mb-0">Maksimal 366 hari. Tabel dan ekspor mengikuti filter yang sudah diterapkan. Kondisi/kategori mengikuti master saat ini; identitas alat saat dipinjam tetap memakai snapshot.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2">
            <h5>Inventaris Terkini</h5>
            @can('exportReport', App\Models\EquipmentLoan::class)
                <a class="btn btn-outline-primary" href="{{ route('admin.equipment-reports.export', ['type' => 'inventory', ...$filters]) }}">Ekspor Inventaris CSV</a>
            @endcan
        </div>
        <div class="card-body">
            <p class="small text-muted">Mengikuti filter kategori dan kondisi. Tanggal dan status transaksi tidak membatasi inventaris terkini; unit tanpa histori tetap ditampilkan.</p>
            <div class="row g-3 mb-3">
                @foreach(['total' => 'Total unit', 'available' => 'Tersedia', 'borrowed' => 'Belum kembali, termasuk hilang', 'damaged' => 'Rusak'] as $key => $label)
                    <div class="col-6 col-lg-3"><div class="border rounded p-3"><div class="text-muted">{{ $label }}</div><strong class="fs-4">{{ number_format($stock[$key], 0, ',', '.') }}</strong></div></div>
                @endforeach
            </div>
            <div class="table-responsive">
                <table class="table align-middle"><thead><tr><th>Kode</th><th>Nama</th><th>Kategori saat ini</th><th>Lokasi</th><th>Kondisi</th><th>Aktif</th><th>Ketersediaan</th><th>Histori pinjam</th></tr></thead>
                    <tbody>@forelse($inventoryRows as $row)<tr wire:key="stock-row-{{ $loop->index }}-{{ $inventoryRows->currentPage() }}"><td>{{ $row['inventory_code'] }}</td><td>{{ $row['name'] }}</td><td>{{ $row['category_current'] }}</td><td>{{ $row['storage_location'] }}</td><td>{{ $row['condition_current'] }}</td><td>{{ $row['active'] }}</td><td>{{ $row['availability'] }}</td><td>{{ $row['loan_count'] }}</td></tr>@empty<tr><td colspan="8" class="text-muted text-center">Tidak ada unit sesuai filter.</td></tr>@endforelse</tbody>
                </table>
            </div>
            {{ $inventoryRows->links() }}
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2">
            <h5>Riwayat Peminjaman per Unit</h5>
            @can('exportReport', App\Models\EquipmentLoan::class)
                <a class="btn btn-outline-primary" href="{{ route('admin.equipment-reports.export', ['type' => 'loans', ...$filters]) }}">Ekspor Riwayat CSV</a>
            @endcan
        </div>
        <div class="card-body">
            <p>{{ $filters['startDate'] }} s.d. {{ $filters['endDate'] }} WIB, berdasarkan {{ $filters['dateBasis'] === 'borrowed_at' ? 'tanggal pinjam' : 'tanggal kembali' }}. Data per {{ $asOf->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }} WIB.</p>
            <p class="small text-muted">Ringkasan mengikuti baris hasil filter. Pinjaman dan unit dihitung unik; pengembalian/kehilangan/keterlambatan dihitung per baris unit. Perpanjangan dihitung sekali per kejadian pada transaksi terpilih sepanjang historinya. Keterlambatan sebelum perpanjangan ditampilkan terpisah dari keterlambatan unit saat ini atau saat kembali. Status transaksi berlaku pada seluruh pinjaman. Filter tanggal kembali mengecualikan unit yang belum kembali.</p>
            <div class="row g-3 mb-3">
                @foreach(['units' => 'Unit unik', 'loans' => 'Transaksi unik', 'rows' => 'Baris peminjaman', 'returns' => 'Unit dikembalikan', 'late' => 'Unit terlambat, aktif / saat kembali', 'losses' => 'Unit berhistori hilang', 'unresolved_losses' => 'Hilang belum kembali', 'extensions' => 'Perpanjangan transaksi terpilih', 'late_extensions' => 'Perpanjangan setelah terlambat'] as $key => $label)
                    <div class="col-6 col-lg-3"><div class="border rounded p-3"><div class="text-muted">{{ $label }}</div><strong class="fs-4">{{ number_format($summary[$key], 0, ',', '.') }}</strong></div></div>
                @endforeach
            </div>
            <div class="mb-3"><label for="report-pages" class="form-label">Baris per halaman pada kedua tabel</label><select id="report-pages" class="form-select w-auto" wire:model.live="perPage"><option>10</option><option>25</option><option>50</option></select></div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Transaksi</th><th>Kode / nama saat pinjam</th><th>Dipinjam (WIB)</th><th>Jatuh tempo awal / terkini (WIB)</th><th>Kembali / batas saat kembali (WIB)</th><th>Kondisi keluar / masuk</th><th>Status transaksi / unit</th><th>Terlambat</th><th>Kehilangan (WIB)</th><th>Perpanjangan total / setelah terlambat</th></tr></thead>
                    <tbody>@forelse($loanRows as $row)<tr wire:key="report-row-{{ $loop->index }}-{{ $loanRows->currentPage() }}">
                        <td>{{ $row['loan_code'] }}</td><td>{{ $row['inventory_code'] }} / {{ $row['name'] }}</td><td>{{ $row['borrowed_at'] }}</td>
                        <td>{{ $row['initial_due_at'] }}<br>{{ $row['due_at'] }}</td><td>{{ $row['returned_at'] }}<br>{{ $row['due_at_on_return'] }}</td>
                        <td>{{ $row['condition_out'] }} / {{ $row['condition_in'] }}</td><td>{{ $row['loan_status'] }} / {{ $row['item_status'] }}</td>
                        <td>{{ $row['late'] }}</td><td>{{ $row['lost_at'] }}</td><td>{{ $row['extension_count'] }} / {{ $row['late_extension_count'] }}</td>
                    </tr>@empty<tr><td colspan="10" class="text-muted text-center">Tidak ada riwayat sesuai filter.</td></tr>@endforelse</tbody>
                </table>
            </div>
            {{ $loanRows->links() }}
            <p class="small text-muted mt-3 mb-0">CSV memuat seluruh hasil filter, maksimal 10.000 baris, tanpa identitas peminjam, kontak, atau catatan bebas. Data dapat berubah setelah halaman dibuka.</p>
        </div>
    </div>
</div>
