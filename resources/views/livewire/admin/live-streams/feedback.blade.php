@if($loadError)
    <div class="card border-danger"><div class="card-body" role="alert"><h4 class="h6">Live Streaming belum dapat dimuat</h4><p class="mb-0">{{ $loadError }}</p><small class="text-muted">Aksi dinonaktifkan sampai konfigurasi atau koneksi diperiksa. Jika submit sebelumnya terputus, muat ulang untuk memeriksa hasilnya.</small></div></div>
@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
