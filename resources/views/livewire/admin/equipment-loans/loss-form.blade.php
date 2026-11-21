<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2">
        <div>
            <h4>Laporkan Alat Hilang</h4>
            <p class="mb-0">{{ $loan->loan_code }} — {{ $loan->borrower_name }}</p>
        </div>
        <a class="btn btn-light" href="{{ route('admin.equipment-loans.show', $loan) }}">Kembali ke Detail</a>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            Unit yang dilaporkan hilang dinonaktifkan dan tetap terikat pada peminjaman ini.
            Bila ditemukan, catat penerimaannya melalui pengembalian alat dengan catatan penemuan.
        </div>
        @if($loan->version !== $expectedVersion)
            <div class="alert alert-warning" role="status">
                Transaksi sudah berubah. Pengiriman ulang batch identik tetap aman.
                Untuk laporan baru, <a href="{{ route('admin.equipment-loans.loss', $loan) }}">muat ulang form</a>.
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @if($items->isEmpty())
            <p class="text-muted">Tidak ada unit yang dapat dilaporkan hilang.</p>
        @else
            <form wire:submit.prevent="save">
                <button type="button" class="btn btn-outline-primary mb-3" wire:click="selectAll" wire:loading.attr="disabled">Pilih Semua Unit</button>
                @foreach($items as $item)
                    <fieldset class="border rounded p-3 mb-3" wire:key="loss-item-{{ $item->id }}">
                        <legend class="fs-6 float-none w-auto px-1">
                            {{ $item->unit_snapshot['inventory_code'] ?? '-' }} — {{ $item->unit_snapshot['name'] ?? '-' }}
                        </legend>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="loss-select-{{ $item->id }}"
                                value="{{ $item->id }}" wire:model.live="selectedIds">
                            <label class="form-check-label" for="loss-select-{{ $item->id }}">Laporkan unit ini hilang</label>
                        </div>
                        @if($item->returned_at !== null || $item->lost_at !== null)
                            <p class="text-warning">Status unit ini telah berubah. Hanya pengiriman ulang batch identik yang telah tersimpan dapat diterima.</p>
                        @endif
                        <div class="mb-3">
                            <label class="form-label" for="loss-date-{{ $item->id }}">Waktu kehilangan (WIB)</label>
                            <input type="datetime-local" step="1" class="form-control" id="loss-date-{{ $item->id }}"
                                wire:model="losses.{{ $item->id }}.lost_at">
                        </div>
                        <div>
                            <label class="form-label" for="loss-notes-{{ $item->id }}">Alasan dan rincian kehilangan (wajib)</label>
                            <textarea class="form-control" id="loss-notes-{{ $item->id }}" maxlength="5000"
                                wire:model="losses.{{ $item->id }}.loss_notes"></textarea>
                        </div>
                    </fieldset>
                @endforeach
                <button type="submit" class="btn btn-warning" wire:loading.attr="disabled" @disabled(count($selectedIds) === 0)>Simpan Laporan Kehilangan</button>
            </form>
        @endif
    </div>
</div>
