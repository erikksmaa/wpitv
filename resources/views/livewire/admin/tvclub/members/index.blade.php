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

    <!-- 4 Summary Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card metric-card metric-primary border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small fw-semibold">Total Anggota</span>
                        <div class="bg-primary bg-opacity-10 text-primary p-2 rounded">
                            <i class="bi bi-people-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-0 text-dark">{{ number_format($stats['total']) }}</h3>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card metric-card metric-success border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small fw-semibold">Anggota Aktif</span>
                        <div class="bg-success bg-opacity-10 text-success p-2 rounded">
                            <i class="bi bi-person-check-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-0 text-success">{{ number_format($stats['active']) }}</h3>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card metric-card metric-orange border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small fw-semibold">Alumni / Nonaktif</span>
                        <div class="bg-secondary bg-opacity-10 text-secondary p-2 rounded">
                            <i class="bi bi-person-x-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-0 text-secondary">{{ number_format($stats['inactive']) }}</h3>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card metric-card metric-cyan border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small fw-semibold">Generasi</span>
                        <div class="bg-info bg-opacity-10 text-info p-2 rounded">
                            <i class="bi bi-mortarboard-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-0 text-info">{{ number_format($stats['generations_count']) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h5 class="card-title mb-1 text-primary"><i class="bi bi-person-badge me-2"></i>Anggota TV Club</h5>
                    <p class="text-muted small mb-0">Kelola data mahasiswa, generasi, foto profil, dan status keanggotaan TV Club.</p>
                </div>
                <div>
                    @can('create', App\Models\TVClubMember::class)
                        <a href="{{ route('admin.tvclub.members.create') }}" class="btn btn-primary">
                            <i class="bi bi-person-plus me-1"></i> Tambah Anggota
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
                               placeholder="Cari nama atau NIM anggota...">
                        @if($search)
                            <button class="btn btn-light border" type="button" wire:click="$set('search', '')">
                                <i class="bi bi-x"></i>
                            </button>
                        @endif
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <select wire:model.live="generationFilter" class="form-select bg-light">
                        <option value="">Semua Generasi</option>
                        @foreach ($generations as $gen)
                            <option value="{{ $gen->id }}">{{ $gen->name }} ({{ $gen->year }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <select wire:model.live="statusFilter" class="form-select bg-light">
                        <option value="">Semua Status</option>
                        <option value="1">Aktif</option>
                        <option value="0">Alumni / Nonaktif</option>
                    </select>
                </div>

                <div class="col-6 col-md-2 ms-auto">
                    <select wire:model.live="perPage" class="form-select bg-light">
                        <option value="10">10 per hal</option>
                        <option value="25">25 per hal</option>
                        <option value="50">50 per hal</option>
                    </select>
                </div>
            </div>

            <!-- Members Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 50px;">Foto</th>
                            <th scope="col" style="cursor: pointer; width: 140px;" wire:click="sortBy('student_number')">
                                NIM
                                @if($sortField === 'student_number')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" style="cursor: pointer;" wire:click="sortBy('name')">
                                Nama
                                @if($sortField === 'name')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" style="cursor: pointer;" wire:click="sortBy('generation')">
                                Generasi
                                @if($sortField === 'generation')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" class="text-center" style="cursor: pointer; width: 110px;" wire:click="sortBy('status')">
                                Status
                                @if($sortField === 'status')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" class="text-center" style="width: 110px;">Kelompok</th>
                            <th scope="col" style="cursor: pointer; width: 130px;" wire:click="sortBy('created_at')">
                                Dibuat
                                @if($sortField === 'created_at')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" class="text-end" style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($members as $member)
                            <tr wire:key="member-{{ $member->id }}">
                                <td>
                                    @if ($member->photo_url)
                                        <img src="{{ $member->photo_url }}" 
                                             alt="{{ $member->name }}" 
                                             class="rounded-circle object-fit-cover shadow-sm border" 
                                             style="width: 42px; height: 42px;"
                                             onerror="this.onerror=null;this.src='{{ asset('images/placeholders/program-placeholder.svg') }}';"
                                             loading="lazy">
                                    @else
                                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center border" 
                                             style="width: 42px; height: 42px; font-size: 0.9rem;">
                                            {{ $member->initials }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="font-monospace fw-semibold text-dark">
                                        {{ $member->student_number }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">
                                        {{ $member->name }}
                                    </div>
                                </td>
                                <td>
                                    @if ($member->generation)
                                        <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1">
                                            <i class="bi bi-mortarboard me-1"></i>
                                            {{ $member->generation->name }} ({{ $member->generation->year }})
                                        </span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($member->status)
                                        <span class="badge bg-success">Aktif</span>
                                    @else
                                        <span class="badge bg-secondary">Alumni / Nonaktif</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        <i class="bi bi-collection me-1 text-muted"></i> {{ $member->groups_count }} Kelompok
                                    </span>
                                </td>
                                <td>
                                    <span class="small text-muted">
                                        {{ $member->created_at ? $member->created_at->locale('id')->translatedFormat('d M Y') : '-' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        @can('update', $member)
                                            <a href="{{ route('admin.tvclub.members.edit', $member) }}" 
                                               class="btn btn-outline-primary" 
                                               title="Edit Anggota">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        @endcan

                                        @can('delete', $member)
                                            <button type="button" 
                                                    wire:click="delete({{ $member->id }})" 
                                                    data-admin-confirm="Apakah Anda yakin ingin menghapus anggota '{{ $member->name }}'? Tindakan ini tidak dapat dibatalkan."
                                                    class="btn btn-outline-danger" 
                                                    title="Hapus Anggota">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary"></i>
                                        <h6>Anggota TV Club tidak ditemukan.</h6>
                                        <p class="small mb-3">Belum ada data anggota yang terdaftar atau sesuai kriteria filter.</p>
                                        @can('create', App\Models\TVClubMember::class)
                                            <a href="{{ route('admin.tvclub.members.create') }}" class="btn btn-sm btn-primary">
                                                <i class="bi bi-person-plus me-1"></i> Tambah Anggota
                                            </a>
                                        @endcan
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
                    Menampilkan {{ $members->firstItem() ?? 0 }} sampai {{ $members->lastItem() ?? 0 }} dari {{ $members->total() }} anggota
                </div>
                <div>
                    {{ $members->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
