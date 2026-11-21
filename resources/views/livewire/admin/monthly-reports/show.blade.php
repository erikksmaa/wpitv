<div>
    @php
        $rules = \App\Support\MonthlyActivityRules::class;
        $sourceLabels = \App\Services\MonthlyActivityCandidates::LABELS;
    @endphp
    <a href="{{ route('admin.monthly-reports.index') }}" class="d-inline-block mb-2">Kembali ke Laporan Bulanan</a>
    <h1 class="h3">{{ $rules::DIVISION_LABELS[$report->division] }} · {{ $report->period_start->locale('id')->translatedFormat('F Y') }}</h1>
    <p><span class="badge bg-{{ $rules::STATUS_BADGES[$report->status] }}">{{ $rules::STATUS_LABELS[$report->status] }}</span> · Versi laporan {{ $report->version }}</p>
    @if ($report->status === 'approved')
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="{{ route('admin.monthly-reports.print', $reportId) }}" class="btn btn-outline-primary">Halaman Cetak</a>
            <form method="post" action="{{ route('admin.monthly-reports.csv', $reportId) }}">
                @csrf
                <button type="submit" class="btn btn-outline-primary">Unduh CSV</button>
            </form>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @foreach (['report', 'activity'] as $field)
        @error($field)<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
    @endforeach
    @if ($stale)
        <div class="border border-warning rounded p-3 mb-3" role="alert">
            Laporan telah berubah. <a href="{{ route('admin.monthly-reports.show', $reportId) }}">Muat ulang laporan</a> sebelum menyimpan.
        </div>
    @endif
    @if ($revisionNote)
        <section class="card border-warning" aria-labelledby="monthly-revision-note">
            <div class="card-body">
                <h2 class="h5" id="monthly-revision-note">Catatan Revisi · Versi Kiriman {{ $revisionNote->reportVersion?->revision_number }}</h2>
                <p class="small mb-2">{{ $revisionNote->actor_name }} · {{ $revisionNote->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>
                <p class="text-break mb-0">{{ $revisionNote->reason }}</p>
            </div>
        </section>
    @endif
    @if ($editable && !$stale && !$editing)
        <form wire:submit="submitReport" class="mb-3" data-admin-confirm-title="Kirim laporan ini untuk ditinjau?" data-admin-confirm-text="Ya, kirim">
            @foreach (['items', 'nil_reason'] as $field)
                @error($field)<div class="text-danger mb-2" role="alert">{{ $message }}</div>@enderror
            @endforeach
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Kirim untuk Review</button>
            <span wire:loading wire:target="submitReport" role="status">Mengirim laporan…</span>
        </form>
    @endif

    @if ($editable && !$stale && $editing)
        <div class="card" wire:key="monthly-item-editor">
            <div class="card-header"><h2 class="h5 mb-0">{{ $itemId ? 'Edit Kegiatan' : 'Tambah Kegiatan' }}</h2></div>
            <div class="card-body">
                @if ($source)
                    <p class="text-break">{{ $sourceLabels[$source['type']] }} · {{ $source['title'] }} · {{ $source['date_basis'] }}: {{ $source['date'] }}. Periksa tanggal pelaksanaan sebelum menyimpan.</p>
                @endif
                <form wire:submit="saveItem">
                    <div class="mb-3">
                        <label class="form-label" for="monthly-activity-date">Tanggal pelaksanaan (WIB)</label>
                        <input id="monthly-activity-date" type="date" class="form-control" wire:model="activity.activity_date" min="{{ $report->period_start->toDateString() }}" max="{{ $report->period_start->endOfMonth()->toDateString() }}" required>
                        @error('activity.activity_date')<div class="text-danger">{{ $message }}</div>@enderror
                    </div>
                    @foreach ($rules::ITEM_FIELDS as $field => $label)
                        <div class="mb-3">
                            <label class="form-label" for="monthly-item-{{ $field }}">{{ $label }}</label>
                            @if ($field === 'title')
                                <input id="monthly-item-{{ $field }}" class="form-control" wire:model="activity.{{ $field }}" maxlength="{{ $rules::itemLength($field) }}" required>
                            @else
                                <textarea id="monthly-item-{{ $field }}" class="form-control" wire:model="activity.{{ $field }}" rows="2" maxlength="{{ $rules::itemLength($field) }}" required></textarea>
                            @endif
                            @error('activity.'.$field)<div class="text-danger">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                    @error('confirmDuplicate')
                        <div class="form-check mb-3">
                            <input id="monthly-confirm-duplicate" type="checkbox" wire:model="confirmDuplicate" class="form-check-input">
                            <label for="monthly-confirm-duplicate" class="form-check-label">{{ $message }}</label>
                        </div>
                    @enderror
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Simpan Kegiatan</button>
                    <button type="button" class="btn btn-outline-secondary" wire:click="cancelEdit" wire:loading.attr="disabled">Batal</button>
                </form>
                @if ($itemId)
                    <div class="border-top pt-3 mt-3">
                        @include('livewire.admin.monthly-reports.upload', ['uploadItemId' => $itemId])
                    </div>
                @else
                    <p class="small mt-3 mb-0">Simpan kegiatan untuk menambahkan lampiran.</p>
                @endif
            </div>
        </div>
    @endif

    @if ($items !== null)
    <section class="card" aria-labelledby="monthly-attachments">
        <div class="card-header"><h2 class="h5 mb-0" id="monthly-attachments">Lampiran Draft</h2></div>
        <div class="card-body">
            @error('attachment')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
            @if ($editable && !$stale && !$editing)
                @include('livewire.admin.monthly-reports.upload', ['uploadItemId' => null])
            @endif
            <ul class="list-unstyled mb-0">
                @forelse ($attachments as $file)
                    @php($readable = $files->readable($file))
                    <li class="border-bottom py-2 d-flex flex-wrap gap-2 align-items-center justify-content-between" wire:key="monthly-file-{{ $file->id }}">
                        <div class="text-break">
                            <strong>{{ $file->display_name }}</strong>
                            <div class="small">{{ $file->item?->title ?? 'Tingkat laporan' }} · {{ $file->mime_type }} · {{ number_format($file->size_bytes / 1024, 1, ',', '.') }} KB</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($readable)
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.monthly-reports.attachments.download', [$reportId, $file->id]) }}">Unduh</a>
                            @else
                                <span class="badge bg-warning text-dark">File tidak tersedia</span>
                            @endif
                            @if ($editable && !$stale && !$editing)
                                <form wire:submit="detachAttachment({{ $file->id }})" data-admin-confirm-title="Lepas lampiran dari draft?" data-admin-confirm-text="Ya, lepas">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" wire:loading.attr="disabled">Lepas</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @empty
                    <li>Belum ada lampiran.</li>
                @endforelse
            </ul>
        </div>
    </section>
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <h2 class="h5 mb-0">Kegiatan</h2>
            @if ($editable && !$stale && !$editing)
                <button type="button" class="btn btn-primary" wire:click="newItem" wire:loading.attr="disabled">Tambah Manual</button>
            @endif
        </div>
        <div class="card-body">
            @forelse ($items as $item)
                <article class="border-bottom pb-3 mb-3" wire:key="monthly-item-{{ $item->id }}">
                    <h3 class="h6 text-break">{{ $item->title }}</h3>
                    <p class="mb-2">
                        {{ $item->activity_date->format('d/m/Y') }} WIB · {{ $sourceLabels[$item->source_type] ?? 'Manual' }}
                        @if ($editable && $item->source_type)
                            @php($sourceNow = $current[$item->source_type][$item->source_id] ?? null)
                            @if (!$sourceNow)
                                <span class="badge bg-secondary">Sumber tidak tersedia</span>
                            @elseif ($item->source_context && $rules::snapshotHash($item->source_context) !== $rules::snapshotHash($sourceNow))
                                <span class="badge bg-warning text-dark">Sumber berubah</span>
                            @endif
                        @endif
                    </p>
                    @if ($item->source_context)
                        <p class="small text-break">Saat dipilih: {{ $item->source_context['title'] ?? '—' }} · {{ $item->source_context['date_basis'] ?? 'Tanggal sumber' }}: {{ $item->source_context['date'] ?? '—' }}</p>
                    @endif
                    <dl class="row mb-2">
                        @foreach (array_diff_key($rules::ITEM_FIELDS, ['title' => true]) as $field => $label)
                            <dt class="col-sm-3">{{ $label }}</dt>
                            <dd class="col-sm-9 text-break">{{ $item->$field }}</dd>
                        @endforeach
                    </dl>
                    @if ($editable && !$stale && !$editing)
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="editItem({{ $item->id }})" wire:loading.attr="disabled">Edit</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="moveItem({{ $item->id }}, -1)" wire:loading.attr="disabled" @disabled($items->firstItem() + $loop->index === 1)>Naik</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="moveItem({{ $item->id }}, 1)" wire:loading.attr="disabled" @disabled($items->firstItem() + $loop->index === $items->total())>Turun</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-admin-confirm wire:click="removeItem({{ $item->id }})" wire:loading.attr="disabled">Hapus</button>
                        </div>
                    @endif
                </article>
            @empty
                <p class="mb-0">Belum ada kegiatan pada laporan ini.</p>
            @endforelse
            {{ $items->links() }}
        </div>
    </div>

    @if (!$items->total() || $report->nil_reason)
        <div class="card">
            <div class="card-body">
                @if ($editable && !$stale && !$editing)
                    <form wire:submit="saveNilReason">
                        <label class="form-label" for="monthly-nil">Alasan laporan nihil</label>
                        <textarea id="monthly-nil" class="form-control" wire:model="nilReason" maxlength="1000" rows="2"></textarea>
                        @error('nil_reason')<div class="text-danger">{{ $message }}</div>@enderror
                        <button type="submit" class="btn btn-outline-primary mt-2" wire:loading.attr="disabled">Simpan Alasan Nihil</button>
                    </form>
                @elseif ($report->nil_reason)
                    <h2 class="h6">Alasan laporan nihil</h2>
                    <p class="text-break mb-0">{{ $report->nil_reason }}</p>
                @endif
            </div>
        </div>
    @endif
    @endif

    @if ($candidates !== null)
        <div class="card">
            <div class="card-header"><h2 class="h5 mb-0">Kandidat Kegiatan</h2></div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-sm-4">
                        <label class="form-label" for="monthly-source-type">Sumber</label>
                        <select id="monthly-source-type" class="form-select" wire:model.live="candidateType">
                            @foreach ($types as $type)<option value="{{ $type }}">{{ $sourceLabels[$type] }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-sm-8">
                        <label class="form-label" for="monthly-source-search">Cari judul kandidat</label>
                        <input id="monthly-source-search" type="search" class="form-control" wire:model.live.debounce.350ms="search" maxlength="100">
                    </div>
                </div>
                @forelse ($candidates as $candidate)
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center border-bottom py-2" wire:key="monthly-candidate-{{ $candidate['type'] }}-{{ $candidate['id'] }}">
                        <div class="text-break">
                            <strong>{{ $candidate['title'] }}</strong>
                            <div class="small">{{ $candidate['date_basis'] }}: {{ $candidate['date'] }}</div>
                            @if ($candidate['status'])
                                <div class="small">Status sumber: {{ $candidate['type'] === 'coverage' ? (\App\Models\Coverage::getStatusOptions()[$candidate['status']] ?? $candidate['status']) : (\App\Models\WpitvAgenda::STATUSES[$candidate['status']] ?? $candidate['status']) }}</div>
                            @endif
                        </div>
                        @if (in_array($candidate['id'], $selected))
                            <span class="badge bg-secondary">Sudah dipilih</span>
                        @else
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="chooseCandidate('{{ $candidate['type'] }}', {{ $candidate['id'] }})" wire:loading.attr="disabled">Pilih</button>
                        @endif
                    </div>
                @empty
                    <p>{{ $report->division === 'news' && $candidateType === 'video' && trim($search) === '' ? 'Cari judul Tayangan yang relevan dengan kontribusi editorial.' : 'Tidak ada kandidat yang cocok. Kegiatan dapat dicatat secara manual.' }}</p>
                @endforelse
                {{ $candidates->links() }}
            </div>
        </div>
    @endif

    @include('livewire.admin.monthly-reports.history')
</div>
