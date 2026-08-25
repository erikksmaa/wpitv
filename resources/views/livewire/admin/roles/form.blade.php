<div>
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="card-title mb-1 text-primary">
                                <i class="bi bi-shield-lock me-2"></i>
                                {{ $isEdit ? 'Edit Peran: ' . $name : 'Tambah Peran Baru' }}
                            </h5>
                            <p class="text-muted small mb-0">
                                {{ $isEdit ? 'Perbarui informasi dan deskripsi peran hak akses.' : 'Tambahkan peran baru untuk klasifikasi hak akses administrator.' }}
                            </p>
                        </div>
                        <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i> Kembali
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <form wire:submit.prevent="save">
                        <!-- Role Name -->
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold text-dark">
                                Nama Peran <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   id="name" 
                                   wire:model="name" 
                                   class="form-control @error('name') is-invalid @enderror" 
                                   placeholder="Contoh: Admin Finance, Editor, dll.">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Description -->
                        <div class="mb-4">
                            <label for="description" class="form-label fw-semibold text-dark">Deskripsi Peran</label>
                            <textarea id="description" 
                                      wire:model="description" 
                                      rows="3" 
                                      class="form-control @error('description') is-invalid @enderror" 
                                      placeholder="Tuliskan cakupan tugas atau kewenangan role ini..."></textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        @if ($isEdit)
                            <!-- Read-only Metadata Card -->
                            <div class="card bg-light border-0 p-3 mb-4">
                                <div class="row g-2 text-muted small">
                                    <div class="col-6">
                                        <span class="fw-semibold">Total Pengguna Terkait:</span> {{ $usersCount }} pengguna
                                    </div>
                                    <div class="col-6">
                                        <span class="fw-semibold">Dibuat Pada:</span> {{ $createdAtFormatted }}
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Action Buttons -->
                        <div class="d-flex align-items-center gap-2 pt-3 border-top">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save">
                                    <i class="bi bi-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Tambah Peran' }}
                                </span>
                                <span wire:loading wire:target="save">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Menyimpan Data...
                                </span>
                            </button>
                            <a href="{{ route('admin.roles.index') }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
