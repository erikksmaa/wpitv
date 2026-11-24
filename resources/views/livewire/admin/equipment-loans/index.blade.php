<div>
    <div class="page-heading d-flex flex-wrap justify-content-between gap-2">
        <div><h3>{{ $creating ? 'Peminjaman Baru' : 'Peminjaman Peralatan' }}</h3><p class="text-muted">Pencatatan peminjaman, pengembalian, dan histori peralatan WPiTV.</p></div>
        <div>
            @if($creating)
                <a href="{{ route('admin.equipment-loans.index') }}" class="btn btn-secondary">Kembali ke daftar</a>
            @else
                @can('equipment.loan.checkout')
                    <a href="{{ route('admin.equipment-loans.create') }}" class="btn btn-primary">Peminjaman baru</a>
                @endcan
            @endif
        </div>
    </div>
    @if(session()->has('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul></div>
    @endif
    @if($creating)
        <form wire:submit="checkout" wire:confirm="Simpan peminjaman untuk seluruh unit yang dipilih? Periksa identitas dan jatuh tempo sebelum menyerahkan unit.">
            <div class="row g-3">
                <div class="col-12 col-lg-5">
                    <section class="card"><div class="card-header"><h5>Identitas peminjam</h5></div><div class="card-body">
                        <label for="loan-type" class="form-label">Jenis peminjam</label>
                        <select id="loan-type" wire:model="form.borrower_type" class="form-select mb-3"><option value="student">Mahasiswa</option><option value="lecturer">Dosen</option><option value="staff">Staf</option></select>
                        <label for="loan-name" class="form-label">Nama lengkap</label><input id="loan-name" wire:model="form.borrower_name" maxlength="150" class="form-control mb-3" required>
                        <label for="loan-identity" class="form-label">NIM / NIDN / NIP / identitas institusi</label><input id="loan-identity" wire:model="form.identity_number" maxlength="40" class="form-control mb-3" required>
                        <label for="loan-contact" class="form-label">Kontak / WhatsApp</label><input id="loan-contact" wire:model="form.borrower_contact" maxlength="100" class="form-control mb-3" required>
                        <label for="loan-affiliation" class="form-label">Program studi / unit / instansi</label><input id="loan-affiliation" wire:model="form.borrower_affiliation" maxlength="150" class="form-control mb-3" required>
                        <label for="loan-purpose" class="form-label">Keperluan</label><textarea id="loan-purpose" wire:model="form.purpose" maxlength="5000" class="form-control mb-3" required></textarea>
                        <label for="loan-due" class="form-label">Jatuh tempo (akhir hari WIB)</label><input id="loan-due" type="date" wire:model="form.due_date" min="{{ now('Asia/Jakarta')->toDateString() }}" class="form-control" required>
                    </div></section>
                </div>
                <div class="col-12 col-lg-7">
                    <section class="card"><div class="card-header"><h5>Unit tersedia</h5></div><div class="card-body">
                        <label for="available-search" class="visually-hidden">Cari unit tersedia</label><input id="available-search" wire:model.live.debounce.300ms="search" maxlength="100" class="form-control mb-3" placeholder="Cari kode aset, model, atau kategori…">
                        <p class="small text-muted">Hanya unit aktif, kondisi baik, model aktif, dan tidak sedang dipinjam. Maksimal 50 unit dari 20 model.</p>
                        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Pilih</th><th>Aset</th><th>Model / kategori</th></tr></thead><tbody>
                            @forelse($available as $unit)
                                <tr wire:key="available-{{ $unit->id }}"><td><input type="checkbox" wire:model.live="selected" value="{{ $unit->id }}" aria-label="Pilih {{ $unit->asset_code }}" class="form-check-input"></td><td>{{ $unit->asset_code }}</td><td>{{ $unit->model_name }}<div class="small text-muted">{{ $unit->category_name }}</div></td></tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">Tidak ada unit tersedia yang cocok. Periksa Inventory atau ubah pencarian.</td></tr>
                            @endforelse
                        </tbody></table></div>
                        {{ $available->links() }}
                    </div></section>
                    <section class="card"><div class="card-header"><h5>Unit dipilih ({{ count($selected) }})</h5></div><div class="card-body">
                        @forelse($basket as $unit)
                            <div class="d-flex justify-content-between gap-2 border-bottom py-2" wire:key="basket-{{ $unit->id }}"><div><strong>{{ $unit->asset_code }}</strong> · {{ $unit->model_name }}</div><button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeUnit({{ $unit->id }})">Lepas</button></div>
                        @empty
                            <p class="text-muted">Pilih minimal satu unit dari daftar tersedia.</p>
                        @endforelse
                        <p class="small mt-3">Nomor peminjaman dan waktu dicatat otomatis. Ketersediaan diperiksa ulang saat penyimpanan.</p>
                        <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="checkout" @disabled(!count($selected))>Simpan peminjaman</button>
                    </div></section>
                </div>
            </div>
        </form>
    @else
        <section class="card"><div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-4"><label for="loan-search" class="visually-hidden">Pencarian transaksi</label><input id="loan-search" wire:model.live.debounce.300ms="search" maxlength="100" class="form-control" placeholder="Cari nomor, nama, atau identitas…"></div>
                <div class="col-md-3"><label for="loan-status" class="visually-hidden">Status transaksi</label><select id="loan-status" wire:model.live="status" class="form-select"><option value="">Semua status</option><option value="open">Menunggu pengembalian</option><option value="active">Aktif</option><option value="partially_resolved">Selesai sebagian</option><option value="closed">Selesai</option><option value="voided">Dibatalkan</option><option value="overdue">Terlambat</option><option value="integrity_error">Perlu pemeriksaan</option></select></div>
                <div class="col-md-3"><label for="loan-borrower" class="visually-hidden">Jenis peminjam</label><select id="loan-borrower" wire:model.live="borrowerType" class="form-select"><option value="">Semua peminjam</option><option value="student">Mahasiswa</option><option value="lecturer">Dosen</option><option value="staff">Staf</option></select></div>
                <div class="col-md-2"><label for="loan-page-size" class="visually-hidden">Baris per halaman</label><select id="loan-page-size" wire:model.live="perPage" class="form-select"><option value="10">10 / halaman</option><option value="25">25 / halaman</option><option value="50">50 / halaman</option></select></div>
            </div>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nomor / waktu</th><th>Peminjam</th><th>Jatuh tempo</th><th>Unit / progress</th><th>Detail</th></tr></thead><tbody>
                @forelse($loans as $loan)
                    <tr wire:key="loan-{{ $loan->id }}"><td><strong>{{ $loan->loan_number }}</strong><div class="small text-muted">{{ \App\Services\Equipment\LoanRules::wib($loan->borrowed_at) }}</div></td><td>{{ $loan->borrower_name }}<div class="small text-muted">{{ ['student'=>'Mahasiswa','lecturer'=>'Dosen','staff'=>'Staf'][$loan->borrower_type] ?? $loan->borrower_type }}</div></td><td>{{ \Carbon\CarbonImmutable::parse($loan->due_date)->format('d/m/Y') }} WIB
                        @if($loan->overdue)
                            <div><span class="badge bg-danger">Terlambat</span></div>
                        @endif
                    </td><td>{{ $loan->total_units - $loan->open_units }}/{{ $loan->total_units }} selesai<div>{{ ['active'=>'Aktif','partially_resolved'=>'Selesai sebagian','closed'=>'Selesai','voided'=>'Dibatalkan','integrity_error'=>'Perlu pemeriksaan'][$loan->progress] }}</div></td><td><a href="{{ route('admin.equipment-loans.show',['loanId'=>$loan->id]) }}" class="btn btn-sm btn-outline-primary">Buka</a></td></tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada transaksi yang sesuai filter.</td></tr>
                @endforelse
            </tbody></table></div>
            {{ $loans->links() }}
        </div></section>
    @endif
</div>
