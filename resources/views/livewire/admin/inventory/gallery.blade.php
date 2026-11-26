<div class="p-3 rounded border mt-2" aria-label="Galeri {{ $name }}">
    <h3 class="h6">Galeri {{ $name }}</h3>
    <div class="row g-2">
        @forelse($gallery as $photo)
            @php($url = route('admin.inventory.photos', ['kind'=>$detailKind,'photo'=>$photo->id]))
            <div class="col-6 col-sm-4 col-xl-3" wire:key="inventory-photo-{{ $photo->id }}" x-data>
                <button type="button" class="btn p-0 w-100" x-on:click="$refs.preview.showModal()" aria-label="Perbesar foto {{ $loop->iteration }}">
                    <img src="{{ $url }}" alt="Foto {{ $name }}" loading="lazy" class="w-100 rounded object-fit-cover" style="aspect-ratio:4/3">
                </button>
                <small class="text-muted">{{ \App\Services\Equipment\LoanRules::wib($photo->created_at) }}</small>
                <dialog x-ref="preview" class="modal inventory-dialog" aria-label="Pratinjau foto {{ $name }}">
                    <div class="modal-dialog modal-lg"><div class="modal-content">
                        <div class="modal-header"><strong>{{ $name }}</strong><button type="button" class="btn-close" x-on:click="$refs.preview.close()" aria-label="Tutup foto"></button></div>
                        <div class="modal-body text-center"><img src="{{ $url }}" alt="Foto {{ $name }}" loading="lazy" class="img-fluid" style="max-height:70dvh"></div>
                    </div></div>
                </dialog>
            </div>
        @empty
            <p class="text-muted mb-0">Belum ada foto.</p>
        @endforelse
    </div>
    {{ $gallery->links() }}
</div>
