<div>
    <div class="row">
        <!-- Main Form Column -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 text-primary">
                            <i class="bi {{ $isEdit ? 'bi-pencil-square' : 'bi-plus-circle' }} me-2"></i>
                            {{ $isEdit ? 'Formulir Edit Generasi' : 'Formulir Tambah Generasi Baru' }}
                        </h5>
                        <a href="{{ route('admin.tvclub.generations.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <form wire:submit.prevent="save">
                        <!-- Nama Generasi & Tahun -->
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-7">
                                <label for="name" class="form-label fw-semibold">
                                    Nama Generasi <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       id="name" 
                                       wire:model="name" 
                                       class="form-control @error('name') is-invalid @enderror" 
                                       placeholder="Contoh: Generasi 10 (2024)">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-5">
                                <label for="year" class="form-label fw-semibold">
                                    Tahun Angkatan <span class="text-danger">*</span>
                                </label>
                                <input type="number" 
                                       id="year" 
                                       wire:model="year" 
                                       min="1900" 
                                       max="2100" 
                                       class="form-control @error('year') is-invalid @enderror" 
                                       placeholder="Contoh: 2024">
                                @error('year')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted" style="font-size: 0.8rem;">Tahun harus unik (satu generasi per tahun).</small>
                            </div>
                        </div>

                        <!-- Status Aktif -->
                        <div class="mb-3">
                            <label for="status" class="form-label fw-semibold">
                                Status Generasi <span class="text-danger">*</span>
                            </label>
                            <select id="status" wire:model="status" class="form-select @error('status') is-invalid @enderror">
                                <option value="1">Aktif (Kru Aktif Berproduksi)</option>
                                <option value="0">Nonaktif (Alumni / Masa Bakti Selesai)</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Deskripsi -->
                        <div class="mb-4">
                            <label for="description" class="form-label fw-semibold">Deskripsi / Catatan Angkatan</label>
                            <textarea id="description" 
                                      wire:model="description" 
                                      rows="4" 
                                      class="form-control @error('description') is-invalid @enderror" 
                                      placeholder="Tuliskan keterangan detail mengenai angkatan/generasi ini..."></textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex align-items-center gap-2 pt-3 border-top">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save">
                                    <i class="bi bi-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Tambah Generasi' }}
                                </span>
                                <span wire:loading wire:target="save">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Menyimpan Data...
                                </span>
                            </button>
                            <a href="{{ route('admin.tvclub.generations.index') }}" class="btn btn-light">
                                Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar Info & Audit Column -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <h6 class="card-title mb-0 text-primary">
                        <i class="bi bi-info-circle me-2"></i>Panduan Generasi TV Club
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Tabel generasi menjadi entitas induk untuk mengelompokkan seluruh anggota (kru) mahasiswa TV Club dan penugasan kelompok produksi.
                    </p>
                    <ul class="list-unstyled small text-muted mb-0">
                        <li class="mb-2"><i class="bi bi-check2-circle text-success me-1"></i> <strong>Tahun Unik:</strong> Satu tahun angkatan hanya boleh memiliki satu entri generasi.</li>
                        <li class="mb-2"><i class="bi bi-shield-lock text-warning me-1"></i> <strong>Proteksi Relasi:</strong> Generasi yang masih memiliki anggota atau kelompok tidak dapat dihapus.</li>
                    </ul>
                </div>
            </div>

            <!-- Readonly Stats Card (Edit Mode Only) -->
            @if ($isEdit)
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-4 pb-2">
                        <h6 class="card-title mb-0 text-secondary">
                            <i class="bi bi-bar-chart-line me-2"></i>Statistik & Audit Data
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Total Anggota:</span>
                                <span class="badge bg-info bg-opacity-10 text-info fw-bold">{{ $membersCount }} Anggota</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Total Kelompok:</span>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold">{{ $groupsCount }} Grup</span>
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
