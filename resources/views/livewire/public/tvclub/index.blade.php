<div class="tvclub-landing">
    <section class="tvclub-landing-hero" aria-labelledby="tvclubHeroTitle">
        <div class="tvclub-hero-orbit tvclub-hero-orbit--one" aria-hidden="true"></div>
        <div class="tvclub-hero-orbit tvclub-hero-orbit--two" aria-hidden="true"></div>
        <div class="pub-container tvclub-landing-hero__grid">
            <div class="tvclub-landing-hero__content">
                <nav class="tvclub-landing-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('public.home') }}">Beranda</a>
                    <span aria-hidden="true">/</span>
                    <span aria-current="page">TV Club</span>
                </nav>
                <div class="tvclub-landing-eyebrow"><span aria-hidden="true"></span>Di Balik Layar WPiTV</div>
                <h1 id="tvclubHeroTitle">Mereka yang berada <br>di balik layar WPiTV.</h1>
                <p>TV Club menjadi ruang bagi mahasiswa untuk belajar, berkarya, dan terlibat langsung dalam produksi program, berita, tayangan, broadcasting, jurnalistik, dan media digital WPiTV.</p>
                <div class="tvclub-landing-hero__actions">
                    <a href="{{ route('tvclub.members.index') }}" class="pub-btn pub-btn--primary">Kenali Kru TV Club</a>
                    <a href="{{ route('tvclub.generations.index') }}" class="pub-btn tvclub-hero-secondary">Lihat Generasi</a>
                </div>
                <a href="{{ route('tvclub.groups.index') }}" class="tvclub-hero-tertiary">Jelajahi kelompok produksi <span aria-hidden="true">→</span></a>
            </div>

            <div class="tvclub-hero-visual" aria-label="Kru aktif TV Club WPiTV">
                <div class="tvclub-hero-viewfinder" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                <div class="tvclub-hero-media">
                    @forelse($crewPreview->take(3) as $member)
                        <a href="{{ route('tvclub.members.show', $member->id) }}" class="tvclub-hero-person">
                            <span class="tvclub-hero-person__portrait">
                                @if($member->photo_url)
                                    <img src="{{ $member->photo_url }}" alt="Foto {{ $member->name }}" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif>
                                @else
                                    <span aria-hidden="true">{{ $member->initials }}</span>
                                @endif
                            </span>
                            <span class="tvclub-hero-person__copy">
                                <strong>{{ $member->name }}</strong>
                                <small>{{ $member->generation?->name ?? 'TV Club WPiTV' }}</small>
                            </span>
                        </a>
                    @empty
                        <div class="tvclub-hero-media__empty">
                            <span aria-hidden="true">● REC</span>
                            <strong>Produksi berlangsung di balik layar.</strong>
                            <small>Kenali perjalanan TV Club WPiTV.</small>
                        </div>
                    @endforelse
                </div>
                <div class="tvclub-hero-visual__label"><span aria-hidden="true"></span>Mahasiswa · Produksi · Kolaborasi</div>
            </div>
        </div>
    </section>

    <section class="tvclub-stats-bar" aria-label="Statistik TV Club">
        <div class="pub-container">
            <div class="tvclub-stats-grid">
                @foreach([
                    ['value' => $stats['generations'], 'label' => 'Generasi'],
                    ['value' => $stats['members'], 'label' => 'Kru TV Club'],
                    ['value' => $stats['groups'], 'label' => 'Tim Produksi'],
                    ['value' => $stats['assignments'], 'label' => 'Penugasan Produksi'],
                ] as $stat)
                    <div class="tvclub-stat-item"><strong>{{ $stat['value'] }}</strong><span>{{ $stat['label'] }}</span></div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="pub-section pub-section--muted tvclub-active-section" aria-labelledby="activeGenH">
        <div class="pub-container">
            <div class="tvclub-section-heading">
                <div><div class="sec-label">Generasi Berjalan</div><h2 class="sec-h2" id="activeGenH">Generasi Aktif WPiTV</h2></div>
                <a href="{{ route('tvclub.generations.index') }}" class="sec-link">Lihat Semua Generasi <span aria-hidden="true">→</span></a>
            </div>
            @if($activeGenerations->isNotEmpty())
                <div class="tvclub-active-grid {{ $activeGenerations->count() === 1 ? 'tvclub-active-grid--single' : '' }}">
                    @foreach($activeGenerations as $generation)
                        <article class="tvclub-active-generation-card">
                            <div class="tvclub-active-generation-card__year" aria-hidden="true">{{ $generation->year }}</div>
                            <div class="tvclub-active-generation-card__content">
                                <div class="tvclub-active-generation-card__heading">
                                    <span class="tvclub-badge-active"><span aria-hidden="true"></span>Aktif</span>
                                    <span>{{ $generation->year }}</span>
                                </div>
                                <h3>{{ $generation->name }}</h3>
                                @if(filled($generation->description))<p>{{ $generation->description }}</p>@endif
                                <div class="tvclub-active-generation-card__meta">
                                    <span><strong>{{ $generation->members_count }}</strong> Kru</span>
                                    <span><strong>{{ $generation->groups_count }}</strong> Tim Produksi</span>
                                </div>
                                <a href="{{ route('tvclub.generations.show', $generation->id) }}">Lihat Generasi <span aria-hidden="true">→</span></a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="tvclub-active-empty">
                    <div><h3>Belum ada generasi aktif yang ditampilkan.</h3><p>Arsip perjalanan TV Club tetap dapat dijelajahi berdasarkan angkatan.</p></div>
                    <a href="{{ route('tvclub.generations.index') }}" class="pub-btn tvclub-hero-secondary">Lihat Arsip Generasi</a>
                </div>
            @endif
        </div>
    </section>

    @if($crewPreview->isNotEmpty())
        <section class="pub-section tvclub-crew-preview" aria-labelledby="crewPreviewH">
            <div class="pub-container">
                <div class="tvclub-section-heading">
                    <div><div class="sec-label">Kenali Kru</div><h2 class="sec-h2" id="crewPreviewH">Orang-orang di Balik Produksi</h2><p class="tvclub-section-copy">Mahasiswa yang saat ini menjadi bagian dari aktivitas produksi WPiTV.</p></div>
                    <a href="{{ route('tvclub.members.index') }}" class="sec-link">Lihat Semua Kru <span aria-hidden="true">→</span></a>
                </div>
                <div class="tvclub-crew-preview-grid">
                    @foreach($crewPreview as $member)
                        <article class="tvclub-crew-preview-card">
                            <a href="{{ route('tvclub.members.show', $member->id) }}" aria-label="Lihat profil {{ $member->name }}">
                                <span class="tvclub-crew-preview-card__portrait">
                                    @if($member->photo_url)
                                        <img src="{{ $member->photo_url }}" alt="Foto {{ $member->name }}" loading="lazy">
                                    @else
                                        <span aria-hidden="true">{{ $member->initials }}</span>
                                    @endif
                                </span>
                                <span class="tvclub-crew-preview-card__body">
                                    <strong>{{ $member->name }}</strong>
                                    <small>{{ $member->generation?->name ?? 'TV Club WPiTV' }}</small>
                                    <span>{{ $member->groups_count }} Penugasan Produksi</span>
                                </span>
                                <span class="tvclub-crew-preview-card__link">Lihat Profil <span aria-hidden="true">→</span></span>
                            </a>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="pub-section tvclub-explore-section" aria-labelledby="tvclubExploreH">
        <div class="pub-container">
            <div class="tvclub-section-heading"><div><div class="sec-label">Portal TV Club</div><h2 class="sec-h2" id="tvclubExploreH">Jelajahi TV Club</h2><p class="tvclub-section-copy">Temukan perjalanan, orang, dan tim yang menghidupkan produksi WPiTV.</p></div></div>
            <div class="tvclub-portal-grid">
                <a href="{{ route('tvclub.generations.index') }}" class="tvclub-explore-card tvclub-explore-card--generation"><span class="tvclub-explore-card__number">01</span><span class="tvclub-explore-card__motif" aria-hidden="true"><i></i><i></i><i></i></span><div><span class="tvclub-explore-card__eyebrow">Perjalanan</span><h3>Generasi TV Club</h3><p>Telusuri perjalanan setiap angkatan dan kenali mereka yang pernah menjadi bagian dari WPiTV.</p></div><span class="tvclub-explore-card__link">Jelajahi Generasi <span aria-hidden="true">→</span></span></a>
                <a href="{{ route('tvclub.members.index') }}" class="tvclub-explore-card tvclub-explore-card--crew"><span class="tvclub-explore-card__number">02</span><span class="tvclub-explore-card__motif" aria-hidden="true"><i></i><i></i><i></i></span><div><span class="tvclub-explore-card__eyebrow">Di Balik Layar</span><h3>Kru TV Club</h3><p>Kenali mahasiswa yang berkarya di balik produksi dan tayangan WPiTV.</p></div><span class="tvclub-explore-card__link">Lihat Kru <span aria-hidden="true">→</span></span></a>
                <a href="{{ route('tvclub.groups.index') }}" class="tvclub-explore-card tvclub-explore-card--production"><span class="tvclub-explore-card__number">03</span><span class="tvclub-explore-card__motif" aria-hidden="true"><i></i><i></i><i></i></span><div><span class="tvclub-explore-card__eyebrow">Produksi</span><h3>Kelompok Produksi</h3><p>Temukan tim yang mengerjakan berbagai program dan produksi WPiTV.</p></div><span class="tvclub-explore-card__link">Lihat Tim Produksi <span aria-hidden="true">→</span></span></a>
            </div>
        </div>
    </section>

    <section class="tvclub-culture-strip" aria-labelledby="cultureH">
        <div class="pub-container tvclub-culture-strip__inner">
            <div><div class="sec-label">Kultur Produksi</div><h2 id="cultureH">Belajar. Berkarya. Berkolaborasi.</h2></div>
            <div class="tvclub-culture-strip__points">
                <div><strong>Belajar</strong><span>Mengenal proses produksi media secara langsung.</span></div>
                <div><strong>Berkarya</strong><span>Mengembangkan ide menjadi konten dan tayangan.</span></div>
                <div><strong>Berkolaborasi</strong><span>Bekerja bersama dalam berbagai peran produksi.</span></div>
            </div>
        </div>
    </section>

    <section class="tvclub-closing-cta" aria-labelledby="tvclubClosingH">
        <div class="tvclub-closing-cta__orbit" aria-hidden="true"></div>
        <div class="pub-container"><div class="tvclub-closing-cta__inner">
            <div><div class="sec-label sec-label--navy">Karya Bersama</div><h2 id="tvclubClosingH">Di balik setiap tayangan ada sebuah tim.</h2><p>Kenali generasi, kru, dan kelompok produksi yang membentuk WPiTV.</p></div>
            <div class="tvclub-closing-cta__actions"><a href="{{ route('tvclub.members.index') }}" class="pub-btn pub-btn--primary">Lihat Kru TV Club</a><a href="{{ route('program.index') }}" class="pub-btn pub-btn--ghost">Jelajahi Program</a></div>
        </div></div>
    </section>
</div>
