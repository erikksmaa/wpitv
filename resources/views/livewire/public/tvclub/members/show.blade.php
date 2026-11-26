@php
    $memberGenerationName = $member->generation
        ? preg_replace('/\s*\('.preg_quote((string) $member->generation->year, '/').'\)\s*$/', '', $member->generation->name)
        : null;
@endphp

<div class="tvclub-member-profile">
    <section class="tvclub-member-hero" aria-labelledby="memberProfileTitle">
        <div class="tvclub-member-hero__glow" aria-hidden="true"></div>
        <div class="pub-container">
            <nav class="tvclub-crew-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('public.home') }}">Beranda</a><span aria-hidden="true">/</span><a href="{{ route('tvclub.index') }}">TV Club</a><span aria-hidden="true">/</span><a href="{{ route('tvclub.members.index') }}">Kru TV Club</a><span aria-hidden="true">/</span><span aria-current="page">{{ $member->name }}</span></nav>
            <div class="tvclub-member-hero__grid">
                <div class="tvclub-member-hero__portrait">@if($member->photo_url)<img src="{{ $member->photo_url }}" alt="Foto {{ $member->name }}" loading="eager" fetchpriority="high">@else<span aria-hidden="true">{{ $member->initials }}</span>@endif</div>
                <div class="tvclub-member-hero__identity">
                    <span class="tvclub-crew-eyebrow"><i aria-hidden="true"></i>TV Club · Kru TV Club</span>
                    <h1 id="memberProfileTitle">{{ $member->name }}</h1>
                    <div class="tvclub-member-hero__labels">@if($member->generation)<span>{{ $memberGenerationName }} · {{ $member->generation->year }}</span>@endif<span class="{{ $member->status ? 'is-active' : '' }}"><i aria-hidden="true"></i>{{ $member->status ? 'Aktif' : 'Arsip' }}</span></div>
                    <p>Rekam jejak produksi anggota dalam tim, program, dan generasi TV Club WPiTV.</p>
                    @if($member->generation)<a href="{{ route('tvclub.generations.show', $member->generation->id) }}" class="tvclub-member-hero__cta">Lihat Generasi <span aria-hidden="true">→</span></a>@endif
                </div>
                <dl class="tvclub-member-snapshot" aria-label="Ringkasan produksi"><div><dt>Penugasan</dt><dd>{{ $member->groups->count() }}</dd><span>Tim produksi</span></div><div><dt>Program</dt><dd>{{ $programs->count() }}</dd><span>Program terkait</span></div><div><dt>Generasi</dt><dd>{{ $member->generation?->year ?? '—' }}</dd><span>{{ $memberGenerationName ?? 'Belum tercatat' }}</span></div></dl>
            </div>
        </div>
    </section>

    <section class="pub-section tvclub-member-assignments" aria-labelledby="assignmentsH"><div class="pub-container">
        <div class="tvclub-member-section-heading"><div><span>Rekam Jejak Kru</span><h2 id="assignmentsH">Penugasan Produksi</h2><p>Peran dan tim produksi yang dijalankan dalam kegiatan TV Club.</p></div><strong>{{ $member->groups->count() }} Penugasan</strong></div>
        @if($member->groups->isNotEmpty())
            <div class="tvclub-member-assignments__grid" role="list">
                @foreach($member->groups as $group)
                    @php($role = filled(trim((string) $group->pivot?->role)) ? trim($group->pivot->role) : 'Anggota Kru')
                    <article class="tvclub-assignment-card" role="listitem">
                        <div class="tvclub-assignment-card__top"><span>{{ $group->program?->name ?? 'Produksi WPiTV' }}</span><small class="{{ $group->status ? 'is-active' : '' }}">{{ $group->status ? 'Aktif' : 'Arsip' }}</small></div>
                        <h3>{{ $group->name }}</h3>
                        <dl><div><dt>Peran</dt><dd>{{ $role }}</dd></div><div><dt>Generasi</dt><dd>{{ $group->generation?->name ?? $member->generation?->name ?? 'TV Club WPiTV' }}</dd></div></dl>
                        <div class="tvclub-assignment-card__footer"><span>{{ $group->members_count }} Kru dalam Tim</span>@if($group->program)<a href="{{ route('program.show', $group->program->slug) }}">Lihat Program <span aria-hidden="true">→</span></a>@endif</div>
                    </article>
                @endforeach
            </div>
        @else<div class="tvclub-member-assignments__empty"><span aria-hidden="true">◎</span><p>Belum ada penugasan produksi yang tercatat.</p></div>@endif
    </div></section>

    @if($programs->isNotEmpty())
        <section class="pub-section tvclub-member-programs" aria-labelledby="memberProgramsH"><div class="pub-container">
            <div class="tvclub-member-section-heading"><div><span>Portofolio Program</span><h2 id="memberProgramsH">Program yang Dikerjakan</h2><p>Program WPiTV yang terhubung dengan penugasan produksi anggota ini.</p></div></div>
            <div class="tvclub-member-programs__grid" role="list">@foreach($programs as $program)<article class="tvclub-member-program-card" role="listitem"><a href="{{ route('program.show', $program->slug) }}" aria-label="Lihat program {{ $program->name }}"><span class="tvclub-member-program-card__image"><img src="{{ $program->image_url }}" alt="Sampul program {{ $program->name }}" loading="lazy"></span><span class="tvclub-member-program-card__body"><small>Program WPiTV</small><strong>{{ $program->name }}</strong>@if($program->description)<span>{{ Str::limit(strip_tags($program->description), 95) }}</span>@endif<i>Lihat Program <b aria-hidden="true">→</b></i></span></a></article>@endforeach</div>
        </div></section>
    @endif

    @if($member->generation)
        <section class="pub-section tvclub-member-generation" aria-labelledby="memberGenerationH"><div class="pub-container"><div class="tvclub-member-generation__card">
            <div><span>Komunitas &amp; Regenerasi</span><h2 id="memberGenerationH">Bagian dari {{ $memberGenerationName }}</h2><p>{{ $member->generation->year }} · {{ $member->generation->status ? 'Generasi Aktif' : 'Arsip Generasi' }}</p></div>
            <dl><div><dt>Kru</dt><dd>{{ $member->generation->members_count }}</dd></div><div><dt>Tim Produksi</dt><dd>{{ $member->generation->groups_count }}</dd></div></dl>
            <a href="{{ route('tvclub.generations.show', $member->generation->id) }}">Lihat Generasi <span aria-hidden="true">→</span></a>
        </div></div></section>
    @endif

    @if($relatedCrew->isNotEmpty())
        <section class="pub-section tvclub-related-crew" aria-labelledby="relatedCrewH"><div class="pub-container">
            <div class="tvclub-member-section-heading"><div><span>Satu Generasi</span><h2 id="relatedCrewH">Kru Lain dari Generasi Ini</h2></div><a href="{{ route('tvclub.members.index', ['generationId' => $member->generation_id]) }}">Lihat Semua Kru <span aria-hidden="true">→</span></a></div>
            <div class="tvclub-related-crew__grid" role="list">@foreach($relatedCrew as $crew)<article class="tvclub-related-crew__card" role="listitem"><a href="{{ route('tvclub.members.show', $crew->id) }}"><span class="tvclub-related-crew__portrait">@if($crew->photo_url)<img src="{{ $crew->photo_url }}" alt="Foto {{ $crew->name }}" loading="lazy">@else<span aria-hidden="true">{{ $crew->initials }}</span>@endif</span><span><strong>{{ $crew->name }}</strong><small>{{ $crew->groups_count }} Penugasan Produksi</small></span><i aria-hidden="true">→</i></a></article>@endforeach</div>
        </div></section>
    @endif

    <div class="tvclub-member-profile__back"><div class="pub-container"><a href="{{ route('tvclub.members.index') }}">← Kembali ke Kru TV Club</a></div></div>
</div>
