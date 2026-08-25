<div>
    <!-- HERO / HEADER SECTION -->
    <section class="pub-hero !py-12 md:!py-16 border-b border-[var(--border)]" aria-labelledby="successTitle">
        <div class="hero-deco" aria-hidden="true"></div>
        <div class="pub-container relative z-10">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-xs text-[var(--text-muted)] mb-4" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}" class="hover:text-brand-blue transition-colors">Beranda</a>
                <span class="text-[var(--text-faint)]">/</span>
                <a href="{{ route('peliputan.index') }}" class="hover:text-brand-blue transition-colors">Peliputan</a>
                <span class="text-[var(--text-faint)]">/</span>
                <span class="text-brand-blue font-medium" aria-current="page">Pengajuan Berhasil</span>
            </nav>

            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-2 text-xs font-bold text-green-600 bg-green-500/10 border border-green-500/20 px-3 py-1 rounded-full mb-3 uppercase tracking-wider">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Permohonan Diterima
                </div>
                <h1 class="hero-title !text-3xl md:!text-4xl !mb-2.5" id="successTitle">
                    Pengajuan Peliputan Terkirim!
                </h1>
                <p class="text-sm md:text-base text-[var(--text-muted)] leading-relaxed">
                    Terima kasih. Permohonan peliputan Anda telah masuk ke sistem antrean redaksi WPiTV untuk ditinjau.
                </p>
            </div>
        </div>
    </section>

    <!-- SUCCESS CONTENT -->
    <section class="pub-section">
        <div class="pub-container">
            <div class="max-w-2xl mx-auto space-y-6">
                <!-- CODE CARD -->
                <div class="bg-[var(--surface)] p-6 sm:p-8 rounded-3xl border border-[var(--border)] shadow-sm text-center">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[var(--text-muted)] block mb-2">
                        Kode Unik Pengajuan Anda
                    </span>
                    <div class="inline-block bg-[var(--surface-muted)] border-2 border-dashed border-brand-blue/40 rounded-2xl px-6 py-4 mb-4">
                        <span class="font-mono font-extrabold text-2xl sm:text-3xl text-brand-blue dark:text-brand-cyan tracking-wider select-all">
                            {{ $coverage->submission_code }}
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-[var(--text-muted)] max-w-md mx-auto leading-relaxed mb-6">
                        Mohon simpan dan catat kode pengajuan di atas. Anda dapat menggunakannya kapan saja untuk mengecek perkembangan status liputan.
                    </p>

                    <!-- QUICK SUMMARY -->
                    <div class="bg-[var(--bg)] p-4 rounded-2xl border border-[var(--border)] text-left text-xs text-[var(--text-secondary)] space-y-2 mb-6">
                        <div class="flex justify-between py-1 border-b border-[var(--border)]">
                            <span class="text-[var(--text-muted)]">Nama Kegiatan:</span>
                            <span class="font-semibold text-[var(--text-primary)] text-right max-w-[60%]">{{ $coverage->event_name }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-[var(--border)]">
                            <span class="text-[var(--text-muted)]">Tanggal Acara:</span>
                            <span class="font-semibold text-[var(--text-primary)]">{{ $coverage->event_date ? $coverage->event_date->isoFormat('D MMMM Y') : '-' }}</span>
                        </div>
                        <div class="flex justify-between py-1">
                            <span class="text-[var(--text-muted)]">Status Awal:</span>
                            <span class="peliputan-badge-pending">Menunggu Review</span>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                        <a href="{{ route('peliputan.status') }}" class="pub-btn pub-btn--primary w-full sm:w-auto">
                            Cek Status Pengajuan
                        </a>
                        <a href="{{ route('public.home') }}" class="pub-btn pub-btn--ghost w-full sm:w-auto">
                            Kembali ke Beranda
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
