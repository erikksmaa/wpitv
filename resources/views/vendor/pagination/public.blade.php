@if ($paginator->hasPages())
    <nav class="public-pagination" role="navigation" aria-label="Navigasi halaman">
        <p class="public-pagination__summary">
            Menampilkan <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong>
            dari <strong>{{ $paginator->total() }}</strong> hasil
        </p>
        <div class="public-pagination__controls">
            @if ($paginator->onFirstPage())
                <span class="public-pagination__step is-disabled" aria-disabled="true">← <span>Sebelumnya</span></span>
            @else
                <button type="button" class="public-pagination__step" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" rel="prev">← <span>Sebelumnya</span></button>
            @endif
            <span class="public-pagination__mobile-status">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</span>
            <div class="public-pagination__pages" aria-label="Nomor halaman">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="public-pagination__ellipsis" aria-hidden="true">{{ $element }}</span>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="public-pagination__page is-current" aria-current="page">{{ $page }}</span>
                            @else
                                <button type="button" class="public-pagination__page" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')">{{ $page }}</button>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>
            @if ($paginator->hasMorePages())
                <button type="button" class="public-pagination__step" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" rel="next"><span>Berikutnya</span> →</button>
            @else
                <span class="public-pagination__step is-disabled" aria-disabled="true"><span>Berikutnya</span> →</span>
            @endif
        </div>
    </nav>
@endif
