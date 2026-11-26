@props(['variant' => 'footer'])

@php($accounts = app(\App\Services\PublicSocialAccounts::class)->all())

@if($accounts->isNotEmpty())
    @if($variant === 'about')
        <div class="pub-social-about mt-8 pt-7 border-t border-[var(--border)] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-brand-blue dark:text-brand-cyan mb-1">Kanal Resmi WPiTV</p>
                <p class="text-sm text-[var(--text-muted)] m-0">Ikuti tayangan, berita, dan kegiatan kampus melalui media sosial resmi.</p>
            </div>
            <div class="flex flex-wrap gap-2.5" aria-label="Media sosial resmi WPiTV">
                @foreach($accounts as $account)
                    <a href="{{ $account->url }}" target="_blank" rel="noopener noreferrer" class="pub-social-pill" aria-label="{{ $account->aria_label }}" title="{{ $account->aria_label }}">
                        @switch($account->platform)
                            @case('instagram')
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
                                @break
                            @case('youtube')
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.96C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.96A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="currentColor" stroke="none"/></svg>
                                @break
                            @case('tiktok')
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.32 6.32 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34v-7.1a8.17 8.17 0 0 0 4.77 1.52V6.28a4.85 4.85 0 0 1-1-.41z"/></svg>
                                @break
                            @case('facebook')
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                                @break
                        @endswitch
                        <span>{{ $account->has_multiple_on_platform ? $account->short_label : $account->platform_label }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @else
        <div class="flex flex-wrap gap-2.5" aria-label="Media sosial WPiTV">
            @foreach($accounts as $account)
                <a href="{{ $account->url }}" target="_blank" rel="noopener noreferrer"
                   class="footer-soc {{ $account->has_multiple_on_platform ? 'footer-soc--labeled' : '' }}"
                   aria-label="{{ $account->aria_label }}"
                   title="{{ $account->aria_label }}">
                    @switch($account->platform)
                        @case('instagram')
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
                            @break
                        @case('youtube')
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.96C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.96A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="currentColor" stroke="none"/></svg>
                            @break
                        @case('tiktok')
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.32 6.32 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34v-7.1a8.17 8.17 0 0 0 4.77 1.52V6.28a4.85 4.85 0 0 1-1-.41z"/></svg>
                            @break
                        @case('facebook')
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                            @break
                    @endswitch
                    @if($account->has_multiple_on_platform)
                        <span class="footer-soc__label">{{ $account->short_label }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
@endif
