<div>
    @php($labels = \App\Services\EquipmentStock::LABELS)
    @if(session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    <section class="card">
        <div class="card-body d-flex align-items-start gap-3 flex-wrap">
            @if($isParent && $cover)
                <img class="rounded object-fit-cover" width="112" height="84" src="{{ route('admin.inventory.photo', $cover) }}" alt="Foto barang">
            @endif
            <div class="flex-grow-1">
                <a class="small" href="{{ $isParent ? route('admin.inventory.units.index') : route('admin.inventory.units.show', $parent) }}">
                    {{ $isParent ? '← Daftar inventaris' : '← Kembali ke detail barang' }}
                </a>
                <h4 class="mt-2">{{ $isParent ? ($item?->name ?? $unit->name) : 'Unit '.$unitNumber.' · '.$unit->name }}</h4>
                <p class="mb-0 text-break">{{ $item?->item_code ?? $unit->inventory_code }} · {{ $item?->category?->name ?? $unit->category?->name }}</p>
                @if(filled($item ? $item->brand : $unit->brand) || filled($item ? $item->model : $unit->model))
                    <p class="mb-0">{{ $item ? $item->brand : $unit->brand }} {{ $item ? $item->model : $unit->model }}</p>
                @endif
            </div>
            @if($item)
                @can('update', $unit)<a class="btn btn-outline-primary" href="{{ route('admin.inventory.units.edit', [$unit, 'barang'=>1]) }}">Edit Barang</a>@endcan
            @elseif(!$isParent)
                @can('update', $unit)
                    @if(!$borrowed)<a class="btn btn-outline-primary" href="{{ route('admin.inventory.units.edit', $unit) }}">Ubah kondisi / status</a>@endif
                @endcan
            @endif
        </div>
    </section>

    @if($isParent)
        <section id="kelola-stok" class="card">
            <div class="card-body">
                <h5>Kelola stok barang</h5>
                <div class="row g-2">
                    @foreach($labels as $key => $label)
                        <div class="col-6 col-md-4 col-xl-2">
                            <div class="border rounded-3 p-2 h-100">
                                <strong class="d-block fs-5">{{ $counts[$key] }}</strong>
                                <x-equipment-status :status="$key"/>
                            </div>
                        </div>
                    @endforeach
                </div>
                @can('update', $unit)
                    <details class="mt-3" @if($errors->hasAny(['quantity','adjustReason','adjustment'])) open @endif>
                        <summary class="text-primary fw-semibold">Sesuaikan jumlah stok</summary>
                        <p class="small text-body-secondary mt-2 mb-0">Untuk mengubah kondisi satu unit, gunakan tombol di daftar unit fisik.</p>
                        <form wire:submit="adjustStock" class="row g-2 mt-1" data-admin-confirm-text="Ya, sesuaikan stok">
                            <div class="col-md-5">
                                <label for="adjust">Tindakan</label>
                                <select id="adjust" class="form-select" wire:model="adjustment">
                                    <option value="add">Tambah unit baru</option>
                                    <option value="archive">Nonaktifkan unit yang tersedia</option>
                                </select>
                                @error('adjustment')<div class="text-danger">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-2">
                                <label for="count">Jumlah</label>
                                <input id="count" class="form-control" type="number" min="1" max="50" wire:model="quantity">
                                @error('quantity')<div class="text-danger">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-5">
                                <label for="why">Alasan</label>
                                <input id="why" class="form-control" wire:model="adjustReason" required>
                                @error('adjustReason')<div class="text-danger">{{ $message }}</div>@enderror
                            </div>
                            <div><button class="btn btn-primary" wire:loading.attr="disabled">Simpan penyesuaian</button></div>
                        </form>
                    </details>
                @endcan
            </div>
        </section>
        <section class="card">
            <div class="card-body">
                <h5>Unit fisik</h5>
                <ul class="list-group list-group-flush mb-3">
                    @foreach($members as $member)
                        <li wire:key="physical-{{ $member->id }}" class="list-group-item px-0 py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="text-break">
                                <strong>Unit {{ $members->firstItem() + $loop->index }}</strong>
                                @if($member->id === $parent || preg_match('/-[0-9]+$/D', $member->inventory_code))
                                    <div class="small text-body-secondary font-monospace">{{ $member->inventory_code }}</div>
                                @else
                                    <details class="small text-body-secondary"><summary>Kode lama</summary><span class="font-monospace">{{ $member->inventory_code }}</span></details>
                                @endif
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <x-equipment-status :status="$member->condition"/>
                                <x-equipment-status :status="$member->stock_status"/>
                                @if($member->id !== $parent)
                                    <a class="btn btn-sm btn-light" href="{{ route('admin.inventory.units.show', $member) }}">Detail unit</a>
                                @endif
                                @can('update', $member)
                                    @if(!in_array($member->stock_status, ['borrowed','lost'], true))
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.inventory.units.edit', $member) }}">Ubah kondisi / status</a>
                                    @endif
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
                {{ $members->links() }}
            </div>
        </section>
    @else
        <section class="card"><div class="card-body">
            <h5>Kondisi dan ketersediaan</h5>
            <x-equipment-status :status="$unit->condition"/>
            <x-equipment-status :status="$unit->stock_status"/>
            @if($unit->serial_number)<p class="mt-2 mb-0">Nomor seri · {{ $unit->serial_number }}</p>@endif
            @if($unit->notes)<p class="mt-2 mb-0 text-break">{{ $unit->notes }}</p>@endif
        </div></section>
    @endif
    <section class="card"><div class="card-body">
        <details>
            <summary class="fw-semibold">Foto dan histori</summary>
            @if(!$isParent && ($photo = $photos->first()))
                <img width="160" class="img-fluid rounded mt-2" src="{{ route('admin.inventory.photo', [$unit, $photo->id]) }}" alt="Foto unit">
            @endif
            @foreach($coverHistory as $revision)
                <p class="mt-2 mb-1"><a href="{{ route('admin.inventory.photo', [$unit, $revision->id, 'cover'=>1,'download'=>1]) }}">Unduh cover versi {{ $revision->revision }}</a></p>
            @endforeach
            @if($item){{ $coverHistory->links() }}@endif
            @forelse($photos as $photo)
                <p class="mt-2 mb-1"><a href="{{ route('admin.inventory.photo', [$unit, $photo->id, 'download'=>1]) }}">Unduh foto versi {{ $photo->revision }}</a></p>
            @empty
                <p class="mt-2">Belum ada foto khusus unit ini.</p>
            @endforelse
            @if($photos instanceof \Illuminate\Contracts\Pagination\Paginator){{ $photos->links() }}@endif
        </details>
        @if($isParent)
            <details class="mt-2"><summary class="fw-semibold">Riwayat stok dan aktivitas</summary>
                @foreach($changes as $change)
                    <p class="mt-2">{{ $change->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB · {{ $change->actor_name }} · {{ $change->reason }} · {{ count($change->after_data['units']) }} unit</p>
                @endforeach
                {{ $changes->links() }}
                @foreach($events as $event)
                    <p class="mt-2">{{ $event->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB · {{ $event->event_type }} · {{ $event->actor_name }} @if($event->loan_id)<a href="{{ route('admin.equipment-loans.show', $event->loan_id) }}">Detail transaksi</a>@endif</p>
                @endforeach
                {{ $events->links() }}
            </details>
        @endif
    </div></section>
    @if($hasLossHistory)
<div class="card">
    <div class="card-header"><h5>Inspeksi Unit dengan Histori Kehilangan</h5></div>
    <div class="card-body">
        @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        <p>Histori kehilangan tetap tersimpan setelah alat ditemukan.</p>
        @if($borrowed)
            <p class="text-warning">Unit belum kembali. Catat penemuannya melalui pengembalian pada detail peminjaman.</p>
        @elseif($unit->is_active)
            <p class="text-success">Unit sudah aktif.</p>
        @else
            <p>Unit sudah kembali dan masih nonaktif. Hanya Super Admin dapat mengaktifkannya setelah inspeksi.</p>
            @can('reactivateRecovered', $unit)
                @if($unit->condition !== 'good')
                    <div class="alert alert-warning">Unit masih rusak. Catat kondisi baik melalui form Edit / Status setelah perbaikan dan inspeksi sebelum aktivasi.</div>
                @endif
                @if($unit->version !== $expectedVersion)
                    <div class="alert alert-warning">Unit telah berubah. <a href="{{ route('admin.inventory.units.show', $unit) }}">Muat ulang detail</a> sebelum aktivasi.</div>
                @endif
                <form wire:submit.prevent="updateStatus">
                    <div class="mb-3">
                        <label for="recovery-inspection-notes" class="form-label">Hasil inspeksi dan alasan aktivasi</label>
                        <textarea id="recovery-inspection-notes" class="form-control" wire:model="inspectionNotes" maxlength="1000" required></textarea>
                        @error('inspectionNotes')<div class="text-danger">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-check mb-3">
                        <input id="recovery-inspection-confirmed" class="form-check-input" type="checkbox" wire:model="inspectionConfirmed" required>
                        <label for="recovery-inspection-confirmed" class="form-check-label">Saya telah menginspeksi unit dan memastikan alat layak digunakan.</label>
                        @error('inspectionConfirmed')<div class="text-danger">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-primary" type="submit" wire:loading.attr="disabled" @disabled($unit->condition !== 'good')>Aktifkan Setelah Inspeksi</button>
                </form>
            @endcan
        @endif
    </div>
</div>
@endif
<div class="card"><div class="card-header"><h5>Riwayat Peminjaman</h5></div><div class="card-body"><div class="table-responsive"><table class="table"><thead><tr><th>Transaksi</th><th>Identitas saat dipinjam</th><th>Dipinjam</th><th>Kembali</th><th>Hilang (WIB)</th><th>Alasan kehilangan</th><th>Catatan penemuan / kembali</th></tr></thead><tbody>@forelse($history as $item)<tr wire:key="history-{{ $item->id }}"><td>@if($item->loan)<a href="{{ route('admin.equipment-loans.show', $item->loan) }}">{{ $item->loan->loan_code }}</a>@else - @endif</td><td>{{ $item->unit_snapshot['inventory_code'] ?? '-' }} / {{ $item->unit_snapshot['name'] ?? '-' }}</td><td>{{ $item->loan?->borrowed_at?->timezone('Asia/Jakarta')->format('d/m/Y') }}</td><td>{{ $item->returned_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? 'Belum kembali' }}</td><td>{{ $item->lost_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-' }}</td><td class="text-break">{{ $item->loss_notes ?? '-' }}</td><td class="text-break">{{ $item->return_notes ?? '-' }}</td></tr>@empty<tr><td colspan="7">Belum ada histori peminjaman.</td></tr>@endforelse</tbody></table></div>{{ $history->links() }}</div></div></div>