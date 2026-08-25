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
                    <h5 class="card-title mb-1 text-primary"><i class="bi bi-newspaper me-2"></i>Daftar Artikel Berita</h5>
                    <p class="text-muted small mb-0">Total {{ $newsList->total() }} artikel berita terdaftar pada sistem</p>
                </div>
                <div>
                    @can('create', App\Models\News::class)
                        <a href="{{ route('admin.news.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Berita
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
                               placeholder="Cari judul, slug, atau nama penulis...">
                        @if($search)
                            <button class="btn btn-light border" type="button" wire:click="$set('search', '')">
                                <i class="bi bi-x"></i>
                            </button>
                        @endif
                    </div>
                </div>

                <div class="col-6 col-md-4 col-lg-3 ms-auto">
                    <select wire:model.live="year" class="form-select bg-light">
                        <option value="all">Semua Tahun</option>
                        @foreach ($years as $yr)
                            <option value="{{ $yr }}">Tahun {{ $yr }}</option>
                        @endforeach
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

            <!-- News Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 50px;">#</th>
                            <th scope="col" style="width: 80px;">Sampul</th>
                            <th scope="col" style="cursor: pointer;" wire:click="sortBy('title')">
                                Judul Berita & Slug
                                @if($sortField === 'title')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col">Penulis</th>
                            <th scope="col" style="cursor: pointer;" wire:click="sortBy('created_at')">
                                Tanggal Terbit
                                @if($sortField === 'created_at')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </th>
                            <th scope="col" class="text-end" style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($newsList as $index => $news)
                            <tr wire:key="news-{{ $news->id }}">
                                <td class="text-muted fw-semibold small">
                                    {{ $newsList->firstItem() + $index }}
                                </td>
                                <td>
                                    <img src="{{ $news->image_url }}" 
                                         alt="{{ $news->title }}" 
                                         class="rounded object-fit-cover shadow-sm border" 
                                         style="width: 60px; height: 45px;"
                                         onerror="this.onerror=null;this.src='{{ asset('images/placeholders/program-placeholder.svg') }}';"
                                         loading="lazy">
                                </td>
                                <td>
                                    <div class="fw-bold text-dark mb-1 text-wrap" style="max-width: 450px;">
                                        {{ $news->title }}
                                    </div>
                                    <small class="text-muted font-monospace d-block text-truncate" style="max-width: 400px;">
                                        /news/{{ $news->slug }}
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        <i class="bi bi-person me-1"></i>{{ $news->author_display_name }}
                                    </span>
                                </td>
                                <td>
                                    <span class="small fw-semibold text-dark">
                                        {{ $news->created_at ? $news->created_at->format('d M Y H:i') : '-' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        @can('update', $news)
                                            <a href="{{ route('admin.news.edit', $news) }}" 
                                               class="btn btn-outline-primary" 
                                               title="Edit Berita">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        @endcan

                                        @can('delete', $news)
                                            <button type="button" 
                                                    wire:click="delete({{ $news->id }})" 
                                                    data-admin-confirm="Apakah Anda yakin ingin menghapus artikel berita '{{ $news->title }}'? Tindakan ini bersifat permanen."
                                                    class="btn btn-outline-danger" 
                                                    title="Hapus Berita">
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
                                        <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary"></i>
                                        <h6>Tidak ada artikel berita ditemukan</h6>
                                        <p class="small mb-0">Coba ubah kata kunci pencarian atau filter tahun yang dipilih.</p>
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
                    Menampilkan {{ $newsList->firstItem() ?? 0 }} sampai {{ $newsList->lastItem() ?? 0 }} dari {{ $newsList->total() }} berita
                </div>
                <div>
                    {{ $newsList->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
