<div x-on:equipment-feedback.window="window.WPiAlert?.toast($event.detail.message, $event.detail.type)">
    <div class="page-heading d-flex flex-wrap justify-content-between align-items-end gap-2">
        <div>
            <nav aria-label="Breadcrumb"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>@if($creating)<li class="breadcrumb-item"><a href="{{ route('admin.equipment-loans.index') }}">Peminjaman</a></li><li class="breadcrumb-item active" aria-current="page">Peminjaman Baru</li>@else<li class="breadcrumb-item active" aria-current="page">Peminjaman Peralatan</li>@endif</ol></nav>
            <h1 class="h3 mb-1">{{ $creating ? 'Peminjaman Baru' : 'Peminjaman Peralatan' }}</h1>
            <p class="text-muted mb-0">Pencatatan peminjaman, pengembalian, dan histori peralatan WPiTV.</p>
        </div>
        <div>
            @if($creating)
                <a href="{{ route('admin.equipment-loans.index') }}" class="btn btn-outline-secondary">Kembali ke daftar</a>
            @else
                @can('equipment.loan.checkout')
                    <a href="{{ route('admin.equipment-loans.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Peminjaman baru</a>
                @endcan
            @endif
        </div>
    </div>
    @if($creating)
        @include('livewire.admin.equipment-field-error', ['field'=>'action'])
        <form wire:submit="checkout" data-admin-confirm-title="Simpan peminjaman untuk {{ count($selected) }} unit yang dipilih?" data-admin-confirm-text="Ya, simpan peminjaman">
            <div class="row g-3">
                <div class="col-12 col-lg-5">
                    <section class="card"><div class="card-header"><h5>Identitas peminjam</h5></div><div class="card-body">
                        <label for="loan-type" class="form-label">Jenis peminjam</label>
                        <select id="loan-type" wire:model="form.borrower_type" class="form-select mb-3"><option value="student">Mahasiswa</option><option value="lecturer">Dosen</option><option value="staff">Staf</option></select>@include('livewire.admin.equipment-field-error', ['field'=>'form.borrower_type'])
                        <label for="loan-name" class="form-label">Nama lengkap</label><input id="loan-name" wire:model="form.borrower_name" maxlength="150" class="form-control mb-3" required>@include('livewire.admin.equipment-field-error', ['field'=>'form.borrower_name'])
                        <label for="loan-identity" class="form-label">NIM / NIDN / NIP / identitas institusi</label><input id="loan-identity" wire:model="form.identity_number" maxlength="40" class="form-control mb-3" required>@include('livewire.admin.equipment-field-error', ['field'=>'form.identity_number'])
                        <label for="loan-contact" class="form-label">Kontak / WhatsApp</label><input id="loan-contact" type="tel" autocomplete="tel" wire:model="form.borrower_contact" maxlength="100" class="form-control mb-3" required>@include('livewire.admin.equipment-field-error', ['field'=>'form.borrower_contact'])
                        <label for="loan-affiliation" class="form-label">Program studi / unit / instansi</label><input id="loan-affiliation" wire:model="form.borrower_affiliation" maxlength="150" class="form-control mb-3" required>@include('livewire.admin.equipment-field-error', ['field'=>'form.borrower_affiliation'])
                        <label for="loan-purpose" class="form-label">Keperluan</label><textarea id="loan-purpose" wire:model="form.purpose" maxlength="5000" class="form-control mb-3" required></textarea>@include('livewire.admin.equipment-field-error', ['field'=>'form.purpose'])
                        <label for="loan-due" class="form-label">Jatuh tempo (akhir hari WIB)</label><input id="loan-due" type="date" wire:model="form.due_date" min="{{ now('Asia/Jakarta')->toDateString() }}" class="form-control" required>@include('livewire.admin.equipment-field-error', ['field'=>'form.due_date'])
                    </div></section>
                </div>
                <div class="col-12 col-lg-7">
                    <section class="card"><div class="card-header"><h2 class="h5 mb-0">Unit tersedia</h2></div><div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-7"><label for="available-search" class="form-label small mb-1">Cari unit tersedia</label><input id="available-search" wire:model.live.debounce.300ms="search" maxlength="100" class="form-control" placeholder="Cari kode aset, serial, model, atau kategori…"></div>
                            <div class="col-md-5"><label for="available-category" class="form-label small mb-1">Filter kategori</label><select id="available-category" wire:model.live="category" class="form-select"><option value="">Semua kategori</option>@foreach($categories ?? [] as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach</select></div>
                        </div>
                        <p class="small text-muted">Hanya unit aktif, kondisi baik, model aktif, dan tidak sedang dipinjam. Maksimal 50 unit dari 20 model.</p>
                        <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Pilih</th><th>Aset</th><th>Model / kategori</th></tr></thead><tbody>
                            @forelse($available as $unit)
                                <tr wire:key="available-{{ $unit->id }}"><td><input @disabled(count($selected)>=50 && !in_array($unit->id,$selected)) type="checkbox" wire:model.live="selected" value="{{ $unit->id }}" id="asset-{{ $unit->id }}" aria-label="Pilih {{ $unit->asset_code }}" class="form-check-input"></td><td><label for="asset-{{ $unit->id }}" class="fw-semibold mb-0">{{ $unit->asset_code }}</label>@if($unit->serial_number)<div class="small text-muted">SN: {{ $unit->serial_number }}</div>@endif</td><td>{{ $unit->model_name }}<div class="small text-muted">{{ $unit->category_name }}</div></td></tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">Tidak ada unit tersedia yang cocok. Periksa Inventaris atau ubah pencarian.</td></tr>
                            @endforelse
                        </tbody></table></div>
                        {{ $available->links() }}
                    </div></section>
                    <section class="card"><div class="card-header"><h5 aria-live="polite">Unit dipilih ({{ count($selected) }})</h5></div><div class="card-body">
                        @include('livewire.admin.equipment-field-error', ['field'=>'units'])
                        @forelse($basket as $unit)
                            <div class="d-flex justify-content-between gap-2 border-bottom py-2" wire:key="basket-{{ $unit->id }}"><div><strong>{{ $unit->asset_code }}</strong> · {{ $unit->model_name }}</div><button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeUnit({{ $unit->id }})" aria-label="Lepas {{ $unit->asset_code }}">Lepas</button></div>
                        @empty
                            <p class="text-muted">Pilih minimal satu unit dari daftar tersedia.</p>
                        @endforelse
                        <p class="small mt-3">Nomor peminjaman dan waktu dicatat otomatis. Ketersediaan diperiksa ulang saat penyimpanan.</p>
                        <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="checkout,selected,removeUnit" @disabled(!count($selected))>Simpan peminjaman</button>
                    </div></section>
                </div>
            </div>
        </form>
    @else
        <section class="card"><div class="card-body pt-3"><span wire:loading.delay role="status" class="small text-muted">Memuat transaksi…</span>
            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-4"><label for="loan-search" class="form-label small mb-1">Pencarian transaksi</label><input id="loan-search" wire:model.live.debounce.300ms="search" maxlength="100" class="form-control" placeholder="Cari nomor, nama, atau identitas…"></div>
                <div class="col-md-3"><label for="loan-status" class="form-label small mb-1">Status transaksi</label><select id="loan-status" wire:model.live="status" class="form-select"><option value="">Semua status</option><option value="open">Menunggu pengembalian</option><option value="active">Aktif</option><option value="partially_resolved">Selesai sebagian</option><option value="closed">Selesai</option><option value="voided">Dibatalkan</option><option value="overdue">Terlambat</option><option value="integrity_error">Perlu pemeriksaan</option></select></div>
                <div class="col-md-2"><label for="loan-borrower" class="form-label small mb-1">Jenis peminjam</label><select id="loan-borrower" wire:model.live="borrowerType" class="form-select"><option value="">Semua peminjam</option><option value="student">Mahasiswa</option><option value="lecturer">Dosen</option><option value="staff">Staf</option></select></div>
                <div class="col-auto"><label for="loan-page-size" class="form-label small mb-1">Per halaman</label><select id="loan-page-size" wire:model.live="perPage" class="form-select"><option value="10">10</option><option value="25">25</option><option value="50">50</option></select></div>
                <div class="col-auto"><button type="button" class="btn btn-outline-secondary" wire:click="resetFilters">Reset filter</button></div>
            </div>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nomor / waktu</th><th>Peminjam</th><th>Jatuh tempo</th><th>Unit / progress</th><th>Detail</th></tr></thead><tbody>
                @forelse($loans as $loan)
                    @php($progressBadge = match($loan->progress) {
                        'active' => 'bg-primary',
                        'partially_resolved' => 'bg-warning text-dark',
                        'closed' => 'bg-success',
                        'voided' => 'bg-secondary',
                        default => 'bg-danger',
                    })
                    <tr wire:key="loan-{{ $loan->id }}"><td><strong>{{ $loan->loan_number }}</strong><div class="small text-muted">{{ \App\Services\Equipment\LoanRules::wib($loan->borrowed_at) }}</div></td><td>{{ $loan->borrower_name }}<div class="small text-muted">{{ ['student'=>'Mahasiswa','lecturer'=>'Dosen','staff'=>'Staf'][$loan->borrower_type] ?? $loan->borrower_type }}</div></td><td>{{ \Carbon\CarbonImmutable::parse($loan->due_date)->format('d/m/Y') }} WIB
                        @if($loan->overdue)
                            <div><span class="badge bg-danger">Terlambat</span></div>
                        @endif
                    </td><td><div class="fw-semibold">{{ $loan->total_units - $loan->open_units }}/{{ $loan->total_units }} selesai</div><span class="badge {{ $progressBadge }}">{{ ['active'=>'Aktif','partially_resolved'=>'Selesai sebagian','closed'=>'Selesai','voided'=>'Dibatalkan','integrity_error'=>'Perlu pemeriksaan'][$loan->progress] }}</span></td><td><a href="{{ route('admin.equipment-loans.show',['loanId'=>$loan->id]) }}" class="btn btn-sm btn-outline-primary">Buka</a></td></tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">{{ $search || $status || $borrowerType ? 'Tidak ada peminjaman yang cocok dengan filter.' : 'Belum ada peminjaman.' }}</td></tr>
                @endforelse
            </tbody></table></div>
            {{ $loans->links() }}
        </div></section>
    @endif
</div>
