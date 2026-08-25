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
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card metric-card metric-primary border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-lg bg-primary bg-opacity-10 rounded-3 p-2 me-3">
                            <i class="bi bi-people-fill text-primary fs-3"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase">Total Pengguna</div>
                            <h4 class="mb-0 fw-bold text-dark">{{ $stats['total'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card metric-card metric-success border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-lg bg-success bg-opacity-10 rounded-3 p-2 me-3">
                            <i class="bi bi-person-check-fill text-success fs-3"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase">Pengguna Aktif</div>
                            <h4 class="mb-0 fw-bold text-dark">{{ $stats['active'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card metric-card metric-red border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-lg bg-danger bg-opacity-10 rounded-3 p-2 me-3">
                            <i class="bi bi-person-x-fill text-danger fs-3"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase">Pengguna Nonaktif</div>
                            <h4 class="mb-0 fw-bold text-dark">{{ $stats['inactive'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card metric-card metric-cyan border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-lg bg-info bg-opacity-10 rounded-3 p-2 me-3">
                            <i class="bi bi-shield-lock-fill text-info fs-3"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase">Peran Tersedia</div>
                            <h4 class="mb-0 fw-bold text-dark">{{ $stats['roles_count'] }}</h4>
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
                    <h5 class="card-title mb-1 text-primary"><i class="bi bi-people me-2"></i>Manajemen Pengguna</h5>
                    <p class="text-muted small mb-0">Kelola akun administrator, penetapan peran, dan status akses masuk.</p>
                </div>
                <div>
                    @can('create', App\Models\User::class)
                        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                            <i class="bi bi-person-plus-fill me-1"></i> Tambah Pengguna
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="card-body">
            <!-- Filter & Search Toolbar -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" 
                               wire:model.live.debounce.300ms="search" 
                               class="form-control bg-light border-start-0" 
                               placeholder="Cari nama pengguna atau username...">
                        @if($search)
                            <button class="btn btn-light border" type="button" wire:click="$set('search', '')">
                                <i class="bi bi-x"></i>
                            </button>
                        @endif
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <select wire:model.live="roleFilter" class="form-select bg-light">
                        <option value="">Semua Peran</option>
                        @foreach ($roles as $roleOption)
                            <option value="{{ $roleOption->id }}">{{ $roleOption->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <select wire:model.live="statusFilter" class="form-select bg-light">
                        <option value="">Semua Status</option>
                        <option value="1">Aktif (1)</option>
                        <option value="0">Nonaktif (0)</option>
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

            <!-- Users Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 50px;">Avatar</th>
                            <th scope="col" style="cursor: pointer;" wire:click="sortBy('name')">
                                Nama & Nama Pengguna
                                @if($sortField === 'name')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" style="cursor: pointer; width: 180px;" wire:click="sortBy('role')">
                                Peran
                                @if($sortField === 'role')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" class="text-center" style="cursor: pointer; width: 110px;" wire:click="sortBy('status')">
                                Status
                                @if($sortField === 'status')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
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
                        @forelse ($users as $user)
                            <tr wire:key="user-{{ $user->id }}">
                                <td>
                                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center border" 
                                         style="width: 40px; height: 40px; font-size: 0.85rem;">
                                        {{ $user->initials }}
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">
                                        {{ $user->name }}
                                        @if($user->id === auth()->id())
                                            <span class="badge bg-secondary bg-opacity-25 text-dark ms-1 small">You</span>
                                        @endif
                                    </div>
                                    <small class="text-muted font-monospace">@<span>{{ $user->username }}</span></small>
                                </td>
                                <td>
                                    @if ($user->role)
                                        @if($user->role->name === 'Super Admin')
                                            <span class="badge bg-danger"><i class="bi bi-shield-fill-check me-1"></i>Super Admin</span>
                                        @elseif($user->role->name === 'Admin Production')
                                            <span class="badge bg-primary"><i class="bi bi-camera-reels-fill me-1"></i>Production</span>
                                        @elseif($user->role->name === 'Admin News')
                                            <span class="badge bg-info text-dark"><i class="bi bi-newspaper me-1"></i>Berita</span>
                                        @else
                                            <span class="badge bg-secondary">{{ $user->role->name }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($user->status)
                                        <span class="badge bg-success">Aktif</span>
                                    @else
                                        <span class="badge bg-danger">Nonaktif</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="small text-muted">
                                        {{ $user->created_at ? $user->created_at->locale('id')->translatedFormat('d M Y') : '-' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        @can('update', $user)
                                            <a href="{{ route('admin.users.edit', $user) }}" 
                                               class="btn btn-outline-primary" 
                                               title="Edit Pengguna">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        @endcan

                                        @can('delete', $user)
                                            <button type="button" 
                                                    wire:click="delete({{ $user->id }})" 
                                                    data-admin-confirm="Apakah Anda yakin ingin menghapus pengguna '{{ $user->name ?: $user->username }}'? Tindakan ini tidak dapat dibatalkan."
                                                    class="btn btn-outline-danger" 
                                                    title="Hapus Pengguna">
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
                                        <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                                        <h6>Pengguna tidak ditemukan.</h6>
                                        <p class="small mb-0">Belum ada data user yang sesuai dengan filter atau pencarian Anda.</p>
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
                    Menampilkan {{ $users->firstItem() ?? 0 }} sampai {{ $users->lastItem() ?? 0 }} dari {{ $users->total() }} pengguna
                </div>
                <div>
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
