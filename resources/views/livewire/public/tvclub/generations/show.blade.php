@php($generationDisplayName = preg_replace('/\s*\('.$generation->year.'\)\s*$/', '', $generation->name))

<div class="tvclub-generation-detail">
    <section class="tvclub-generation-hero tvclub-generation-hero--detail" aria-labelledby="genDetailTitle">
        <div class="tvclub-generation-hero__orbit" aria-hidden="true"></div>
        <div class="pub-container">
            <nav class="tvclub-generation-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}">Beranda</a><span aria-hidden="true">/</span>
                <a href="{{ route('tvclub.index') }}">TV Club</a><span aria-hidden="true">/</span>
                <a href="{{ route('tvclub.generations.index') }}">Generasi</a><span aria-hidden="true">/</span>
                <span aria-current="page">{{ $generationDisplayName }}</span>
            </nav>

            <div class="tvclub-generation-detail-hero">
                <div class="tvclub-generation-detail-hero__year" aria-label="Tahun {{ $generation->year }}">{{ $generation->year }}</div>
                <div class="tvclub-generation-detail-hero__content">
                    <div class="tvclub-generation-eyebrow"><span aria-hidden="true"></span>TV Club · Generasi</div>
                    <div class="tvclub-generation-detail-hero__status">
                        @if($generation->status)
                            <span class="tvclub-badge-active"><span aria-hidden="true"></span>Aktif</span>
                        @else
                            <span class="tvclub-badge-inactive">Arsip</span>
                        @endif
                    </div>
                    <h1 id="genDetailTitle">{{ $generationDisplayName }}</h1>
                    @if(filled($generation->description))<p>{{ $generation->description }}</p>@endif
                </div>
            </div>
        </div>
    </section>

    <section class="tvclub-generation-snapshot" aria-labelledby="snapshotH">
        <div class="pub-container tvclub-generation-snapshot__inner">
            <div><span>Generasi dalam Angka</span><h2 id="snapshotH">Jejak Produksi {{ $generationDisplayName }}</h2></div>
            <dl>
                <div><dt>Kru</dt><dd>{{ $generation->members_count }}</dd></div>
                <div><dt>Tim Produksi</dt><dd>{{ $generation->groups_count }}</dd></div>
                <div><dt>Penugasan</dt><dd>{{ $assignmentCount }}</dd></div>
                <div><dt>Program Terkait</dt><dd>{{ $linkedProgramCount }}</dd></div>
            </dl>
        </div>
    </section>

    @if(!empty($organizationTree))
        <section class="pub-section public-org-section public-org-section--tvclub" id="struktur-organisasi" aria-labelledby="generationOrganizationTitle">
            <div class="pub-container">
                <div class="tvclub-section-heading"><div><div class="sec-label">Susunan Kepengurusan</div><h2 class="sec-h2" id="generationOrganizationTitle">Struktur Organisasi</h2><p class="tvclub-section-copy">Peran dan anggota yang menjalankan organisasi {{ $generationDisplayName }}.</p></div></div>
                <div class="public-org-tree-scroll"><ul class="public-org-tree public-org-tree--roots">
                    @foreach($organizationTree as $node)<x-public.organization-tree-node :node="$node" tv-club />@endforeach
                </ul></div>
            </div>
        </section>
    @endif

    <section class="pub-section tvclub-generation-members" id="anggota" aria-labelledby="membersH">
        <div class="pub-container">
            <div class="tvclub-section-heading"><div><div class="sec-label">Kru &amp; Talenta</div><h2 class="sec-h2" id="membersH">Anggota Generasi</h2><p class="tvclub-section-copy">Kenali kru yang menjadi bagian dari {{ $generationDisplayName }}.</p></div></div>

            @if($members->isNotEmpty())
                <div class="tvclub-generation-members-grid" role="list">
                    @foreach($members as $member)
                        <article class="tvclub-generation-member-card" role="listitem">
                            <a href="{{ route('tvclub.members.show', $member->id) }}" aria-label="Lihat profil {{ $member->name }}">
                                <span class="tvclub-generation-member-card__portrait">
                                    @if($member->photo_url)
                                        <img src="{{ $member->photo_url }}" alt="Foto {{ $member->name }}" loading="lazy">
                                    @else
                                        <span aria-hidden="true">{{ $member->initials }}</span>
                                    @endif
                                </span>
                                <span class="tvclub-generation-member-card__body">
                                    <span class="tvclub-generation-member-card__status {{ $member->status ? 'is-active' : '' }}"><i aria-hidden="true"></i>{{ $member->status ? 'Aktif' : 'Arsip' }}</span>
                                    <strong>{{ $member->name }}</strong>
                                    <small>{{ $member->groups_count }} Penugasan Produksi</small>
                                </span>
                                <span class="tvclub-generation-member-card__link">Lihat Profil <span aria-hidden="true">→</span></span>
                            </a>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="tvclub-generation-empty"><h3>Belum ada kru yang tercatat pada generasi ini.</h3><p>Informasi generasi tetap dapat dijelajahi melalui kelompok dan program terkait.</p></div>
            @endif
        </div>
    </section>

    <section class="pub-section tvclub-generation-groups" id="kelompok" aria-labelledby="genGroupsH">
        <div class="pub-container">
            <div class="tvclub-section-heading"><div><div class="sec-label">Struktur Produksi</div><h2 class="sec-h2" id="genGroupsH">Kelompok Produksi</h2><p class="tvclub-section-copy">Tim yang dijalankan oleh generasi ini dalam produksi WPiTV.</p></div></div>

            @if($groups->isNotEmpty())
                <div class="tvclub-generation-groups-grid" role="list">
                    @foreach($groups as $group)
                        <article class="tvclub-generation-group-card" role="listitem">
                            <div class="tvclub-generation-group-card__top"><span>Tim Produksi</span><span class="{{ $group->status ? 'tvclub-badge-active' : 'tvclub-badge-inactive' }}">@if($group->status)<span aria-hidden="true"></span>Aktif @else Arsip @endif</span></div>
                            <h3>{{ $group->name }}</h3>
                            @if(filled($group->description))<p>{{ $group->description }}</p>@endif
                            @if($group->program)
                                <a href="{{ route('program.show', $group->program->slug) }}" class="tvclub-generation-group-card__program">
                                    <span>Program Terkait</span><strong>{{ $group->program->name }}</strong><i aria-hidden="true">→</i>
                                </a>
                            @endif
                            <div class="tvclub-generation-group-card__footer"><span><strong>{{ $group->members_count }}</strong> Kru</span>@if($group->program)<span>Terhubung ke program WPiTV</span>@endif</div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="tvclub-generation-empty"><h3>Belum ada kelompok produksi yang tercatat.</h3><p>Generasi ini belum memiliki relasi kelompok produksi pada data saat ini.</p></div>
            @endif
        </div>
    </section>

    <section class="tvclub-generation-nav" aria-labelledby="generationNavH">
        <div class="pub-container">
            <h2 class="sr-only" id="generationNavH">Navigasi generasi</h2>
            <div class="tvclub-generation-nav__grid">
                @if($previousGeneration)
                    <a href="{{ route('tvclub.generations.show', $previousGeneration->id) }}" class="tvclub-generation-nav__previous"><span aria-hidden="true">←</span><span><small>Generasi Sebelumnya</small><strong>{{ preg_replace('/\s*\('.$previousGeneration->year.'\)\s*$/', '', $previousGeneration->name) }}</strong><em>{{ $previousGeneration->year }}</em></span></a>
                @else
                    <span aria-hidden="true"></span>
                @endif

                <a href="{{ route('tvclub.generations.index') }}" class="tvclub-generation-nav__all">Semua Generasi</a>

                @if($nextGeneration)
                    <a href="{{ route('tvclub.generations.show', $nextGeneration->id) }}" class="tvclub-generation-nav__next"><span><small>Generasi Berikutnya</small><strong>{{ preg_replace('/\s*\('.$nextGeneration->year.'\)\s*$/', '', $nextGeneration->name) }}</strong><em>{{ $nextGeneration->year }}</em></span><span aria-hidden="true">→</span></a>
                @else
                    <span aria-hidden="true"></span>
                @endif
            </div>
        </div>
    </section>
</div>
