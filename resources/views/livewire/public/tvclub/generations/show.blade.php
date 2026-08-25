<div>
    <!-- HERO SECTION -->
    <section class="pub-hero !py-12 md:!py-16 border-b border-[var(--border)]" aria-labelledby="genDetailTitle">
        <div class="hero-deco" aria-hidden="true"></div>
        <div class="pub-container relative z-10">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-xs text-[var(--text-muted)] mb-4" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}" class="hover:text-brand-blue transition-colors">Beranda</a>
                <span class="text-[var(--text-faint)]">/</span>
                <a href="{{ route('tvclub.index') }}" class="hover:text-brand-blue transition-colors">TV Club</a>
                <span class="text-[var(--text-faint)]">/</span>
                <span class="text-brand-blue font-medium" aria-current="page">{{ $generation->name }}</span>
            </nav>

            <div class="max-w-3xl">
                <div class="flex items-center gap-3 flex-wrap mb-3">
                    <span class="hero-eyebrow !mb-0">
                        <span class="w-1.5 h-1.5 rounded-full bg-brand-blue" aria-hidden="true"></span>
                        Tahun {{ $generation->year }}
                    </span>
                    @if($generation->status)
                        <span class="tvclub-badge-active">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-600 animate-pulse" aria-hidden="true"></span>
                            Generasi Aktif
                        </span>
                    @else
                        <span class="tvclub-badge-inactive">Demisioner / Alumni</span>
                    @endif
                </div>

                <h1 class="hero-title !text-3xl md:!text-4xl !mb-3" id="genDetailTitle">
                    {{ $generation->name }}
                </h1>

                @if($generation->description)
                    <p class="text-sm md:text-base text-[var(--text-muted)] leading-relaxed mb-6">
                        {{ $generation->description }}
                    </p>
                @endif

                <div class="flex items-center gap-4 text-xs font-semibold text-[var(--text-secondary)]">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[var(--surface)] border border-[var(--border)]">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        {{ $generation->members_count }} Anggota Terdaftar
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[var(--surface)] border border-[var(--border)]">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/></svg>
                        {{ $generation->groups_count }} Kelompok Produksi
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- MEMBERS SECTION -->
    <section class="pub-section" id="anggota" aria-labelledby="membersH">
        <div class="pub-container">
            <div class="flex items-end justify-between gap-5 flex-wrap mb-8">
                <div>
                    <div class="sec-label">Kru &amp; Talenta</div>
                    <h2 class="sec-h2" id="membersH">Daftar Anggota {{ $generation->name }}</h2>
                </div>
            </div>

            @if($members->isNotEmpty())
                <div class="tvclub-member-grid mb-12" role="list">
                    @foreach($members as $m)
                        <article role="listitem">
                            <a href="{{ route('tvclub.members.show', $m->id) }}" class="tvclub-member-card group">
                                <div class="tvclub-avatar-circle">
                                    @if($m->photo_url)
                                        <img src="{{ $m->photo_url }}" alt="{{ $m->name }}" loading="lazy">
                                    @else
                                        <span>{{ $m->initials }}</span>
                                    @endif
                                </div>

                                <h3 class="font-heading font-bold text-sm text-[var(--text-primary)] group-hover:text-brand-blue transition-colors truncate max-w-full mb-1">
                                    {{ $m->name }}
                                </h3>

                                <p class="text-xs text-[var(--text-muted)] truncate max-w-full mb-3">
                                    {{ $m->groups->first()?->pivot->role ?? 'Anggota Kru' }}
                                </p>

                                <div class="mt-auto">
                                    @if($m->status)
                                        <span class="tvclub-badge-active">Aktif</span>
                                    @else
                                        <span class="tvclub-badge-inactive">Demisioner</span>
                                    @endif
                                </div>
                            </a>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 px-4 bg-[var(--surface)] border border-[var(--border)] rounded-2xl max-w-md mx-auto mb-12">
                    <p class="text-sm text-[var(--text-muted)]">Belum ada anggota yang terdaftar pada generasi ini.</p>
                </div>
            @endif
        </div>
    </section>

    <!-- PRODUCTION GROUPS SECTION -->
    @if($groups->isNotEmpty())
        <section class="pub-section bg-[var(--surface-muted)] border-t border-[var(--border)]" id="kelompok" aria-labelledby="genGroupsH">
            <div class="pub-container">
                <div class="flex items-end justify-between gap-5 flex-wrap mb-8">
                    <div>
                        <div class="sec-label">Struktur Produksi</div>
                        <h2 class="sec-h2" id="genGroupsH">Kelompok Produksi {{ $generation->name }}</h2>
                    </div>
                </div>

                <div class="tvclub-group-grid" role="list">
                    @foreach($groups as $grp)
                        <div class="tvclub-group-card" role="listitem">
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="text-xs font-semibold text-brand-blue dark:text-brand-cyan uppercase tracking-wider">
                                    Tim Produksi
                                </span>
                                @if($grp->status)
                                    <span class="tvclub-badge-active">Aktif</span>
                                @else
                                    <span class="tvclub-badge-inactive">Selesai</span>
                                @endif
                            </div>

                            <h3 class="font-heading font-bold text-base text-[var(--text-primary)] mb-2">
                                {{ $grp->name }}
                            </h3>

                            @if($grp->program)
                                <div class="mb-3">
                                    <span class="text-[10px] text-[var(--text-faint)] block">Program Terkait:</span>
                                    <a href="{{ route('program.show', $grp->program->slug) }}" class="text-xs text-brand-blue dark:text-brand-cyan hover:underline font-semibold inline-flex items-center gap-1">
                                        {{ $grp->program->name }} &rarr;
                                    </a>
                                </div>
                            @endif

                            @if($grp->description)
                                <p class="text-xs text-[var(--text-secondary)] leading-relaxed line-clamp-3 mb-4">
                                    {{ $grp->description }}
                                </p>
                            @endif

                            <div class="mt-auto pt-3 border-t border-[var(--border)] text-xs text-[var(--text-muted)]">
                                <span>{{ $grp->members_count }} Anggota Kru</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</div>
