<div>
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="card-title mb-1 text-primary">
                                <i class="bi bi-camera-reels-fill me-2"></i>
                                {{ $isEdit ? 'Edit Peliputan: ' . $submission_code : 'Tambah Permohonan Peliputan Baru' }}
                            </h5>
                            <p class="text-muted small mb-0">
                                {{ $isEdit ? 'Perbarui rincian permohonan liputan, jadwal acara, dan status produksi.' : 'Inputkan permohonan peliputan acara baru ke dalam sistem jadwal produksi WPiTV.' }}
                            </p>
                        </div>
                        <a href="{{ route('admin.peliputan.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i> Kembali
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <form wire:submit.prevent="save">
                        <div class="row g-3">
                            <!-- Header Info -->
                            <div class="col-12 col-md-6">
                                <label for="submission_code" class="form-label fw-semibold text-dark">
                                    Kode Pengajuan <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       id="submission_code" 
                                       wire:model="submission_code" 
                                       class="form-control font-monospace bg-light @error('submission_code') is-invalid @enderror" 
                                       readonly>
                                @error('submission_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="status" class="form-label fw-semibold text-dark">
                                    Status Permohonan <span class="text-danger">*</span>
                                </label>
                                <select id="status" 
                                        wire:model="status" 
                                        class="form-select @error('status') is-invalid @enderror">
                                    @foreach ($statusOptions as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Section: Data Acara -->
                            <div class="col-12 pt-2">
                                <h6 class="text-secondary fw-semibold border-bottom pb-2 mb-0">
                                    <i class="bi bi-calendar2-event me-1"></i> Informasi Agenda Acara
                                </h6>
                            </div>

                            <div class="col-12">
                                <label for="event_name" class="form-label fw-semibold text-dark">
                                    Nama Agenda / Kegiatan <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       id="event_name" 
                                       wire:model="event_name" 
                                       class="form-control @error('event_name') is-invalid @enderror" 
                                       placeholder="Contoh: Seminar Nasional Teknologi 2026, Wisuda Periode II, dll.">
                                @error('event_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="event_date" class="form-label fw-semibold text-dark">
                                    Tanggal Pelaksanaan <span class="text-danger">*</span>
                                </label>
                                <input type="date" 
                                       id="event_date" 
                                       wire:model="event_date" 
                                       class="form-control @error('event_date') is-invalid @enderror">
                                @error('event_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="event_time" class="form-label fw-semibold text-dark">
                                    Waktu / Jam Acara <span class="text-muted small">(Opsional, contoh: 09:00)</span>
                                </label>
                                <input type="time" 
                                       id="event_time" 
                                       wire:model="event_time" 
                                       class="form-control @error('event_time') is-invalid @enderror">
                                @error('event_time')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="location" class="form-label fw-semibold text-dark">
                                    Lokasi Pelaksanaan <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       id="location" 
                                       wire:model="location" 
                                       class="form-control @error('location') is-invalid @enderror" 
                                       placeholder="Contoh: Auditorium Utama Kampus, Gedung Serba Guna Lt. 3">
                                @error('location')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label fw-semibold text-dark">
                                    Deskripsi & Kebutuhan Peliputan <span class="text-danger">*</span>
                                </label>
                                <textarea id="description" 
                                          wire:model="description" 
                                          rows="4" 
                                          class="form-control @error('description') is-invalid @enderror" 
                                          placeholder="Rincian susunan acara, kebutuhan kru, format video/output liputan..."></textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Section: Data Pemohon -->
                            <div class="col-12 pt-2">
                                <h6 class="text-secondary fw-semibold border-bottom pb-2 mb-0">
                                    <i class="bi bi-person-lines-fill me-1"></i> Data Pemohon / PIC
                                </h6>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="name" class="form-label fw-semibold text-dark">
                                    Nama Pemohon <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       id="name" 
                                       wire:model="name" 
                                       class="form-control @error('name') is-invalid @enderror" 
                                       placeholder="Nama PIC pemohon...">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="phone" class="form-label fw-semibold text-dark">
                                    No. Telepon / WhatsApp <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       id="phone" 
                                       wire:model="phone" 
                                       class="form-control @error('phone') is-invalid @enderror" 
                                       placeholder="08xxxxxxxxxx">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="email" class="form-label fw-semibold text-dark">
                                    Email Pemohon <span class="text-danger">*</span>
                                </label>
                                <input type="email" 
                                       id="email" 
                                       wire:model="email" 
                                       class="form-control @error('email') is-invalid @enderror" 
                                       placeholder="pic@instansi.ac.id">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Section: Evaluasi Internal / Catatan Admin -->
                            <div class="col-12 pt-2">
                                <h6 class="text-secondary fw-semibold border-bottom pb-2 mb-0">
                                    <i class="bi bi-clipboard-check me-1"></i> Catatan Admin / Penugasan Produksi
                                </h6>
                            </div>

                            <div class="col-12">
                                <label for="admin_note" class="form-label fw-semibold text-dark">
                                    Catatan Internal Admin <span class="text-muted small">(Opsional)</span>
                                </label>
                                <textarea id="admin_note" 
                                          wire:model="admin_note" 
                                          rows="3" 
                                          class="form-control @error('admin_note') is-invalid @enderror" 
                                          placeholder="Catatan penugasan tim produksi, arahan teknis, atau catatan evaluasi..."></textarea>
                                @error('admin_note')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        @if ($isEdit && $preparedWhatsAppUrl)
                            <div class="alert alert-success mt-4 mb-0" role="status">
                                <strong>Perubahan sudah disimpan.</strong>
                                Draft WhatsApp menggunakan status terbaru dan siap dibuka.
                            </div>
                        @endif
                        @error('whatsapp')
                            <div class="alert alert-warning mt-4 mb-0" role="alert">{{ $message }}</div>
                        @enderror
                        <div class="d-flex flex-wrap align-items-center gap-2 pt-4 border-top mt-4">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save">
                                    <i class="bi bi-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Tambah Permohonan' }}
                                </span>
                                <span wire:loading wire:target="save">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Menyimpan Data...
                                </span>
                            </button>
                            @if ($isEdit)
                                @if ($preparedWhatsAppUrl)
                                    <a href="{{ $preparedWhatsAppUrl }}"
                                       class="btn btn-outline-primary"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       aria-label="Buka WhatsApp untuk pengajuan {{ $submission_code }}">
                                        <i class="bi bi-whatsapp me-1" aria-hidden="true"></i>Buka WhatsApp
                                    </a>
                                @else
                                    <button type="button"
                                            class="btn btn-outline-primary"
                                            wire:click="saveAndPrepareWhatsApp"
                                            wire:loading.attr="disabled"
                                            data-admin-confirm
                                            data-admin-confirm-kind="update">
                                        <i class="bi bi-whatsapp me-1" aria-hidden="true"></i>Simpan &amp; Siapkan WhatsApp
                                    </button>
                                @endif
                                @if ($clientWhatsAppUrl)
                                    <a href="{{ $clientWhatsAppUrl }}"
                                       class="btn btn-link text-decoration-none"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       aria-label="Hubungi {{ $name }} melalui WhatsApp">
                                        Hubungi Pemohon
                                    </a>
                                @else
                                    <span class="small text-muted">Nomor WhatsApp tidak valid</span>
                                @endif
                            @endif
                            <a href="{{ route('admin.peliputan.index') }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
