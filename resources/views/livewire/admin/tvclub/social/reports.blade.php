<div>
    @php
        $platformNames = ['tiktok' => 'TikTok', 'instagram' => 'Instagram', 'facebook' => 'Facebook'];
        $metrics = ['views' => 'Tayangan', 'likes' => 'Suka / Reaksi', 'comments' => 'Komentar', 'shares' => 'Bagikan'];
        $wib = fn ($time) => \Carbon\CarbonImmutable::parse($time)->timezone('Asia/Jakarta')->format('d/m/Y H:i:s').' WIB';
    @endphp
    @if ($version)
        <a href="{{ route('admin.tvclub.social-reports.index', ['period' => $month]) }}">Kembali ke rekap bulanan</a>
    @endif
    <h1 class="h3 my-3">{{ $version ? 'Laporan Media Sosial · Revisi '.$version->revision_number : 'Rekap Bulanan Media Sosial' }}</h1>
    @if ($version)
        <p>{{ $month }} · Terbit {{ $wib($version->published_at) }}</p>
        @can('export', $version)
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach (['posts' => 'CSV Metrik Unggahan', 'followers' => 'CSV Pengikut Akun'] as $kind => $label)
                    <form method="post" action="{{ route('admin.tvclub.social-reports.csv', [$version, $kind]) }}">
                        @csrf
                        <button class="btn btn-outline-primary" type="submit">{{ $label }}</button>
                    </form>
                @endforeach
            </div>
        @endcan
        @if ($version->reason)<p class="text-break">{{ $version->reason }}</p>@endif
        @if ($previous)
            <p>Selisih jumlah unggahan dari <a href="{{ route('admin.tvclub.social-reports.show', $previous) }}">revisi {{ $previous->revision_number }}</a>: {{ sprintf('%+d', $postDifference) }}.</p>
        @endif
    @endif
    <div class="card"><div class="card-body">
        <div class="row g-3">
            @unless ($version)
                <div class="col-sm">
                    <label class="form-label" for="recap-period">Bulan dan tahun (WIB)</label>
                    <input id="recap-period" type="month" class="form-control" wire:model.live="period">
                </div>
            @endunless
            <div class="col-sm">
                <label class="form-label" for="recap-platform">Platform</label>
                <select id="recap-platform" class="form-select" wire:model.live="platform">
                    <option value="">Semua platform</option>
                    @foreach ($platformNames as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-sm">
                <label class="form-label" for="recap-account">Akun</label>
                <select id="recap-account" class="form-select" wire:model.live="account" wire:key="recap-accounts-{{ $platform }}">
                    <option value="">Semua akun</option>
                    @foreach ($options as $option)
                        <option value="{{ $option['id'] }}">{{ $platformNames[$option['platform']] }} · {{ $option['handle'] }}{{ $option['archived_at'] ? ' (Arsip)' : '' }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <p class="mt-2 mb-0" wire:loading role="status">Memuat laporan…</p>
    </div></div>
    @if (!$reportPeriod || $unavailable)
        <p role="alert">{{ $unavailable ? 'Rekap periode ini belum dapat dimuat.' : 'Pilih bulan dan tahun yang valid.' }}</p>
    @else
        <section class="card"><div class="card-body">
            <h2 class="h5">Performa Unggahan · {{ $month }}</h2>
            <p>Statistik dicatat manual. Tanda — berarti data belum tersedia.</p>
            <p><strong>{{ $postCount }}</strong> unggahan pada periode dan filter ini.</p>
            <dl class="row g-2">
                @foreach ($metrics as $key => $label)
                    <div class="col-6 col-lg-3">
                        <dt>{{ $label }}</dt><dd class="text-break">{{ $summary[$key]['total'] ?? '—' }}<br><small>{{ $summary[$key]['coverage'] }} dari {{ $postCount }} unggahan</small></dd>
                    </div>
                @endforeach
            </dl>
            <div class="table-responsive"><table class="table align-middle">
                <thead><tr><th scope="col">Unggahan</th><th scope="col">Akun</th><th scope="col">Publikasi</th><th scope="col">Observasi terakhir</th>
                    @foreach ($metrics as $label)<th scope="col">{{ $label }}</th>@endforeach
                </tr></thead>
                <tbody>
                    @forelse ($posts as $post)
                        <tr wire:key="recap-post-{{ $post['id'] }}">
                            <td class="text-break">{{ $post['title'] }}{{ $post['archived_at'] ? ' (Arsip)' : '' }}</td>
                            <td class="text-break">{{ $platformNames[$accounts[$post['account_id']]['platform']] }} · {{ $accounts[$post['account_id']]['handle'] }}</td>
                            <td>{{ $wib($post['published_at']) }}</td>
                            <td>{{ isset($post['observation']) ? $wib($post['observation']['observed_at']) : '—' }}</td>
                            @foreach ($metrics as $key => $label)<td>{{ $post['observation']['metrics'][$key] ?? '—' }}</td>@endforeach
                        </tr>
                    @empty
                        <tr><td colspan="8">Belum ada unggahan pada periode dan filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            {{ $posts->links() }}
        </div></section>
        <section class="card"><div class="card-body">
            <h2 class="h5">Pengikut per Akun</h2>
            <div class="table-responsive"><table class="table align-middle">
                <thead><tr><th scope="col">Akun</th><th scope="col">Pengikut</th><th scope="col">Observasi terakhir</th></tr></thead>
                <tbody>
                    @forelse ($followers as $row)
                        <tr wire:key="recap-followers-{{ $row['id'] }}">
                            <td class="text-break">{{ $platformNames[$row['platform']] }} · {{ $row['handle'] }}{{ $row['archived_at'] ? ' (Arsip)' : '' }}</td>
                            <td>{{ $row['observation']['metrics']['followers'] ?? '—' }}</td>
                            <td>{{ isset($row['observation']) ? $wib($row['observation']['observed_at']) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Belum ada akun yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            {{ $followers->links() }}
        </div></section>
    @endif
    @if ($canPublish)
        <section class="card"><div class="card-body">
            <h2 class="h5">Terbitkan Laporan Internal</h2>
            <p>Laporan mencakup semua akun dan platform pada periode {{ $month }}.</p>
            @error('operation')<p class="text-danger" role="alert">{{ $message }} <a href="{{ route('admin.tvclub.social-reports.index', ['period' => $month]) }}">Muat ulang rekap</a></p>@enderror
            @error('period')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
            <form wire:submit="publish" data-admin-confirm-title="Terbitkan laporan internal periode ini?" data-admin-confirm-text="Ya, terbitkan">
                @if ($expectedRevision)
                    <label class="form-label" for="recap-reason">Alasan revisi</label>
                    <textarea id="recap-reason" class="form-control mb-2" maxlength="1000" wire:model="reason" required></textarea>
                    @error('reason')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
                @endif
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Terbitkan Revisi {{ $expectedRevision + 1 }}</button>
                <span wire:loading wire:target="publish" role="status">Menerbitkan…</span>
            </form>
        </div></section>
    @elseif (!$version && $reportPeriod && $reportPeriod->addMonth()->isFuture())
        <p>Laporan dapat diterbitkan setelah bulan WIB berakhir.</p>
    @endif
    @if ($versions)
        <section class="card"><div class="card-body">
            <h2 class="h5">Histori Revisi · {{ $month }}</h2>
            <ul class="list-unstyled">
                @forelse ($versions as $revision)
                    <li class="border-bottom py-2 text-break" wire:key="recap-version-{{ $revision->id }}">
                        <a href="{{ route('admin.tvclub.social-reports.show', $revision) }}">Revisi {{ $revision->revision_number }}</a>
                        · {{ $wib($revision->published_at) }}
                        @if ($revision->reason)<div>{{ $revision->reason }}</div>@endif
                    </li>
                @empty
                    <li>Belum ada versi yang terbit.</li>
                @endforelse
            </ul>
            {{ $versions->links() }}
        </div></section>
    @endif
</div>
