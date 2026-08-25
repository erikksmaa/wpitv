<div>
    <!-- Flash Feedback Alert -->
    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible show fade">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill fs-5 me-2"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible show fade">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                <div>{{ session('error') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Summary Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card metric-card metric-primary border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Total Permohonan</div>
                    <h4 class="mb-0 fw-bold text-dark mt-1">{{ $stats['total'] }}</h4>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card metric-card metric-orange border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Menunggu</div>
                    <h4 class="mb-0 fw-bold text-warning mt-1">{{ $stats['pending'] }}</h4>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card metric-card metric-primary border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Disetujui</div>
                    <h4 class="mb-0 fw-bold text-primary mt-1">{{ $stats['approved'] }}</h4>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card metric-card metric-cyan border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Sedang Berjalan</div>
                    <h4 class="mb-0 fw-bold text-info mt-1">{{ $stats['in_progress'] }}</h4>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card metric-card metric-success border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Selesai</div>
                    <h4 class="mb-0 fw-bold text-success mt-1">{{ $stats['completed'] }}</h4>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card metric-card metric-red border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Ditolak</div>
                    <h4 class="mb-0 fw-bold text-danger mt-1">{{ $stats['rejected'] }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h5 class="card-title mb-1 text-primary"><i class="bi bi-camera-reels-fill me-2"></i>Peliputan & Liputan Acara</h5>
                    <p class="text-muted small mb-0">Kelola permohonan peliputan acara, jadwal liputan, dan status produksi liputan.</p>
                </div>
                <div>
                    @can('create', App\Models\Coverage::class)
                        <a href="{{ route('admin.peliputan.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Peliputan
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="card-body">
            <!-- Filter & Search Toolbar -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" 
                               wire:model.live.debounce.300ms="search" 
                               class="form-control bg-light border-start-0" 
                               placeholder="Cari kode, agenda, lokasi, atau pemohon...">
                        @if($search)
                            <button class="btn btn-light border" type="button" wire:click="$set('search', '')">
                                <i class="bi bi-x"></i>
                            </button>
                        @endif
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <select wire:model.live="statusFilter" class="form-select bg-light">
                        <option value="">Semua Status</option>
                        @foreach ($statusOptions as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <input type="date" wire:model.live="dateFrom" class="form-control bg-light" title="Tanggal Mulai">
                </div>

                <div class="col-6 col-md-2">
                    <input type="date" wire:model.live="dateTo" class="form-control bg-light" title="Tanggal Selesai">
                </div>

                <div class="col-6 col-md-1">
                    <select wire:model.live="perPage" class="form-select bg-light" title="Baris per halaman">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>

            <!-- Coverages Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 140px;">Kode / Tanggal</th>
                            <th scope="col" style="cursor: pointer;" wire:click="sortBy('event_name')">
                                Agenda & Lokasi
                                @if($sortField === 'event_name')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" style="cursor: pointer; width: 180px;" wire:click="sortBy('name')">
                                Pemohon / Kontak
                                @if($sortField === 'name')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" style="cursor: pointer; width: 140px;" wire:click="sortBy('event_date')">
                                Jadwal Acara
                                @if($sortField === 'event_date')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" class="text-center" style="cursor: pointer; width: 130px;" wire:click="sortBy('status')">
                                Status
                                @if($sortField === 'status')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" class="text-end" style="width: 140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($coverages as $coverage)
                            <tr wire:key="coverage-{{ $coverage->id }}">
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace">{{ $coverage->submission_code }}</span>
                                    <div class="text-muted small mt-1">
                                        {{ $coverage->created_at ? $coverage->created_at->locale('id')->translatedFormat('d M Y') : '-' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $coverage->event_name }}</div>
                                    <div class="text-muted small">
                                        <i class="bi bi-geo-alt me-1 text-danger"></i>{{ Str::limit($coverage->location, 45) }}
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $coverage->name }}</div>
                                    <div class="text-muted small">
                                        <i class="bi bi-telephone me-1"></i>{{ $coverage->phone }}
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">
                                        <i class="bi bi-calendar-event me-1 text-primary"></i>
                                        {{ $coverage->event_date ? $coverage->event_date->locale('id')->translatedFormat('d M Y') : '-' }}
                                    </div>
                                    @if ($coverage->event_time)
                                        <div class="text-muted small">
                                            <i class="bi bi-clock me-1"></i>{{ substr($coverage->event_time, 0, 5) }} WIB
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $coverage->status_badge_class }} px-2 py-1">
                                        {{ $coverage->status_label }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" 
                                                wire:click="openDetailModal({{ $coverage->id }})" 
                                                class="btn btn-outline-info" 
                                                title="Lihat Detail Permohonan">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        @can('updateStatus', $coverage)
                                            <button type="button" 
                                                    wire:click="openStatusModal({{ $coverage->id }})" 
                                                    class="btn btn-outline-warning" 
                                                    title="Ubah Status">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        @endcan

                                        @can('update', $coverage)
                                            <a href="{{ route('admin.peliputan.edit', $coverage) }}" 
                                               class="btn btn-outline-primary" 
                                               title="Edit Peliputan">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        @endcan

                                        @can('delete', $coverage)
                                            <button type="button" 
                                                    wire:click="delete({{ $coverage->id }})" 
                                                    data-admin-confirm="Apakah Anda yakin ingin menghapus permohonan peliputan '{{ $coverage->submission_code }}'? Tindakan ini tidak dapat dibatalkan."
                                                    class="btn btn-outline-danger" 
                                                    title="Hapus Peliputan">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-camera-reels fs-1 d-block mb-2 text-secondary"></i>
                                        <h6>Permohonan peliputan tidak ditemukan.</h6>
                                        <p class="small mb-0">Belum ada permohonan peliputan yang sesuai dengan filter atau pencarian Anda.</p>
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
                    Menampilkan {{ $coverages->firstItem() ?? 0 }} sampai {{ $coverages->lastItem() ?? 0 }} dari {{ $coverages->total() }} permohonan
                </div>
                <div>
                    {{ $coverages->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Status Modal -->
    @if ($showStatusModal)
        <div class="modal fade show d-block admin-modal-backdrop"
             tabindex="-1"
             aria-modal="true"
             aria-labelledby="status-modal-title"
             role="dialog"
             wire:keydown.escape.window="closeStatusModal"
             wire:click.self="closeStatusModal">
            <div class="modal-dialog modal-dialog-centered admin-modal-dialog">
                <div class="modal-content admin-modal">
                    <div class="admin-modal-header">
                        <div class="admin-modal-heading">
                            <span class="admin-modal-heading-icon" aria-hidden="true"><i class="bi bi-arrow-repeat"></i></span>
                            <div>
                                <span class="admin-modal-eyebrow">Manajemen Peliputan</span>
                                <h5 class="modal-title" id="status-modal-title">Perbarui Status Permohonan</h5>
                            </div>
                        </div>
                        <button type="button" class="admin-modal-close" wire:click="closeStatusModal" aria-label="Tutup modal">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </div>

                    <form wire:submit.prevent="updateStatus">
                        <div class="modal-body admin-modal-body">
                            <div class="admin-modal-intro">
                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                                <div>
                                    <strong>Tentukan progres penanganan permohonan</strong>
                                    <span>Status akan terlihat oleh petugas sebagai acuan proses produksi berikutnya.</span>
                                </div>
                            </div>

                            <div class="admin-modal-field">
                                <label for="newStatus" class="form-label fw-semibold">
                                    <i class="bi bi-activity" aria-hidden="true"></i>Status Baru
                                </label>
                                <select id="newStatus" wire:model="newStatus" class="form-select @error('newStatus') is-invalid @enderror">
                                    @foreach ($statusOptions as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('newStatus')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="admin-modal-field-help">Pilih status yang paling sesuai dengan kondisi penanganan saat ini.</small>
                            </div>

                            <div class="admin-modal-field">
                                <label for="adminNote" class="form-label fw-semibold">
                                    <i class="bi bi-journal-text" aria-hidden="true"></i>Catatan Admin
                                </label>
                                <textarea id="adminNote" 
                                          wire:model="adminNote" 
                                          rows="3" 
                                          class="form-control @error('adminNote') is-invalid @enderror"
                                          placeholder="Tuliskan catatan tindak lanjut, PIC liputan, atau alasan penolakan..."></textarea>
                                @error('adminNote')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="admin-modal-field-help">Isi bila ada evaluasi, PIC, atau informasi tindak lanjut yang perlu dicatat.</small>
                            </div>
                        </div>

                        <div class="admin-modal-footer">
                            <span class="admin-modal-footer-hint"><i class="bi bi-shield-check" aria-hidden="true"></i> Perubahan dicatat setelah dikonfirmasi</span>
                            <div class="admin-modal-actions">
                                <button type="button" class="btn btn-light" wire:click="closeStatusModal">Batal</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Simpan
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Detail View Modal -->
    @if ($showDetailModal && $selectedCoverage)
        <div class="modal fade show d-block coverage-detail-backdrop" tabindex="-1" aria-modal="true" role="dialog" wire:keydown.escape.window="closeDetailModal" wire:click.self="closeDetailModal">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content coverage-detail-modal">
                    <div class="coverage-detail-header">
                        <div class="coverage-detail-heading">
                            <span class="coverage-detail-heading-icon" aria-hidden="true"><i class="bi bi-camera-reels"></i></span>
                            <div>
                                <span class="coverage-detail-eyebrow">Permohonan Peliputan</span>
                                <h5 class="modal-title">Rincian Pengajuan</h5>
                            </div>
                        </div>
                        <button type="button" class="coverage-detail-close" wire:click="closeDetailModal" aria-label="Tutup rincian">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="modal-body coverage-detail-body">
                        <section class="coverage-detail-hero">
                            <div class="coverage-detail-hero-main">
                                <div class="coverage-detail-meta-row">
                                    <span class="coverage-code"><i class="bi bi-hash" aria-hidden="true"></i>{{ $selectedCoverage->submission_code }}</span>
                                    <span class="badge {{ $selectedCoverage->status_badge_class }}">{{ $selectedCoverage->status_label }}</span>
                                </div>
                                <h4>{{ $selectedCoverage->event_name }}</h4>
                                <div class="coverage-detail-event-meta">
                                    <span><i class="bi bi-calendar3" aria-hidden="true"></i>{{ $selectedCoverage->event_date ? $selectedCoverage->event_date->locale('id')->translatedFormat('l, d F Y') : 'Jadwal belum ditentukan' }}</span>
                                    @if ($selectedCoverage->event_time)
                                        <span><i class="bi bi-clock" aria-hidden="true"></i>{{ substr($selectedCoverage->event_time, 0, 5) }} WIB</span>
                                    @endif
                                    <span><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ $selectedCoverage->location }}</span>
                                </div>
                            </div>
                        </section>

                        <div class="coverage-detail-grid">
                            <section class="coverage-info-card">
                                <span class="coverage-info-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
                                <div>
                                    <span class="coverage-info-label">Pemohon / PIC</span>
                                    <strong>{{ $selectedCoverage->name }}</strong>
                                </div>
                            </section>
                            <section class="coverage-info-card">
                                <span class="coverage-info-icon"><i class="bi bi-telephone" aria-hidden="true"></i></span>
                                <div>
                                    <span class="coverage-info-label">Nomor Telepon</span>
                                    <strong>{{ $selectedCoverage->phone ?: '-' }}</strong>
                                </div>
                            </section>
                            <section class="coverage-info-card coverage-info-card--wide">
                                <span class="coverage-info-icon"><i class="bi bi-envelope" aria-hidden="true"></i></span>
                                <div>
                                    <span class="coverage-info-label">Alamat Email</span>
                                    <strong>{{ $selectedCoverage->email ?: '-' }}</strong>
                                </div>
                            </section>
                        </div>

                        <section class="coverage-detail-section">
                            <div class="coverage-detail-section-title">
                                <span><i class="bi bi-card-text" aria-hidden="true"></i></span>
                                <div>
                                    <h6>Kebutuhan Peliputan</h6>
                                    <small>Deskripsi dan kebutuhan dari pemohon</small>
                                </div>
                            </div>
                            <div class="coverage-detail-description">{{ $selectedCoverage->description ?: 'Pemohon tidak menyertakan deskripsi tambahan.' }}</div>
                        </section>

                        @if ($selectedCoverage->admin_note)
                            <section class="coverage-admin-note">
                                <span class="coverage-admin-note-icon"><i class="bi bi-journal-check" aria-hidden="true"></i></span>
                                <div>
                                    <span>Catatan Admin</span>
                                    <p>{{ $selectedCoverage->admin_note }}</p>
                                </div>
                            </section>
                        @endif
                    </div>

                    <div class="coverage-detail-footer">
                        <span class="coverage-detail-footer-hint"><i class="bi bi-shield-check" aria-hidden="true"></i> Data hanya dapat diubah oleh petugas berwenang</span>
                        <div class="d-flex gap-2">
                            @can('update', $selectedCoverage)
                                <a href="{{ route('admin.peliputan.edit', $selectedCoverage) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Edit
                                </a>
                            @endcan
                            <button type="button" class="btn btn-primary btn-sm" wire:click="closeDetailModal">
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
