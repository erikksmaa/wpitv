<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2">
        <div><h4>Perpanjang Peminjaman</h4><p class="mb-0">{{ $loan->loan_code }} — {{ $loan->borrower_name }}</p></div>
        <a class="btn btn-light" href="{{ route('admin.equipment-loans.show', $loan) }}">Kembali ke Detail</a>
    </div>
    <div class="card-body">
        <p>Jatuh tempo awal: {{ $loan->initial_due_at->timezone('Asia/Jakarta')->format('d/m/Y') }} WIB.</p>
        <p>Jatuh tempo saat ini: {{ $loan->due_at->timezone('Asia/Jakarta')->format('d/m/Y') }} WIB.</p>
        <p class="text-muted">Alasan perpanjangan wajib dicatat. Keterlambatan yang sudah terjadi tetap masuk riwayat.</p>
        @if($loan->version !== $expectedVersion || ! $hasOpenItems || $hasUnresolvedLoss)
            <div class="alert alert-warning" role="status">
                Transaksi telah berubah, selesai, atau memiliki kehilangan yang belum terselesaikan.
                Perpanjangan baru akan ditolak. Pengiriman ulang permintaan identik yang telah tersimpan tetap aman.
                Untuk permintaan baru, <a href="{{ route('admin.equipment-loans.extend', $loan) }}">muat ulang form</a>.
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form wire:submit.prevent="save" data-admin-confirm-title="Perpanjang jatuh tempo peminjaman ini?" data-admin-confirm-text="Ya, perpanjang">
            <div class="mb-3">
                <label for="extension-due" class="form-label">Jatuh tempo baru (WIB)</label>
                <input id="extension-due" class="form-control" type="date" wire:model="newDueAt" required>
            </div>
            <div class="mb-3">
                <label for="extension-reason" class="form-label">Alasan perpanjangan</label>
                <textarea id="extension-reason" class="form-control" wire:model="reason" maxlength="1000" required></textarea>
            </div>
            <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">Simpan Perpanjangan</button>
        </form>
    </div>
</div>
