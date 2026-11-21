<div>
    @php($rules = \App\Support\MonthlyActivityRules::class)
    <h1 class="h3 mb-3">Laporan Bulanan</h1>

    @can('review', new \App\Models\MonthlyActivityReport)
        <a class="btn btn-outline-primary mb-3" href="{{ route('admin.monthly-reports.index', ['status' => 'submitted']) }}">Antrean Review</a>
    @endcan
    @if ($ownDivision !== '')
        <div class="card">
            <div class="card-body">
                <form wire:submit="createDraft" class="row g-3 align-items-end">
                    <div class="col-sm-5 col-lg-4">
                        <label for="monthly-draft-period" class="form-label">Periode draft {{ $rules::DIVISION_LABELS[$ownDivision] }}</label>
                        <input id="monthly-draft-period" type="month" class="form-control" wire:model="draftPeriod" required>
                        @error('draftPeriod')<div class="text-danger">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-auto">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Buat Draft</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-sm">
                    <label for="monthly-period" class="form-label">Periode</label>
                    <input id="monthly-period" type="month" class="form-control" wire:model.live="period">
                </div>
                <div class="col-sm">
                    <label for="monthly-division" class="form-label">Divisi</label>
                    @if ($ownDivision === '')
                        <select id="monthly-division" class="form-select" wire:model.live="division">
                            <option value="">Semua divisi</option>
                            @foreach ($rules::DIVISION_LABELS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @else
                        <input id="monthly-division" class="form-control" value="{{ $rules::DIVISION_LABELS[$ownDivision] }}" readonly>
                    @endif
                </div>
                <div class="col-sm">
                    <label for="monthly-status" class="form-label">Status</label>
                    <select id="monthly-status" class="form-select" wire:model.live="status">
                        <option value="">Semua status</option>
                        @foreach ($rules::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th scope="col">Periode</th><th scope="col">Divisi</th><th scope="col">Status</th><th scope="col">Kegiatan</th></tr></thead>
                    <tbody>
                        @forelse ($reports as $report)
                            <tr wire:key="monthly-report-{{ $report->id }}">
                                <td><a href="{{ route('admin.monthly-reports.show', $report->id) }}">{{ $report->period_start->locale('id')->translatedFormat('F Y') }}</a></td>
                                <td>{{ $rules::DIVISION_LABELS[$report->division] }}</td>
                                <td><span class="badge bg-{{ $rules::STATUS_BADGES[$report->status] }}">{{ $rules::STATUS_LABELS[$report->status] }}</span></td>
                                <td>{{ $report->items_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">Belum ada laporan untuk filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $reports->links() }}
        </div>
    </div>
</div>
