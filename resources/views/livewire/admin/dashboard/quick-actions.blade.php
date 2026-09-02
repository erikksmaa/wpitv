<section class="card dashboard-panel quick-actions-panel mb-3" aria-labelledby="quick-actions-title">
    <div class="card-header dashboard-panel-header"><div><span class="dashboard-section-kicker">Langkah berikutnya</span><h2 id="quick-actions-title"><i class="bi bi-lightning-charge me-2"></i>Aksi Cepat</h2></div></div>
    <div class="card-body quick-actions-grid">
        @can('create', App\Models\Program::class)<a href="{{ route('admin.programs.create') }}"><i class="bi bi-collection-play"></i>Tambah Program</a>@endcan
        @can('create', App\Models\News::class)<a href="{{ route('admin.news.create') }}"><i class="bi bi-pencil-square"></i>Tulis Berita</a>@endcan
        @can('create', App\Models\Video::class)<a href="{{ route('admin.videos.create') }}"><i class="bi bi-camera-video"></i>Tambah Tayangan</a>@endcan
        @can('create', App\Models\WpitvAgenda::class)<a href="{{ route('admin.agenda.create') }}"><i class="bi bi-calendar-plus"></i>Tambah Agenda</a>@endcan
        @can('updateStatus', App\Models\Coverage::class)<a href="{{ route('admin.peliputan.index') }}"><i class="bi bi-camera-reels"></i>Proses Peliputan</a>@endcan
        @can('create', App\Models\TVClubGeneration::class)<a href="{{ route('admin.tvclub.generations.create') }}"><i class="bi bi-calendar-plus"></i>Tambah Generasi</a>@endcan
        @can('create', App\Models\TVClubMember::class)<a href="{{ route('admin.tvclub.members.create') }}"><i class="bi bi-person-plus"></i>Tambah Kru</a>@endcan
        @can('create', App\Models\TVClubGroup::class)<a href="{{ route('admin.tvclub.groups.create') }}"><i class="bi bi-people"></i>Tambah Kelompok Produksi</a>@endcan
        @can('create', App\Models\TvClubOrganizationPosition::class)
            <a href="{{ ($dashboard['tv_club']['generation'] ?? null) ? route('admin.tvclub.generations.organization', $dashboard['tv_club']['generation']) : route('admin.tvclub.generations.index') }}"><i class="bi bi-diagram-3"></i>Kelola Struktur Organisasi</a>
        @endcan
        @can('viewAny', App\Models\User::class)<a href="{{ route('admin.users.index') }}"><i class="bi bi-person-gear"></i>Kelola Pengguna</a>@endcan
    </div>
</section>
