<div>
    @if(session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @php
        $conditions = ['good' => 'Baik', 'minor_damage' => 'Rusak ringan', 'major_damage' => 'Rusak berat'];
        $openCount = $loan->items->whereNull('returned_at')->count();
        $unresolvedLossCount = $loan->items->whereNull('returned_at')->whereNotNull('lost_at')->count();
    @endphp
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2">
            <div class="w-100">
                <a class="small" href="{{ route('admin.equipment-loans.index') }}">← Daftar peminjaman</a>
                <h4 class="mt-2 text-break">{{ $loan->loan_code }}</h4>
                <x-equipment-status :status="$openCount === 0 ? 'returned' : ($openCount < $loan->items->count() ? 'partially_returned' : 'active')"/>
            </div><div class="d-flex flex-wrap gap-2">
            @if($openCount > 0 && $unresolvedLossCount === 0)
                @can('extend', $loan)
                    <a class="btn btn-outline-primary" href="{{ route('admin.equipment-loans.extend', $loan) }}">Perpanjang</a>
                @endcan
            @endif
            @can('correct', $loan)
                <a class="btn btn-outline-secondary" href="{{ route('admin.equipment-loans.correct', $loan) }}">Koreksi Identitas / Catatan</a>
            @endcan
            @if($openCount > 0)
                @can('recordReturn', $loan)
                    <a class="btn btn-primary" href="{{ route('admin.equipment-loans.return', $loan) }}">Catat Pengembalian</a>
                @endcan
            @endif
            @if($loan->items->contains(fn($item) => $item->returned_at === null && $item->lost_at === null))
                @can('reportLoss', $loan)
                    <a class="btn btn-outline-warning" href="{{ route('admin.equipment-loans.loss', $loan) }}">Laporkan Hilang</a>
                @endcan
            @endif
            </div>
        </div>
        <div class="card-body">
            <div class="row g-2 mb-4">
                @foreach(['unit dipinjam'=>$loan->items->count(), 'sudah kembali'=>$loan->items->count()-$openCount, 'belum kembali'=>$openCount] as $label=>$count)
                    <div class="col-4"><div class="border rounded-3 p-2 p-md-3 h-100">
                        <strong class="fs-4">{{ $count }}</strong> <span class="small">{{ $label }}</span>
                    </div></div>
                @endforeach
            </div>
            @if($unresolvedLossCount > 0)
                <div class="alert alert-warning">{{ $unresolvedLossCount }} unit hilang belum kembali. Kewajiban peminjaman tetap terbuka; unit tidak dapat dipinjam ulang.</div>
            @endif
            @if($openCount > 0 && $loan->due_at->isPast())
                <p class="text-danger">Terlambat — masih ada {{ $openCount }} unit yang belum kembali.</p>
            @endif
            <div class="row g-4 mb-4">
                <section class="col-md-7">
                    <h5>Peminjam</h5><p class="fs-5 fw-semibold text-break mb-2">{{ $loan->borrower_name }}</p>
                    <dl class="row small">
                        @foreach(['NIM'=>$loan->student_number, 'Program studi'=>$loan->study_program, 'WhatsApp'=>$loan->whatsapp_number] as $label=>$value)
                            <dt class="col-4 text-body-secondary">{{ $label }}</dt><dd class="col-8 text-break">{{ $value }}</dd>
                        @endforeach
                    </dl>
                    <h6 class="mb-1">Keperluan</h6><p class="text-break">{{ $loan->purpose }}</p>
                    @if(filled($loan->notes))<h6 class="mb-1">Catatan</h6><p class="text-break">{{ $loan->notes }}</p>@endif
                </section>
                <section class="col-md-5"><div class="border rounded-3 bg-body-tertiary p-3">
                    <h5>Jadwal peminjaman</h5><dl class="mb-0">
                        @foreach(['Tanggal pinjam'=>$loan->borrowed_at,'Jatuh tempo'=>$loan->due_at] as $label=>$date)
                            <dt class="small text-body-secondary">{{ $label }}</dt>
                            <dd class="fw-semibold">{{ $date->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y') }}</dd>
                        @endforeach
                        @if(!$loan->initial_due_at->eq($loan->due_at))
                            <dt class="small text-body-secondary">Jatuh tempo awal</dt><dd>{{ $loan->initial_due_at->timezone('Asia/Jakarta')->format('d/m/Y') }}</dd>
                        @endif
                        <dt class="small text-body-secondary">Petugas pencatat</dt><dd class="mb-0 text-break">{{ $loan->recorded_by_name }}</dd>
                    </dl>
                </div></section>
            </div>
            <h5>Alat yang dipinjam</h5>
            <ul class="list-group">
                @foreach($loan->items as $item)
                    <li wire:key="loan-item-{{ $item->id }}" class="list-group-item py-3">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <strong class="text-break">{{ $item->unit_snapshot['name'] ?? 'Alat' }}</strong>
                            <span class="badge {{ $item->returned_at ? 'bg-success' : 'bg-secondary' }}">{{ $item->returned_at ? 'Sudah kembali' : 'Belum kembali' }}</span>
                        </div>
                        <div class="font-monospace small text-break mb-2">{{ $item->unit_snapshot['inventory_code'] ?? '—' }}</div>
                        <div class="small">Kondisi saat dipinjam · {{ $conditions[$item->condition_out] ?? $item->condition_out }}</div>
                        @if($details=array_filter(array_intersect_key($item->unit_snapshot,array_flip(['brand','model','serial_number','storage_location']))))
                            <details class="small mt-2"><summary>Identitas alat saat dipinjam</summary>
                                @foreach($details as $field=>$value)<div class="text-break">{{ ['brand'=>'Merek','model'=>'Model','serial_number'=>'Nomor seri','storage_location'=>'Lokasi'][$field] }} · {{ $value }}</div>@endforeach
                            </details>
                        @endif
                        @if($item->returned_at)
                            <div class="border-top mt-2 pt-2 small">
                                <strong>Dikembalikan {{ $item->returned_at->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }} WIB</strong>
                                <div>Kondisi kembali · {{ $conditions[$item->condition_in] ?? '—' }}</div>
                                <div>Penerima · {{ $item->received_by_name ?? '—' }}</div>
                                @if($item->due_at_on_return)<div>Batas saat kembali · {{ $item->due_at_on_return->timezone('Asia/Jakarta')->format('d/m/Y') }}</div>@endif
                                @if($item->return_notes)<p class="text-break mb-0">{{ $item->return_notes }}</p>@endif
                                @if($item->due_at_on_return && $item->returned_at->gt($item->due_at_on_return))<span class="badge bg-warning text-dark">Dikembalikan terlambat</span>@endif
                            </div>
                        @endif
                        @if($item->lost_at)
                            <div class="mt-2 text-break"><strong>{{ $item->returned_at ? 'Ditemukan dan dikembalikan' : 'Hilang, belum kembali' }}</strong> · {{ $item->lost_at->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }} WIB<br>{{ $item->loss_notes }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if($extensions->isNotEmpty())
                <h5 class="mt-4">Riwayat Perpanjangan</h5>
                <p class="text-muted">Maksimal 10 perpanjangan terbaru. Semua waktu ditampilkan dalam WIB.</p>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Dicatat</th><th>Jatuh tempo lama</th><th>Jatuh tempo baru</th><th>Keterlambatan saat diperpanjang</th><th>Petugas</th><th>Alasan</th></tr></thead>
                        <tbody>
                            @foreach($extensions as $extension)
                                <tr wire:key="extension-{{ $extension->id }}">
                                    <td>{{ isset($extension->after_data['extended_at']) ? \Carbon\CarbonImmutable::parse($extension->after_data['extended_at'], 'UTC')->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') : '-' }}</td>
                                    <td>{{ isset($extension->before_data['due_at']) ? \Carbon\CarbonImmutable::parse($extension->before_data['due_at'], 'UTC')->timezone('Asia/Jakarta')->format('d/m/Y') : '-' }}</td>
                                    <td>{{ isset($extension->after_data['due_at']) ? \Carbon\CarbonImmutable::parse($extension->after_data['due_at'], 'UTC')->timezone('Asia/Jakarta')->format('d/m/Y') : '-' }}</td>
                                    <td>{{ $extension->after_data['overdue_seconds_at_extension'] ?? '-' }} detik @if($extension->after_data['was_overdue'] ?? false)<span class="badge bg-warning text-dark">Sudah terlambat</span>@endif</td>
                                    <td>{{ $extension->actor_name }}</td>
                                    <td class="text-break">{{ $extension->reason }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <a class="btn btn-light" href="{{ route('admin.equipment-loans.index') }}">Kembali</a>
        </div>
    </div>
<section class="card p-3 p-md-4"><h5>Riwayat serah terima</h5><ol>@foreach($timeline as $event)<li>{{ ['checkout'=>'Peminjaman','lost'=>'Kehilangan','returned'=>'Pengembalian'][$event->event_type] }} · {{ $event->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB · {{ $event->actor_name }}
@if($receipts->has($event->id))<span class="small text-body-secondary"> · {{ $receipts->get($event->id) }}</span>@endif
@foreach($evidence->get($event->id,[]) as $file)<div>{{ $file->lost_at ? 'Pemulihan' : 'Kondisi kembali' }} <x-equipment-status :status="$file->condition_in"/> <a href="{{ route('admin.equipment-loans.photo',[$loan,$event->id,$file->item_id,$file->id]) }}">Buka bukti item {{ $file->item_id }}</a></div>@endforeach
@if($event->event_type==='returned' && !$evidence->has($event->id))<small>Retur historis tanpa bukti foto</small>@endif</li>@endforeach</ol></section></div>
