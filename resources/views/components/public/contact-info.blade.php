@props(['variant' => 'footer'])

@php($contact = app(\App\Services\WebsiteSettings::class)->publicContact())

@if($contact['has_any'])
    @if($variant === 'about')
        <div class="mt-8 pt-7 border-t border-[var(--border)]">
            <p class="text-xs font-semibold uppercase tracking-wider text-brand-blue dark:text-brand-cyan mb-2">Kontak Resmi WPiTV</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-[var(--text-muted)]">
                @if($contact['address'])
                    <div class="flex items-start gap-2.5">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5 text-brand-blue dark:text-brand-cyan" aria-hidden="true">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        <p class="leading-relaxed m-0">{!! nl2br(e($contact['address'])) !!}</p>
                    </div>
                @endif

                @if($contact['email'])
                    <div class="flex items-start gap-2.5">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5 text-brand-blue dark:text-brand-cyan" aria-hidden="true">
                            <rect x="2" y="4" width="20" height="16" rx="2"/>
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                        </svg>
                        <a href="mailto:{{ $contact['email'] }}" class="text-[var(--text-primary)] hover:text-brand-blue dark:hover:text-brand-cyan transition-colors break-all">
                            {{ $contact['email'] }}
                        </a>
                    </div>
                @endif

                @if($contact['phone'] && $contact['phone_href'])
                    <div class="flex items-start gap-2.5">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5 text-brand-blue dark:text-brand-cyan" aria-hidden="true">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                        <a href="{{ $contact['phone_href'] }}" class="text-[var(--text-primary)] hover:text-brand-blue dark:hover:text-brand-cyan transition-colors">
                            {{ $contact['phone'] }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    @else
        <div>
            <h4 class="font-heading font-bold text-xs tracking-wider uppercase text-white/35 mb-4">Kontak</h4>
            <div class="space-y-2.5 text-sm text-white/60">
                @if($contact['address'])
                    <p class="leading-relaxed">{!! nl2br(e($contact['address'])) !!}</p>
                @endif
                @if($contact['email'])
                    <p>
                        <a href="mailto:{{ $contact['email'] }}" class="hover:text-white transition-colors break-all">{{ $contact['email'] }}</a>
                    </p>
                @endif
                @if($contact['phone'] && $contact['phone_href'])
                    <p>
                        <a href="{{ $contact['phone_href'] }}" class="hover:text-white transition-colors">{{ $contact['phone'] }}</a>
                    </p>
                @endif
            </div>
        </div>
    @endif
@endif
