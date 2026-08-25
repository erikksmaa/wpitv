<div>
    <div class="row">
        <!-- Main Form Column -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 text-primary">
                            <i class="bi {{ $isEdit ? 'bi-pencil-square' : 'bi-person-plus-fill' }} me-2"></i>
                            {{ $isEdit ? 'Formulir Edit Anggota' : 'Formulir Tambah Anggota TV Club' }}
                        </h5>
                        <a href="{{ route('admin.tvclub.members.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <form wire:submit.prevent="save">
                        <!-- Informasi Anggota -->
                        <h6 class="text-secondary fw-semibold border-bottom pb-2 mb-3">
                            <i class="bi bi-person-lines-fill me-1"></i> Informasi Mahasiswa
                        </h6>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-5">
                                <label for="student_number" class="form-label fw-semibold">
                                    NIM (Nomor Induk Mahasiswa) <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       id="student_number" 
                                       wire:model="student_number" 
                                       class="form-control font-monospace @error('student_number') is-invalid @enderror" 
                                       placeholder="Contoh: 21.240.0001">
                                @error('student_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-7">
                                <label for="name" class="form-label fw-semibold">
                                    Nama Lengkap <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       id="name" 
                                       wire:model="name" 
                                       class="form-control @error('name') is-invalid @enderror" 
                                       placeholder="Contoh: Ahmad Fadilah">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Generasi & Status -->
                        <h6 class="text-secondary fw-semibold border-bottom pb-2 mb-3 pt-2">
                            <i class="bi bi-mortarboard me-1"></i> Angkatan & Status
                        </h6>

                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-7">
                                <label for="generation_id" class="form-label fw-semibold">
                                    Generasi Angkatan <span class="text-danger">*</span>
                                </label>
                                <select id="generation_id" wire:model="generation_id" class="form-select @error('generation_id') is-invalid @enderror">
                                    <option value="">-- Pilih Generasi Angkatan --</option>
                                    @foreach ($generations as $gen)
                                        <option value="{{ $gen->id }}">
                                            {{ $gen->name }} (Tahun {{ $gen->year }}) {{ $gen->status ? '' : '[Nonaktif]' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('generation_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-5">
                                <label for="status" class="form-label fw-semibold">
                                    Status Keanggotaan <span class="text-danger">*</span>
                                </label>
                                <select id="status" wire:model="status" class="form-select @error('status') is-invalid @enderror">
                                    <option value="1">Aktif (Kru Aktif)</option>
                                    <option value="0">Alumni / Nonaktif</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Foto Profil -->
                        <h6 class="text-secondary fw-semibold border-bottom pb-2 mb-3 pt-2">
                            <i class="bi bi-camera me-1"></i> Foto Profil Anggota
                        </h6>

                        <div class="mb-4">
                            <div class="row align-items-center g-3">
                                <!-- Preview Column -->
                                <div class="col-auto">
                                    @if ($photo)
                                        <div class="text-center">
                                            <img src="{{ $photo->temporaryUrl() }}" 
                                                 alt="Pratinjau" 
                                                 class="rounded-circle object-fit-cover shadow-sm border border-primary border-2" 
                                                 style="width: 80px; height: 80px;">
                                            <div class="badge bg-primary d-block mt-1">Pratinjau Baru</div>
                                        </div>
                                    @elseif ($isEdit && $member->photo_url)
                                        <div class="text-center">
                                            <img src="{{ $member->photo_url }}" 
                                                 alt="{{ $member->name }}" 
                                                 class="rounded-circle object-fit-cover shadow-sm border" 
                                                 style="width: 80px; height: 80px;">
                                            <div class="badge bg-light text-dark border d-block mt-1">Foto Saat Ini</div>
                                        </div>
                                    @else
                                        <div class="rounded-circle bg-light text-muted d-flex align-items-center justify-content-center border" 
                                             style="width: 80px; height: 80px;">
                                            <i class="bi bi-person fs-1"></i>
                                        </div>
                                    @endif
                                </div>

                                <!-- File Input Column -->
                                <div class="col">
                                    <label for="photo" class="form-label fw-semibold mb-1">
                                        {{ $isEdit ? 'Unggah Foto Baru (Opsional)' : 'Unggah Foto Profil (Opsional)' }}
                                    </label>
                                    <input type="file" 
                                           id="photo" 
                                           wire:model="photo" 
                                           accept="image/png,image/jpeg,image/jpg,image/webp" 
                                           class="form-control @error('photo') is-invalid @enderror">
                                    @error('photo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text small text-muted">
                                        Format file yang didukung: JPG, PNG, WEBP. Ukuran maksimal: 2 MB.
                                    </div>
                                    <div wire:loading wire:target="photo" class="text-primary small mt-1">
                                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                        Mengunggah dan memproses preview foto...
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex align-items-center gap-2 pt-3 border-top">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save">
                                    <i class="bi bi-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Tambah Anggota' }}
                                </span>
                                <span wire:loading wire:target="save">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Menyimpan Data...
                                </span>
                            </button>
                            <a href="{{ route('admin.tvclub.members.index') }}" class="btn btn-light">
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
                        <i class="bi bi-info-circle me-2"></i>Panduan Anggota TV Club
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Data anggota memuat identitas kru mahasiswa TV Club yang terhubung ke angkatan generasi masing-masing.
                    </p>
                    <ul class="list-unstyled small text-muted mb-0">
                        <li class="mb-2"><i class="bi bi-check2-circle text-success me-1"></i> <strong>NIM:</strong> Pastikan NIM diisi sesuai data akademik mahasiswa.</li>
                        <li class="mb-2"><i class="bi bi-check2-circle text-success me-1"></i> <strong>Generasi:</strong> Pilih generasi angkatan aktif saat mahasiswa bergabung.</li>
                        <li class="mb-2"><i class="bi bi-shield-lock text-warning me-1"></i> <strong>Proteksi Hapus:</strong> Anggota yang telah ditugaskan dalam kelompok produksi siaran tidak dapat dihapus sebelum penugasannya dilepas.</li>
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
                                <span class="text-muted">Kelompok Produksi:</span>
                                <span class="badge bg-light text-dark border fw-bold">{{ $groupsCount }} Kelompok</span>
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
