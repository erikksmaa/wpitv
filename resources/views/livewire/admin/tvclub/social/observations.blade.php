<section class="card" aria-labelledby="social-observations-title">
    <div class="card-header">
        <h2 class="h5 mb-1" id="social-observations-title">{{ $scope === 'account' ? 'Statistik Pengikut Akun' : 'Metrik Unggahan' }}</h2>
        <p class="small mb-0">Data dicatat manual. Tanda — berarti angka belum tersedia.</p>
    </div>
    <div class="card-body">
        @if ($latest)
            <h3 class="h6">Observasi Terbaru</h3>
            <p>{{ $latest->observed_at->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }} WIB</p>
            <dl class="row g-2 mb-3">
                @foreach ($fields as $field => $label)
                    <div class="col-6 col-sm"><dt>{{ $label }}</dt><dd class="text-break">{{ $latest->$field ?? '—' }}</dd></div>
                @endforeach
            </dl>
        @else
            <p>Belum ada observasi.</p>
        @endif

        @if ($notice)<p class="alert alert-success" role="status">{{ $notice }}</p>@endif
        @if ($editable)
            <form wire:submit="save" class="border rounded p-3 mb-4"
                @if ($correctsId) data-admin-confirm-title="Simpan koreksi sebagai catatan baru?" data-admin-confirm-text="Ya, simpan koreksi" @endif>
                <h3 class="h6">{{ $correctsId ? 'Koreksi observasi #'.$correctsId : 'Catat Observasi' }}</h3>
                @error('operation')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
                @error('metrics')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
                <div class="mb-3">
                    <label for="social-observed-at" class="form-label">Waktu observasi (WIB)</label>
                    <input id="social-observed-at" type="datetime-local" step="1" class="form-control" wire:model="observedAt" required @readonly($correctsId)>
                    @error('observedAt')<p class="text-danger">{{ $message }}</p>@enderror
                    @error('observed_at')<p class="text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="row g-3 mb-3">
                    @foreach ($fields as $field => $label)
                        <div class="col-6 col-sm">
                            <label for="social-observation-{{ $field }}" class="form-label">{{ $label }}</label>
                            <input id="social-observation-{{ $field }}" type="text" inputmode="numeric" maxlength="19" pattern="[0-9]*"
                                class="form-control" wire:model="metrics.{{ $field }}" placeholder="—">
                            @error($field)<p class="text-danger">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
                <div class="mb-3">
                    <label for="social-observation-note" class="form-label">Catatan sumber (opsional)</label>
                    <textarea id="social-observation-note" rows="2" maxlength="500" class="form-control" wire:model="sourceNote"></textarea>
                    @error('source_note')<p class="text-danger">{{ $message }}</p>@enderror
                </div>
                @if ($correctsId)
                    <div class="mb-3">
                        <label for="social-correction-reason" class="form-label">Alasan koreksi</label>
                        <textarea id="social-correction-reason" rows="2" maxlength="1000" class="form-control" wire:model="reason" required></textarea>
                        @error('reason')<p class="text-danger">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ $correctsId ? 'Simpan Koreksi' : 'Simpan Observasi' }}</button>
                    @if ($correctsId)
                        <button type="button" class="btn btn-outline-secondary" wire:click="cancelCorrection" wire:loading.attr="disabled">Batal Koreksi</button>
                    @endif
                    <span wire:loading wire:target="save" role="status">Menyimpan…</span>
                </div>
            </form>
        @endif

        @if ($history->isNotEmpty())
            <h3 class="h6">Histori Observasi</h3>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr>
                        <th scope="col">Observasi (WIB)</th>
                        @foreach ($fields as $label)<th scope="col">{{ $label }}</th>@endforeach
                        <th scope="col">Catatan</th>
                        @if ($editable)<th scope="col">Aksi</th>@endif
                    </tr></thead>
                    <tbody>
                        @foreach ($history as $row)
                            <tr wire:key="social-observation-{{ $row->id }}">
                                <td>
                                    {{ $row->observed_at->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }}
                                    <div class="small">#{{ $row->id }}</div>
                                </td>
                                @foreach ($fields as $field => $label)<td>{{ $row->$field ?? '—' }}</td>@endforeach
                                <td class="text-break">
                                    <div class="small">{{ $row->actor?->name ?? 'Aktor tidak tersedia' }} · Dicatat {{ $row->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }} WIB</div>
                                    @if ($row->source_note)<div>{{ $row->source_note }}</div>@endif
                                    @if ($row->corrects_id)<div>Koreksi #{{ $row->corrects_id }} · {{ $row->reason }}</div>@endif
                                    @if ($row->correction_id)<div class="small">Digantikan oleh #{{ $row->correction_id }}</div>@endif
                                </td>
                                @if ($editable)
                                    <td>
                                        @if (!$row->correction_id)
                                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="correct({{ $row->id }})"
                                                wire:loading.attr="disabled" aria-label="Koreksi observasi {{ $row->id }}">Koreksi</button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $history->links() }}
        @endif
    </div>
</section>
