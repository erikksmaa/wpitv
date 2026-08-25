<div>
    <!-- HERO SECTION -->
    <section class="pub-hero !py-12 md:!py-16 border-b border-[var(--border)]" aria-labelledby="peliputanHeroTitle">
        <div class="hero-deco" aria-hidden="true"></div>
        <div class="pub-container relative z-10">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-xs text-[var(--text-muted)] mb-4" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}" class="hover:text-brand-blue transition-colors">Beranda</a>
                <span class="text-[var(--text-faint)]">/</span>
                <span class="text-brand-blue font-medium" aria-current="page">Peliputan</span>
            </nav>

            <div class="max-w-2xl">
                <div class="hero-eyebrow !mb-3">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-blue" aria-hidden="true"></span>
                    Layanan Liputan &amp; Dokumentasi
                </div>
                <h1 class="hero-title !text-3xl md:!text-4xl !mb-3" id="peliputanHeroTitle">
                    Punya kegiatan yang ingin diliput?
                </h1>
                <p class="text-sm md:text-base text-[var(--text-muted)] leading-relaxed mb-6">
                    WPiTV melayani pengajuan peliputan kegiatan Institut Widya Pratama dan kegiatan media lainnya. Tim kru siap mendokumentasikan, menyiarkan, dan mempublikasikan agenda Anda.
                </p>
                <div class="flex items-center gap-3 flex-wrap">
                    <a href="{{ route('peliputan.create') }}" class="pub-btn pub-btn--primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13.23 19.79 19.79 0 0 1 1.62 4.7 2 2 0 0 1 3.59 2.52h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 10.1a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        Ajukan Peliputan Sekarang
                    </a>
                    <a href="{{ route('peliputan.status') }}" class="pub-btn pub-btn--ghost">
                        Cek Status Pengajuan
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- STEPS ALUR SECTION -->
    <section class="pub-section" aria-labelledby="stepsH">
        <div class="pub-container">
            <div class="text-center max-w-xl mx-auto mb-12">
                <div class="sec-label justify-center">Alur Layanan</div>
                <h2 class="sec-h2" id="stepsH">Cara Mengajukan Peliputan</h2>
                <p class="text-sm text-[var(--text-muted)] mt-2">
                    Ikuti tahapan sederhana berikut untuk mengajukan permohonan liputan kegiatan Anda ke tim redaksi WPiTV.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Step 1 -->
                <div class="peliputan-step-card">
                    <div class="w-10 h-10 rounded-xl bg-brand-blue/10 text-brand-blue font-heading font-extrabold text-lg flex items-center justify-center mb-4">
                        1
                    </div>
                    <h3 class="font-heading font-bold text-base text-[var(--text-primary)] mb-2">
                        Isi Formulir Pengajuan
                    </h3>
                    <p class="text-xs text-[var(--text-muted)] leading-relaxed">
                        Lengkapi informasi nama pemohon, kontak, nama agenda kegiatan, tanggal, waktu, dan lokasi acara secara jelas.
                    </p>
                </div>

                <!-- Step 2 -->
                <div class="peliputan-step-card">
                    <div class="w-10 h-10 rounded-xl bg-brand-blue/10 text-brand-blue font-heading font-extrabold text-lg flex items-center justify-center mb-4">
                        2
                    </div>
                    <h3 class="font-heading font-bold text-base text-[var(--text-primary)] mb-2">
                        Dapatkan Kode Pengajuan
                    </h3>
                    <p class="text-xs text-[var(--text-muted)] leading-relaxed">
                        Simpan kode unik pengajuan (misal: <code class="text-brand-blue font-semibold">PLP-2026-XXXX</code>) yang diterbitkan sistem untuk pengecekan status.
                    </p>
                </div>

                <!-- Step 3 -->
                <div class="peliputan-step-card">
                    <div class="w-10 h-10 rounded-xl bg-brand-blue/10 text-brand-blue font-heading font-extrabold text-lg flex items-center justify-center mb-4">
                        3
                    </div>
                    <h3 class="font-heading font-bold text-base text-[var(--text-primary)] mb-2">
                        Review &amp; Penugasan Tim
                    </h3>
                    <p class="text-xs text-[var(--text-muted)] leading-relaxed">
                        Tim redaksi WPiTV akan meninjau kelayakan jadwal, ketersediaan kru, dan perlengkapan siaran kegiatan Anda.
                    </p>
                </div>

                <!-- Step 4 -->
                <div class="peliputan-step-card">
                    <div class="w-10 h-10 rounded-xl bg-brand-blue/10 text-brand-blue font-heading font-extrabold text-lg flex items-center justify-center mb-4">
                        4
                    </div>
                    <h3 class="font-heading font-bold text-base text-[var(--text-primary)] mb-2">
                        Cek Status &amp; Koordinasi
                    </h3>
                    <p class="text-xs text-[var(--text-muted)] leading-relaxed">
                        Pantau status pengajuan secara berkala melalui halaman Cek Status dan tunggu kontak dari narahubung kru WPiTV.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- CRITERIA SECTION -->
    <section class="pub-section bg-[var(--surface-muted)] border-y border-[var(--border)]" aria-labelledby="criteriaH">
        <div class="pub-container">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
                <div class="lg:col-span-5">
                    <div class="sec-label">Lingkup Layanan</div>
                    <h2 class="sec-h2 mb-3" id="criteriaH">Kategori Kegiatan yang Kami Liput</h2>
                    <p class="text-sm text-[var(--text-secondary)] leading-relaxed mb-6">
                        WPiTV berfokus mendokumentasikan kegiatan akademis, kemahasiswaan, dan agenda penting Institut Widya Pratama.
                    </p>
                    <a href="{{ route('peliputan.create') }}" class="pub-btn pub-btn--primary !text-xs !py-2 !px-4 inline-flex items-center gap-1.5">
                        Ajukan Kegiatan Anda &rarr;
                    </a>
                </div>

                <div class="lg:col-span-7">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-5 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-sm">
                            <h4 class="font-heading font-bold text-sm text-[var(--text-primary)] mb-1.5">
                                Seminar &amp; Kuliah Umum
                            </h4>
                            <p class="text-xs text-[var(--text-muted)] leading-relaxed">
                                Agenda ilmiah, webinar, workshop, dan kuliah tamu program studi maupun institut.
                            </p>
                        </div>
                        <div class="p-5 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-sm">
                            <h4 class="font-heading font-bold text-sm text-[var(--text-primary)] mb-1.5">
                                Lomba &amp; Prestasi Kampus
                            </h4>
                            <p class="text-xs text-[var(--text-muted)] leading-relaxed">
                                Kompetisi teknologi, olahraga, seni, dan ajang pencapaian mahasiswa di lingkungan kampus.
                            </p>
                        </div>
                        <div class="p-5 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-sm">
                            <h4 class="font-heading font-bold text-sm text-[var(--text-primary)] mb-1.5">
                                Kegiatan UKM &amp; Ormawa
                            </h4>
                            <p class="text-xs text-[var(--text-muted)] leading-relaxed">
                                Program kerja organisasi mahasiswa, bakti sosial, pelantikan pengurus, dan festival.
                            </p>
                        </div>
                        <div class="p-5 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-sm">
                            <h4 class="font-heading font-bold text-sm text-[var(--text-primary)] mb-1.5">
                                Acara Resmi &amp; Wisuda
                            </h4>
                            <p class="text-xs text-[var(--text-muted)] leading-relaxed">
                                Sidang senat terbuka, wisuda sarjana, dies natalis, dan penerimaan mahasiswa baru.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- BOTTOM CTA BANNER -->
    <section class="pub-section">
        <div class="pub-container">
            <div class="p-8 sm:p-12 rounded-3xl bg-[var(--surface)] border border-[var(--border)] shadow-sm flex flex-col md:flex-row items-center justify-between gap-6">
                <div>
                    <h3 class="font-heading font-bold text-xl sm:text-2xl text-[var(--text-primary)] mb-2">
                        Sudah pernah mengajukan permohonan liputan?
                    </h3>
                    <p class="text-sm text-[var(--text-muted)]">
                        Gunakan kode pengajuan Anda untuk memeriksa perkembangan review dan status persetujuan kru.
                    </p>
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <a href="{{ route('peliputan.status') }}" class="pub-btn pub-btn--secondary">
                        Cek Status Pengajuan
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>
