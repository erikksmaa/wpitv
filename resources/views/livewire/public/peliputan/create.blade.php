<div class="coverage-form-page">
    <section class="coverage-form-hero" aria-labelledby="coverageFormTitle"><div class="pub-container">
        <nav class="coverage-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('public.home') }}">Beranda</a><span aria-hidden="true">/</span><a href="{{ route('peliputan.index') }}">Peliputan</a><span aria-hidden="true">/</span><span aria-current="page">Ajukan</span></nav>
        <a href="{{ route('peliputan.index') }}" class="coverage-back-link"><span aria-hidden="true">←</span> Kembali ke Peliputan</a>
        <p class="coverage-eyebrow"><span aria-hidden="true"></span> Ajukan Peliputan</p>
        <h1 id="coverageFormTitle">Ceritakan Kegiatan Anda</h1>
        <p>Lengkapi informasi berikut agar tim WPiTV dapat meninjau kebutuhan peliputan.</p>
    </div></section>

    <section class="pub-section coverage-form-content"><div class="pub-container">
        @if($errors->has('general'))
            <div class="coverage-form-alert" role="alert"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg><span>{{ $errors->first('general') }}</span></div>
        @endif
        <div class="coverage-form-layout">
            <form wire:submit.prevent="submit" class="coverage-form-card" novalidate>
                <p class="coverage-required-note"><span aria-hidden="true">*</span> Wajib diisi</p>

                <section class="coverage-form-section" aria-labelledby="applicantSectionTitle">
                    <header><span>01</span><div><p>Tentang Pemohon</p><h2 id="applicantSectionTitle">Informasi Kontak</h2></div></header>
                    <div class="coverage-fields coverage-fields--two">
                        <div class="coverage-field">
                            <label for="name">Nama Pemohon <span aria-hidden="true">*</span></label>
                            <input type="text" id="name" wire:model="name" autocomplete="name" placeholder="Nama lengkap pemohon" aria-describedby="nameHelp @error('name') nameError @enderror" @error('name') aria-invalid="true" @enderror>
                            <small id="nameHelp">Nama pihak yang dapat dihubungi terkait kegiatan.</small>
                            @error('name')<p class="coverage-field-error" id="nameError">{{ $message }}</p>@enderror
                        </div>
                        <div class="coverage-field">
                            <label for="phone">Nomor WhatsApp / Telepon <span aria-hidden="true">*</span></label>
                            <input type="tel" id="phone" wire:model="phone" autocomplete="tel" inputmode="tel" placeholder="Contoh: 0812 3456 7890" aria-describedby="phoneHelp @error('phone') phoneError @enderror" @error('phone') aria-invalid="true" @enderror>
                            <small id="phoneHelp">Nomor ini digunakan untuk koordinasi dan verifikasi status pengajuan.</small>
                            @error('phone')<p class="coverage-field-error" id="phoneError">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section class="coverage-form-section" aria-labelledby="eventSectionTitle">
                    <header><span>02</span><div><p>Tentang Kegiatan</p><h2 id="eventSectionTitle">Jadwal Kegiatan</h2></div></header>
                    <div class="coverage-fields coverage-fields--two">
                        <div class="coverage-field coverage-field--wide">
                            <label for="event_name">Nama Kegiatan <span aria-hidden="true">*</span></label>
                            <input type="text" id="event_name" wire:model="event_name" placeholder="Contoh: Seminar Nasional Teknologi Informasi" aria-describedby="eventNameHelp @error('event_name') eventNameError @enderror" @error('event_name') aria-invalid="true" @enderror>
                            <small id="eventNameHelp">Tuliskan nama kegiatan secara ringkas dan jelas.</small>
                            @error('event_name')<p class="coverage-field-error" id="eventNameError">{{ $message }}</p>@enderror
                        </div>
                        <div class="coverage-field">
                            <label for="event_date">Tanggal Kegiatan <span aria-hidden="true">*</span></label>
                            <input type="date" id="event_date" wire:model="event_date" aria-describedby="eventDateHelp @error('event_date') eventDateError @enderror" @error('event_date') aria-invalid="true" @enderror>
                            <small id="eventDateHelp">Pilih tanggal pelaksanaan kegiatan.</small>
                            @error('event_date')<p class="coverage-field-error" id="eventDateError">{{ $message }}</p>@enderror
                        </div>
                        <div class="coverage-field">
                            <label for="event_time">Waktu Kegiatan <em>Opsional</em></label>
                            <input type="time" id="event_time" wire:model="event_time" aria-describedby="eventTimeHelp @error('event_time') eventTimeError @enderror" @error('event_time') aria-invalid="true" @enderror>
                            <small id="eventTimeHelp">Jika belum pasti, kosongkan dan koordinasikan setelah pengajuan.</small>
                            @error('event_time')<p class="coverage-field-error" id="eventTimeError">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section class="coverage-form-section" aria-labelledby="detailSectionTitle">
                    <header><span>03</span><div><p>Detail Peliputan</p><h2 id="detailSectionTitle">Lokasi &amp; Konteks</h2></div></header>
                    <div class="coverage-fields">
                        <div class="coverage-field">
                            <label for="location">Lokasi Kegiatan <span aria-hidden="true">*</span></label>
                            <input type="text" id="location" wire:model="location" placeholder="Contoh: Auditorium lantai 3" aria-describedby="locationHelp @error('location') locationError @enderror" @error('location') aria-invalid="true" @enderror>
                            <small id="locationHelp">Sertakan nama ruang atau lokasi yang mudah dikenali.</small>
                            @error('location')<p class="coverage-field-error" id="locationError">{{ $message }}</p>@enderror
                        </div>
                        <div class="coverage-field">
                            <label for="description">Deskripsi Kegiatan <span aria-hidden="true">*</span></label>
                            <textarea id="description" wire:model="description" rows="6" maxlength="3000" placeholder="Tuliskan tujuan dan rangkaian singkat kegiatan..." aria-describedby="descriptionHelp @error('description') descriptionError @enderror" @error('description') aria-invalid="true" @enderror></textarea>
                            <small id="descriptionHelp">Jelaskan tujuan, rangkaian singkat, atau konteks kegiatan yang perlu diketahui tim WPiTV.</small>
                            @error('description')<p class="coverage-field-error" id="descriptionError">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section class="coverage-form-section coverage-form-review" aria-labelledby="reviewSectionTitle">
                    <header><span>04</span><div><p>Review &amp; Kirim</p><h2 id="reviewSectionTitle">Periksa Sebelum Dikirim</h2></div></header>
                    <div class="coverage-review-note"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg><div><strong>Pastikan tanggal, kontak, dan detail kegiatan sudah benar.</strong><p>Pengiriman formulir tidak berarti peliputan otomatis disetujui.</p></div></div>
                    <div class="coverage-submit-row">
                        <a href="{{ route('peliputan.index') }}" class="coverage-cancel-link">Kembali</a>
                        <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="pub-btn pub-btn--primary">
                            <span wire:loading.remove wire:target="submit">Kirim Pengajuan</span>
                            <span wire:loading wire:target="submit" class="coverage-loading"><i aria-hidden="true"></i> Mengirim...</span>
                        </button>
                    </div>
                </section>
            </form>

            <aside class="coverage-form-aside" aria-labelledby="coverageGuideTitle">
                <div class="coverage-guide-card"><p class="coverage-section-eyebrow">Sebelum Mengirim</p><h2 id="coverageGuideTitle">Pengajuan yang mudah ditinjau</h2><ul><li><span>✓</span> Pastikan kontak aktif</li><li><span>✓</span> Periksa tanggal kegiatan</li><li><span>✓</span> Jelaskan kegiatan dengan ringkas</li><li><span>✓</span> Simpan kode setelah berhasil</li></ul></div>
                <div class="coverage-guide-status"><strong>Sudah pernah mengajukan?</strong><p>Gunakan kode dan nomor kontak yang didaftarkan untuk memantau perkembangannya.</p><a href="{{ route('peliputan.status') }}">Cek Status Pengajuan <span aria-hidden="true">→</span></a></div>
            </aside>
        </div>
    </div></section>
</div>
