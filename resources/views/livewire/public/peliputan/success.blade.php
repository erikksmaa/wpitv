<div class="coverage-success">
    <section class="coverage-success-hero" aria-labelledby="coverageSuccessTitle"><div class="pub-container">
        <nav class="coverage-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('public.home') }}">Beranda</a><span aria-hidden="true">/</span><a href="{{ route('peliputan.index') }}">Peliputan</a><span aria-hidden="true">/</span><span aria-current="page">Berhasil</span></nav>
        <div class="coverage-success-mark" aria-hidden="true"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m5 12 4 4L19 6"/></svg></div>
        <p class="coverage-eyebrow"><span aria-hidden="true"></span> Pengajuan Tersimpan</p>
        <h1 id="coverageSuccessTitle">Pengajuan Berhasil Dikirim</h1>
        <p>Tim WPiTV akan meninjau detail kegiatan yang telah dikirim.</p>
    </div></section>

    <section class="pub-section coverage-success-content"><div class="pub-container"><div class="coverage-success-layout">
        <main class="coverage-success-card">
            <div class="coverage-success-code"><span>Kode Pengajuan</span><strong>{{ $coverage->submission_code }}</strong><p>Simpan kode ini untuk mengecek status pengajuan.</p></div>
            <div class="coverage-success-notice"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg><p>Pengajuan masih menunggu peninjauan dan belum berarti peliputan otomatis disetujui.</p></div>
            <section class="coverage-success-summary" aria-labelledby="coverageSummaryTitle"><div class="coverage-summary-heading"><p>Ringkasan</p><h2 id="coverageSummaryTitle">Detail kegiatan</h2></div><dl>
                <div><dt>Nama kegiatan</dt><dd>{{ $coverage->event_name }}</dd></div>
                <div><dt>Tanggal</dt><dd>{{ $coverage->event_date ? $coverage->event_date->locale('id')->translatedFormat('j F Y') : '-' }}</dd></div>
                @if($coverage->event_time)<div><dt>Waktu</dt><dd>{{ substr($coverage->event_time, 0, 5) }} WIB</dd></div>@endif
            </dl></section>
            <div class="coverage-success-actions"><a href="{{ route('peliputan.status', ['submission_code' => $coverage->submission_code]) }}" class="pub-btn pub-btn--primary">Cek Status</a>@if($whatsAppUrl)<a href="{{ $whatsAppUrl }}" class="pub-btn pub-btn--secondary coverage-whatsapp-action" target="_blank" rel="noopener noreferrer" aria-label="Hubungi Admin WPiTV melalui WhatsApp"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.5 9.8 9.8 0 0 1-4-.9L3 21l1.7-4.6A8.5 8.5 0 1 1 21 11.5Z"/><path d="M8.5 8.5c.5 3 2 4.5 5 5"/></svg>Hubungi Admin</a>@endif</div>
            @if($whatsAppUrl)<p class="coverage-success-coordination">Butuh koordinasi lanjutan? Hubungi Admin WPiTV melalui WhatsApp dengan membawa detail pengajuan.</p>@endif
            <a href="{{ route('peliputan.index') }}" class="coverage-success-back">Kembali ke Peliputan</a>
        </main>
        <aside class="coverage-success-next" aria-labelledby="coverageNextTitle"><span>LANGKAH BERIKUTNYA</span><h2 id="coverageNextTitle">Apa yang terjadi setelah ini?</h2><ol><li><i>1</i><div><strong>Peninjauan informasi</strong><p>Tim memeriksa detail kegiatan dan kesiapan jadwal.</p></div></li><li><i>2</i><div><strong>Koordinasi bila diperlukan</strong><p>WhatsApp dapat digunakan untuk menindaklanjuti pengajuan.</p></div></li><li><i>3</i><div><strong>Pantau status</strong><p>Gunakan kode pengajuan dan nomor kontak yang didaftarkan.</p></div></li></ol></aside>
    </div></div></section>
</div>
