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
                        <span class="text-muted small fw-semibold">Total Kelompok</span>
                        <div class="bg-primary bg-opacity-10 text-primary p-2 rounded">
                            <i class="bi bi-collection-play-fill fs-5"></i>
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
                        <span class="text-muted small fw-semibold">Kelompok Aktif</span>
                        <div class="bg-success bg-opacity-10 text-success p-2 rounded">
                            <i class="bi bi-check-circle-fill fs-5"></i>
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
                        <span class="text-muted small fw-semibold">Kelompok Nonaktif</span>
                        <div class="bg-secondary bg-opacity-10 text-secondary p-2 rounded">
                            <i class="bi bi-dash-circle-fill fs-5"></i>
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
                        <span class="text-muted small fw-semibold">Kelompok Beranggota</span>
                        <div class="bg-info bg-opacity-10 text-info p-2 rounded">
                            <i class="bi bi-people-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-0 text-info">{{ number_format($stats['with_members']) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h5 class="card-title mb-1 text-primary"><i class="bi bi-camera-reels me-2"></i>Kelompok TV Club</h5>
                    <p class="text-muted small mb-0">Kelola kelompok produksi, program, generasi, dan penugasan kru.</p>
                </div>
                <div>
                    @can('create', App\Models\TVClubGroup::class)
                        <a href="{{ route('admin.tvclub.groups.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Kelompok
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="card-body">
            <!-- Filter & Search Toolbar -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" 
                               wire:model.live.debounce.300ms="search" 
                               class="form-control bg-light border-start-0" 
                               placeholder="Cari kelompok, program...">
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
                    <select wire:model.live="programFilter" class="form-select bg-light">
                        <option value="">Semua Program</option>
                        @foreach ($programs as $prog)
                            <option value="{{ $prog->id }}">{{ $prog->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <select wire:model.live="statusFilter" class="form-select bg-light">
                        <option value="">Semua Status</option>
                        <option value="1">Aktif</option>
                        <option value="0">Nonaktif</option>
                    </select>
                </div>

                <div class="col-6 col-md-1 ms-auto">
                    <select wire:model.live="perPage" class="form-select bg-light">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>

            <!-- Groups Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 50px;">No.</th>
                            <th scope="col" style="cursor: pointer;" wire:click="sortBy('name')">
                                Group
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
                            <th scope="col" style="cursor: pointer;" wire:click="sortBy('program')">
                                Program
                                @if($sortField === 'program')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" class="text-center" style="width: 120px;">Anggota</th>
                            <th scope="col" class="text-center" style="cursor: pointer; width: 110px;" wire:click="sortBy('status')">
                                Status
                                @if($sortField === 'status')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
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
                        @forelse ($groups as $index => $group)
                            <tr wire:key="group-{{ $group->id }}">
                                <td class="text-muted fw-semibold small">
                                    {{ $groups->firstItem() + $index }}
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">
                                        <i class="bi bi-camera-reels text-primary me-1"></i>
                                        {{ $group->name }}
                                    </div>
                                    @if ($group->description)
                                        <small class="text-muted d-block">{{ Str::limit($group->description, 50) }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if ($group->generation)
                                        <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1">
                                            <i class="bi bi-mortarboard me-1"></i>
                                            {{ $group->generation->name }} ({{ $group->generation->year }})
                                        </span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($group->program)
                                        <span class="badge bg-info bg-opacity-10 text-info px-2 py-1">
                                            <i class="bi bi-play-circle me-1"></i>
                                            {{ $group->program->name }}
                                        </span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        <i class="bi bi-people me-1 text-muted"></i> {{ $group->members_count }} Anggota
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if ($group->status)
                                        <span class="badge bg-success">Aktif</span>
                                    @else
                                        <span class="badge bg-secondary">Nonaktif</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="small text-muted">
                                        {{ $group->created_at ? $group->created_at->locale('id')->translatedFormat('d M Y') : '-' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        @can('update', $group)
                                            <a href="{{ route('admin.tvclub.groups.edit', $group) }}" 
                                               class="btn btn-outline-primary" 
                                               title="Edit Kelompok & Kru">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        @endcan

                                        @can('delete', $group)
                                            <button type="button" 
                                                    wire:click="delete({{ $group->id }})" 
                                                    data-admin-confirm="Apakah Anda yakin ingin menghapus kelompok '{{ $group->name }}'? Tindakan ini tidak dapat dibatalkan."
                                                    class="btn btn-outline-danger" 
                                                    title="Hapus Kelompok">
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
                                        <i class="bi bi-camera-reels fs-1 d-block mb-2 text-secondary"></i>
                                        <h6>Kelompok produksi TV Club tidak ditemukan.</h6>
                                        <p class="small mb-3">Belum ada kelompok produksi yang terdaftar atau sesuai kriteria filter.</p>
                                        @can('create', App\Models\TVClubGroup::class)
                                            <a href="{{ route('admin.tvclub.groups.create') }}" class="btn btn-sm btn-primary">
                                                <i class="bi bi-plus-lg me-1"></i> Tambah Kelompok
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
                    Menampilkan {{ $groups->firstItem() ?? 0 }} sampai {{ $groups->lastItem() ?? 0 }} dari {{ $groups->total() }} kelompok
                </div>
                <div>
                    {{ $groups->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
