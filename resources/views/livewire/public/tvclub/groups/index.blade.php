<div class="tvclub-production-page">
    <section class="tvclub-production-hero" aria-labelledby="productionGroupsTitle">
        <div class="tvclub-production-hero__network" aria-hidden="true">
            <span>Program</span><i></i><span>Tim</span><i></i><span>Kru</span>
        </div>
        <div class="pub-container">
            <nav class="tvclub-production-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}">Beranda</a><span aria-hidden="true">/</span>
                <a href="{{ route('tvclub.index') }}">TV Club</a><span aria-hidden="true">/</span>
                <span aria-current="page">Kelompok Produksi</span>
            </nav>
            <div class="tvclub-production-hero__copy">
                <span class="tvclub-production-eyebrow"><i aria-hidden="true"></i>Tim di Balik Program</span>
                <h1 id="productionGroupsTitle">Kelompok Produksi WPiTV</h1>
                <p>Kenali tim mahasiswa yang bekerja di balik program, berita, dan tayangan WPiTV. Setiap kelompok menghubungkan kru TV Club, generasi, dan program yang mereka kerjakan.</p>
            </div>
        </div>
    </section>

    <section class="pub-section tvclub-production-page__content" aria-labelledby="productionGroupsListTitle">
        <div class="pub-container">
            <div class="tvclub-production-heading">
                <div><span>Organisasi Produksi</span><h2 id="productionGroupsListTitle">Tim Produksi</h2><p>Kelompok yang menghubungkan kru TV Club dengan program yang mereka kerjakan.</p></div>
                <strong>{{ $groups->count() }} Tim Produksi</strong>
            </div>

            @if($groups->isNotEmpty())
                <div class="tvclub-production-grid" role="list">
                    @foreach($groups as $group)
                        @php
                            $generationName = $group->generation?->name;
                            $generationYear = $group->generation?->year;
                            $generationLabel = $generationName;
                            if ($generationName && $generationYear && ! str_contains($generationName, (string) $generationYear)) {
                                $generationLabel .= ' · '.$generationYear;
                            }
                            $desktopOverflow = max($group->members_count - 4, 0);
                            $mobileOverflow = max($group->members_count - 3, 0);
                        @endphp
                        <article class="tvclub-production-card {{ $group->status ? 'is-active' : 'is-archive' }}" role="listitem">
                            @if($group->program)
                                <div class="tvclub-production-program">
                                    <span class="tvclub-production-program__image"><img src="{{ $group->program->image_url }}" alt="Sampul program {{ $group->program->name }}" loading="lazy"></span>
                                    <span class="tvclub-production-program__identity"><small>Program WPiTV</small><strong>{{ $group->program->name }}</strong></span>
                                    <span class="tvclub-production-card__status"><i aria-hidden="true"></i>{{ $group->status ? 'Aktif' : 'Arsip' }}</span>
                                </div>
                            @else
                                <div class="tvclub-production-card__status-row"><span class="tvclub-production-card__status"><i aria-hidden="true"></i>{{ $group->status ? 'Aktif' : 'Arsip' }}</span></div>
                            @endif

                            <div class="tvclub-production-card__body">
                                <h3>{{ $group->name }}</h3>
                                @if($generationLabel)
                                    <a href="{{ route('tvclub.generations.show', $group->generation->id) }}" class="tvclub-production-generation" aria-label="Lihat {{ $generationLabel }}"><span aria-hidden="true">◎</span>{{ $generationLabel }}</a>
                                @endif
                                @if(filled($group->description))<p>{{ $group->description }}</p>@endif
                            </div>

                            <div class="tvclub-production-crew">
                                <div class="tvclub-production-crew__heading"><span>Kru dalam Tim</span><strong>{{ $group->members_count }} Kru</strong></div>
                                @if($group->members->isNotEmpty())
                                    <div class="tvclub-production-crew__avatars" aria-label="Daftar kru {{ $group->name }}">
                                        @foreach($group->members->take(4) as $member)
                                            @php($memberRole = filled(trim((string) $member->pivot?->role)) ? trim($member->pivot->role) : 'Anggota Kru')
                                            <a href="{{ route('tvclub.members.show', $member->id) }}" class="tvclub-production-avatar {{ $loop->iteration === 4 ? 'is-fourth' : '' }}" aria-label="{{ $member->name }}, {{ $memberRole }}" title="{{ $member->name }} · {{ $memberRole }}">
                                                @if($member->photo_url)<img src="{{ $member->photo_url }}" alt="Foto {{ $member->name }}" loading="lazy">@else<span aria-hidden="true">{{ $member->initials }}</span>@endif
                                            </a>
                                        @endforeach
                                        @if($desktopOverflow > 0)<span class="tvclub-production-avatar tvclub-production-avatar--more is-desktop" aria-label="{{ $desktopOverflow }} kru lainnya">+{{ $desktopOverflow }}</span>@endif
                                        @if($mobileOverflow > 0)<span class="tvclub-production-avatar tvclub-production-avatar--more is-mobile" aria-label="{{ $mobileOverflow }} kru lainnya">+{{ $mobileOverflow }}</span>@endif
                                    </div>
                                @else
                                    <p class="tvclub-production-crew__empty">Belum ada kru tercatat</p>
                                @endif
                            </div>

                            <div class="tvclub-production-actions">
                                @if($group->program && $group->program->status)<a href="{{ route('program.show', $group->program->slug) }}">Lihat Program <span aria-hidden="true">→</span></a>@endif
                                @if($group->generation)<a href="{{ route('tvclub.generations.show', $group->generation->id) }}">Lihat Generasi <span aria-hidden="true">→</span></a>@endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="tvclub-production-empty" role="status"><span aria-hidden="true">◎</span><h2>Belum ada kelompok produksi.</h2><p>Kelompok produksi TV Club akan ditampilkan di sini saat data tersedia.</p><a href="{{ route('tvclub.members.index') }}" class="pub-btn pub-btn--primary">Kenali Kru TV Club</a></div>
            @endif
        </div>
    </section>
</div>
