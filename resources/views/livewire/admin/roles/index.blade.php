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
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card metric-card metric-primary border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-lg bg-primary bg-opacity-10 rounded-3 p-2 me-3">
                            <i class="bi bi-shield-check text-primary fs-3"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase">Total Peran</div>
                            <h4 class="mb-0 fw-bold text-dark">{{ $stats['total_roles'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card metric-card metric-success border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-lg bg-success bg-opacity-10 rounded-3 p-2 me-3">
                            <i class="bi bi-person-badge-fill text-success fs-3"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase">Peran Digunakan</div>
                            <h4 class="mb-0 fw-bold text-dark">{{ $stats['roles_in_use'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card metric-card metric-cyan border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-lg bg-info bg-opacity-10 rounded-3 p-2 me-3">
                            <i class="bi bi-people-fill text-info fs-3"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase">Total Pengguna</div>
                            <h4 class="mb-0 fw-bold text-dark">{{ $stats['total_users'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h5 class="card-title mb-1 text-primary"><i class="bi bi-shield-lock me-2"></i>Manajemen Peran</h5>
                    <p class="text-muted small mb-0">Kelola peran, hak akses, dan otorisasi administrator WPiTV.</p>
                </div>
                <div>
                    @can('create', App\Models\Role::class)
                        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Peran
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="card-body">
            <!-- Filter & Search Toolbar -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" 
                               wire:model.live.debounce.300ms="search" 
                               class="form-control bg-light border-start-0" 
                               placeholder="Cari nama role atau deskripsi...">
                        @if($search)
                            <button class="btn btn-light border" type="button" wire:click="$set('search', '')">
                                <i class="bi bi-x"></i>
                            </button>
                        @endif
                    </div>
                </div>

                <div class="col-6 col-md-2 ms-auto">
                    <select wire:model.live="perPage" class="form-select bg-light">
                        <option value="10">10 per hal</option>
                        <option value="25">25 per hal</option>
                        <option value="50">50 per hal</option>
                    </select>
                </div>
            </div>

            <!-- Roles Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 50px;">No.</th>
                            <th scope="col" style="cursor: pointer;" wire:click="sortBy('name')">
                                Nama Peran
                                @if($sortField === 'name')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col">Deskripsi</th>
                            <th scope="col" class="text-center" style="width: 130px;">Pengguna Terkait</th>
                            <th scope="col" style="cursor: pointer; width: 140px;" wire:click="sortBy('created_at')">
                                Dibuat
                                @if($sortField === 'created_at')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" class="text-end" style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roles as $index => $role)
                            <tr wire:key="role-{{ $role->id }}">
                                <td class="text-muted fw-semibold small">
                                    {{ $roles->firstItem() + $index }}
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">
                                        @if($role->name === 'Super Admin')
                                            <span class="badge bg-danger me-1"><i class="bi bi-shield-fill-check me-1"></i>Super Admin</span>
                                        @elseif($role->name === 'Admin Production')
                                            <span class="badge bg-primary me-1"><i class="bi bi-camera-reels-fill me-1"></i>Production</span>
                                        @elseif($role->name === 'Admin News')
                                            <span class="badge bg-info text-dark me-1"><i class="bi bi-newspaper me-1"></i>Berita</span>
                                        @else
                                            <span class="badge bg-secondary me-1"><i class="bi bi-person-badge me-1"></i>{{ $role->name }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="text-muted small">
                                        {{ $role->description ? Str::limit($role->description, 80) : '-' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        <i class="bi bi-people me-1 text-muted"></i> {{ $role->users_count }} Pengguna
                                    </span>
                                </td>
                                <td>
                                    <span class="small text-muted">
                                        {{ $role->created_at ? $role->created_at->locale('id')->translatedFormat('d M Y') : '-' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        @can('update', $role)
                                            <a href="{{ route('admin.roles.edit', $role) }}" 
                                               class="btn btn-outline-primary" 
                                               title="Edit Peran">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        @endcan

                                        @can('delete', $role)
                                            <button type="button" 
                                                    wire:click="delete({{ $role->id }})" 
                                                    data-admin-confirm="Apakah Anda yakin ingin menghapus peran '{{ $role->name }}'? Tindakan ini tidak dapat dibatalkan."
                                                    class="btn btn-outline-danger" 
                                                    title="Hapus Peran">
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
                                        <i class="bi bi-shield-lock fs-1 d-block mb-2 text-secondary"></i>
                                        <h6>Peran tidak ditemukan.</h6>
                                        <p class="small mb-0">Belum ada data peran yang sesuai dengan pencarian Anda.</p>
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
                    Menampilkan {{ $roles->firstItem() ?? 0 }} sampai {{ $roles->lastItem() ?? 0 }} dari {{ $roles->total() }} peran
                </div>
                <div>
                    {{ $roles->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
