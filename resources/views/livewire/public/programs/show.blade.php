<div>
    <!-- PROGRAM DETAIL HERO -->
    <section class="pub-hero !py-10 md:!py-14 border-b border-[var(--border)]" aria-labelledby="programTitle">
        <div class="hero-deco" aria-hidden="true"></div>
        <div class="pub-container relative z-10">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-xs text-[var(--text-muted)] mb-6 flex-wrap" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}" class="hover:text-brand-blue transition-colors">Beranda</a>
                <span class="text-[var(--text-faint)]">/</span>
                <a href="{{ route('program.index') }}" class="hover:text-brand-blue transition-colors">Program</a>
                <span class="text-[var(--text-faint)]">/</span>
                <span class="text-brand-blue font-medium truncate max-w-xs" aria-current="page">{{ $program->name }}</span>
            </nav>

            <!-- Split Hero Grid -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-8 md:gap-12 items-start">
                <!-- Left: Poster -->
                <div class="md:col-span-4 lg:col-span-3 w-full max-w-[250px] mx-auto md:max-w-none">
                    <div class="aspect-[3/4] rounded-2xl overflow-hidden bg-[var(--surface-muted)] border border-[var(--border)] shadow-md">
                        <img
                            src="{{ $program->image_url }}"
                            alt="Poster {{ $program->name }}"
                            class="w-full h-full object-cover"
                            loading="eager"
                            fetchpriority="high"
                        >
                    </div>
                </div>

                <!-- Right: Details -->
                <div class="md:col-span-8 lg:col-span-9 flex flex-col">
                    <div class="hero-eyebrow !mb-3">
                        <span class="w-1.5 h-1.5 rounded-full bg-brand-blue" aria-hidden="true"></span>
                        Program Acara
                    </div>

                    <h1 class="hero-title !text-3xl md:!text-4xl lg:!text-5xl !mb-3" id="programTitle">
                        {{ $program->name }}
                    </h1>

                    <div class="flex items-center gap-3 mb-6">
                        <span class="inline-flex items-center gap-1.5 bg-brand-blue/10 text-brand-blue dark:text-brand-cyan text-xs font-semibold px-3 py-1 rounded-full">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="5,3 19,12 5,21"/></svg>
                            {{ $videos->total() }} Tayangan
                        </span>
                    </div>

                    @if(filled($program->description))
                        <div class="prose max-w-none text-sm md:text-base text-[var(--text-secondary)] leading-relaxed mb-8">
                            <p class="whitespace-pre-line">{{ $program->description }}</p>
                        </div>
                    @endif

                    <div class="flex gap-3 flex-wrap mt-auto">
                        <a href="#daftar-tayangan" class="pub-btn pub-btn--primary">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor" aria-hidden="true"><polygon points="3,1 13,7 3,13"/></svg>
                            Lihat Tayangan
                        </a>
                        <a href="{{ route('program.index') }}" class="pub-btn pub-btn--secondary">
                            &larr; Kembali ke Program
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @include('livewire.public.live-streams.highlights')
    <!-- VIDEO LIST PER PROGRAM -->
    <section class="pub-section" id="daftar-tayangan" aria-labelledby="videoSectionTitle">
        <div class="pub-container">
            <div class="flex items-end justify-between gap-5 flex-wrap mb-8">
                <div>
                    <div class="sec-label">Daftar Tayangan</div>
                    <h2 class="sec-h2" id="videoSectionTitle">Tayangan {{ $program->name }}</h2>
                </div>
                <span class="text-xs font-medium text-[var(--text-muted)]">
                    Total {{ $videos->total() }} video
                </span>
            </div>

            @if($videos->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-10">
                    @foreach($videos as $video)
                        <article class="video-card group">
                            <a href="{{ route('video.show', $video) }}" class="block">
                                <div class="video-thumb">
                                    <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" loading="lazy">
                                    <div class="vc-play" aria-hidden="true">
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="white" class="ml-0.5"><polygon points="3,1 13,7 3,13"/></svg>
                                    </div>
                                </div>
                                <div class="font-heading font-bold text-sm text-[var(--text-primary)] leading-snug mb-1.5 line-clamp-2 group-hover:text-brand-blue transition-colors">
                                    {{ $video->title }}
                                </div>
                                <div class="text-xs text-[var(--text-muted)]">
                                    {{ $video->air_date ? $video->air_date->isoFormat('D MMMM Y') : 'Tanggal tidak tersedia' }}
                                </div>
                            </a>
                        </article>
                    @endforeach
                </div>

                <!-- PAGINATION -->
                <div class="mt-8">
                    {{ $videos->links('vendor.pagination.public') }}
                </div>
            @else
                <div class="text-center py-14 px-4 bg-[var(--surface)] border border-[var(--border)] rounded-2xl max-w-md mx-auto">
                    <div class="w-14 h-14 rounded-full bg-brand-blue/10 text-brand-blue flex items-center justify-center mx-auto mb-3.5">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polygon points="5,3 19,12 5,21"/>
                        </svg>
                    </div>
                    <h3 class="font-heading font-bold text-base text-[var(--text-primary)] mb-1">
                        Belum ada tayangan untuk program ini.
                    </h3>
                    <p class="text-xs text-[var(--text-muted)]">
                        Tayangan episode terbaru akan segera diunggah.
                    </p>
                </div>
            @endif
        </div>
    </section>

    <!-- PRODUCTION CREW -->
    @if($productionGroups->isNotEmpty())
        <section class="program-crew-section" aria-labelledby="programCrewTitle" data-program-crew>
            <div class="pub-container">
                <div class="program-crew-header">
                    <div>
                        <div class="sec-label">Di Balik Layar</div>
                        <h2 class="sec-h2" id="programCrewTitle">Tim di Balik Program</h2>
                        <p>Kenali mahasiswa TV Club yang terlibat dalam produksi {{ $program->name }}.</p>
                    </div>
                    <a href="{{ route('tvclub.index') }}" class="sec-link">
                        Kenali TV Club
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                </div>

                @if($productionGroups->count() > 1)
                    <div class="program-crew-groups" role="tablist" aria-label="Pilih kelompok produksi">
                        @foreach($productionGroups as $group)
                            @php
                                $isDefaultGroup = $defaultProductionGroup?->is($group);
                            @endphp
                            <button
                                type="button"
                                class="program-crew-group-tab {{ $isDefaultGroup ? 'is-active' : '' }}"
                                id="programCrewTab{{ $group->id }}"
                                role="tab"
                                aria-selected="{{ $isDefaultGroup ? 'true' : 'false' }}"
                                aria-controls="programCrewPanel{{ $group->id }}"
                                tabindex="{{ $isDefaultGroup ? '0' : '-1' }}"
                                data-program-crew-tab="{{ $group->id }}"
                            >
                                <span>{{ $group->generation?->name ?? $group->name }}</span>
                                @if($group->status)
                                    <span class="program-crew-group-status">Aktif</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="program-crew-panels" data-active-group="{{ $defaultProductionGroup?->id }}">
                    @foreach($productionGroups as $group)
                        @php
                            $isDefaultGroup = $defaultProductionGroup?->is($group);
                            $generationContext = $group->generation?->name;
                            if ($generationContext && $group->generation?->year && ! str_contains($generationContext, (string) $group->generation->year)) {
                                $generationContext .= ' · '.$group->generation->year;
                            }
                        @endphp
                        <div
                            class="program-crew-panel"
                            id="programCrewPanel{{ $group->id }}"
                            role="tabpanel"
                            aria-labelledby="{{ $productionGroups->count() > 1 ? 'programCrewTab'.$group->id : 'programCrewTitle' }}"
                            data-program-crew-panel="{{ $group->id }}"
                            {{ $isDefaultGroup ? '' : 'hidden' }}
                        >
                            <div class="program-crew-context">
                                <span>Kelompok Produksi</span>
                                <strong>{{ $group->name }}</strong>
                                @if($generationContext)
                                    <span>{{ $generationContext }}</span>
                                @endif
                            </div>

                            <div class="program-crew-grid" role="list">
                                @foreach($group->members as $member)
                                    @php
                                        $memberRole = filled($member->pivot?->role) ? trim($member->pivot->role) : 'Anggota Kru';
                                        $generationLabel = $group->generation?->name ?? $member->generation?->name ?? 'TV Club WPiTV';
                                    @endphp
                                    <article class="program-crew-member" role="listitem">
                                        <a href="{{ route('tvclub.members.show', $member->id) }}" aria-label="Lihat profil {{ $member->name }}">
                                            <span class="program-crew-avatar">
                                                @if($member->photo_url)
                                                    <img src="{{ $member->photo_url }}" alt="Foto {{ $member->name }}" loading="lazy">
                                                @else
                                                    <span role="img" aria-label="Inisial {{ $member->name }}">{{ $member->initials }}</span>
                                                @endif
                                            </span>
                                            <span class="program-crew-name">{{ $member->name }}</span>
                                            <span class="program-crew-role">{{ $memberRole }}</span>
                                            <span class="program-crew-generation">{{ $generationLabel }}</span>
                                            <span class="program-crew-profile">Lihat Profil <span aria-hidden="true">→</span></span>
                                        </a>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <!-- RELATED / OTHER PROGRAMS -->
    @if($otherPrograms->isNotEmpty())
        <section class="pub-section pub-section--muted border-t border-[var(--border)]" aria-labelledby="otherProgramsTitle">
            <div class="pub-container">
                <div class="flex items-end justify-between gap-5 flex-wrap mb-8">
                    <div>
                        <div class="sec-label">Eksplorasi Lainnya</div>
                        <h2 class="sec-h2" id="otherProgramsTitle">Program Lainnya</h2>
                    </div>
                    <a href="{{ route('program.index') }}" class="sec-link">
                        Lihat Semua
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                    @foreach($otherPrograms as $other)
                        <article class="bg-[var(--surface)] border border-[var(--border)] rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all duration-300 flex flex-col group hover:-translate-y-1">
                            <a href="{{ route('program.show', $other->slug) }}" class="block aspect-[3/4] overflow-hidden relative bg-[var(--surface-muted)]">
                                <img
                                    src="{{ $other->image_url }}"
                                    alt="Poster {{ $other->name }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    loading="lazy"
                                >
                                <div class="absolute bottom-2.5 left-2.5 bg-black/75 backdrop-blur-sm text-white text-[11px] font-semibold px-2.5 py-1 rounded-md">
                                    {{ $other->videos_count }} Tayangan
                                </div>
                            </a>
                            <div class="p-4 flex flex-col flex-grow">
                                <h3 class="font-heading font-bold text-sm text-[var(--text-primary)] group-hover:text-brand-blue transition-colors line-clamp-1 mb-2">
                                    <a href="{{ route('program.show', $other->slug) }}">
                                        {{ $other->name }}
                                    </a>
                                </h3>
                                <a href="{{ route('program.show', $other->slug) }}" class="text-xs font-semibold text-brand-blue dark:text-brand-cyan hover:underline mt-auto">
                                    Lihat Program &rarr;
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</div>
