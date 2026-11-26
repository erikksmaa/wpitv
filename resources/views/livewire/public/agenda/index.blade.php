<div class="public-agenda-page">
    <section class="pub-hero !py-12 md:!py-16 border-b border-[var(--border)]" aria-labelledby="agendaHeroTitle">
        <div class="hero-deco" aria-hidden="true"></div>
        <div class="pub-container relative z-10">
            <nav class="flex items-center gap-2 text-xs text-[var(--text-muted)] mb-4" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}" class="hover:text-brand-blue transition-colors">Beranda</a>
                <span class="text-[var(--text-faint)]" aria-hidden="true">/</span>
                <span class="text-brand-blue font-medium" aria-current="page">Agenda</span>
            </nav>

            <div class="max-w-2xl">
                <div class="hero-eyebrow !mb-3">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-blue" aria-hidden="true"></span>
                    Kalender Publik WPiTV
                </div>
                <h1 class="hero-title !text-3xl md:!text-4xl !mb-3" id="agendaHeroTitle">
                    Agenda WPiTV
                </h1>
                <p class="text-sm md:text-base text-[var(--text-muted)] leading-relaxed mb-6">
                    Ikuti jadwal produksi, peliputan, siaran, dan kegiatan publik WPiTV.
                </p>
                <div class="flex items-center gap-3 flex-wrap">
                    <a href="#agenda-terdekat" class="pub-btn pub-btn--primary">Lihat Agenda</a>
                </div>
            </div>
        </div>
    </section>

    <section class="pub-section" id="agenda-terdekat">
        <div class="pub-container">
            <div class="public-agenda-heading"><div><span class="sec-label">Jadwal Publik</span><h2 class="sec-h2">Agenda Terdekat</h2><p>Menampilkan kegiatan publik hari ini dan jadwal mendatang dalam waktu WIB.</p></div></div>
            <div class="public-agenda-list">
                @forelse($upcoming as $agenda)
                    <article class="public-agenda-card">
                        <div class="public-agenda-date"><strong>{{ $agenda->agenda_date->format('d') }}</strong><span>{{ $agenda->agenda_date->locale('id')->translatedFormat('M') }}</span><small>{{ $agenda->agenda_date->format('Y') }}</small></div>
                        <div class="public-agenda-card-body"><span class="public-agenda-type">{{ $agenda->type_label }}</span><h3>{{ $agenda->title }}</h3>@if($agenda->liveStream)<a class="pub-btn pub-btn--primary" href="{{ route('live.show', $agenda->liveStream->id) }}">Tonton Siaran</a>@endif<div class="public-agenda-meta"><span><i class="bi bi-clock"></i>{{ $agenda->time_label }}</span><span><i class="bi bi-geo-alt"></i>{{ $agenda->location ?: 'Lokasi menyusul' }}</span></div>@if($agenda->program)<a href="{{ route('program.show',$agenda->program->slug) }}" class="public-agenda-program">Program: {{ $agenda->program->name }} <i class="bi bi-arrow-up-right"></i></a>@endif</div>
                    </article>
                @empty
                    <div class="public-agenda-empty"><i class="bi bi-calendar2-heart"></i><h3>Belum ada agenda mendatang.</h3><p>Jadwal kegiatan WPiTV berikutnya akan ditampilkan di sini.</p></div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="pub-section pub-section--muted public-agenda-calendar-section">
        <div class="pub-container">
            <div class="public-agenda-shell">
                <div class="public-agenda-shell-header">
                    <span class="public-agenda-shell-eyebrow">Kalender Agenda</span>
                    <div class="public-agenda-shell-titlerow">
                        <button type="button" wire:click="previousMonth" class="public-agenda-nav-btn" aria-label="Bulan sebelumnya">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m15 18-6-6 6-6" /></svg>
                        </button>
                        <h2>{{ $monthStart->locale('id')->translatedFormat('F Y') }}</h2>
                        <button type="button" wire:click="nextMonth" class="public-agenda-nav-btn" aria-label="Bulan berikutnya">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m9 6 6 6-6 6" /></svg>
                        </button>
                    </div>
                    <button wire:click="currentMonth" class="public-agenda-current">Kembali ke Bulan Ini</button>
                </div>

                <div class="public-agenda-calendar">
                    @foreach(['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $weekday)
                        <div class="public-agenda-weekday {{ $loop->index >= 5 ? 'is-weekend-hdr' : '' }}">{{ $weekday }}</div>
                    @endforeach

                    @foreach($days as $day)
                        @php($items = $calendarAgendas->get($day->format('Y-m-d'), collect()))
                        <div class="public-agenda-day{{ $day->month !== $monthStart->month ? ' is-outside' : '' }}{{ $day->isToday() ? ' is-today' : '' }}{{ $day->isWeekend() ? ' is-weekend' : '' }}{{ $items->isNotEmpty() ? ' has-agenda' : '' }}">
                            <div class="public-agenda-day__header">
                                <span class="public-agenda-day__num">{{ $day->day }}</span>
                                @if($items->isNotEmpty() && $day->month === $monthStart->month)
                                    <span class="public-agenda-dot" aria-hidden="true"></span>
                                @endif
                            </div>
                            @if($day->isToday())
                                <span class="public-agenda-today-label">Hari ini</span>
                            @endif
                            <div class="public-agenda-chips">
                                @foreach($items->take(2) as $agenda)
                                    <div class="public-agenda-chip" title="{{ $agenda->title }}">
                                        <span class="public-agenda-chip__time">{{ $agenda->start_time ? substr($agenda->start_time,0,5) : 'Agenda' }}</span>
                                        <span class="public-agenda-chip__title">{{ $agenda->title }}</span>
                                    </div>
                                @endforeach
                                @if($items->count() > 2)
                                    <span class="public-agenda-more">+{{ $items->count()-2 }} agenda lainnya</span>
                                @endif
                            </div>
                            @if($items->isNotEmpty())
                                <span class="public-agenda-mobile-badge" aria-hidden="true">{{ $items->count() }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            <p class="public-agenda-mobile-note">Pada layar kecil, angka di setiap tanggal menandakan jumlah agenda. Lihat daftar Agenda Terdekat di atas untuk rincian lengkapnya.</p>
        </div>
    </section>
</div>
