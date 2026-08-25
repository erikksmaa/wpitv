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
                    <h5 class="card-title mb-1 text-primary"><i class="bi bi-play-btn me-2"></i>Daftar Tayangan Video</h5>
                    <p class="text-muted small mb-0">Total {{ $videos->total() }} video tayangan terdaftar pada sistem</p>
                </div>
                <div>
                    @can('create', App\Models\Video::class)
                        <a href="{{ route('admin.videos.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Tayangan
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
                               placeholder="Cari judul video atau YouTube ID...">
                        @if($search)
                            <button class="btn btn-light border" type="button" wire:click="$set('search', '')">
                                <i class="bi bi-x"></i>
                            </button>
                        @endif
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <select wire:model.live="programId" class="form-select bg-light">
                        <option value="all">Semua Program</option>
                        <option value="none">Tanpa Program</option>
                        @foreach ($programs as $prog)
                            <option value="{{ $prog->id }}">{{ $prog->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <select wire:model.live="year" class="form-select bg-light">
                        <option value="all">Semua Tahun</option>
                        @foreach ($years as $yr)
                            <option value="{{ $yr }}">{{ $yr }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-3 col-lg-2 ms-auto">
                    <select wire:model.live="perPage" class="form-select bg-light">
                        <option value="10">10 per hal</option>
                        <option value="25">25 per hal</option>
                        <option value="50">50 per hal</option>
                    </select>
                </div>
            </div>

            <!-- Video Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 50px;">#</th>
                            <th scope="col" style="width: 80px;">Gambar Mini</th>
                            <th scope="col" style="cursor: pointer;" wire:click="sortBy('title')">
                                Judul Video & YouTube ID
                                @if($sortField === 'title')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col">Program</th>
                            <th scope="col" style="cursor: pointer;" wire:click="sortBy('air_date')">
                                Tanggal Tayang
                                @if($sortField === 'air_date')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" class="d-none d-lg-table-cell">Pengunggah</th>
                            <th scope="col" class="text-end" style="width: 140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($videos as $index => $video)
                            <tr wire:key="video-{{ $video->id }}">
                                <td class="text-muted fw-semibold small">
                                    {{ $videos->firstItem() + $index }}
                                </td>
                                <td>
                                    <a href="{{ $video->watch_url }}" target="_blank" rel="noopener noreferrer" title="Tonton di YouTube">
                                        <div class="position-relative d-inline-block">
                                            <img src="{{ $video->thumbnail_url }}" 
                                                 alt="{{ $video->title }}" 
                                                 class="rounded object-fit-cover shadow-sm border" 
                                                 style="width: 60px; height: 45px;"
                                                 onerror="this.onerror=null;this.src='{{ asset('images/placeholders/program-placeholder.svg') }}';"
                                                 loading="lazy">
                                            <span class="position-absolute top-50 start-50 translate-middle text-white bg-dark bg-opacity-75 rounded-circle p-1 d-flex align-items-center justify-content-center" style="width: 20px; height: 20px; font-size: 10px;">
                                                <i class="bi bi-play-fill"></i>
                                            </span>
                                        </div>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark mb-1 text-wrap" style="max-width: 380px;">
                                        {{ $video->title }}
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="badge bg-light text-dark font-monospace border px-2 py-1" style="font-size: 0.75rem;">
                                            <i class="bi bi-youtube text-danger me-1"></i>{{ $video->youtube_id }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @if ($video->program)
                                        <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold px-2 py-1">
                                            {{ $video->program->name }}
                                        </span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary fw-normal px-2 py-1">
                                            Tanpa Program
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="small fw-semibold text-dark">
                                        {{ $video->air_date ? $video->air_date->locale('id')->translatedFormat('d M Y') : '-' }}
                                    </span>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    @if ($video->user)
                                        <span class="small text-muted">
                                            <i class="bi bi-person me-1"></i>{{ $video->user->name }}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-muted border" style="font-size: 0.75rem;">
                                            Legacy / System
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ $video->watch_url }}" 
                                           target="_blank" 
                                           rel="noopener noreferrer" 
                                           class="btn btn-outline-secondary" 
                                           title="Buka di YouTube">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>

                                        @can('update', $video)
                                            <a href="{{ route('admin.videos.edit', $video) }}" 
                                               class="btn btn-outline-primary" 
                                               title="Edit Tayangan">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        @endcan

                                        @can('delete', $video)
                                            <button type="button" 
                                                    wire:click="delete({{ $video->id }})" 
                                                    data-admin-confirm="Apakah Anda yakin ingin menghapus tayangan '{{ $video->title }}'? Tindakan ini bersifat permanen."
                                                    class="btn btn-outline-danger" 
                                                    title="Hapus Tayangan">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-camera-video fs-1 d-block mb-2 text-secondary"></i>
                                        <h6>Tidak ada tayangan video ditemukan</h6>
                                        <p class="small mb-0">Coba ubah kata kunci pencarian atau filter program/tahun yang dipilih.</p>
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
                    Menampilkan {{ $videos->firstItem() ?? 0 }} sampai {{ $videos->lastItem() ?? 0 }} dari {{ $videos->total() }} video
                </div>
                <div>
                    {{ $videos->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
