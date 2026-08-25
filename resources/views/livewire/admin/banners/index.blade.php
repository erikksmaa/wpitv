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
                    <h5 class="card-title mb-1 text-primary"><i class="bi bi-image me-2"></i>Daftar Banner Promosi</h5>
                    <p class="text-muted small mb-0">Total {{ $banners->total() }} banner terdaftar pada sistem</p>
                </div>
                <div>
                    @can('create', App\Models\Banner::class)
                        <a href="{{ route('admin.banners.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Banner
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
                               placeholder="Cari judul banner atau deskripsi...">
                        @if($search)
                            <button class="btn btn-light border" type="button" wire:click="$set('search', '')">
                                <i class="bi bi-x"></i>
                            </button>
                        @endif
                    </div>
                </div>

                <div class="col-6 col-md-3 col-lg-2 ms-auto">
                    <select wire:model.live="perPage" class="form-select bg-light">
                        <option value="10">10 per hal</option>
                        <option value="25">25 per hal</option>
                        <option value="50">50 per hal</option>
                    </select>
                </div>
            </div>

            <!-- Banner Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 50px;">#</th>
                            <th scope="col" style="width: 120px;">Pratinjau</th>
                            <th scope="col" style="cursor: pointer;" wire:click="sortBy('title')">
                                Judul Banner
                                @if($sortField === 'title')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col">Deskripsi / Konten</th>
                            <th scope="col" style="cursor: pointer;" wire:click="sortBy('created_at')">
                                Tanggal Dibuat
                                @if($sortField === 'created_at')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" class="text-end" style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($banners as $index => $banner)
                            <tr wire:key="banner-{{ $banner->id }}">
                                <td class="text-muted fw-semibold small">
                                    {{ $banners->firstItem() + $index }}
                                </td>
                                <td>
                                    <img src="{{ $banner->image_url }}" 
                                         alt="{{ $banner->title }}" 
                                         class="rounded object-fit-cover shadow-sm border" 
                                         style="width: 100px; height: 45px;"
                                         onerror="this.onerror=null;this.src='{{ asset('images/placeholders/program-placeholder.svg') }}';"
                                         loading="lazy">
                                </td>
                                <td>
                                    <div class="fw-bold text-dark mb-1">
                                        {{ $banner->title }}
                                    </div>
                                    @if ($banner->image)
                                        <small class="text-muted font-monospace d-block text-truncate" style="max-width: 250px;" title="{{ $banner->image }}">
                                            File: {{ $banner->image }}
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-muted small">
                                        {{ $banner->content ? Str::limit($banner->content, 80) : '-' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="small fw-semibold text-dark">
                                        {{ $banner->created_at ? $banner->created_at->format('d M Y H:i') : '-' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        @can('update', $banner)
                                            <a href="{{ route('admin.banners.edit', $banner) }}" 
                                               class="btn btn-outline-primary" 
                                               title="Edit Banner">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        @endcan

                                        @can('delete', $banner)
                                            <button type="button" 
                                                    wire:click="delete({{ $banner->id }})" 
                                                    data-admin-confirm="Apakah Anda yakin ingin menghapus banner '{{ $banner->title }}'? Tindakan ini bersifat permanen."
                                                    class="btn btn-outline-danger" 
                                                    title="Hapus Banner">
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
                                        <i class="bi bi-card-image fs-1 d-block mb-2 text-secondary"></i>
                                        <h6>Tidak ada banner ditemukan</h6>
                                        <p class="small mb-0">Coba ubah kata kunci pencarian yang dipilih.</p>
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
                    Menampilkan {{ $banners->firstItem() ?? 0 }} sampai {{ $banners->lastItem() ?? 0 }} dari {{ $banners->total() }} banner
                </div>
                <div>
                    {{ $banners->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
