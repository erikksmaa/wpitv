<div class="tvclub-crew-directory">
    <section class="tvclub-crew-directory__hero" aria-labelledby="crewDirectoryTitle">
        <div class="tvclub-crew-directory__glow" aria-hidden="true"></div>
        <div class="pub-container">
            <nav class="tvclub-crew-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}">Beranda</a><span aria-hidden="true">/</span>
                <a href="{{ route('tvclub.index') }}">TV Club</a><span aria-hidden="true">/</span>
                <span aria-current="page">Kru TV Club</span>
            </nav>
            <div class="tvclub-crew-directory__intro">
                <span class="tvclub-crew-eyebrow"><i aria-hidden="true"></i>Kru TV Club</span>
                <h1 id="crewDirectoryTitle">Mereka yang Berkarya di Balik Layar</h1>
                <p>Kenali mahasiswa TV Club yang terlibat dalam produksi program, tayangan, broadcasting, jurnalistik, dan media digital WPiTV.</p>
            </div>
        </div>
    </section>

    <section class="pub-section tvclub-crew-directory__content" aria-labelledby="crewListTitle">
        <div class="pub-container">
            <div class="tvclub-crew-directory__heading">
                <div><span>Direktori Kru</span><h2 id="crewListTitle">Kenali Kru WPiTV</h2></div>
                @if($members->total() > 0)
                    <p aria-live="polite">
                        @if($search !== '' || $generationId !== '')
                            {{ $members->total() }} kru ditemukan
                        @else
                            Menampilkan {{ $members->firstItem() }}–{{ $members->lastItem() }} dari {{ $members->total() }} kru
                        @endif
                    </p>
                @endif
            </div>

            <div class="tvclub-crew-filter" aria-label="Filter direktori kru">
                <div class="tvclub-crew-filter__field tvclub-crew-filter__field--search">
                    <label for="crewSearch">Cari kru TV Club</label>
                    <span class="tvclub-crew-filter__control"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg><input id="crewSearch" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama kru..." autocomplete="off"></span>
                </div>
                <div class="tvclub-crew-filter__field">
                    <label for="crewGeneration">Generasi</label>
                    <select id="crewGeneration" wire:model.live="generationId"><option value="">Semua Generasi</option>@foreach($generations as $generation)<option value="{{ $generation->id }}">{{ $generation->name }} · {{ $generation->year }}</option>@endforeach</select>
                </div>
                @if($search !== '' || $generationId !== '')<button type="button" wire:click="resetFilters" class="tvclub-crew-filter__reset">Reset Filter</button>@endif
            </div>

            @if($members->isNotEmpty())
                <div class="tvclub-crew-grid" role="list">
                    @foreach($members as $member)
                        <article class="tvclub-crew-card" role="listitem">
                            <a href="{{ route('tvclub.members.show', $member->id) }}" aria-label="Lihat profil {{ $member->name }}">
                                <span class="tvclub-crew-photo">
                                    @if($member->photo_url)<img src="{{ $member->photo_url }}" alt="Foto {{ $member->name }}" loading="lazy">@else<span aria-hidden="true">{{ $member->initials }}</span>@endif
                                    <small class="{{ $member->status ? 'is-active' : '' }}"><i aria-hidden="true"></i>{{ $member->status ? 'Aktif' : 'Arsip' }}</small>
                                </span>
                                <span class="tvclub-crew-card__body"><strong>{{ $member->name }}</strong><span>{{ $member->generation?->name ?? 'TV Club WPiTV' }}</span><span class="tvclub-crew-card__meta">{{ $member->groups_count }} Penugasan Produksi</span><span class="tvclub-crew-card__cta">Lihat Profil <i aria-hidden="true">→</i></span></span>
                            </a>
                        </article>
                    @endforeach
                </div>

                <div class="mt-8">{{ $members->links('vendor.pagination.public') }}</div>
            @else
                <div class="tvclub-crew-empty" role="status"><span aria-hidden="true">⌕</span><h3>Belum ada kru yang ditemukan.</h3><p>Coba gunakan nama atau generasi yang berbeda.</p><button type="button" wire:click="resetFilters" class="pub-btn pub-btn--primary">Reset Filter</button></div>
            @endif
        </div>
    </section>
</div>
