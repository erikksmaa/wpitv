<div class="about-wpitv-page">
    <section class="pub-hero about-wpitv-hero" aria-labelledby="about-wpitv-title">
        <div class="hero-deco" aria-hidden="true"></div>
        <div class="pub-container about-wpitv-hero-inner">
            <div class="about-wpitv-hero-copy" data-reveal>
                <nav class="about-wpitv-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('public.home') }}">Beranda</a>
                    <span aria-hidden="true">/</span>
                    <span aria-current="page">Tentang</span>
                </nav>
                <div class="hero-eyebrow">
                    <span class="about-wpitv-signal-dot" aria-hidden="true"></span>
                    Tentang WPiTV
                </div>
                <h1 class="hero-title about-wpitv-hero-title" id="about-wpitv-title">
                    Membawa cerita kampus lebih dekat melalui layar.
                </h1>
                <p class="about-wpitv-hero-lead">
                    WPiTV adalah media televisi digital Institut Widya Pratama yang menjadi ruang informasi, kreativitas, pembelajaran, dan produksi media bagi lingkungan kampus.
                </p>
                <p class="hero-slogan">Brings The World Into You</p>
                <div class="about-wpitv-actions">
                    <a href="{{ route('program.index') }}" class="pub-btn pub-btn--primary">Jelajahi Program</a>
                    <a href="{{ route('video.index') }}" class="pub-btn pub-btn--secondary">Tonton Tayangan</a>
                </div>
            </div>

            <div class="about-wpitv-hero-visual" data-reveal data-d="1">
                @if ($heroVideo)
                    <a href="{{ route('video.show', $heroVideo) }}" class="about-wpitv-hero-media" aria-label="Tonton {{ $heroVideo->title }}">
                        <img src="{{ $heroVideo->thumbnail_url }}"
                             data-fallback="{{ asset('images/placeholders/program-placeholder.svg') }}"
                             alt="Cuplikan tayangan WPiTV: {{ $heroVideo->title }}"
                             loading="eager"
                             fetchpriority="high">
                        <span class="about-wpitv-media-shade" aria-hidden="true"></span>
                        <span class="about-wpitv-media-content">
                            <span class="about-wpitv-media-label">Produksi WPiTV</span>
                            <strong>{{ $heroVideo->title }}</strong>
                            <small>{{ $heroVideo->program?->name ?? 'Televisi Digital Kampus' }}</small>
                        </span>
                        <span class="about-wpitv-play" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 18 18" fill="currentColor"><path d="M5 3.2 14 9l-9 5.8V3.2Z"/></svg>
                        </span>
                    </a>
                @else
                    <div class="about-wpitv-hero-media about-wpitv-hero-media--fallback">
                        <img src="{{ asset('logo-wpi.png') }}" alt="Logo WPiTV" loading="eager" fetchpriority="high">
                        <p>Televisi digital kampus untuk informasi, pembelajaran, dan karya mahasiswa.</p>
                    </div>
                @endif
                <div class="about-wpitv-visual-note" aria-hidden="true">
                    <span>ON</span>
                    <strong>Campus Broadcast</strong>
                </div>
            </div>
        </div>
    </section>

    <section class="about-wpitv-section about-wpitv-intro" aria-labelledby="about-wpitv-intro-title">
        <div class="pub-container about-wpitv-intro-grid">
            <div data-reveal>
                <span class="about-wpitv-kicker">Siapa Kami</span>
                <h2 id="about-wpitv-intro-title">Mengenal WPiTV</h2>
                <p class="about-wpitv-intro-statement">Media kampus yang menghubungkan informasi, pengalaman belajar, dan kreativitas mahasiswa.</p>
            </div>
            <div class="about-wpitv-intro-copy" data-reveal data-d="1">
                <p>WPiTV (Widya Pratama Internet Televisi) merupakan media televisi digital yang hadir di lingkungan Institut Widya Pratama sebagai sarana komunikasi, informasi, dokumentasi, dan pengembangan kreativitas mahasiswa.</p>
                <p>Melalui berbagai program, berita, tayangan, dan kegiatan peliputan, WPiTV menghadirkan cerita dari kehidupan kampus sekaligus menjadi ruang bagi mahasiswa untuk belajar dan mengembangkan kemampuan di bidang broadcasting dan media digital.</p>
                <div class="about-wpitv-managed-by">
                    <span aria-hidden="true">WPI</span>
                    <p>Dikelola oleh unit terkait Institut Widya Pratama bersama mahasiswa TV Club.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="about-wpitv-brand" aria-labelledby="about-wpitv-brand-title">
        <div class="about-wpitv-brand-orbit" aria-hidden="true"></div>
        <div class="pub-container about-wpitv-brand-inner" data-reveal>
            <img src="{{ asset('logo-wpi.png') }}" alt="Logo WPiTV" loading="lazy">
            <span class="about-wpitv-kicker about-wpitv-kicker--light">Brand Statement</span>
            <h2 id="about-wpitv-brand-title">Brings The World Into You</h2>
            <p>Membawa informasi, kreativitas, pengalaman, dan cerita lebih dekat melalui media digital.</p>
            <div class="about-wpitv-brand-accent" aria-hidden="true"><span></span><span></span></div>
        </div>
    </section>

    <section class="about-wpitv-section about-wpitv-vision" aria-labelledby="about-wpitv-vision-title">
        <div class="pub-container">
            <div class="about-wpitv-section-heading" data-reveal>
                <span class="about-wpitv-kicker">Arah &amp; Peran</span>
                <h2 id="about-wpitv-vision-title">Visi &amp; Misi</h2>
                <p>Substansi berikut dipertahankan dari profil WPiTV terdahulu dan disajikan kembali agar lebih mudah dibaca.</p>
            </div>

            <article class="about-wpitv-vision-card" data-reveal>
                <div class="about-wpitv-vision-mark" aria-hidden="true">“</div>
                <div>
                    <span>Visi · redaksi profil terdahulu</span>
                    <p>Untuk menjadi media komunikasi utama STMIK Widya Pratama yang berbasis pada teknologi informasi yang dapat diterima sebagai panutan, serta siap untuk bersaing dan beradaptasi dengan era globalisasi.</p>
                    <small>Redaksi “STMIK Widya Pratama” dipertahankan sesuai sumber legacy dan belum dinyatakan sebagai pembaruan resmi.</small>
                </div>
            </article>

            <div class="about-wpitv-mission-grid">
                <article class="about-wpitv-mission-card" data-reveal>
                    <span class="about-wpitv-index">01</span>
                    <h3>Media Komunitas Kampus</h3>
                    <p>Menjadi televisi komunitas kampus yang menjadi ruang komunikasi dan informasi bagi lingkungan Widya Pratama.</p>
                </article>
                <article class="about-wpitv-mission-card" data-reveal data-d="1">
                    <span class="about-wpitv-index">02</span>
                    <h3>Pengembangan Potensi</h3>
                    <p>Menghadirkan program yang mendukung teknologi informasi, komunikasi, bahasa asing, kepemimpinan, inovasi, kewirausahaan, dan karakter positif.</p>
                </article>
                <article class="about-wpitv-mission-card" data-reveal data-d="2">
                    <span class="about-wpitv-index">03</span>
                    <h3>Ruang Belajar Penyiaran</h3>
                    <p>Menjadi ruang bagi mahasiswa untuk belajar, berlatih, dan mengembangkan keterampilan penyiaran serta produksi media.</p>
                </article>
                <article class="about-wpitv-mission-card" data-reveal data-d="3">
                    <span class="about-wpitv-index">04</span>
                    <h3>SDM Penyiaran Berkualitas</h3>
                    <p>Mendorong terbentuknya sumber daya manusia yang berkualitas dan kompeten di bidang penyiaran.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="about-wpitv-section about-wpitv-pillars-section" aria-labelledby="about-wpitv-pillars-title">
        <div class="pub-container">
            <div class="about-wpitv-section-heading about-wpitv-section-heading--split" data-reveal>
                <div>
                    <span class="about-wpitv-kicker">Ruang Berkarya</span>
                    <h2 id="about-wpitv-pillars-title">Apa yang Kami Lakukan</h2>
                </div>
                <p>Empat ruang yang membuat cerita kampus dapat direncanakan, diproduksi, dibagikan, dan terus berkembang.</p>
            </div>

            <div class="about-wpitv-pillars">
                <a href="{{ route('program.index') }}" class="about-wpitv-pillar about-wpitv-pillar--blue" data-reveal>
                    <span class="about-wpitv-index">01</span><small>Program &amp; Tayangan</small>
                    <h3>Menciptakan konten yang bercerita.</h3>
                    <p>Produksi program dan tayangan yang menghadirkan informasi, edukasi, hiburan, dan kehidupan kampus.</p>
                    <strong>Jelajahi Program <span aria-hidden="true">→</span></strong>
                </a>
                <a href="{{ route('news.index') }}" class="about-wpitv-pillar about-wpitv-pillar--cyan" data-reveal data-d="1">
                    <span class="about-wpitv-index">02</span><small>Berita</small>
                    <h3>Mengabarkan cerita dari kampus.</h3>
                    <p>Menyampaikan informasi, kegiatan, prestasi, dan cerita dari lingkungan Institut Widya Pratama.</p>
                    <strong>Baca Berita <span aria-hidden="true">→</span></strong>
                </a>
                <a href="{{ route('peliputan.create') }}" class="about-wpitv-pillar about-wpitv-pillar--orange" data-reveal data-d="2">
                    <span class="about-wpitv-index">03</span><small>Peliputan</small>
                    <h3>Mengabadikan momen yang berarti.</h3>
                    <p>Mendokumentasikan berbagai kegiatan melalui layanan peliputan WPiTV.</p>
                    <strong>Ajukan Peliputan <span aria-hidden="true">→</span></strong>
                </a>
                <a href="{{ route('tvclub.index') }}" class="about-wpitv-pillar about-wpitv-pillar--red" data-reveal data-d="3">
                    <span class="about-wpitv-index">04</span><small>TV Club</small>
                    <h3>Belajar dan berkarya di balik layar.</h3>
                    <p>Ruang mahasiswa untuk terlibat dalam broadcasting, produksi, jurnalistik, editing, dan media digital.</p>
                    <strong>Kenali TV Club <span aria-hidden="true">→</span></strong>
                </a>
            </div>
        </div>
    </section>

    <section class="about-wpitv-stats" aria-labelledby="about-wpitv-stats-title">
        <div class="pub-container">
            <div class="about-wpitv-stats-heading" data-reveal>
                <span class="about-wpitv-kicker about-wpitv-kicker--light">Data Aktual</span>
                <h2 id="about-wpitv-stats-title">WPiTV dalam Angka</h2>
                <p>Potret ekosistem konten dan kru berdasarkan data yang tercatat saat ini.</p>
            </div>
            <div class="about-wpitv-stats-grid">
                <div data-reveal><strong data-count data-target="{{ $stats['programs'] }}">{{ $stats['programs'] }}</strong><span>Program Aktif</span></div>
                <div data-reveal data-d="1"><strong data-count data-target="{{ $stats['videos'] }}">{{ $stats['videos'] }}</strong><span>Total Tayangan</span></div>
                <div data-reveal data-d="2"><strong data-count data-target="{{ $stats['news'] }}">{{ $stats['news'] }}</strong><span>Berita Terbit</span></div>
                <div data-reveal data-d="3"><strong data-count data-target="{{ $stats['members'] }}">{{ $stats['members'] }}</strong><span>Anggota Aktif</span></div>
            </div>
        </div>
    </section>

    <section class="about-wpitv-section about-wpitv-workflow" aria-labelledby="about-wpitv-workflow-title">
        <div class="pub-container">
            <div class="about-wpitv-section-heading" data-reveal>
                <span class="about-wpitv-kicker">Proses Produksi</span>
                <h2 id="about-wpitv-workflow-title">Dari Ide Hingga Tayang</h2>
                <p>Di balik sebuah tayangan terdapat proses kreatif dan kolaborasi dari berbagai peran.</p>
            </div>
            <ol class="about-wpitv-workflow-list">
                <li data-reveal><span>01</span><h3>Ide &amp; Perencanaan</h3><p>Menentukan konsep, tujuan, materi, dan arah produksi.</p></li>
                <li data-reveal data-d="1"><span>02</span><h3>Persiapan</h3><p>Menyiapkan kru, peralatan, kebutuhan teknis, dan materi produksi.</p></li>
                <li data-reveal data-d="2"><span>03</span><h3>Produksi</h3><p>Pengambilan gambar, rekaman, wawancara, atau peliputan.</p></li>
                <li data-reveal data-d="3"><span>04</span><h3>Post-Production</h3><p>Editing video, audio, grafis, dan finalisasi konten.</p></li>
                <li data-reveal><span>05</span><h3>Publikasi</h3><p>Konten dipublikasikan melalui WPiTV dan media digital.</p></li>
            </ol>
        </div>
    </section>

    <section class="about-wpitv-section about-wpitv-crew" aria-labelledby="about-wpitv-crew-title">
        <div class="pub-container">
            <div class="about-wpitv-section-heading about-wpitv-section-heading--split" data-reveal>
                <div><span class="about-wpitv-kicker">Kolaborasi Mahasiswa</span><h2 id="about-wpitv-crew-title">Di Balik Layar WPiTV</h2></div>
                <div><p>Setiap tayangan merupakan hasil kolaborasi mahasiswa dan tim dari perencanaan hingga publikasi.</p><a href="{{ route('tvclub.index') }}">Kenali Seluruh TV Club <span aria-hidden="true">→</span></a></div>
            </div>

            @if ($crewMembers->isNotEmpty())
                <div class="about-wpitv-crew-grid">
                    @foreach ($crewMembers as $member)
                        <a href="{{ route('tvclub.members.show', $member) }}" class="about-wpitv-crew-card" data-reveal>
                            <div class="about-wpitv-avatar">
                                @if ($member->photo_url)
                                    <img src="{{ $member->photo_url }}" data-fallback="{{ asset('images/placeholders/program-placeholder.svg') }}" alt="Foto {{ $member->name }}" loading="lazy">
                                @else
                                    <span aria-hidden="true">{{ $member->initials }}</span>
                                @endif
                            </div>
                            <div><h3>{{ $member->name }}</h3><p>{{ $member->generation?->name ?? 'TV Club WPiTV' }}</p></div>
                            <span class="about-wpitv-card-arrow" aria-hidden="true">↗</span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="about-wpitv-crew-empty" data-reveal>
                    <img src="{{ asset('logo-wpi.png') }}" alt="Logo WPiTV" loading="lazy">
                    <h3>Kru akan segera diperkenalkan</h3>
                    <p>Profil anggota aktif TV Club belum tersedia untuk ditampilkan.</p>
                </div>
            @endif
        </div>
    </section>

    <section class="about-wpitv-section about-wpitv-ecosystem" aria-labelledby="about-wpitv-ecosystem-title">
        <div class="pub-container">
            <div class="about-wpitv-section-heading about-wpitv-section-heading--center" data-reveal>
                <span class="about-wpitv-kicker">Terhubung dalam Satu Portal</span>
                <h2 id="about-wpitv-ecosystem-title">Satu Ekosistem Media Kampus</h2>
                <p>WPiTV menghubungkan program, tayangan, berita, produksi mahasiswa, dan layanan peliputan menjadi satu ekosistem media digital Institut Widya Pratama.</p>
            </div>
            <nav class="about-wpitv-ecosystem-map" aria-label="Jelajahi ekosistem WPiTV">
                <a href="{{ route('program.index') }}"><span>01</span><strong>Program</strong><small>Konsep &amp; format produksi</small></a>
                <a href="{{ route('video.index') }}"><span>02</span><strong>Tayangan</strong><small>Hasil produksi audiovisual</small></a>
                <div class="about-wpitv-ecosystem-core" aria-hidden="true"><img src="{{ asset('logo-wpi.png') }}" alt=""></div>
                <a href="{{ route('news.index') }}"><span>03</span><strong>Berita</strong><small>Informasi &amp; jurnalistik kampus</small></a>
                <a href="{{ route('tvclub.index') }}"><span>04</span><strong>TV Club</strong><small>Kru &amp; proses di balik layar</small></a>
                <a href="{{ route('peliputan.index') }}"><span>05</span><strong>Peliputan</strong><small>Kegiatan menjadi konten</small></a>
            </nav>
        </div>
    </section>

    <section class="about-wpitv-section about-wpitv-coverage" aria-labelledby="about-wpitv-coverage-title">
        <div class="pub-container">
            <div class="about-wpitv-coverage-card" data-reveal>
                <div class="about-wpitv-coverage-signal" aria-hidden="true"><span></span><span></span><span></span></div>
                <div>
                    <span class="about-wpitv-kicker about-wpitv-kicker--light">Layanan Peliputan</span>
                    <h2 id="about-wpitv-coverage-title">Punya kegiatan yang ingin diliput?</h2>
                    <p>Ceritakan kegiatanmu kepada tim WPiTV dan ajukan permohonan peliputan melalui portal layanan WPiTV.</p>
                </div>
                <div class="about-wpitv-actions">
                    <a href="{{ route('peliputan.create') }}" class="pub-btn pub-btn--primary">Ajukan Peliputan</a>
                    <a href="{{ route('peliputan.status') }}" class="pub-btn pub-btn--ghost-white">Cek Status</a>
                </div>
            </div>
        </div>
    </section>

    <section class="about-wpitv-closing" aria-labelledby="about-wpitv-closing-title">
        <div class="pub-container about-wpitv-closing-inner" data-reveal>
            <div><span class="about-wpitv-kicker">Lanjut Menjelajah</span><h2 id="about-wpitv-closing-title">Temukan lebih banyak cerita dari WPiTV.</h2><p>Jelajahi program, tayangan, berita, dan karya mahasiswa yang menjadi bagian dari perjalanan WPiTV.</p></div>
            <nav class="about-wpitv-closing-links" aria-label="Jelajahi konten WPiTV">
                <a href="{{ route('program.index') }}">Program <span aria-hidden="true">→</span></a>
                <a href="{{ route('video.index') }}">Tayangan <span aria-hidden="true">→</span></a>
                <a href="{{ route('news.index') }}">Berita <span aria-hidden="true">→</span></a>
                <a href="{{ route('tvclub.index') }}">TV Club <span aria-hidden="true">→</span></a>
            </nav>
        </div>
    </section>
</div>
