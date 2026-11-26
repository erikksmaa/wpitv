@use('App\Services\SocialReport','Social')
<div>
    <div class="page-heading">
        <nav aria-label="Breadcrumb"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li class="breadcrumb-item"><a href="{{ route('admin.social.accounts') }}">Media Sosial</a></li><li class="breadcrumb-item active" aria-current="page">Laporan Bulanan</li></ol></nav>
        <h1 class="h3 mb-0">Laporan Aktivitas Media Sosial Bulanan</h1>
    </div>
    @unless($schemaReady)
        <div class="alert alert-warning" role="alert">
            <h2 class="h6 mb-1">Laporan Media Sosial belum siap</h2>
            <p class="mb-0">Tabel laporan Media Sosial belum tersedia pada database. Terapkan skrip SQL Phase 1 yang disetujui terlebih dahulu.</p>
        </div>
    @else
    <form wire:submit="loadPeriod" class="d-flex align-items-end gap-2 flex-wrap mb-3">
        <div><label for="social-period" class="form-label small mb-1">Periode (WIB)</label><input id="social-period" type="month" class="form-control" wire:model="month" required></div>
        <button class="btn btn-outline-primary">Buka periode</button>
        @error('month')<span class="text-danger small">{{ $message }}</span>@enderror
    </form>
    @php($activeState = $snapshot['status'] ?? $status)
    @php($stateBadge = match($activeState) { 'approved'=>'bg-success', 'submitted'=>'bg-primary', 'revision'=>'bg-warning text-dark', default=>'bg-secondary' })
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex align-items-center gap-2">
            <h2 class="h5 mb-0">{{ $period }}</h2>
            <span class="badge {{ $stateBadge }}">{{ Social::STATES[$activeState] ?? $activeState }}</span>
            @if($history)<span class="badge bg-info text-dark">Versi historis</span>@endif
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if($previewUrl)<a class="btn btn-sm btn-outline-primary" href="{{ $previewUrl }}" target="_blank" rel="noopener"><i class="bi bi-eye me-1" aria-hidden="true"></i>Pratinjau {{ $activeState==='approved'?'laporan resmi':'versi tersimpan' }}</a>@endif
            @if($officialVersionId)
                <a class="btn btn-sm btn-success" href="{{ route('admin.social.preview', [$officialVersionId, 'xlsx']) }}"><i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>Unduh Excel</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.social.preview', [$officialVersionId, 'print']) }}" target="_blank" rel="noopener"><i class="bi bi-printer me-1" aria-hidden="true"></i>Cetak / PDF</a>
            @endif
        </div>
    </div>
    @if($status === 'submitted' && !($reviewerConfigured ?? true))
        <div class="alert alert-info py-2" role="status">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>Pengesah laporan belum ditetapkan pada pengaturan sistem. Hubungi administrator agar laporan dapat disetujui.
        </div>
    @endif
    @if(session('social_notice'))<div class="alert alert-success" role="status">{{ session('social_notice') }}</div>@endif
    @error('workflow')<div class="alert alert-danger">{{ $message }}</div>@enderror
    <div wire:loading class="small text-muted mb-2" role="status">Memuat…</div>
    @if($snapshot !== null && !isset($snapshot['counts']))
        <details open><summary>Isi versi terdahulu</summary><pre class="text-wrap">{{ json_encode($snapshot,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>
    @else
        @php($platforms=Social::PLATFORMS+['total'=>'Total / rata-rata'])
        <section class="card"><div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><h3 class="h5 mb-0">1. Ringkasan Metrik Utama per Platform</h3>
            @if($editable)
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="fillStartFromPreviousMonth">Salin followers awal bulan lalu</button>
                    @if($accountReadings->isNotEmpty())<button type="button" class="btn btn-sm btn-outline-secondary" wire:click="fillEndFromObservations">Isi followers akhir dari observasi</button>@endif
                </div>
            @endif
        </div><div class="card-body pt-3">
            @if($editable)<p class="small text-muted">Isi dari analitik platform. Kosongkan metrik yang belum tersedia. Isi 0 hanya jika hasil pengukuran memang nol.</p>@endif
            @if($accountReadings->isNotEmpty())
                <details class="mb-3"><summary>Observasi followers terakhir per akun dalam periode ini</summary>
                    @foreach($accountReadings as $reading)
                        <div class="small">{{ Social::PLATFORMS[$reading->platform] }} · {{ $reading->handle }} · {{ number_format($reading->followers,0,',','.') }} followers · {{ \Carbon\Carbon::parse($reading->observed_at,'UTC')->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</div>
                    @endforeach
                </details>
            @endif
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Platform</th>
                @foreach(Social::METRICS as $key=>$label)<th>{{ $label }}</th>@if($key==='end')<th>Pertumbuhan (%)</th>@endif @endforeach
            </tr></thead><tbody>
            @foreach($platforms as $p=>$platform)
                <tr><th>{{ $platform }}</th>@foreach(Social::METRICS as $key=>$label)
                <td>@if($editable && $p!=='total')
                    <input aria-label="{{ $label }} {{ $platform }}" style="min-width:6em" type="number" step="{{ $key==='er'?'any':'1' }}" min="0" class="form-control form-control-sm" wire:model.live.debounce.500ms="metrics.{{ $p }}.{{ $key }}">
                    @error('metrics.'.$p.'.'.$key)<small class="text-danger">{{ $message }}</small>@enderror
                @else
                    @php($value=$p==='total'?($summary['totals'][$key] ?? null):(($snapshot['metrics'] ?? $metrics)[$p][$key] ?? null))
                    {{ is_numeric($value)?number_format($value,$key==='er'?2:0,',','.'):'—' }}
                @endif</td>
                @if($key==='end')<td>{{ isset($summary['growth'][$p])?number_format($summary['growth'][$p],2,',','.').' %':'—' }}</td>@endif
                @endforeach</tr>
            @endforeach</tbody></table></div>
        </div></section>
        <section class="card"><div class="card-body pt-3"><h3 class="h5">2. Rekapitulasi Aktivitas &amp; Jumlah Konten</h3>
            <div class="table-responsive"><table class="table"><thead><tr><th>Platform</th>@foreach(Social::TYPES as $label)<th>{{ $label }}</th>@endforeach<th>Total konten</th></tr></thead>
            <tbody>@foreach(Social::PLATFORMS+['total'=>'Total konten'] as $p=>$label)<tr><th>{{ $label }}</th>@foreach(array_keys(Social::TYPES+['total'=>'Total']) as $type)<td>{{ $summary['counts'][$p][$type] }}</td>@endforeach</tr>@endforeach</tbody></table></div>
        </div></section>
        <section class="card"><div class="card-body pt-3"><h3 class="h5">3. Analisis, Evaluasi, dan Rencana Strategi Berikutnya</h3>
            @foreach(Social::ANALYSIS as $key=>$label)
                <div class="mb-3"><label class="form-label" for="analysis-{{ $key }}">{{ $label }}</label>
                @if($editable)<textarea id="analysis-{{ $key }}" class="form-control" wire:model="analysis.{{ $key }}" rows="3"></textarea>
                    @error('analysis.'.$key)<small class="text-danger">{{ $message }}</small>@enderror
                    @if(in_array($key,['best','lowest']))<select aria-label="Referensi {{ $label }}" class="form-select mt-1" wire:model.live="refs.{{ $key }}"><option value="">Referensi publikasi (opsional)</option>
                        @foreach($posts as $post)<option value="{{ $post->id }}">{{ Social::PLATFORMS[$post->platform] }} · {{ $post->title }}</option>@endforeach
                    </select>@error('refs.'.$key)<small class="text-danger">{{ $message }}</small>@enderror @endif
                @else<p class="text-break" style="white-space:pre-line">{{ ($snapshot['analysis'] ?? $analysis)[$key] ?? '—' }}</p>
                    @if(isset($snapshot['references'][$key]))<small>{{ $snapshot['references'][$key]['title'] }}</small>@endif
                @endif</div>
            @endforeach
            @if($editable)<button class="btn btn-outline-primary" wire:click="change('save')" wire:loading.attr="disabled">Simpan draf</button>
                <button class="btn btn-primary" wire:click="change('submit')" wire:confirm="Ajukan versi laporan ini?" wire:loading.attr="disabled">Ajukan laporan</button>@endif
            @if(!$history && $status==='submitted' && auth()->user()->can('social.review') && ($snapshot['submitted_by']['id'] ?? null)!=auth()->id())
                <label class="d-block">Catatan revisi<textarea class="form-control mb-2" wire:model="note"></textarea>@error('note')<small class="text-danger">{{ $message }}</small>@enderror</label>
                <button class="btn btn-outline-secondary" wire:click="change('revise')" wire:confirm="Kembalikan untuk revisi?">Minta revisi</button>
                <button class="btn btn-primary" wire:click="change('approve')" wire:confirm="Setujui versi ini?">Setujui</button>
            @endif
            @if($snapshot)<p class="small mt-3">Pembuat: {{ $snapshot['author']['name'] ?? '—' }} · Pengesah: {{ $snapshot['approved_by']['name'] ?? '—' }}</p>@endif
        </div></section>
    @endif
    <details class="card p-3" @if($history) open @endif><summary>Histori versi dan aktivitas</summary>
        @if($history)<button class="btn btn-link" wire:click="version(0)">Kembali ke laporan aktif</button>@endif
        @forelse($versions as $v)<button class="btn btn-link text-start" wire:click="version({{ $v->id }})">Versi {{ $v->revision_number }} · {{ \Carbon\Carbon::parse($v->published_at,'UTC')->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</button>@empty<p>Belum ada versi.</p>@endforelse
        @foreach($events as $e) @php($details=json_decode($e->changes,true))
            <p class="small mb-1">{{ $details['actor'] ?? 'Sistem' }} · {{ Social::STATES[$details['status'] ?? ''] ?? ($details['status'] ?? '—') }} · {{ \Carbon\Carbon::parse($e->created_at,'UTC')->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB {{ $details['note'] ?? '' }}</p>
        @endforeach
    </details>
    @endunless
</div>
