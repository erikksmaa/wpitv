<div class="tvclub-generations-index">
    <section class="tvclub-generation-hero tvclub-generation-hero--index" aria-labelledby="generationIndexTitle">
        <div class="tvclub-generation-hero__orbit" aria-hidden="true"></div>
        <div class="pub-container tvclub-generation-hero__grid">
            <div>
                <nav class="tvclub-generation-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('public.home') }}">Beranda</a><span aria-hidden="true">/</span>
                    <a href="{{ route('tvclub.index') }}">TV Club</a><span aria-hidden="true">/</span>
                    <span aria-current="page">Generasi</span>
                </nav>
                <div class="tvclub-generation-eyebrow"><span aria-hidden="true"></span>Generasi TV Club</div>
                <h1 id="generationIndexTitle">Perjalanan TV Club dari Generasi ke Generasi</h1>
                <p>Kenali setiap angkatan mahasiswa yang telah menjadi bagian dari perjalanan produksi WPiTV.</p>
            </div>

            @if($generations->isNotEmpty())
                <div class="tvclub-generation-hero__timeline" aria-label="Generasi terbaru TV Club">
                    <span class="tvclub-generation-hero__line" aria-hidden="true"></span>
                    @foreach($generations->take(4) as $generation)
                        <a href="{{ route('tvclub.generations.show', $generation->id) }}" class="{{ $generation->status ? 'is-active' : '' }}">
                            <span aria-hidden="true"></span>
                            <strong>{{ $generation->year }}</strong>
                            <small>{{ preg_replace('/\s*\('.$generation->year.'\)\s*$/', '', $generation->name) }}</small>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="pub-section tvclub-generation-active" aria-labelledby="activeGenerationTitle">
        <div class="pub-container">
            <div class="tvclub-section-heading">
                <div><div class="sec-label">Generasi Aktif</div><h2 class="sec-h2" id="activeGenerationTitle">Sedang Berkarya</h2><p class="tvclub-section-copy">Angkatan yang saat ini terlibat dalam perjalanan produksi WPiTV.</p></div>
            </div>

            @if($activeGenerations->isNotEmpty())
                <div class="tvclub-generation-active-grid {{ $activeGenerations->count() === 1 ? 'tvclub-generation-active-grid--single' : '' }}">
                    @foreach($activeGenerations as $generation)
                        <article class="tvclub-generation-feature">
                            <div class="tvclub-generation-feature__year">{{ $generation->year }}</div>
                            <div class="tvclub-generation-feature__body">
                                <div class="tvclub-generation-feature__top"><span>Generasi Berjalan</span><span class="tvclub-badge-active"><span aria-hidden="true"></span>Aktif</span></div>
                                <h3>{{ preg_replace('/\s*\('.$generation->year.'\)\s*$/', '', $generation->name) }}</h3>
                                @if(filled($generation->description))<p>{{ $generation->description }}</p>@endif
                                <div class="tvclub-generation-feature__metrics"><span><strong>{{ $generation->members_count }}</strong> Kru</span><span><strong>{{ $generation->groups_count }}</strong> Tim Produksi</span></div>
                                <a href="{{ route('tvclub.generations.show', $generation->id) }}">Lihat Generasi <span aria-hidden="true">→</span></a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="tvclub-generation-empty"><h3>Belum ada generasi aktif yang ditampilkan.</h3><p>Jejak generasi sebelumnya tetap dapat dijelajahi melalui arsip di bawah ini.</p></div>
            @endif
        </div>
    </section>

    <section class="pub-section tvclub-generation-archive" aria-labelledby="historicalGenerationTitle">
        <div class="pub-container">
            <div class="tvclub-section-heading">
                <div><div class="sec-label">Arsip Generasi</div><h2 class="sec-h2" id="historicalGenerationTitle">Jejak Perjalanan TV Club</h2><p class="tvclub-section-copy">Rekam perjalanan angkatan terdahulu, disusun dari tahun terbaru.</p></div>
            </div>

            @if($historicalGenerations->isNotEmpty())
                <ol class="tvclub-generation-timeline">
                    @foreach($historicalGenerations as $generation)
                        <li class="tvclub-generation-timeline-item">
                            <span class="tvclub-generation-timeline-item__line" aria-hidden="true"></span>
                            <div class="tvclub-generation-year">{{ $generation->year }}</div>
                            <span class="tvclub-generation-timeline-item__dot" aria-hidden="true"></span>
                            <article class="tvclub-generation-timeline-item__content">
                                <div class="tvclub-generation-timeline-item__heading"><div><span>Arsip Generasi</span><h3>{{ preg_replace('/\s*\('.$generation->year.'\)\s*$/', '', $generation->name) }}</h3></div><span class="tvclub-badge-inactive">Arsip</span></div>
                                @if(filled($generation->description))<p>{{ $generation->description }}</p>@endif
                                <div class="tvclub-generation-timeline-item__footer">
                                    <div><span><strong>{{ $generation->members_count }}</strong> Kru</span><span><strong>{{ $generation->groups_count }}</strong> Tim Produksi</span></div>
                                    <a href="{{ route('tvclub.generations.show', $generation->id) }}">Lihat Generasi <span aria-hidden="true">→</span></a>
                                </div>
                            </article>
                        </li>
                    @endforeach
                </ol>
            @else
                <div class="tvclub-generation-empty"><h3>Belum ada generasi dalam arsip.</h3><p>Arsip akan tampil ketika terdapat generasi yang sudah tidak aktif.</p></div>
            @endif
        </div>
    </section>
</div>
