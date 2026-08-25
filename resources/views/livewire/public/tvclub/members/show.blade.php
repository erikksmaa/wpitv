<div>
    <!-- HERO SECTION -->
    <section class="pub-hero !py-12 md:!py-16 border-b border-[var(--border)]" aria-labelledby="memberProfileTitle">
        <div class="hero-deco" aria-hidden="true"></div>
        <div class="pub-container relative z-10">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-xs text-[var(--text-muted)] mb-6" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}" class="hover:text-brand-blue transition-colors">Beranda</a>
                <span class="text-[var(--text-faint)]">/</span>
                <a href="{{ route('tvclub.index') }}" class="hover:text-brand-blue transition-colors">TV Club</a>
                @if($member->generation)
                    <span class="text-[var(--text-faint)]">/</span>
                    <a href="{{ route('tvclub.generations.show', $member->generation->id) }}" class="hover:text-brand-blue transition-colors">{{ $member->generation->name }}</a>
                @endif
                <span class="text-[var(--text-faint)]">/</span>
                <span class="text-brand-blue font-medium" aria-current="page">{{ $member->name }}</span>
            </nav>

            <!-- MEMBER PROFILE CARD -->
            <div class="bg-[var(--surface)] p-6 sm:p-10 rounded-2xl border border-[var(--border)] shadow-sm max-w-4xl">
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 sm:gap-8 text-center sm:text-left">
                    <!-- Avatar -->
                    <div class="tvclub-avatar-circle !w-28 !h-28 !text-2xl !mb-0 flex-shrink-0">
                        @if($member->photo_url)
                            <img src="{{ $member->photo_url }}" alt="{{ $member->name }}" loading="eager" fetchpriority="high">
                        @else
                            <span>{{ $member->initials }}</span>
                        @endif
                    </div>

                    <!-- Info -->
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 mb-2">
                            <span class="hero-eyebrow !mb-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-brand-blue" aria-hidden="true"></span>
                                Kru Broadcasting
                            </span>
                            @if($member->status)
                                <span class="tvclub-badge-active">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-600 animate-pulse" aria-hidden="true"></span>
                                    Anggota Aktif
                                </span>
                            @else
                                <span class="tvclub-badge-inactive">Demisioner</span>
                            @endif
                        </div>

                        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-[var(--text-primary)] mb-2" id="memberProfileTitle">
                            {{ $member->name }}
                        </h1>

                        @if($member->generation)
                            <p class="text-sm text-[var(--text-muted)] mb-4">
                                Bagian dari 
                                <a href="{{ route('tvclub.generations.show', $member->generation->id) }}" class="text-brand-blue dark:text-brand-cyan font-semibold hover:underline">
                                    {{ $member->generation->name }} ({{ $member->generation->year }})
                                </a>
                            </p>
                        @endif

                        <div class="flex items-center justify-center sm:justify-start gap-3 text-xs text-[var(--text-muted)]">
                            <span class="px-3 py-1.5 rounded-lg bg-[var(--surface-muted)] border border-[var(--border)] font-medium">
                                {{ $member->groups->count() }} Penugasan Kelompok
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ASSIGNMENTS SECTION -->
    <section class="pub-section" aria-labelledby="assignmentsH">
        <div class="pub-container">
            <div class="max-w-4xl mx-auto">
                <div class="flex items-end justify-between gap-5 flex-wrap mb-8">
                    <div>
                        <div class="sec-label">Penugasan Produksi</div>
                        <h2 class="sec-h2" id="assignmentsH">Kelompok &amp; Peran Kru</h2>
                    </div>
                </div>

                @if($member->groups->isNotEmpty())
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5" role="list">
                        @foreach($member->groups as $grp)
                            <div class="bg-[var(--surface)] p-6 rounded-2xl border border-[var(--border)] shadow-sm flex flex-col justify-between" role="listitem">
                                <div>
                                    <div class="flex items-center justify-between gap-2 mb-3">
                                        <span class="text-xs font-bold text-brand-blue dark:text-brand-cyan uppercase tracking-wider">
                                            {{ $grp->pivot->role ?: 'Anggota Kru' }}
                                        </span>
                                        @if($grp->status)
                                            <span class="tvclub-badge-active">Aktif</span>
                                        @else
                                            <span class="tvclub-badge-inactive">Selesai</span>
                                        @endif
                                    </div>

                                    <h3 class="font-heading font-bold text-lg text-[var(--text-primary)] mb-2">
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
                                </div>

                                @if($grp->generation)
                                    <div class="pt-3 border-t border-[var(--border)] text-xs text-[var(--text-muted)] flex items-center justify-between">
                                        <span>{{ $grp->generation->name }}</span>
                                        <a href="{{ route('tvclub.generations.show', $grp->generation->id) }}" class="text-brand-blue dark:text-brand-cyan font-semibold hover:underline">
                                            Lihat Generasi &rarr;
                                        </a>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12 px-4 bg-[var(--surface)] border border-[var(--border)] rounded-2xl">
                        <p class="text-sm text-[var(--text-muted)]">Belum ada penugasan kelompok produksi yang tercatat untuk anggota ini.</p>
                    </div>
                @endif

                <div class="mt-10 pt-6 border-t border-[var(--border)] flex justify-between items-center">
                    <a href="{{ route('tvclub.index') }}" class="pub-btn pub-btn--ghost !text-xs !py-2 !px-4 inline-flex items-center gap-2">
                        &larr; Kembali ke TV Club
                    </a>
                    @if($member->generation)
                        <a href="{{ route('tvclub.generations.show', $member->generation->id) }}" class="text-xs text-brand-blue dark:text-brand-cyan font-semibold hover:underline">
                            Lihat {{ $member->generation->name }} &rarr;
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
