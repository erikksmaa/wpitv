<div>
    @php($labels = ['tiktok' => 'TikTok', 'instagram' => 'Instagram', 'facebook' => 'Facebook'])
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <h1 class="h3">{{ $posts ? 'Konten Media Sosial' : 'Akun Media Sosial' }}</h1>
        @if ($posts)
            @can('manageContent', \App\Models\TVClubSocialPost::class)
                <a class="btn btn-primary" href="{{ route('admin.tvclub.social-posts.create') }}">Catat Unggahan</a>
            @endcan
        @else
            <a class="btn btn-primary" href="{{ route('admin.tvclub.social-accounts.create') }}">Daftarkan Akun</a>
        @endif
    </div>
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    <div class="card">
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-sm">
                    <label for="social-platform" class="form-label">Platform</label>
                    <select id="social-platform" class="form-select" wire:model.live="platform">
                        <option value="">Semua platform</option>
                        @foreach ($platforms as $value)<option value="{{ $value }}">{{ $labels[$value] }}</option>@endforeach
                    </select>
                </div>
                @if ($posts)
                    <div class="col-sm">
                        <label for="social-account" class="form-label">Akun</label>
                        <select id="social-account" class="form-select" wire:model.live="account" wire:key="social-accounts-filter-{{ $platform }}">
                            <option value="">Semua akun</option>
                            @foreach ($options as $option)<option value="{{ $option->id }}">{{ $labels[$option->platform] }} · {{ $option->handle }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-sm">
                        <label for="social-period" class="form-label">Periode publikasi (WIB)</label>
                        <input id="social-period" type="month" class="form-control" wire:model.live="period">
                    </div>
                    <div class="col-sm">
                        <label for="social-type" class="form-label">Jenis konten</label>
                        <select id="social-type" class="form-select" wire:model.live="type" wire:key="social-types-filter-{{ $platform }}">
                            <option value="">Semua jenis</option>
                            @foreach ($types as $value)<option value="{{ $value }}">{{ \Illuminate\Support\Str::headline($value) }}</option>@endforeach
                        </select>
                    </div>
                @endif
            </div>
            <p wire:loading role="status">Memuat data…</p>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr>
                        <th scope="col">{{ $posts ? 'Unggahan' : 'Nama akun' }}</th>
                        <th scope="col">Platform</th>
                        <th scope="col">Handle</th>
                        @if ($posts)<th scope="col">Publikasi (WIB)</th><th scope="col">Jenis</th>
                        @else<th scope="col">URL profil</th>@endif
                        <th scope="col">Status</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($rows as $row)
                        @php($owner = $posts ? $row->account : $row)
                        <tr wire:key="social-list-{{ $row->id }}">
                            <td><a class="text-break" href="{{ route($posts ? 'admin.tvclub.social-posts.show' : 'admin.tvclub.social-accounts.edit', $row->id) }}">{{ $posts ? $row->title : $row->display_name }}</a></td>
                            <td>{{ $labels[$owner->platform] }}</td>
                            <td class="text-break">{{ $owner->handle }}</td>
                            @if ($posts)
                                <td>{{ $row->published_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td>
                                <td>{{ \Illuminate\Support\Str::headline($row->content_type) }}</td>
                            @else
                                <td class="text-break">{{ $row->profile_url ?? '—' }}</td>
                            @endif
                            <td><span class="badge {{ $row->archived_at ? 'bg-secondary' : 'bg-success' }}">{{ $row->archived_at ? 'Arsip' : 'Aktif' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $posts ? 6 : 5 }}">Belum ada data yang cocok.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $rows->links() }}
        </div>
    </div>
</div>
