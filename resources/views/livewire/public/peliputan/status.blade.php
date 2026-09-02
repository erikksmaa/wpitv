<div>
    <!-- HERO / HEADER SECTION -->
    <section class="pub-hero !py-12 md:!py-16 border-b border-[var(--border)]" aria-labelledby="statusTitle">
        <div class="hero-deco" aria-hidden="true"></div>
        <div class="pub-container relative z-10">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-xs text-[var(--text-muted)] mb-4" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}" class="hover:text-brand-blue transition-colors">Beranda</a>
                <span class="text-[var(--text-faint)]">/</span>
                <a href="{{ route('peliputan.index') }}" class="hover:text-brand-blue transition-colors">Peliputan</a>
                <span class="text-[var(--text-faint)]">/</span>
                <span class="text-brand-blue font-medium" aria-current="page">Cek Status</span>
            </nav>

            <div class="max-w-2xl">
                <div class="hero-eyebrow !mb-3">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-blue" aria-hidden="true"></span>
                    Pelacakan Pengajuan
                </div>
                <h1 class="hero-title !text-3xl md:!text-4xl !mb-2.5" id="statusTitle">
                    Cek Status Permohonan Liputan
                </h1>
                <p class="text-sm md:text-base text-[var(--text-muted)] leading-relaxed">
                    Masukkan kode unik pengajuan dan kontak verifikasi Anda untuk melihat perkembangan tindak lanjut dari tim WPiTV.
                </p>
            </div>
        </div>
    </section>

    <!-- CONTENT SECTION -->
    <section class="pub-section">
        <div class="pub-container">
            <div class="max-w-3xl mx-auto space-y-8">
                <!-- LOOKUP FORM CARD -->
                <div class="peliputan-form-card">
                    <form wire:submit.prevent="check" class="space-y-4" novalidate>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Kode Pengajuan -->
                            <div>
                                <label for="submission_code" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1.5">
                                    Kode Pengajuan <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="submission_code" 
                                    wire:model="submission_code"
                                    placeholder="Contoh: PLP-20260822-ABCD"
                                    class="w-full px-3.5 py-2.5 bg-[var(--bg)] border @error('submission_code') border-red-500 @else border-[var(--border)] @enderror rounded-lg text-sm text-[var(--text-primary)] placeholder-[var(--text-faint)] font-mono uppercase focus:outline-none focus:border-brand-blue transition-colors"
                                >
                                @error('submission_code')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Kontak Verifikasi -->
                            <div>
                                <label for="phone" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1.5">
                                    No. WhatsApp / Telepon Pemohon <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="phone"
                                    wire:model="phone"
                                    inputmode="tel"
                                    autocomplete="tel"
                                    placeholder="Nomor WA saat pengajuan"
                                    class="w-full px-3.5 py-2.5 bg-[var(--bg)] border @error('phone') border-red-500 @else border-[var(--border)] @enderror rounded-lg text-sm text-[var(--text-primary)] placeholder-[var(--text-faint)] focus:outline-none focus:border-brand-blue transition-colors"
                                >
                                @error('phone')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        @error('lookup')
                            <div class="p-3.5 rounded-xl bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 text-xs flex items-center gap-2.5">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="flex-shrink-0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                <span>{{ $message }}</span>
                            </div>
                        @enderror

                        <div class="pt-2 flex items-center justify-end">
                            <button 
                                type="submit" 
                                wire:loading.attr="disabled"
                                class="pub-btn pub-btn--primary w-full sm:w-auto inline-flex items-center justify-center gap-2"
                            >
                                <span wire:loading.remove>Periksa Status &rarr;</span>
                                <span wire:loading class="inline-flex items-center gap-2">
                                    <svg class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    Memeriksa...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- RESULT CARD -->
                @if($coverageResult)
                    <div class="peliputan-status-card animate-fadeIn">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 mb-6 border-b border-[var(--border)]">
                            <div>
                                <span class="text-xs font-mono font-bold text-brand-blue dark:text-brand-cyan tracking-wider uppercase block mb-1">
                                    {{ $coverageResult->submission_code }}
                                </span>
                                <h2 class="font-heading font-extrabold text-xl sm:text-2xl text-[var(--text-primary)]">
                                    {{ $coverageResult->event_name }}
                                </h2>
                            </div>

                            <div class="flex-shrink-0">
                                @if($coverageResult->status === 'pending')
                                    <span class="peliputan-badge-pending">
                                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse" aria-hidden="true"></span>
                                        Menunggu Review
                                    </span>
                                @elseif($coverageResult->status === 'approved')
                                    <span class="peliputan-badge-approved">
                                        <span class="w-2 h-2 rounded-full bg-blue-600" aria-hidden="true"></span>
                                        Disetujui
                                    </span>
                                @elseif($coverageResult->status === 'in_progress')
                                    <span class="peliputan-badge-in_progress">
                                        <span class="w-2 h-2 rounded-full bg-cyan-600 animate-pulse" aria-hidden="true"></span>
                                        Sedang Diproses
                                    </span>
                                @elseif($coverageResult->status === 'completed')
                                    <span class="peliputan-badge-completed">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                        Selesai
                                    </span>
                                @elseif($coverageResult->status === 'rejected')
                                    <span class="peliputan-badge-rejected">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        Ditolak
                                    </span>
                                @else
                                    <span class="peliputan-badge-pending">{{ $coverageResult->status_label }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- DETAILS GRID -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs text-[var(--text-secondary)] mb-6">
                            <div class="p-3.5 rounded-xl bg-[var(--surface-muted)] border border-[var(--border)]">
                                <span class="text-[var(--text-faint)] block mb-1">Tanggal Kegiatan</span>
                                <span class="font-semibold text-sm text-[var(--text-primary)]">
                                    {{ $coverageResult->event_date ? $coverageResult->event_date->isoFormat('dddd, D MMMM Y') : '-' }}
                                </span>
                            </div>

                            <div class="p-3.5 rounded-xl bg-[var(--surface-muted)] border border-[var(--border)]">
                                <span class="text-[var(--text-faint)] block mb-1">Waktu &amp; Lokasi Acara</span>
                                <span class="font-semibold text-sm text-[var(--text-primary)]">
                                    {{ $coverageResult->event_time ? substr($coverageResult->event_time, 0, 5) . ' WIB — ' : '' }}{{ $coverageResult->location }}
                                </span>
                            </div>

                            <div class="p-3.5 rounded-xl bg-[var(--surface-muted)] border border-[var(--border)]">
                                <span class="text-[var(--text-faint)] block mb-1">Pemohon Terverifikasi</span>
                                <span class="font-semibold text-sm text-[var(--text-primary)]">
                                    {{ $coverageResult->name }} ({{ $this->maskPhone($coverageResult->phone) }})
                                </span>
                            </div>

                            <div class="p-3.5 rounded-xl bg-[var(--surface-muted)] border border-[var(--border)]">
                                <span class="text-[var(--text-faint)] block mb-1">Tanggal Diajukan</span>
                                <span class="font-semibold text-sm text-[var(--text-primary)]">
                                    {{ $coverageResult->created_at ? $coverageResult->created_at->isoFormat('D MMMM Y, HH:mm') : '-' }} WIB
                                </span>
                            </div>
                        </div>

                        <!-- STATUS MESSAGE -->
                        <div class="text-xs text-[var(--text-muted)] leading-relaxed p-4 rounded-xl bg-[var(--bg)] border border-[var(--border)]">
                            @if($coverageResult->status === 'pending')
                                Permohonan Anda sedang dalam tahap verifikasi jadwal dan kru. Tim kami akan segera meninjau detail acara Anda.
                            @elseif($coverageResult->status === 'approved')
                                Permohonan telah disetujui! Tim kru WPiTV telah dijadwalkan dan narahubung kami akan segera menghubungi Anda untuk koordinasi teknis di lokasi.
                            @elseif($coverageResult->status === 'in_progress')
                                Tim kru WPiTV sedang bertugas meliput dan mendokumentasikan kegiatan ini.
                            @elseif($coverageResult->status === 'completed')
                                Peliputan telah selesai dilaksanakan. Hasil liputan akan diproses oleh tim editing untuk penayangan dan arsip.
                            @elseif($coverageResult->status === 'rejected')
                                Pengajuan peliputan ini belum dapat kami proses. Hubungi Admin WPiTV bila Anda memerlukan informasi lebih lanjut.
                            @endif
                        </div>

                        @if($whatsAppUrl)
                            <div class="coverage-whatsapp-panel">
                                <div>
                                    <strong>Perlu koordinasi lebih lanjut?</strong>
                                    <span>Hubungi Admin WPiTV melalui WhatsApp dengan detail pengajuan Anda.</span>
                                </div>
                                <a href="{{ $whatsAppUrl }}"
                                   class="pub-btn pub-btn--secondary coverage-whatsapp-action"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   aria-label="Hubungi Admin WPiTV melalui WhatsApp">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.5 9.8 9.8 0 0 1-4-.9L3 21l1.7-4.6A8.5 8.5 0 1 1 21 11.5Z"/><path d="M8.5 8.5c.5 3 2 4.5 5 5"/></svg>
                                    Hubungi Admin
                                </a>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
