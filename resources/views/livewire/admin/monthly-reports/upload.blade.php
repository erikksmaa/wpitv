<form method="post" action="{{ route('admin.monthly-reports.attachments.store', $reportId) }}" enctype="multipart/form-data" class="mb-3"
    x-data="{ uploading: false }" x-on:submit="uploading = true">
    @csrf
    <input type="hidden" name="version" value="{{ $expectedVersion }}">
    @if ($uploadItemId)
        <input type="hidden" name="item_id" value="{{ $uploadItemId }}">
    @endif
    <label for="monthly-upload-{{ $uploadItemId ?? 'report' }}" class="form-label">
        {{ $uploadItemId ? 'Lampiran kegiatan' : 'Lampiran laporan' }}
    </label>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <input id="monthly-upload-{{ $uploadItemId ?? 'report' }}" type="file" name="attachment" class="form-control"
            accept=".jpg,.jpeg,.png,.pdf" required>
        <button type="submit" class="btn btn-outline-primary" x-bind:disabled="uploading">Unggah Lampiran</button>
        <span class="small">JPEG, PNG, atau PDF. Maksimal 10 MB.</span>
        <span x-cloak x-show="uploading" role="status">Mengunggah lampiran…</span>
    </div>
    @if ($uploadItemId)
        <p class="small mt-2 mb-0">Simpan perubahan kegiatan sebelum mengunggah. Unggahan akan memuat ulang laporan.</p>
    @endif
</form>
