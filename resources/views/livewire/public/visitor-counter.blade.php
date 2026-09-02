<div class="visitor-widget" data-visitor-widget id="visitorWidget">
    @if($metrics !== null)
        <!-- EXPANDED DETAIL PANEL (opens upward, aligned right) -->
        <div class="visitor-widget__panel" id="visitorWidgetPanel" role="region" aria-labelledby="visitorWidgetHeading" aria-hidden="true" data-visitor-widget-panel>
            <header class="visitor-widget__header">
                <h2 class="visitor-widget__title" id="visitorWidgetHeading">Statistik Pengunjung</h2>
            </header>

            <dl class="visitor-widget__list">
                <div class="visitor-widget__row">
                    <dt>Hari ini</dt>
                    <dd>{{ number_format($metrics['today'], 0, ',', '.') }}</dd>
                </div>
                <div class="visitor-widget__row">
                    <dt>Kemarin</dt>
                    <dd>{{ number_format($metrics['yesterday'], 0, ',', '.') }}</dd>
                </div>
                <div class="visitor-widget__row">
                    <dt>Bulan ini</dt>
                    <dd>{{ number_format($metrics['month'], 0, ',', '.') }}</dd>
                </div>
                <div class="visitor-widget__row">
                    <dt>Tahun ini</dt>
                    <dd>{{ number_format($metrics['year'], 0, ',', '.') }}</dd>
                </div>
                <div class="visitor-widget__row visitor-widget__row--online">
                    <dt>
                        <span class="visitor-widget__status" aria-hidden="true"></span>
                        Online
                    </dt>
                    <dd>{{ number_format($metrics['online'], 0, ',', '.') }}</dd>
                </div>
            </dl>
        </div>

        <!-- COLLAPSED MICRO PILL TRIGGER -->
        <button type="button"
                class="visitor-widget__trigger"
                id="visitorWidgetTrigger"
                aria-expanded="false"
                aria-controls="visitorWidgetPanel"
                aria-label="Statistik Pengunjung: {{ number_format($metrics['today'], 0, ',', '.') }} Hari ini. Klik untuk melihat rincian."
                data-visitor-widget-trigger>
            <span class="visitor-widget__icon-wrap" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
            </span>
            <span class="visitor-widget__count">
                <strong>{{ number_format($metrics['today'], 0, ',', '.') }}</strong>
                <small>Hari ini</small>
            </span>
            <span class="visitor-widget__chevron" aria-hidden="true">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m18 15-6-6-6 6"/>
                </svg>
            </span>
        </button>
    @endif
</div>
