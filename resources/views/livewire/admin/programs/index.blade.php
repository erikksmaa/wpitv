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

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h5 class="card-title mb-1 text-primary"><i class="bi bi-collection-play me-2"></i>Daftar Program Televisi</h5>
                    <p class="text-muted small mb-0">Total {{ $programs->total() }} program acara terdaftar pada sistem</p>
                </div>
                <div>
                    @can('create', App\Models\Program::class)
                        <a href="{{ route('admin.programs.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Program
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
                               placeholder="Cari nama program atau slug...">
                        @if($search)
                            <button class="btn btn-light border" type="button" wire:click="$set('search', '')">
                                <i class="bi bi-x"></i>
                            </button>
                        @endif
                    </div>
                </div>

                <div class="col-6 col-md-4 col-lg-3 ms-auto">
                    <select wire:model.live="status" class="form-select bg-light">
                        <option value="all">Semua Status</option>
                        <option value="1">Status: Aktif</option>
                        <option value="0">Status: Nonaktif</option>
                    </select>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <select wire:model.live="perPage" class="form-select bg-light">
                        <option value="10">10 per hal</option>
                        <option value="25">25 per hal</option>
                        <option value="50">50 per hal</option>
                    </select>
                </div>
            </div>

            <!-- Program Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 50px;">#</th>
                            <th scope="col" style="width: 70px;">Sampul</th>
                            <th scope="col" style="cursor: pointer;" wire:click="sortBy('name')">
                                Nama Program
                                @if($sortField === 'name')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" class="d-none d-lg-table-cell" style="max-width: 250px;">Deskripsi</th>
                            <th scope="col" style="cursor: pointer;" wire:click="sortBy('videos_count')">
                                Video
                                @if($sortField === 'videos_count')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col">Status</th>
                            <th scope="col" class="d-none d-md-table-cell" style="cursor: pointer;" wire:click="sortBy('created_at')">
                                Dibuat
                                @if($sortField === 'created_at')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" class="text-end" style="width: 140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($programs as $index => $program)
                            <tr wire:key="program-{{ $program->id }}">
                                <td class="text-muted fw-semibold">
                                    {{ $programs->firstItem() + $index }}
                                </td>
                                <td>
                                    <img src="{{ $program->image_url }}" 
                                         alt="{{ $program->name }}" 
                                         class="rounded object-fit-cover shadow-sm" 
                                         style="width: 45px; height: 45px;"
                                         loading="lazy">
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $program->name }}</div>
                                    <small class="text-muted font-monospace">/{{ $program->slug }}</small>
                                </td>
                                <td class="d-none d-lg-table-cell text-muted small">
                                    {{ Str::limit($program->description ?? '-', 75) }}
                                </td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold px-2 py-1">
                                        <i class="bi bi-play-circle me-1"></i>{{ $program->videos_count }} Video
                                    </span>
                                </td>
                                <td>
                                    @if ($program->status)
                                        <span class="badge bg-success bg-opacity-10 text-success fw-semibold px-2 py-1">
                                            <i class="bi bi-check-circle-fill me-1"></i>Aktif
                                        </span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger fw-semibold px-2 py-1">
                                            <i class="bi bi-x-circle-fill me-1"></i>Nonaktif
                                        </span>
                                    @endif
                                </td>
                                <td class="d-none d-md-table-cell text-muted small">
                                    {{ $program->created_at ? $program->created_at->locale('id')->translatedFormat('d M Y') : '-' }}
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        @can('update', $program)
                                            <a href="{{ route('admin.programs.edit', $program) }}" 
                                               class="btn btn-outline-primary" 
                                               title="Edit Program">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        @endcan

                                        @can('delete', $program)
                                            <button type="button" 
                                                    wire:click="delete({{ $program->id }})" 
                                                    data-admin-confirm="Apakah Anda yakin ingin menghapus program '{{ $program->name }}'? Program yang memiliki video atau data terkait tidak dapat dihapus."
                                                    class="btn btn-outline-danger" 
                                                    title="Hapus Program">
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
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                        <h6>Tidak ada data program ditemukan</h6>
                                        <p class="small mb-0">Coba ubah kata kunci pencarian atau filter status yang dipilih.</p>
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
                    Menampilkan {{ $programs->firstItem() ?? 0 }} sampai {{ $programs->lastItem() ?? 0 }} dari {{ $programs->total() }} program
                </div>
                <div>
                    {{ $programs->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
