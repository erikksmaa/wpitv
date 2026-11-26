@can('viewAny', App\Models\User::class)
<section class="card dashboard-panel h-100" aria-labelledby="dashboard-system-title">
    <div class="card-header dashboard-panel-header"><div><span class="dashboard-section-kicker">Administrasi sistem</span><h2 id="dashboard-system-title"><i class="bi bi-shield-check me-2"></i>Pengguna &amp; Peran</h2></div><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.index') }}">Kelola Pengguna</a></div>
    <div class="card-body">
        <p class="small text-muted mb-3">{{ number_format($dashboard['system']['active_users']) }} pengguna aktif · {{ number_format($dashboard['system']['inactive_users']) }} nonaktif</p>
        <div class="tvclub-metrics">
            @foreach($dashboard['system']['roles'] as $role)<div><span>{{ $role->name }}</span><strong>{{ number_format($role->users_count) }}</strong></div>@endforeach
        </div>
    </div>
</section>
@endcan
