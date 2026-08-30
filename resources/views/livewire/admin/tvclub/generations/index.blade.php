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
                    <h5 class="card-title mb-1 text-primary"><i class="bi bi-people me-2"></i>Generasi TV Club</h5>
                    <p class="text-muted small mb-0">Kelola generasi anggota dan angkatan produksi TV Club.</p>
                </div>
                <div>
                    @can('create', App\Models\TVClubGeneration::class)
                        <a href="{{ route('admin.tvclub.generations.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Generasi
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
                               placeholder="Cari nama generasi atau tahun angkatan...">
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

            <!-- Generations Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 50px;">No.</th>
                            <th scope="col" style="cursor: pointer;" wire:click="sortBy('name')">
                                Generasi
                                @if($sortField === 'name')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" class="text-center" style="cursor: pointer; width: 120px;" wire:click="sortBy('year')">
                                Year
                                @if($sortField === 'year')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col">Deskripsi</th>
                            <th scope="col" class="text-center" style="width: 110px;">Anggota</th>
                            <th scope="col" class="text-center" style="width: 110px;">Kelompok</th>
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
                            <th scope="col" class="text-end" style="width: 245px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($generations as $index => $gen)
                            <tr wire:key="gen-{{ $gen->id }}">
                                <td class="text-muted fw-semibold small">
                                    {{ $generations->firstItem() + $index }}
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">
                                        <i class="bi bi-mortarboard text-primary me-1"></i>
                                        {{ $gen->name }}
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary fs-6 px-3 py-1">
                                        {{ $gen->year }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted small">
                                        {{ $gen->description ? Str::limit($gen->description, 60) : '-' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info bg-opacity-10 text-info fw-semibold px-2 py-1">
                                        <i class="bi bi-people me-1"></i> {{ $gen->members_count }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary fw-semibold px-2 py-1">
                                        <i class="bi bi-collection me-1"></i> {{ $gen->groups_count }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if ($gen->status)
                                        <span class="badge bg-success">Aktif</span>
                                    @else
                                        <span class="badge bg-danger">Nonaktif</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="small text-muted">
                                        {{ $gen->created_at ? $gen->created_at->locale('id')->translatedFormat('d M Y') : '-' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex flex-wrap justify-content-end gap-1" role="group" aria-label="Aksi {{ $gen->name }}">
                                        <a href="{{ route('admin.tvclub.generations.organization', $gen) }}"
                                           class="btn btn-sm btn-info text-white fw-semibold"
                                           title="Struktur Organisasi"
                                           aria-label="Kelola struktur organisasi {{ $gen->name }}">
                                            <i class="bi bi-diagram-3 me-1"></i> Struktur Organisasi
                                        </a>
                                        @can('update', $gen)
                                            <a href="{{ route('admin.tvclub.generations.edit', $gen) }}" 
                                               class="btn btn-sm btn-outline-primary"
                                               title="Edit Generasi">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        @endcan

                                        @can('delete', $gen)
                                            <button type="button" 
                                                    wire:click="delete({{ $gen->id }})" 
                                                    data-admin-confirm="Apakah Anda yakin ingin menghapus generasi '{{ $gen->name }}'? Tindakan ini tidak dapat dibatalkan."
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Hapus Generasi">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                                        <h6>Generasi tidak ditemukan.</h6>
                                        <p class="small mb-0">Belum ada data generasi yang terdaftar atau sesuai filter.</p>
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
                    Menampilkan {{ $generations->firstItem() ?? 0 }} sampai {{ $generations->lastItem() ?? 0 }} dari {{ $generations->total() }} generasi
                </div>
                <div>
                    {{ $generations->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
