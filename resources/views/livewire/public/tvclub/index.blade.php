<div>
    <!-- HERO SECTION -->
    <section class="pub-hero !py-12 md:!py-16 border-b border-[var(--border)]" aria-labelledby="tvclubHeroTitle">
        <div class="hero-deco" aria-hidden="true"></div>
        <div class="pub-container relative z-10">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-xs text-[var(--text-muted)] mb-4" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}" class="hover:text-brand-blue transition-colors">Beranda</a>
                <span class="text-[var(--text-faint)]">/</span>
                <span class="text-brand-blue font-medium" aria-current="page">TV Club</span>
            </nav>

            <div class="max-w-2xl">
                <div class="hero-eyebrow !mb-3">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-blue" aria-hidden="true"></span>
                    Di Balik Layar Siaran
                </div>
                <h1 class="hero-title !text-3xl md:!text-4xl !mb-2.5" id="tvclubHeroTitle">
                    TV Club WPiTV
                </h1>
                <p class="text-sm md:text-base text-[var(--text-muted)] leading-relaxed">
                    Kenali mahasiswa yang berada di balik produksi program, berita, dan tayangan WPiTV. TV Club merupakan wadah mahasiswa yang terlibat dalam produksi, broadcasting, jurnalistik, dan media digital.
                </p>
            </div>
        </div>
    </section>

    <!-- STATS SECTION -->
    <section class="py-8 bg-[var(--surface)] border-b border-[var(--border)]" aria-label="Statistik TV Club">
        <div class="pub-container">
            <div class="tvclub-stats-grid">
                <div class="p-5 rounded-2xl bg-[var(--bg)] border border-[var(--border)] flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-brand-blue/10 text-brand-blue flex items-center justify-center font-bold text-xl flex-shrink-0" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div>
                        <div class="font-heading font-extrabold text-2xl text-[var(--text-primary)] leading-none mb-1">
                            {{ $stats['generations'] }}
                        </div>
                        <div class="text-xs text-[var(--text-muted)] font-medium">Generasi TV Club</div>
                    </div>
                </div>

                <div class="p-5 rounded-2xl bg-[var(--bg)] border border-[var(--border)] flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-brand-blue/10 text-brand-blue flex items-center justify-center font-bold text-xl flex-shrink-0" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                    <div>
                        <div class="font-heading font-extrabold text-2xl text-[var(--text-primary)] leading-none mb-1">
                            {{ $stats['members'] }}
                        </div>
                        <div class="text-xs text-[var(--text-muted)] font-medium">Anggota Terdaftar</div>
                    </div>
                </div>

                <div class="p-5 rounded-2xl bg-[var(--bg)] border border-[var(--border)] flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-brand-blue/10 text-brand-blue flex items-center justify-center font-bold text-xl flex-shrink-0" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                    </div>
                    <div>
                        <div class="font-heading font-extrabold text-2xl text-[var(--text-primary)] leading-none mb-1">
                            {{ $stats['groups'] }}
                        </div>
                        <div class="text-xs text-[var(--text-muted)] font-medium">Kelompok Produksi</div>
                    </div>
                </div>

                <div class="p-5 rounded-2xl bg-[var(--bg)] border border-[var(--border)] flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-brand-blue/10 text-brand-blue flex items-center justify-center font-bold text-xl flex-shrink-0" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4"/><path d="m21 2-9.6 9.6"/><circle cx="7.5" cy="15.5" r="5.5"/></svg>
                    </div>
                    <div>
                        <div class="font-heading font-extrabold text-2xl text-[var(--text-primary)] leading-none mb-1">
                            {{ $stats['assignments'] }}
                        </div>
                        <div class="text-xs text-[var(--text-muted)] font-medium">Penugasan Kru</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ACTIVE GENERATIONS SECTION -->
    @if($activeGenerations->isNotEmpty())
        <section class="pub-section bg-[var(--surface-muted)] border-b border-[var(--border)]" aria-labelledby="activeGenH">
            <div class="pub-container">
                <div class="flex items-end justify-between gap-5 flex-wrap mb-8">
                    <div>
                        <div class="sec-label">Generasi Berjalan</div>
                        <h2 class="sec-h2" id="activeGenH">Generasi Aktif WPiTV</h2>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($activeGenerations as $gen)
                        <div class="bg-[var(--surface)] p-6 sm:p-8 rounded-2xl border border-[var(--border)] shadow-sm flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between gap-3 mb-3">
                                    <span class="text-xs font-semibold text-brand-blue dark:text-brand-cyan tracking-wider uppercase">
                                        Tahun {{ $gen->year }}
                                    </span>
                                    <span class="tvclub-badge-active">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-600 animate-pulse" aria-hidden="true"></span>
                                        Aktif
                                    </span>
                                </div>
                                <h3 class="font-heading font-extrabold text-xl sm:text-2xl text-[var(--text-primary)] mb-3">
                                    {{ $gen->name }}
                                </h3>
                                <p class="text-sm text-[var(--text-secondary)] leading-relaxed mb-6">
                                    {{ $gen->description ?: 'Generasi aktif yang sedang menjalankan program siaran dan produksi WPiTV saat ini.' }}
                                </p>
                            </div>

                            <div class="pt-5 border-t border-[var(--border)] flex items-center justify-between flex-wrap gap-4">
                                <div class="flex items-center gap-4 text-xs text-[var(--text-muted)]">
                                    <span class="flex items-center gap-1.5">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                        {{ $gen->members_count }} Anggota
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/></svg>
                                        {{ $gen->groups_count }} Kelompok
                                    </span>
                                </div>
                                <a href="{{ route('tvclub.generations.show', $gen->id) }}" class="pub-btn pub-btn--primary !text-xs !py-2 !px-4">
                                    Lihat Anggota &amp; Kelompok &rarr;
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <!-- MEMBER DIRECTORY SECTION -->
    <section class="pub-section" id="anggota" aria-labelledby="membersDirH">
        <div class="pub-container">
            <div class="flex items-end justify-between gap-5 flex-wrap mb-8">
                <div>
                    <div class="sec-label">Direktori Kru</div>
                    <h2 class="sec-h2" id="membersDirH">Anggota TV Club</h2>
                </div>
            </div>

            <!-- CONTROLS & FILTER -->
            <div class="bg-[var(--surface)] p-4 sm:p-5 rounded-2xl border border-[var(--border)] shadow-sm mb-8 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3.5 items-center">
                    <!-- Search Input -->
                    <div class="md:col-span-8 relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[var(--text-faint)]">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        </div>
                        <input 
                            type="text" 
                            wire:model.live.debounce.300ms="search"
                            placeholder="Cari nama kru TV Club..." 
                            class="w-full pl-10 pr-4 py-2.5 bg-[var(--bg)] border border-[var(--border)] rounded-lg text-sm text-[var(--text-primary)] placeholder-[var(--text-faint)] focus:outline-none focus:border-brand-blue transition-colors"
                            aria-label="Cari nama anggota"
                        >
                    </div>

                    <!-- Generation Filter -->
                    <div class="md:col-span-4">
                        <select 
                            wire:model.live="generationId"
                            class="w-full bg-[var(--bg)] border border-[var(--border)] text-[var(--text-primary)] text-sm rounded-lg px-3.5 py-2.5 focus:outline-none focus:border-brand-blue cursor-pointer"
                            aria-label="Filter Generasi"
                        >
                            <option value="">Semua Generasi</option>
                            @foreach($allGenerations as $g)
                                <option value="{{ $g->id }}">{{ $g->name }} ({{ $g->year }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if($search !== '' || $generationId !== '')
                    <div class="flex items-center justify-between pt-3 border-t border-[var(--border)] text-xs text-[var(--text-muted)]">
                        <span>Menampilkan hasil filter anggota</span>
                        <button 
                            type="button" 
                            wire:click="resetFilters" 
                            class="text-brand-blue dark:text-brand-cyan hover:underline font-semibold"
                        >
                            Reset Semua Filter
                        </button>
                    </div>
                @endif
            </div>

            <!-- MEMBERS GRID -->
            @if($members->isNotEmpty())
                <div class="tvclub-member-grid mb-10" role="list">
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
                                    {{ $m->generation?->name ?? 'TV Club' }}
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

                <!-- PAGINATION -->
                <div class="mt-8">
                    {{ $members->links() }}
                </div>
            @else
                <div class="text-center py-16 px-4 bg-[var(--surface)] border border-[var(--border)] rounded-2xl max-w-lg mx-auto">
                    <div class="w-16 h-16 rounded-full bg-brand-blue/10 text-brand-blue flex items-center justify-center mx-auto mb-4">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <h3 class="font-heading font-bold text-lg text-[var(--text-primary)] mb-1">
                        Tidak ada anggota yang ditemukan.
                    </h3>
                    <p class="text-sm text-[var(--text-muted)] max-w-xs mx-auto mb-6">
                        Coba sesuaikan kata kunci pencarian atau filter generasi Anda.
                    </p>
                    <button 
                        type="button" 
                        wire:click="resetFilters" 
                        class="pub-btn pub-btn--primary !text-xs !py-2 !px-4"
                    >
                        Reset Filter
                    </button>
                </div>
            @endif
        </div>
    </section>

    <!-- PRODUCTION GROUPS SECTION -->
    @if($productionGroups->isNotEmpty())
        <section class="pub-section pub-section--navy relative overflow-hidden" id="kelompok" aria-labelledby="groupsH">
            <div class="pub-container">
                <div class="flex items-end justify-between gap-5 flex-wrap mb-8">
                    <div>
                        <div class="sec-label sec-label--navy">Divisi &amp; Tim</div>
                        <h2 class="sec-h2 !text-[#EDF4FF]" id="groupsH">Kelompok Produksi</h2>
                    </div>
                </div>

                <div class="tvclub-group-grid" role="list">
                    @foreach($productionGroups as $grp)
                        <div class="tvclub-group-card bg-white/5 border-white/10 text-white" role="listitem">
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="text-[11px] font-semibold text-brand-cyan tracking-wider uppercase">
                                    {{ $grp->generation?->name ?? 'TV Club' }}
                                </span>
                                @if($grp->status)
                                    <span class="tvclub-badge-active !bg-green-500/20 !text-green-400 !border-green-500/30">Aktif</span>
                                @else
                                    <span class="tvclub-badge-inactive !text-white/40 !border-white/10">Selesai</span>
                                @endif
                            </div>

                            <h3 class="font-heading font-bold text-base text-white mb-2">
                                {{ $grp->name }}
                            </h3>

                            @if($grp->program)
                                <div class="mb-3">
                                    <span class="text-[10px] text-white/50 block">Program Terkait:</span>
                                    <a href="{{ route('program.show', $grp->program->slug) }}" class="text-xs text-brand-cyan hover:underline font-semibold inline-flex items-center gap-1">
                                        {{ $grp->program->name }} &rarr;
                                    </a>
                                </div>
                            @endif

                            @if($grp->description)
                                <p class="text-xs text-white/65 leading-relaxed line-clamp-3 mb-4">
                                    {{ $grp->description }}
                                </p>
                            @endif

                            <div class="mt-auto pt-4 border-t border-white/10 flex items-center justify-between text-xs text-white/55">
                                <span>{{ $grp->members_count }} Anggota Kru</span>
                                @if($grp->generation)
                                    <a href="{{ route('tvclub.generations.show', $grp->generation->id) }}" class="text-brand-cyan hover:underline font-semibold">
                                        Lihat Tim &rarr;
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <!-- GENERATION HISTORY SECTION -->
    <section class="pub-section" id="generasi" aria-labelledby="historyH">
        <div class="pub-container">
            <div class="flex items-end justify-between gap-5 flex-wrap mb-8">
                <div>
                    <div class="sec-label">Arsip &amp; Riwayat</div>
                    <h2 class="sec-h2" id="historyH">Riwayat Generasi TV Club</h2>
                </div>
            </div>

            <div class="tvclub-generation-grid" role="list">
                @forelse($allGenerations as $gen)
                    <a href="{{ route('tvclub.generations.show', $gen->id) }}" class="tvclub-generation-card group" role="listitem">
                        <div class="flex items-center justify-between gap-2 mb-2.5">
                            <span class="text-xs font-semibold text-brand-blue dark:text-brand-cyan">
                                Tahun {{ $gen->year }}
                            </span>
                            @if($gen->status)
                                <span class="tvclub-badge-active">Aktif</span>
                            @else
                                <span class="tvclub-badge-inactive">Demisioner</span>
                            @endif
                        </div>

                        <h3 class="font-heading font-bold text-base text-[var(--text-primary)] group-hover:text-brand-blue transition-colors mb-2">
                            {{ $gen->name }}
                        </h3>

                        <div class="mt-auto pt-3 border-t border-[var(--border)] flex items-center justify-between text-xs text-[var(--text-muted)]">
                            <span>{{ $gen->members_count }} Anggota</span>
                            <span class="text-brand-blue dark:text-brand-cyan font-semibold group-hover:underline">Detail &rarr;</span>
                        </div>
                    </a>
                @empty
                    <p class="text-sm text-[var(--text-muted)] py-4 col-span-full">Belum ada data riwayat generasi.</p>
                @endforelse
            </div>
        </div>
    </section>
</div>
