@php($photoUrls = $photos->map(fn ($photo) => route('admin.inventory.photos', ['kind'=>'unit', 'photo'=>$photo->id]))->values())
<div class="unit-photo-gallery mt-2" wire:key="unit-photos-{{ $unit->id }}-{{ $photos->pluck('id')->implode('-') }}"
    x-data="{ photos: @js($photoUrls), active: 0, touchX: null, move(step) { this.active = (this.active + step + this.photos.length) % this.photos.length } }">
    @if($photos->isNotEmpty())
        <div class="unit-photo-stage position-relative rounded overflow-hidden"
            x-on:touchstart.passive="touchX = $event.changedTouches[0].clientX"
            x-on:touchend.passive="if (touchX !== null && Math.abs($event.changedTouches[0].clientX - touchX) > 45) move($event.changedTouches[0].clientX < touchX ? 1 : -1); touchX = null">
            <button type="button" class="unit-photo-open border-0 p-0 w-100" x-on:click="$refs.preview.showModal()" aria-label="Perbesar foto {{ $unit->asset_code }}">
                <img x-bind:src="photos[active]" alt="Foto unit {{ $unit->asset_code }}" loading="lazy" class="unit-photo-image">
                <span class="unit-photo-zoom badge bg-dark">Klik untuk memperbesar</span>
            </button>
            @if($photos->count() > 1)
                <button type="button" class="unit-photo-prev btn btn-dark rounded-circle" x-on:click="move(-1)" aria-label="Foto sebelumnya"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                <button type="button" class="unit-photo-next btn btn-dark rounded-circle" x-on:click="move(1)" aria-label="Foto berikutnya"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
            @endif
            <span class="unit-photo-count badge bg-dark" x-text="(active + 1) + ' / ' + photos.length"></span>
        </div>
        @if($photos->count() > 1)
            <div class="d-flex gap-2 overflow-auto py-2" aria-label="Pilih foto unit {{ $unit->asset_code }}">
                @foreach($photoUrls as $photoUrl)
                    <button type="button" class="unit-photo-thumb rounded p-1 flex-shrink-0" x-bind:class="active === {{ $loop->index }} ? 'border-primary' : 'border-transparent'" x-bind:aria-pressed="active === {{ $loop->index }}" x-on:click="active = {{ $loop->index }}" aria-label="Lihat foto {{ $loop->iteration }}">
                        <img src="{{ $photoUrl }}" alt="" loading="lazy" class="rounded object-fit-cover" width="40" height="30">
                    </button>
                @endforeach
            </div>
        @endif
        <dialog x-ref="preview" wire:ignore.self class="unit-lightbox" aria-label="Foto unit {{ $unit->asset_code }}"
            x-on:keydown.right.prevent="move(1)" x-on:keydown.left.prevent="move(-1)" x-on:click.self="$refs.preview.close()">
            <div class="unit-lightbox-frame">
                <img x-bind:src="photos[active]" alt="Foto unit {{ $unit->asset_code }}" class="unit-lightbox-image">
                <div class="unit-lightbox-bar">
                    <strong>{{ $unit->asset_code }}</strong>
                    @if($photos->count() > 1)
                        <button type="button" x-on:click="move(-1)" aria-label="Foto sebelumnya"><i class="bi bi-chevron-left"></i></button>
                        <small aria-live="polite" x-text="(active + 1) + ' / ' + photos.length"></small>
                        <button type="button" x-on:click="move(1)" aria-label="Foto berikutnya"><i class="bi bi-chevron-right"></i></button>
                    @endif
                    <button type="button" autofocus x-on:click="$refs.preview.close()" aria-label="Tutup foto"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
        </dialog>
    @else
        <div class="small text-muted mt-1"><i class="bi bi-image me-1" aria-hidden="true"></i>Belum ada foto unit.</div>
    @endif
</div>
