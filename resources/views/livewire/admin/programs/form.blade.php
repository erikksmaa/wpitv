<div>
    <div class="row">
        <!-- Main Form Column -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 text-primary">
                            <i class="bi {{ $isEdit ? 'bi-pencil-square' : 'bi-plus-circle' }} me-2"></i>
                            {{ $isEdit ? 'Formulir Edit Program' : 'Formulir Program Baru' }}
                        </h5>
                        <a href="{{ route('admin.programs.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <form wire:submit.prevent="save">
                        <!-- Nama Program -->
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">
                                Nama Program <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   id="name" 
                                   wire:model.live.debounce.300ms="name" 
                                   class="form-control @error('name') is-invalid @enderror" 
                                   placeholder="Contoh: PODCAST PASTI, AKTUAL, LIVE STREAMING">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Slug URL -->
                        <div class="mb-3">
                            <label for="slug" class="form-label fw-semibold">
                                Slug URL <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted font-monospace small">/programs/</span>
                                <input type="text" 
                                       id="slug" 
                                       wire:model.live.debounce.300ms="slug" 
                                       class="form-control font-monospace @error('slug') is-invalid @enderror" 
                                       placeholder="podcast-pasti">
                            </div>
                            <div class="form-text text-muted">
                                Slug digunakan sebagai URL publik program dan harus unik di seluruh sistem.
                            </div>
                            @error('slug')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Deskripsi Program -->
                        <div class="mb-3">
                            <label for="description" class="form-label fw-semibold">Deskripsi / Sinopsis Acara</label>
                            <textarea id="description" 
                                      wire:model="description" 
                                      rows="4" 
                                      class="form-control @error('description') is-invalid @enderror" 
                                      placeholder="Tuliskan gambaran ringkas dan tujuan program tayangan ini..."></textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Status Program -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold d-block">Status Publikasi</label>
                            <div class="form-check form-switch form-switch-lg">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       role="switch" 
                                       id="statusSwitch" 
                                       wire:model="status">
                                <label class="form-check-label ms-2" for="statusSwitch">
                                    @if ($status)
                                        <span class="badge bg-success">Aktif (Ditayangkan di Publik)</span>
                                    @else
                                        <span class="badge bg-danger">Nonaktif (Diarsipkan)</span>
                                    @endif
                                </label>
                            </div>
                            <div class="form-text text-muted">
                                Program dengan status aktif akan ditampilkan pada portal publik dan dapat dipilih saat mengunggah video baru.
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex align-items-center gap-2 pt-3 border-top">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save">
                                    <i class="bi bi-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Tambah Program' }}
                                </span>
                                <span wire:loading wire:target="save">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Menyimpan Data...
                                </span>
                            </button>
                            <a href="{{ route('admin.programs.index') }}" class="btn btn-light">
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
                        <i class="bi bi-image me-2"></i>Gambar Sampul Program
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
                        @elseif ($program?->image_url)
                            <img src="{{ $program->image_url }}"
                                 alt="{{ $name }}" 
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
                        <label for="imageUpload" class="form-label small fw-semibold">Pilih Berkas Gambar</label>
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
                            <i class="bi bi-info-circle me-2"></i>Informasi & Statistik
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">ID Program:</span>
                                <span class="fw-bold font-monospace">#{{ $programId }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Tayangan Video Terkait:</span>
                                <span class="badge bg-primary rounded-pill">{{ $videosCount }} Video</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Grup TV Club Terkait:</span>
                                <span class="badge bg-secondary rounded-pill">{{ $tvClubGroupsCount }} Grup</span>
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
