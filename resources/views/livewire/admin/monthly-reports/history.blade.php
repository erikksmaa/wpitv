<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <h2 class="h5 mb-0">Versi Kiriman{{ $snapshot ? ' '.$snapshot->revision_number : '' }}</h2>
        @if ($revisionNumber !== null)
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openLatestRevision" wire:loading.attr="disabled">Lihat versi terbaru</button>
        @endif
    </div>
    <div class="card-body">
        @if ($snapshot)
            @php
                $submission = $snapshot->events->firstWhere('action', 'submitted');
                $approval = $snapshot->events->firstWhere('action', 'approved');
            @endphp
            <p class="mb-2">Dikirim oleh {{ $submission?->actor_name ?? '—' }} · {{ $snapshot->submitted_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>
            <p class="mb-2">{{ count($snapshot->snapshot['items'] ?? []) }} kegiatan</p>
            @if ($approval)
                <p>Disetujui oleh {{ $approval->actor_name }}</p>
            @endif
            @if ($snapshot->snapshot['nil_reason'] ?? null)
                <p class="text-break"><strong>Alasan nihil</strong> · {{ $snapshot->snapshot['nil_reason'] }}</p>
            @endif
            @foreach ($snapshotItems as $entry)
                <article class="border-bottom pb-2 mb-3" wire:key="monthly-snapshot-{{ $snapshot->id }}-{{ $entry['id'] }}">
                    <h3 class="h6 text-break">{{ $entry['title'] }}</h3>
                    <p class="mb-2">{{ $entry['activity_date'] }} WIB · {{ $sourceLabels[$entry['source_type']] ?? 'Manual' }}</p>
                    <dl class="row mb-0">
                        @foreach (array_diff_key($rules::ITEM_FIELDS, ['title' => true]) as $field => $label)
                            <dt class="col-sm-3">{{ $label }}</dt>
                            <dd class="col-sm-9 text-break">{{ $entry[$field] }}</dd>
                        @endforeach
                    </dl>
                </article>
            @endforeach
            {{ $snapshotItems->links() }}

            @if ($snapshotAttachments->isNotEmpty())
                <h3 class="h6 mt-3">Lampiran Versi Kiriman</h3>
                <ul class="list-unstyled">
                    @foreach ($snapshotAttachments as $entry)
                        @php($historicalFile = $snapshotFiles->get($entry['id']))
                        <li class="border-bottom py-2 d-flex flex-wrap gap-2 align-items-center justify-content-between" wire:key="monthly-snapshot-file-{{ $snapshot->id }}-{{ $entry['id'] }}">
                            <div class="text-break">
                                <strong>{{ $entry['display_name'] }}</strong>
                                <div class="small">{{ $entry['mime_type'] }} · {{ number_format($entry['size_bytes'] / 1024, 1, ',', '.') }} KB</div>
                            </div>
                            @if ($historicalFile && hash_equals($entry['sha256'], $historicalFile->sha256) && $files->readable($historicalFile))
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.monthly-reports.attachments.historical', [$reportId, $snapshot->revision_number, $entry['id']]) }}">Unduh</a>
                            @else
                                <span class="badge bg-warning text-dark">File tidak tersedia</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($canDecide && !$stale)
                <section class="border-top pt-3 mt-3" aria-labelledby="monthly-review-heading">
                    <h3 class="h6" id="monthly-review-heading">Review Versi Kiriman {{ $snapshot->revision_number }}</h3>
                    <form wire:submit="requestRevision" data-admin-confirm-title="Kirim permintaan revisi versi ini?" data-admin-confirm-text="Ya, minta revisi">
                        <label for="monthly-review-reason" class="form-label">Catatan perbaikan</label>
                        <textarea id="monthly-review-reason" class="form-control" wire:model="reviewReason" rows="3" maxlength="1000" required></textarea>
                        @error('reason')<div class="text-danger" role="alert">{{ $message }}</div>@enderror
                        <button type="submit" class="btn btn-outline-primary my-2" wire:loading.attr="disabled">Minta Revisi</button>
                        <span wire:loading wire:target="requestRevision" role="status">Mengirim catatan…</span>
                    </form>
                    <form wire:submit="approveReport" data-admin-confirm-title="Setujui versi ini dan tutup laporan?" data-admin-confirm-text="Ya, setujui">
                        <button type="submit" class="btn btn-success" wire:loading.attr="disabled">Setujui Laporan</button>
                        <span wire:loading wire:target="approveReport" role="status">Menyetujui laporan…</span>
                    </form>
                </section>
            @endif
        @else
            <p class="mb-0">Belum ada versi yang dikirim.</p>
        @endif
    </div>
</div>

@if ($versions->count())
    <div class="card">
        <div class="card-header"><h2 class="h5 mb-0">Riwayat Versi</h2></div>
        <div class="card-body">
            <ul class="list-unstyled mb-2">
                @foreach ($versions as $version)
                    <li class="d-flex flex-wrap align-items-center gap-2 mb-2" wire:key="monthly-version-{{ $version->id }}">
                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openRevision({{ $version->revision_number }})" wire:loading.attr="disabled">Versi {{ $version->revision_number }}</button>
                        <span>{{ $version->submitted_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</span>
                        @if ($version->id === $snapshot?->id)<span class="badge bg-secondary">Ditampilkan</span>@endif
                    </li>
                @endforeach
            </ul>
            {{ $versions->links() }}
        </div>
    </div>
@endif

<div class="card">
    <div class="card-header"><h2 class="h5 mb-0">Linimasa Laporan</h2></div>
    <div class="card-body">
        @php($eventLabels = ['created' => 'Draft dibuat', 'updated' => 'Isi laporan diperbarui', 'submitted' => 'Laporan dikirim', 'revision_requested' => 'Revisi diminta', 'approved' => 'Laporan disetujui'])
        <ol class="list-unstyled mb-2">
            @forelse ($timeline as $event)
                <li class="border-bottom pb-2 mb-2" wire:key="monthly-event-{{ $event->id }}">
                    <strong>{{ $event->operation === 'report_exported' ? 'CSV diekspor' : ($eventLabels[$event->action] ?? 'Perubahan laporan') }}</strong>
                    <span class="badge bg-{{ $rules::STATUS_BADGES[$event->to_status] }}">{{ $rules::STATUS_LABELS[$event->to_status] }}</span>
                    @if ($event->reportVersion)<span>· Versi kiriman {{ $event->reportVersion->revision_number }}</span>@endif
                    <div class="small text-break">{{ $event->actor_name }} · {{ $event->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</div>
                    @if ($event->reason)<p class="text-break mb-0">{{ $event->reason }}</p>@endif
                </li>
            @empty
                <li>Belum ada perubahan yang tercatat.</li>
            @endforelse
        </ol>
        {{ $timeline->links() }}
    </div>
</div>
