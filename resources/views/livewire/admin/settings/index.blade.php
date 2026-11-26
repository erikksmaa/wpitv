<div>
    <div class="page-heading d-flex flex-wrap justify-content-between align-items-end gap-2">
        <div>
            <nav aria-label="Breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pengaturan Website</li>
                </ol>
            </nav>
            <h1 class="h3 mb-1">Pengaturan Website</h1>
            <p class="text-muted mb-0">Kelola informasi dan kontak resmi yang digunakan pada website WPiTV.</p>
        </div>
    </div>

    @unless($schemaReady)
        <div class="alert alert-warning" role="alert">
            <h2 class="h6 mb-1">Tabel Pengaturan Website belum tersedia</h2>
            <p class="mb-0">
                Impor skrip <code>database/sql/website_settings_and_social_integration.sql</code> melalui phpMyAdmin agar perubahan dapat disimpan ke database. Saat ini sistem memakai nomor fallback dari konfigurasi lingkungan.
            </p>
        </div>
    @endunless

    @if(session('settings_notice'))
        <div class="alert alert-success alert-dismissible show fade" role="status">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <span>{{ session('settings_notice') }}</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif

    @if(session('settings_error'))
        <div class="alert alert-danger alert-dismissible show fade" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                <span>{{ session('settings_error') }}</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <section class="card border-0 shadow-sm mb-4" aria-labelledby="official-contact-heading">
                <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-building text-primary fs-5" aria-hidden="true"></i>
                        <h2 id="official-contact-heading" class="h5 mb-0">Informasi Kontak</h2>
                    </div>
                    <span class="badge bg-light-primary text-primary">Tampil di Website Public</span>
                </div>
                <div class="card-body pt-4">
                    <form wire:submit="saveContact" data-admin-confirm-title="Simpan perubahan informasi kontak?" data-admin-confirm-text="Ya, simpan">
                        <div class="mb-3">
                            <label for="contact-email" class="form-label fw-semibold">
                                Email WPiTV
                            </label>
                            <input
                                id="contact-email"
                                type="email"
                                autocomplete="email"
                                maxlength="150"
                                wire:model.defer="contactEmail"
                                class="form-control @error('contactEmail') is-invalid @enderror"
                                placeholder="Contoh: wpitv@stmik-wp.ac.id"
                                @disabled(!$schemaReady)
                            >
                            @error('contactEmail')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="contact-phone" class="form-label fw-semibold">
                                Nomor Telepon
                            </label>
                            <input
                                id="contact-phone"
                                type="text"
                                inputmode="tel"
                                autocomplete="tel"
                                maxlength="30"
                                wire:model.defer="contactPhone"
                                class="form-control @error('contactPhone') is-invalid @enderror"
                                placeholder="Contoh: (0285) 427817 atau +62 285 427817"
                                @disabled(!$schemaReady)
                            >
                            @error('contactPhone')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Mendukung nomor telepon kantor maupun seluler. Kosongkan jika tidak ingin ditampilkan di halaman publik.
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="contact-address" class="form-label fw-semibold">
                                Alamat
                            </label>
                            <textarea
                                id="contact-address"
                                rows="3"
                                maxlength="300"
                                wire:model.defer="contactAddress"
                                class="form-control @error('contactAddress') is-invalid @enderror"
                                placeholder="Contoh: Jl. Patriot 25 Lt. 2, Pekalongan&#10;Jawa Tengah, Indonesia"
                                @disabled(!$schemaReady)
                            ></textarea>
                            @error('contactAddress')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Maksimal 255 karakter. Gunakan baris baru bila perlu untuk memisahkan jalan dan kota.
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveContact" @disabled(!$schemaReady)>
                                <span wire:loading.remove wire:target="saveContact">
                                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Simpan Perubahan
                                </span>
                                <span wire:loading wire:target="saveContact">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Menyimpan...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="card border-0 shadow-sm mb-0" aria-labelledby="coverage-whatsapp-heading">
                <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-telephone text-primary fs-5" aria-hidden="true"></i>
                        <h2 id="coverage-whatsapp-heading" class="h5 mb-0">Layanan Peliputan</h2>
                    </div>
                    @if($activeNumber)
                        <span class="badge {{ $activeSource === 'database' ? 'bg-success' : 'bg-info text-dark' }}">
                            {{ $activeSource === 'database' ? 'Aktif (Database)' : 'Fallback (.env)' }} · {{ $activeNumber }}
                        </span>
                    @else
                        <span class="badge bg-secondary">Belum diatur</span>
                    @endif
                </div>
                <div class="card-body pt-4">
                    <form wire:submit="save" data-admin-confirm-title="Simpan perubahan pengaturan website?" data-admin-confirm-text="Ya, simpan">
                        <div class="mb-3">
                            <label for="coverage-whatsapp-number" class="form-label fw-semibold">
                                Nomor WhatsApp Tujuan <span class="text-danger">*</span>
                            </label>
                            <input
                                id="coverage-whatsapp-number"
                                type="text"
                                inputmode="tel"
                                autocomplete="tel"
                                maxlength="30"
                                wire:model.live.debounce.300ms="whatsappNumber"
                                class="form-control @error('whatsappNumber') is-invalid @enderror"
                                placeholder="Contoh: 081234567890 atau +6281234567890"
                                @disabled(!$schemaReady)
                            >
                            @error('whatsappNumber')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Nomor ini digunakan untuk menghubungi tim WPiTV terkait pengajuan peliputan. Mendukung awalan <code>08</code>, <code>62</code>, atau <code>+62</code>.
                            </div>
                        </div>

                        <div class="rounded-3 border p-3 mb-4" style="background: rgba(21, 93, 172, 0.04);">
                            <div class="small text-muted mb-1">Pratinjau tautan WhatsApp</div>
                            @if($previewUrl)
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <a href="{{ $previewUrl }}" target="_blank" rel="noopener noreferrer" class="fw-semibold text-decoration-none font-monospace">
                                        {{ $previewDisplay }} <i class="bi bi-box-arrow-up-right ms-1 small" aria-hidden="true"></i>
                                    </a>
                                    <span class="badge bg-light-success text-success">Format valid</span>
                                </div>
                            @else
                                <span class="small text-muted">Masukkan nomor WhatsApp yang valid untuk melihat pratinjau tautan <code>wa.me</code>.</span>
                            @endif
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save" @disabled(!$schemaReady)>
                                <span wire:loading.remove wire:target="save">
                                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Simpan Perubahan
                                </span>
                                <span wire:loading wire:target="save">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Menyimpan...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-5">
            <section class="card border-0 shadow-sm mb-0" aria-labelledby="settings-info-heading">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h2 id="settings-info-heading" class="h6 mb-0">Informasi &amp; Riwayat Perubahan</h2>
                </div>
                <div class="card-body pt-3">
                    <p class="small text-muted mb-3">
                        Perubahan informasi kontak dan nomor WhatsApp langsung berlaku pada website publik tanpa perlu restart server.
                    </p>
                    <h3 class="h6 text-secondary mb-2">Riwayat Audit Terakhir</h3>
                    @if($events->isNotEmpty())
                        <ul class="list-group list-group-flush small">
                            @foreach($events as $event)
                                @php
                                    $settingName = $settingsService->settingLabel($event->setting_key ?? '');
                                    $beforeDisplay = ($event->before_value !== null && $event->before_value !== '') ? $event->before_value : 'Kosong';
                                    $afterDisplay = ($event->after_value !== null && $event->after_value !== '') ? $event->after_value : 'Kosong';
                                @endphp
                                <li class="list-group-item px-0 bg-transparent">
                                    <div class="d-flex justify-content-between gap-2">
                                        <strong>{{ $event->actor_name_snapshot }}</strong>
                                        <span class="text-muted">
                                            {{ \Carbon\Carbon::parse($event->created_at, 'UTC')->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB
                                        </span>
                                    </div>
                                    <div class="text-body-secondary fw-semibold">{{ $settingName }}</div>
                                    <div class="text-muted text-break">
                                        {{ $event->action === 'created' ? 'Menetapkan:' : 'Mengubah:' }}
                                        @if($event->before_value !== null)
                                            <span class="font-monospace">{{ $beforeDisplay }}</span> →
                                        @endif
                                        <span class="font-monospace text-body">{{ $afterDisplay }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="small text-muted mb-0">Belum ada riwayat perubahan pengaturan di database.</p>
                    @endif
                </div>
            </section>
        </div>
    </div>
</div>
