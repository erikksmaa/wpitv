<div>
    <div class="row">
        <!-- DB Status Card -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm {{ $dbConnected ? 'bg-success text-white' : 'bg-danger text-white' }}">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1 text-white"><i class="bi bi-database-check me-2"></i>Status Koneksi Database</h5>
                        <p class="card-text mb-0">{{ $dbMessage }}</p>
                    </div>
                    <span class="badge bg-white text-dark fs-6">{{ $dbConnected ? 'ONLINE' : 'OFFLINE' }}</span>
                </div>
            </div>
        </div>

        <!-- User Info Card -->
        <div class="col-md-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white font-bold text-primary border-0 pt-4">
                    <i class="bi bi-person-badge me-2"></i>Informasi Akun Terautentikasi
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="fw-semibold text-muted" style="width: 140px;">Username:</td>
                            <td><code>{{ $user->username }}</code></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Nama Lengkap:</td>
                            <td><strong>{{ $user->name }}</strong></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Role System:</td>
                            <td><span class="badge bg-info text-dark">{{ $user->role->name ?? 'None' }}</span></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Status Akun:</td>
                            <td>
                                @if($user->status)
                                    <span class="badge bg-success">Aktif (1)</span>
                                @else
                                    <span class="badge bg-danger">Nonaktif (0)</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Database Verification Table Counts -->
        <div class="col-md-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white font-bold text-primary border-0 pt-4">
                    <i class="bi bi-table me-2"></i>Verifikasi Data Eksisting Database (`wpitv`)
                </div>
                <div class="card-body">
                    @if($dbConnected)
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Programs (<code>ms_program</code>)
                                <span class="badge bg-primary rounded-pill">{{ $counts['programs'] ?? 0 }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Videos (<code>ms_video</code>)
                                <span class="badge bg-primary rounded-pill">{{ $counts['videos'] ?? 0 }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                News (<code>ms_news</code>)
                                <span class="badge bg-primary rounded-pill">{{ $counts['news'] ?? 0 }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Banners (<code>ms_banner</code>)
                                <span class="badge bg-primary rounded-pill">{{ $counts['banners'] ?? 0 }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Coverage Requests (<code>tr_perliputan</code>)
                                <span class="badge bg-primary rounded-pill">{{ $counts['coverage'] ?? 0 }}</span>
                            </li>
                        </ul>
                    @else
                        <p class="text-danger">Gagal memuat data verifikasi database.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
