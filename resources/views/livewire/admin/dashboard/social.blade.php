<section class="card mb-3" aria-labelledby="social-dashboard-title">
    @php
        $data = $dashboard['social'];
        $labels = ['tiktok' => 'TikTok', 'instagram' => 'Instagram', 'facebook' => 'Facebook'];
        $metrics = ['views' => 'Tayangan', 'likes' => 'Suka / Reaksi', 'comments' => 'Komentar', 'shares' => 'Bagikan'];
    @endphp
    <div class="card-body">
        <h2 id="social-dashboard-title" class="h5">Performa Media Sosial TVClub</h2>
        <label for="dashboard-social-month" class="form-label">Bulan dan tahun (WIB)</label>
        <input id="dashboard-social-month" type="month" class="form-control mb-2" wire:model.live="socialMonth">
        <p wire:loading wire:target="socialMonth" role="status">Memuat ringkasan…</p>
        @if ($data['unavailable'] ?? false)
            <p role="status">Ringkasan tidak tersedia. Periksa periode atau coba lagi.</p>
        @else
            <p>Statistik dicatat manual.</p>
            @if ($user->isTVClub())
                <p>{{ $data['active'] }} akun aktif selama periode.</p>
            @endif
            <div class="row g-2">
                @foreach ($labels as $key => $label)
                    @php($platform = $data['platforms']->get($key))
                    <div class="col-12 col-lg-4"><div class="border rounded p-3 h-100">
                        <h3 class="h6">{{ $label }}</h3>
                        <p>{{ $platform->posts ?? 0 }} unggahan</p>
                        @if ($user->isTVClub())
                            <dl class="mb-0">
                                @foreach ($metrics as $metric => $name)
                                    <dt>{{ $name }}</dt>
                                    <dd class="text-break">{{ $platform->$metric ?? '—' }} · {{ $platform->{ $metric.'_coverage' } ?? 0 }} dari {{ $platform->posts ?? 0 }}</dd>
                                @endforeach
                            </dl>
                        @endif
                    </div></div>
                @endforeach
            </div>
            @if ($user->isTVClub())
                <h3 class="h6 mt-3">Pengikut per Akun</h3>
                @if ($data['followers']->count() === 10)
                    <p>Maksimal 10 akun ditampilkan. Buka daftar akun untuk melihat lainnya.</p>
                @endif
                <ul class="list-unstyled">
                    @forelse ($data['followers'] as $entry)
                        <li class="mb-2 text-break">
                            <a href="{{ route('admin.tvclub.social-accounts.edit', $entry['account']) }}#social-observations-title">{{ $labels[$entry['account']->platform] }} · {{ $entry['account']->handle }}</a>
                            · {{ $entry['observation']->followers ?? '—' }}
                            @if ($entry['observation']) · {{ \Carbon\CarbonImmutable::parse($entry['observation']->observed_at, 'UTC')->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }} WIB @endif
                        </li>
                    @empty
                        <li>Belum ada akun untuk ditampilkan.</li>
                    @endforelse
                </ul>
                <a class="me-3 d-inline-block" href="{{ route('admin.tvclub.social-accounts.index') }}">Kelola Akun</a>
                <a class="me-3 d-inline-block" href="{{ route('admin.tvclub.social-posts.index', ['period' => $data['month']]) }}">Konten &amp; Pencatatan Metrik</a>
            @endif
            <p class="mt-3 mb-1">
                @if ($data['version'])
                    <a href="{{ route('admin.tvclub.social-reports.show', $data['version']) }}">Terbit · Revisi {{ $data['version']->revision_number }}</a>
                    · {{ $data['version']->published_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB
                @else
                    Belum terbit.
                @endif
            </p>
            <a href="{{ route('admin.tvclub.social-reports.index', ['period' => $data['month']]) }}">Laporan Bulanan &amp; Histori</a>
        @endif
    </div>
</section>
