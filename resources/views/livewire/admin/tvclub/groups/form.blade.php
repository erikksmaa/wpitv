<div>
    <div class="row">
        <!-- Main Form Column -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 text-primary">
                            <i class="bi {{ $isEdit ? 'bi-pencil-square' : 'bi-plus-circle' }} me-2"></i>
                            {{ $isEdit ? 'Formulir Edit Kelompok Produksi' : 'Formulir Tambah Kelompok TV Club' }}
                        </h5>
                        <a href="{{ route('admin.tvclub.groups.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <form wire:submit.prevent="save">
                        <!-- Section 1: Group Information -->
                        <h6 class="text-secondary fw-semibold border-bottom pb-2 mb-3">
                            <i class="bi bi-camera-reels me-1"></i> Informasi Kelompok Produksi
                        </h6>

                        <!-- Generation & Program -->
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label for="generation_id" class="form-label fw-semibold">
                                    Generasi Angkatan <span class="text-danger">*</span>
                                </label>
                                <select id="generation_id" 
                                        wire:model.live="generation_id" 
                                        class="form-select @error('generation_id') is-invalid @enderror">
                                    <option value="">-- Pilih Generasi --</option>
                                    @foreach ($generations as $gen)
                                        <option value="{{ $gen->id }}">
                                            {{ $gen->name }} ({{ $gen->year }}) {{ $gen->status ? '' : '[Nonaktif]' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('generation_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="program_id" class="form-label fw-semibold">
                                    Program Siaran <span class="text-danger">*</span>
                                </label>
                                <select id="program_id" 
                                        wire:model="program_id" 
                                        class="form-select @error('program_id') is-invalid @enderror">
                                    <option value="">-- Pilih Program --</option>
                                    @foreach ($programs as $prog)
                                        <option value="{{ $prog->id }}">{{ $prog->name }}</option>
                                    @endforeach
                                </select>
                                @error('program_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Nama Kelompok & Status -->
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-8">
                                <label for="name" class="form-label fw-semibold">
                                    Nama Kelompok <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       id="name" 
                                       wire:model="name" 
                                       class="form-control @error('name') is-invalid @enderror" 
                                       placeholder="Contoh: Tim Produksi Ngopot Gen 10">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="status" class="form-label fw-semibold">
                                    Status Kelompok <span class="text-danger">*</span>
                                </label>
                                <select id="status" wire:model="status" class="form-select @error('status') is-invalid @enderror">
                                    <option value="1">Aktif</option>
                                    <option value="0">Nonaktif</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Deskripsi -->
                        <div class="mb-4">
                            <label for="description" class="form-label fw-semibold">Deskripsi / Catatan Penugasan</label>
                            <textarea id="description" 
                                      wire:model="description" 
                                      rows="3" 
                                      class="form-control @error('description') is-invalid @enderror" 
                                      placeholder="Tuliskan catatan atau lingkup tugas kelompok ini..."></textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Section 2: Crew Member Assignment -->
                        <h6 class="text-secondary fw-semibold border-bottom pb-2 mb-3 pt-3">
                            <i class="bi bi-people me-1"></i> Penugasan Kru Mahasiswa
                        </h6>

                        @if (session()->has('assignment_error'))
                            <div class="alert alert-warning alert-dismissible show fade py-2 small">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <div>{{ session('assignment_error') }}</div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <!-- Add Member Toolbar -->
                        <div class="card bg-light border-0 p-3 mb-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-5">
                                    <label class="form-label small fw-semibold text-muted mb-1">Pilih Anggota</label>
                                    <select wire:model="selectedMemberId" class="form-select form-select-sm">
                                        <option value="">-- Pilih Anggota Generasi Ini --</option>
                                        @foreach ($availableMembers as $availMember)
                                            <option value="{{ $availMember->id }}">
                                                {{ $availMember->name }} ({{ $availMember->student_number }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-12 col-md-4">
                                    <label class="form-label small fw-semibold text-muted mb-1">Jabatan Kru</label>
                                    <select wire:model="selectedRole" class="form-select form-select-sm mb-1">
                                        @foreach ($rolePresets as $preset)
                                            <option value="{{ $preset }}">{{ $preset }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" 
                                           wire:model="customRole" 
                                           class="form-control form-control-sm" 
                                           placeholder="Atau ketik peran khusus...">
                                </div>

                                <div class="col-12 col-md-3">
                                    <button type="button" 
                                            wire:click="addMember" 
                                            class="btn btn-sm btn-primary w-100">
                                        <i class="bi bi-person-plus me-1"></i> Tugaskan Anggota
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Assigned Members Table -->
                        <div class="table-responsive mb-4 border rounded">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" style="width: 50px;">Foto</th>
                                        <th scope="col">Nama & NIM</th>
                                        <th scope="col" style="width: 200px;">Jabatan</th>
                                        <th scope="col" class="text-end" style="width: 80px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($assignedMembers as $assigned)
                                        <tr wire:key="assigned-{{ $assigned['member_id'] }}">
                                            <td>
                                                @if (!empty($assigned['photo_url']))
                                                    <img src="{{ $assigned['photo_url'] }}" 
                                                         alt="{{ $assigned['name'] }}" 
                                                         class="rounded-circle object-fit-cover border" 
                                                         style="width: 36px; height: 36px;">
                                                @else
                                                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center border" 
                                                         style="width: 36px; height: 36px; font-size: 0.8rem;">
                                                        {{ $assigned['initials'] ?? 'TV' }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark">{{ $assigned['name'] }}</div>
                                                <small class="text-muted font-monospace">{{ $assigned['student_number'] }}</small>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                       value="{{ $assigned['role'] }}" 
                                                       wire:change="updateMemberRole({{ $assigned['member_id'] }}, $event.target.value)" 
                                                       class="form-control form-control-sm" 
                                                       placeholder="Jabatan kru">
                                            </td>
                                            <td class="text-end">
                                                <button type="button" 
                                                        wire:click="removeMember({{ $assigned['member_id'] }})"
                                                        data-admin-confirm="Hapus anggota ini dari penugasan kelompok?"
                                                        class="btn btn-sm btn-outline-danger" 
                                                        title="Lepas Penugasan">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                <i class="bi bi-people d-block fs-3 mb-1 text-secondary"></i>
                                                <span class="small">Belum ada anggota kru yang ditugaskan ke kelompok ini.</span>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex align-items-center gap-2 pt-3 border-top">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save">
                                    <i class="bi bi-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Tambah Kelompok' }}
                                </span>
                                <span wire:loading wire:target="save">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Menyimpan Data...
                                </span>
                            </button>
                            <a href="{{ route('admin.tvclub.groups.index') }}" class="btn btn-light">
                                Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar Info Column -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <h6 class="card-title mb-0 text-primary">
                        <i class="bi bi-info-circle me-2"></i>Panduan Kelompok Produksi
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Kelompok TV Club menghubungkan program siaran kampus dengan kru mahasiswa yang memproduksinya.
                    </p>
                    <ul class="list-unstyled small text-muted mb-0">
                        <li class="mb-2"><i class="bi bi-check2-circle text-success me-1"></i> <strong>Konsistensi Angkatan:</strong> Anggota kru yang ditugaskan harus berasal dari angkatan generasi yang sama dengan kelompok.</li>
                        <li class="mb-2"><i class="bi bi-check2-circle text-success me-1"></i> <strong>Jabatan Kru:</strong> Setiap anggota dapat memiliki jabatan spesifik (Produser, Kameramen, Editor, dll).</li>
                        <li class="mb-2"><i class="bi bi-shield-lock text-warning me-1"></i> <strong>Proteksi Hapus:</strong> Kelompok yang masih memiliki anggota terdaftar tidak dapat dihapus sebelum penugasan kru dilepas.</li>
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
                                <span class="text-muted">Total Kru Ditugaskan:</span>
                                <span class="badge bg-info bg-opacity-10 text-info fw-bold">{{ count($assignedMembers) }} Anggota</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Program Terhubung:</span>
                                <span class="fw-semibold">{{ $group->program ? $group->program->name : '-' }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Tanggal Dibuat:</span>
                                <span>{{ $group->created_at ? $group->created_at->format('d M Y H:i') : '-' }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Terakhir Diperbarui:</span>
                                <span>{{ $group->updated_at ? $group->updated_at->format('d M Y H:i') : '-' }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
