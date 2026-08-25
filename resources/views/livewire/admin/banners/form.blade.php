<div>
    <div class="row">
        <!-- Main Form Column -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 text-primary">
                            <i class="bi {{ $isEdit ? 'bi-pencil-square' : 'bi-plus-circle' }} me-2"></i>
                            {{ $isEdit ? 'Formulir Edit Banner' : 'Formulir Tambah Banner Baru' }}
                        </h5>
                        <a href="{{ route('admin.banners.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <form wire:submit.prevent="save">
                        <!-- Judul Banner -->
                        <div class="mb-3">
                            <label for="title" class="form-label fw-semibold">
                                Judul Banner <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   id="title" 
                                   wire:model="title" 
                                   class="form-control @error('title') is-invalid @enderror" 
                                   placeholder="Contoh: PMB Institut Widya Pratama 2026 / 2027">
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Konten / Deskripsi Banner -->
                        <div class="mb-4">
                            <label for="content" class="form-label fw-semibold">Deskripsi / Informasi Tambahan</label>
                            <textarea id="content" 
                                      wire:model="content" 
                                      rows="4" 
                                      class="form-control @error('content') is-invalid @enderror" 
                                      placeholder="Tuliskan keterangan detail atau teks promosi banner..."></textarea>
                            @error('content')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex align-items-center gap-2 pt-3 border-top">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save">
                                    <i class="bi bi-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Tambah Banner' }}
                                </span>
                                <span wire:loading wire:target="save">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Menyimpan Data...
                                </span>
                            </button>
                            <a href="{{ route('admin.banners.index') }}" class="btn btn-light">
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
                        <i class="bi bi-image me-2"></i>File Gambar Banner
                    </h6>
                </div>
                <div class="card-body">
                    <!-- Image Preview Container -->
                    <div class="mb-3 text-center p-3 bg-light rounded border position-relative">
                        @if ($image)
                            <img src="{{ $image->temporaryUrl() }}" 
                                 alt="Preview Baru" 
                                 class="img-fluid rounded shadow-sm object-fit-cover" 
                                 style="max-height: 180px; width: 100%;">
                            <div class="badge bg-info text-dark mt-2">Gambar Baru Dipilih (Belum Disimpan)</div>
                        @elseif ($existingImageUrl)
                            <img src="{{ $existingImageUrl }}" 
                                 alt="{{ $title }}" 
                                 class="img-fluid rounded shadow-sm object-fit-cover" 
                                 style="max-height: 180px; width: 100%;">
                            @if ($existingImage)
                                <div class="text-muted small font-monospace mt-2 text-truncate" title="{{ $existingImage }}">
                                    File: {{ $existingImage }}
                                </div>
                            @endif
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
                        <label for="imageUpload" class="form-label small fw-semibold">Pilih File Banner</label>
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
                                <span class="fw-bold font-monospace">#{{ $bannerId }}</span>
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
