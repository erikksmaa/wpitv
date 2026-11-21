<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2">
        <div><h4>Koreksi Identitas dan Catatan</h4><p class="mb-0">{{ $loan->loan_code }}</p></div>
        <a class="btn btn-light" href="{{ route('admin.equipment-loans.show', $loan) }}">Kembali ke Detail</a>
    </div>
    <div class="card-body">
        <p>Isi identitas peminjam yang benar, catatan, dan alasan koreksi. Setiap perubahan dicatat pada riwayat audit.</p>
        @if($loan->version !== $expectedVersion)
            <div class="alert alert-warning" role="status">
                Transaksi sudah berubah. Pengiriman ulang koreksi identik yang telah tersimpan tetap aman.
                Untuk koreksi baru, <a href="{{ route('admin.equipment-loans.correct', $loan) }}">muat ulang form</a>.
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form wire:submit.prevent="save" data-admin-confirm-title="Simpan koreksi identitas dan catatan ini?" data-admin-confirm-text="Ya, simpan koreksi">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="correction-name" class="form-label">Nama peminjam</label>
                    <input id="correction-name" class="form-control" wire:model="data.borrower_name" maxlength="150" required>
                </div>
                <div class="col-md-6">
                    <label for="correction-nim" class="form-label">NIM</label>
                    <input id="correction-nim" class="form-control" wire:model="data.student_number" maxlength="30" required>
                </div>
                <div class="col-md-6">
                    <label for="correction-study" class="form-label">Program studi</label>
                    <input id="correction-study" class="form-control" wire:model="data.study_program" maxlength="150" required>
                </div>
                <div class="col-md-6">
                    <label for="correction-wa" class="form-label">WhatsApp</label>
                    <input id="correction-wa" class="form-control" wire:model="data.whatsapp_number" maxlength="20" required>
                </div>
                <div class="col-12">
                    <label for="correction-notes" class="form-label">Catatan transaksi</label>
                    <textarea id="correction-notes" class="form-control" wire:model="data.notes" maxlength="5000"></textarea>
                </div>
                <div class="col-12">
                    <label for="correction-reason" class="form-label">Alasan koreksi</label>
                    <textarea id="correction-reason" class="form-control" wire:model="reason" maxlength="1000" required></textarea>
                </div>
            </div>
            <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">Simpan Koreksi</button>
        </form>
    </div>
</div>
