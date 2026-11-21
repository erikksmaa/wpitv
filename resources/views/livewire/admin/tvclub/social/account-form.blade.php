<div>
    @php($labels = ['tiktok' => 'TikTok', 'instagram' => 'Instagram', 'facebook' => 'Facebook'])
    <a href="{{ route('admin.tvclub.social-accounts.index') }}">Kembali ke Akun Media Sosial</a>
    <h1 class="h3 my-3">{{ $account ? 'Edit Akun' : 'Daftarkan Akun' }}</h1>
    <div class="card">
        <div class="card-body">
            @if ($account?->archived_at)<p><span class="badge bg-secondary">Arsip</span></p>@endif
            @error('operation')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
            <form wire:submit="save">
                <div class="mb-3">
                    <label for="official-platform" class="form-label">Platform</label>
                    <select id="official-platform" class="form-select" wire:model="platform" @disabled($account)>
                        @foreach ($platforms as $value)<option value="{{ $value }}">{{ $labels[$value] }}</option>@endforeach
                    </select>
                    @error('platform')<p class="text-danger">{{ $message }}</p>@enderror
                </div>
                @foreach ([
                    'accountKey' => ['Identitas akun', 'account_key', 100],
                    'handle' => ['Handle', 'handle', 100],
                    'displayName' => ['Nama akun', 'display_name', 160],
                    'profileUrl' => ['URL profil (opsional)', 'url', 500],
                ] as $field => [$label, $error, $length])
                    <div class="mb-3">
                        <label for="official-{{ $field }}" class="form-label">{{ $label }}</label>
                        <input id="official-{{ $field }}" type="{{ $field === 'profileUrl' ? 'url' : 'text' }}" class="form-control"
                            wire:model="{{ $field }}" maxlength="{{ $length }}" @required($field !== 'profileUrl') @readonly($account && $field === 'accountKey')>
                        @if ($field === 'accountKey' && !$account)
                            <div class="form-text">Gunakan ID atau kode tetap untuk akun ini.</div>
                        @endif
                        @error($error)<p class="text-danger">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Simpan Akun</button>
                <span wire:loading wire:target="save" role="status">Menyimpan…</span>
            </form>
            @if ($account && !$account->archived_at)
                <form wire:submit="archive" class="mt-3" data-admin-confirm-title="Arsipkan akun ini?" data-admin-confirm-text="Ya, arsipkan">
                    <button type="submit" class="btn btn-outline-danger" wire:loading.attr="disabled">Arsipkan Akun</button>
                </form>
            @endif
        </div>
    </div>
    @if ($account)
        <livewire:admin.tvclub.social.observations scope="account" :owner-id="$account->id" :key="'social-account-metrics-'.$account->id" />
    @endif
</div>
