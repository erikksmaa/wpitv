<div>
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="card-title mb-1 text-primary">
                                <i class="bi bi-person-gear me-2"></i>
                                {{ $isEdit ? 'Edit Pengguna: ' . $name : 'Tambah Administrator Baru' }}
                            </h5>
                            <p class="text-muted small mb-0">
                                {{ $isEdit ? 'Perbarui informasi akun, peran hak akses, dan status pengguna.' : 'Tambahkan akun administrator baru ke dalam sistem WPiTV.' }}
                            </p>
                        </div>
                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i> Kembali
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <form wire:submit.prevent="save">
                        <div class="row g-3">
                            <!-- Full Name -->
                            <div class="col-12 col-md-6">
                                <label for="name" class="form-label fw-semibold text-dark">
                                    Nama Lengkap <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       id="name" 
                                       wire:model="name" 
                                       class="form-control @error('name') is-invalid @enderror" 
                                       placeholder="Nama lengkap administrator...">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Username -->
                            <div class="col-12 col-md-6">
                                <label for="username" class="form-label fw-semibold text-dark">
                                    Nama Pengguna <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">@</span>
                                    <input type="text" 
                                           id="username" 
                                           wire:model="username" 
                                           class="form-control @error('username') is-invalid @enderror" 
                                           placeholder="username_login">
                                </div>
                                @error('username')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Role -->
                            <div class="col-12 col-md-6">
                                <label for="role_id" class="form-label fw-semibold text-dark">
                                    Peran Hak Akses <span class="text-danger">*</span>
                                </label>
                                <select id="role_id" 
                                        wire:model="role_id" 
                                        class="form-select @error('role_id') is-invalid @enderror">
                                    <option value="">-- Pilih Peran --</option>
                                    @foreach ($roles as $roleOption)
                                        <option value="{{ $roleOption->id }}">{{ $roleOption->name }}</option>
                                    @endforeach
                                </select>
                                @error('role_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Status -->
                            <div class="col-12 col-md-6">
                                <label for="status" class="form-label fw-semibold text-dark">
                                    Status Akun <span class="text-danger">*</span>
                                </label>
                                <select id="status" 
                                        wire:model="status" 
                                        class="form-select @error('status') is-invalid @enderror">
                                    <option value="1">Aktif (Dapat Masuk)</option>
                                    <option value="0">Nonaktif (Tidak Dapat Masuk)</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Password Section Header -->
                            <div class="col-12 pt-2">
                                <h6 class="text-secondary fw-semibold border-bottom pb-2 mb-0">
                                    <i class="bi bi-key me-1"></i> Keamanan & Kata Sandi
                                </h6>
                                @if ($isEdit)
                                    <small class="text-muted">Kosongkan bidang kata sandi jika tidak ingin mengubah kata sandi saat ini.</small>
                                @endif
                            </div>

                            <!-- Password -->
                            <div class="col-12 col-md-6">
                                <label for="password" class="form-label fw-semibold text-dark">
                                    Kata Sandi {{ $isEdit ? '(Opsional)' : '*' }}
                                </label>
                                <input type="password" 
                                       id="password" 
                                       wire:model="password" 
                                       class="form-control @error('password') is-invalid @enderror" 
                                       placeholder="{{ $isEdit ? 'Biarkan kosong untuk mempertahankan kata sandi lama' : 'Minimal 8 karakter' }}">
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Password Confirmation -->
                            <div class="col-12 col-md-6">
                                <label for="password_confirmation" class="form-label fw-semibold text-dark">
                                    Konfirmasi Kata Sandi {{ $isEdit ? '(Opsional)' : '*' }}
                                </label>
                                <input type="password" 
                                       id="password_confirmation" 
                                       wire:model="password_confirmation" 
                                       class="form-control @error('password_confirmation') is-invalid @enderror" 
                                       placeholder="Ulangi kata sandi baru...">
                                @error('password_confirmation')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        @if ($isEdit && $createdAtFormatted)
                            <div class="card bg-light border-0 p-3 my-4">
                                <div class="text-muted small">
                                    <span class="fw-semibold">Akun Dibuat Pada:</span> {{ $createdAtFormatted }}
                                </div>
                            </div>
                        @endif

                        <!-- Action Buttons -->
                        <div class="d-flex align-items-center gap-2 pt-4 border-top mt-4">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save">
                                    <i class="bi bi-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Tambah Pengguna' }}
                                </span>
                                <span wire:loading wire:target="save">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Menyimpan Data...
                                </span>
                            </button>
                            <a href="{{ route('admin.users.index') }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
