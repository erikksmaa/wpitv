<div>
    <div class="row">
        <!-- Main Form Column -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 text-primary">
                            <i class="bi {{ $isEdit ? 'bi-pencil-square' : 'bi-plus-circle' }} me-2"></i>
                            {{ $isEdit ? 'Formulir Edit Berita' : 'Formulir Tambah Berita Baru' }}
                        </h5>
                        <a href="{{ route('admin.news.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <form wire:submit.prevent="save">
                        <!-- Judul Berita -->
                        <div class="mb-3">
                            <label for="title" class="form-label fw-semibold">
                                Judul Berita <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   id="title" 
                                   wire:model.live.debounce.300ms="title" 
                                   class="form-control @error('title') is-invalid @enderror" 
                                   placeholder="Contoh: Mahasiswa WPiTV Raih Prestasi Tingkat Nasional">
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Slug URL -->
                        <div class="mb-3">
                            <label for="slug" class="form-label fw-semibold">
                                Slug URL <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted font-monospace small">/news/</span>
                                <input type="text" 
                                       id="slug" 
                                       wire:model.live.debounce.300ms="slug" 
                                       class="form-control font-monospace @error('slug') is-invalid @enderror" 
                                       placeholder="mahasiswa-wpitv-raih-prestasi-tingkat-nasional">
                            </div>
                            <div class="form-text text-muted">
                                Slug digunakan sebagai URL publik artikel berita dan wajib unik di seluruh sistem.
                            </div>
                            @error('slug')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Nama Penulis (Author Name) -->
                        <div class="mb-3">
                            <label for="author_name" class="form-label fw-semibold">Nama Penulis / Redaksi</label>
                            <input type="text" 
                                   id="author_name" 
                                   wire:model="author_name" 
                                   class="form-control @error('author_name') is-invalid @enderror" 
                                   placeholder="Nama jurnalis atau redaksi...">
                            <div class="form-text text-muted">
                                Secara default terisi dengan nama akun Anda. Anda dapat mengubahnya jika berita ditulis oleh kontributor atau redaksi khusus.
                            </div>
                            @error('author_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Isi Konten Berita -->
                        <div class="mb-4">
                            <label for="news-content-editor" class="form-label fw-semibold">
                                Isi Konten Berita <span class="text-danger">*</span>
                            </label>
                            <div wire:ignore>
                                <textarea id="news-content-editor"
                                          wire:model="content"
                                          rows="12"
                                          class="form-control @error('content') is-invalid @enderror"
                                          placeholder="Tuliskan isi artikel berita di sini..."></textarea>
                            </div>
                            <div class="form-text text-muted">
                                Gunakan toolbar untuk mengatur judul, paragraf, daftar, tautan, gambar, dan tabel.
                            </div>
                            @error('content')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex align-items-center gap-2 pt-3 border-top">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save">
                                    <i class="bi bi-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Terbitkan Berita' }}
                                </span>
                                <span wire:loading wire:target="save">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Menyimpan Data...
                                </span>
                            </button>
                            <a href="{{ route('admin.news.index') }}" class="btn btn-light">
                                Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar / Media & Metadata Column -->
        <div class="col-12 col-lg-4">
            <!-- Image Upload Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <h6 class="card-title mb-0 text-primary">
                        <i class="bi bi-image me-2"></i>Gambar Utama Berita
                    </h6>
                </div>
                <div class="card-body">
                    <!-- Image Preview Container -->
                    <div class="mb-3 text-center p-3 bg-light rounded border position-relative">
                        @if ($image)
                            <img src="{{ $image->temporaryUrl() }}" 
                                 alt="Pratinjau Baru" 
                                 class="img-fluid rounded shadow-sm object-fit-cover" 
                                 style="max-height: 180px; width: 100%;">
                            <div class="badge bg-info text-dark mt-2">Gambar Baru Dipilih (Belum Disimpan)</div>
                        @elseif ($news?->image_url)
                            <img src="{{ $news->image_url }}"
                                 alt="{{ $title }}" 
                                 class="img-fluid rounded shadow-sm object-fit-cover" 
                                 style="max-height: 180px; width: 100%;">
                        @else
                            <img src="{{ asset('images/placeholders/program-placeholder.svg') }}" 
                                 alt="Placeholder" 
                                 class="img-fluid rounded" 
                                 style="max-height: 180px;">
                        @endif

                        <!-- Livewire Upload Loading Spinner -->
                        <div wire:loading wire:target="image" 
                             class="position-absolute top-50 start-50 translate-middle bg-dark bg-opacity-75 text-white p-2 rounded">
                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                            Mengunggah gambar...
                        </div>
                    </div>

                    <!-- File Input -->
                    <div class="mb-2">
                        <label for="imageUpload" class="form-label small fw-semibold">Pilih Berkas Sampul</label>
                        <input type="file" 
                               id="imageUpload" 
                               wire:model="image" 
                               accept="image/jpeg,image/png,image/jpg,image/webp" 
                               class="form-control form-control-sm @error('image') is-invalid @enderror">
                        @error('image')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="text-muted small" style="font-size: 0.8rem;">
                        Format yang didukung: JPG, JPEG, PNG, WEBP. Maksimal 5 MB.
                    </div>
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
                                <span class="fw-bold font-monospace">#{{ $newsId }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Pengunggah / Author:</span>
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

@push('scripts')
    <script src="{{ asset('mazer/extensions/tinymce/tinymce.min.js') }}"></script>
@endpush

@script
<script>
    const editorElement = document.getElementById('news-content-editor');

    if (editorElement && window.tinymce && !tinymce.get(editorElement.id)) {
        const darkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark';

        tinymce.init({
            target: editorElement,
            height: 430,
            menubar: false,
            branding: false,
            promotion: false,
            license_key: 'gpl',
            skin: darkMode ? 'oxide-dark' : 'oxide',
            content_css: darkMode ? 'dark' : 'default',
            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount',
            toolbar: 'undo redo | blocks | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link image media table | blockquote removeformat | preview fullscreen code',
            toolbar_mode: 'sliding',
            setup(editor) {
                const syncContent = () => {
                    editor.save();
                    editorElement.dispatchEvent(new Event('input', { bubbles: true }));
                };

                editor.on('change input undo redo', syncContent);
            }
        });
    }
</script>
@endscript
