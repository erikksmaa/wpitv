<div>
    <div class="row">
        <!-- Main Form Column -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 text-primary">
                            <i class="bi {{ $isEdit ? 'bi-pencil-square' : 'bi-plus-circle' }} me-2"></i>
                            {{ $isEdit ? 'Formulir Edit Tayangan' : 'Formulir Tambah Tayangan Baru' }}
                        </h5>
                        <a href="{{ route('admin.videos.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <form wire:submit.prevent="save">
                        <!-- Program Acara Selection -->
                        <div class="mb-3">
                            <label for="program_id" class="form-label fw-semibold">Program Acara</label>
                            <select id="program_id" 
                                    wire:model="program_id" 
                                    class="form-select @error('program_id') is-invalid @enderror">
                                <option value="">-- Tanpa Program (Video Mandiri) --</option>
                                @foreach ($programs as $prog)
                                    <option value="{{ $prog->id }}">{{ $prog->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text text-muted">
                                Pilih program acara yang menaungi video tayangan ini, atau kosongkan jika video bersifat umum/mandiri.
                            </div>
                            @error('program_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Judul Video -->
                        <div class="mb-3">
                            <label for="title" class="form-label fw-semibold">
                                Judul Video <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   id="title" 
                                   wire:model="title" 
                                   class="form-control @error('title') is-invalid @enderror" 
                                   placeholder="Contoh: LIVE HARLAH 25 UKM Kesenian Patriot STMIK Widya Pratama">
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- YouTube URL / ID Input -->
                        <div class="mb-3">
                            <label for="youtube_input" class="form-label fw-semibold">
                                Tautan / ID Video YouTube <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-danger border-end-0">
                                    <i class="bi bi-youtube fs-5"></i>
                                </span>
                                <input type="text" 
                                       id="youtube_input" 
                                       wire:model.live.debounce.300ms="youtube_input" 
                                       class="form-control font-monospace @error('youtube_input') is-invalid @enderror" 
                                       placeholder="https://www.youtube.com/watch?v=XYZ123 atau ID: XYZ123">
                            </div>
                            <div class="form-text text-muted">
                                Masukkan tautan YouTube lengkap (watch URL, short URL <code>youtu.be</code>, embed URL), atau 11 digit ID YouTube langsung.
                            </div>
                            @error('youtube_input')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <!-- Tanggal Tayang (Air Date) -->
                            <div class="col-12 col-md-6 mb-3">
                                <label for="air_date" class="form-label fw-semibold">
                                    Tanggal Tayang <span class="text-danger">*</span>
                                </label>
                                <input type="date" 
                                       id="air_date" 
                                       wire:model="air_date" 
                                       class="form-control @error('air_date') is-invalid @enderror">
                                @error('air_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Tag -->
                            <div class="col-12 col-md-6 mb-3">
                                <label for="tag" class="form-label fw-semibold">Tag / Kategori</label>
                                <input type="text" 
                                       id="tag" 
                                       wire:model="tag" 
                                       class="form-control @error('tag') is-invalid @enderror" 
                                       placeholder="0">
                                @error('tag')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Deskripsi / Sinopsis -->
                        <div class="mb-4">
                            <label for="description" class="form-label fw-semibold">Deskripsi / Sinopsis Video</label>
                            <textarea id="description" 
                                      wire:model="description" 
                                      rows="4" 
                                      class="form-control @error('description') is-invalid @enderror" 
                                      placeholder="Tuliskan keterangan detail atau ringkasan isi video tayangan..."></textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex align-items-center gap-2 pt-3 border-top">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save">
                                    <i class="bi bi-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Tambah Tayangan' }}
                                </span>
                                <span wire:loading wire:target="save">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Menyimpan Data...
                                </span>
                            </button>
                            <a href="{{ route('admin.videos.index') }}" class="btn btn-light">
                                Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar / Media Preview & Metadata Column -->
        <div class="col-12 col-lg-4">
            <!-- Live YouTube Video Preview Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <h6 class="card-title mb-0 text-primary">
                        <i class="bi bi-play-circle me-2"></i>Pratinjau Pemutar YouTube
                    </h6>
                </div>
                <div class="card-body">
                    @if (strlen($youtube_id) === 11)
                        <div class="ratio ratio-16x9 rounded overflow-hidden shadow-sm mb-3">
                            <iframe src="https://www.youtube.com/embed/{{ $youtube_id }}" 
                                    title="Pratinjau YouTube" 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                    allowfullscreen></iframe>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-success bg-opacity-10 text-success font-monospace px-2 py-1">
                                <i class="bi bi-check-circle me-1"></i>ID: {{ $youtube_id }}
                            </span>
                            <a href="https://www.youtube.com/watch?v={{ $youtube_id }}" 
                               target="_blank" 
                               rel="noopener noreferrer" 
                               class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Buka YouTube
                            </a>
                        </div>
                    @else
                        <div class="text-center p-4 bg-light rounded border text-muted">
                            <i class="bi bi-youtube fs-1 text-secondary opacity-50 d-block mb-2"></i>
                            <p class="small mb-0">
                                Pratinjau pemutar YouTube akan otomatis tampil di sini saat Anda memasukkan URL atau 11 digit ID YouTube yang valid.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Readonly Metadata Card (Edit Mode Only) -->
            @if ($isEdit)
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-4 pb-2">
                        <h6 class="card-title mb-0 text-secondary">
                            <i class="bi bi-info-circle me-2"></i>Informasi & Audit Data
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">ID Rekam:</span>
                                <span class="fw-bold font-monospace">#{{ $videoId }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Pengunggah:</span>
                                <span class="fw-semibold">{{ $uploaderName }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Tanggal Dibuat:</span>
                                <span>{{ $createdAtFormatted }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Terakhir Diperbarui:</span>
                                <span>{{ $updatedAtFormatted }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
