<div>
    @php($labels = ['tiktok' => 'TikTok', 'instagram' => 'Instagram', 'facebook' => 'Facebook'])
    <a href="{{ route('admin.tvclub.social-posts.index') }}">Kembali ke Konten Media Sosial</a>
    <h1 class="h3 my-3">{{ $post ? ($editable ? 'Edit Unggahan' : 'Detail Unggahan') : 'Catat Unggahan' }}</h1>
    <div class="card">
        <div class="card-body">
            @error('operation')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
            @if ($editable && ($post || $accounts->isNotEmpty()))
                <form wire:submit="save">
                    <div class="mb-3">
                        <label for="post-account" class="form-label">Akun resmi</label>
                        @if ($post)
                            <input id="post-account" class="form-control" value="{{ $labels[$post->account->platform] }} · {{ $post->account->handle }}" readonly>
                        @else
                            <select id="post-account" wire:model.live="accountId" class="form-select" required>
                                <option value="">Pilih akun</option>
                                @foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $labels[$account->platform] }} · {{ $account->handle }}</option>@endforeach
                            </select>
                        @endif
                        @error('accountId')<p class="text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm">
                            <label for="post-published" class="form-label">Waktu publikasi (WIB)</label>
                            <input id="post-published" type="datetime-local" step="1" wire:model="publishedAt" class="form-control" required>
                            @error('publishedAt')<p class="text-danger">{{ $message }}</p>@enderror
                            @error('published_at')<p class="text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div class="col-sm">
                            <label for="post-type" class="form-label">Jenis konten</label>
                            <select id="post-type" wire:model="type" class="form-select" wire:key="post-types-{{ $accountId }}" required>
                                <option value="">{{ $types ? 'Pilih jenis' : 'Pilih akun terlebih dahulu' }}</option>
                                @foreach ($types as $value)<option value="{{ $value }}">{{ \Illuminate\Support\Str::headline($value) }}</option>@endforeach
                            </select>
                            @error('content_type')<p class="text-danger">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    @foreach ([
                        'title' => ['Judul', 'title', 200],
                        'url' => ['URL permanen unggahan', 'url', 500],
                        'responsible' => ['Label penanggung jawab', 'responsible_label', 160],
                    ] as $field => [$label, $error, $length])
                        <div class="mb-3">
                            <label for="social-post-{{ $field }}" class="form-label">{{ $label }}</label>
                            <input id="social-post-{{ $field }}" type="{{ $field === 'url' ? 'url' : 'text' }}" wire:model="{{ $field }}" maxlength="{{ $length }}" class="form-control" required>
                            @error($error)<p class="text-danger">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <div class="mb-3">
                        <label for="post-description" class="form-label">Deskripsi (opsional)</label>
                        <textarea id="post-description" wire:model="description" maxlength="5000" class="form-control" rows="3"></textarea>
                        @error('description')<p class="text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div class="row g-3 mb-3">
                        @foreach ([
                            ['memberId', 'memberSearch', 'Anggota TVClub', $members, 'member_id'],
                            ['programId', 'programSearch', 'Program', $programs, 'program_id'],
                        ] as [$field, $search, $label, $options, $error])
                            <div class="col-sm">
                                <label for="social-{{ $search }}" class="form-label">Cari {{ $label }}</label>
                                <input id="social-{{ $search }}" type="search" class="form-control mb-2" wire:model.live.debounce.350ms="{{ $search }}" maxlength="100">
                                <label for="social-{{ $field }}" class="form-label">{{ $label }} (opsional)</label>
                                <select id="social-{{ $field }}" class="form-select" wire:model="{{ $field }}">
                                    <option value="">Tanpa referensi</option>
                                    @foreach ($options as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
                                </select>
                                @error($error)<p class="text-danger">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Simpan Unggahan</button>
                    <span wire:loading wire:target="save" role="status">Menyimpan…</span>
                </form>
            @elseif ($post)
                <h2 class="h5 text-break">{{ $post->title }}</h2>
                @if ($post->archived_at || $post->account->archived_at)
                    <p><span class="badge bg-secondary">{{ $post->archived_at ? 'Unggahan diarsipkan' : 'Akun diarsipkan' }}</span></p>
                @endif
                <dl class="row">
                    @foreach ([
                        'Akun' => $labels[$post->account->platform].' · '.$post->account->handle,
                        'Publikasi' => $post->published_at->timezone('Asia/Jakarta')->format('d/m/Y H:i:s').' WIB',
                        'Jenis' => \Illuminate\Support\Str::headline($post->content_type),
                        'URL' => $post->url,
                        'Deskripsi' => $post->description,
                        'Penanggung jawab' => $post->responsible_label,
                        'Anggota' => $post->member?->name,
                        'Program' => $post->program?->name,
                    ] as $label => $value)
                        <dt class="col-sm-4">{{ $label }}</dt><dd class="col-sm-8 text-break">{{ $value ?? '—' }}</dd>
                    @endforeach
                </dl>
            @else
                <p>Belum ada akun aktif. <a href="{{ route('admin.tvclub.social-accounts.create') }}">Daftarkan akun</a> untuk mencatat unggahan.</p>
            @endif
            @if ($canManage && $post && !$post->archived_at)
                <form wire:submit="archive" class="mt-3" data-admin-confirm-title="Arsipkan unggahan ini?" data-admin-confirm-text="Ya, arsipkan">
                    <button type="submit" class="btn btn-outline-danger" wire:loading.attr="disabled">Arsipkan Unggahan</button>
                </form>
            @endif
        </div>
    </div>
    @if ($post)
        <livewire:admin.tvclub.social.observations scope="post" :owner-id="$post->id" :key="'social-post-metrics-'.$post->id" />
    @endif
</div>
