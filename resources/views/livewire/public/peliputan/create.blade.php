<div>
    <!-- HERO / HEADER SECTION -->
    <section class="pub-hero !py-12 md:!py-16 border-b border-[var(--border)]" aria-labelledby="formTitle">
        <div class="hero-deco" aria-hidden="true"></div>
        <div class="pub-container relative z-10">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-xs text-[var(--text-muted)] mb-4" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}" class="hover:text-brand-blue transition-colors">Beranda</a>
                <span class="text-[var(--text-faint)]">/</span>
                <a href="{{ route('peliputan.index') }}" class="hover:text-brand-blue transition-colors">Peliputan</a>
                <span class="text-[var(--text-faint)]">/</span>
                <span class="text-brand-blue font-medium" aria-current="page">Ajukan Peliputan</span>
            </nav>

            <div class="max-w-2xl">
                <div class="hero-eyebrow !mb-3">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-blue" aria-hidden="true"></span>
                    Permohonan Liputan
                </div>
                <h1 class="hero-title !text-3xl md:!text-4xl !mb-2.5" id="formTitle">
                    Formulir Pengajuan Peliputan
                </h1>
                <p class="text-sm md:text-base text-[var(--text-muted)] leading-relaxed">
                    Silakan lengkapi formulir di bawah ini dengan informasi agenda Anda secara akurat agar tim redaksi dapat segera menindaklanjuti.
                </p>
            </div>
        </div>
    </section>

    <!-- FORM SECTION -->
    <section class="pub-section">
        <div class="pub-container">
            <div class="max-w-3xl mx-auto">
                @if($errors->has('general'))
                    <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 text-sm flex items-center gap-3">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="flex-shrink-0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>{{ $errors->first('general') }}</span>
                    </div>
                @endif

                <form wire:submit.prevent="submit" class="peliputan-form-card space-y-8" novalidate>
                    <!-- SECTION 1: DATA PEMOHON -->
                    <div>
                        <div class="flex items-center gap-2 mb-4 pb-2 border-b border-[var(--border)]">
                            <span class="w-6 h-6 rounded-full bg-brand-blue/10 text-brand-blue font-bold text-xs flex items-center justify-center">1</span>
                            <h2 class="font-heading font-bold text-base text-[var(--text-primary)]">
                                Identitas Pemohon
                            </h2>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Nama Pemohon -->
                            <div class="sm:col-span-2">
                                <label for="name" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1.5">
                                    Nama Pemohon / Organisasi / Unit <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="name" 
                                    wire:model="name"
                                    placeholder="Contoh: BEM Institut Widya Pratama / Nama Penanggung Jawab"
                                    class="w-full px-3.5 py-2.5 bg-[var(--bg)] border @error('name') border-red-500 @else border-[var(--border)] @enderror rounded-lg text-sm text-[var(--text-primary)] placeholder-[var(--text-faint)] focus:outline-none focus:border-brand-blue transition-colors"
                                >
                                @error('name')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1.5">
                                    Alamat Email Aktif <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    wire:model="email"
                                    placeholder="email@domain.com"
                                    class="w-full px-3.5 py-2.5 bg-[var(--bg)] border @error('email') border-red-500 @else border-[var(--border)] @enderror rounded-lg text-sm text-[var(--text-primary)] placeholder-[var(--text-faint)] focus:outline-none focus:border-brand-blue transition-colors"
                                >
                                @error('email')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Phone -->
                            <div>
                                <label for="phone" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1.5">
                                    Nomor WhatsApp / Telepon <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="tel" 
                                    id="phone" 
                                    wire:model="phone"
                                    placeholder="08123456789"
                                    class="w-full px-3.5 py-2.5 bg-[var(--bg)] border @error('phone') border-red-500 @else border-[var(--border)] @enderror rounded-lg text-sm text-[var(--text-primary)] placeholder-[var(--text-faint)] focus:outline-none focus:border-brand-blue transition-colors"
                                >
                                @error('phone')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: INFORMASI ACARA -->
                    <div>
                        <div class="flex items-center gap-2 mb-4 pb-2 border-b border-[var(--border)]">
                            <span class="w-6 h-6 rounded-full bg-brand-blue/10 text-brand-blue font-bold text-xs flex items-center justify-center">2</span>
                            <h2 class="font-heading font-bold text-base text-[var(--text-primary)]">
                                Detail Agenda Kegiatan
                            </h2>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Nama Acara -->
                            <div class="sm:col-span-2">
                                <label for="event_name" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1.5">
                                    Nama Kegiatan / Acara <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="event_name" 
                                    wire:model="event_name"
                                    placeholder="Contoh: Seminar Nasional Teknologi Informasi 2026"
                                    class="w-full px-3.5 py-2.5 bg-[var(--bg)] border @error('event_name') border-red-500 @else border-[var(--border)] @enderror rounded-lg text-sm text-[var(--text-primary)] placeholder-[var(--text-faint)] focus:outline-none focus:border-brand-blue transition-colors"
                                >
                                @error('event_name')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Tanggal Acara -->
                            <div>
                                <label for="event_date" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1.5">
                                    Tanggal Kegiatan <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="date" 
                                    id="event_date" 
                                    wire:model="event_date"
                                    class="w-full px-3.5 py-2.5 bg-[var(--bg)] border @error('event_date') border-red-500 @else border-[var(--border)] @enderror rounded-lg text-sm text-[var(--text-primary)] focus:outline-none focus:border-brand-blue transition-colors"
                                >
                                @error('event_date')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Waktu Acara -->
                            <div>
                                <label for="event_time" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1.5">
                                    Waktu / Jam Kegiatan <span class="text-[var(--text-faint)] font-normal">(Opsional)</span>
                                </label>
                                <input 
                                    type="time" 
                                    id="event_time" 
                                    wire:model="event_time"
                                    class="w-full px-3.5 py-2.5 bg-[var(--bg)] border @error('event_time') border-red-500 @else border-[var(--border)] @enderror rounded-lg text-sm text-[var(--text-primary)] focus:outline-none focus:border-brand-blue transition-colors"
                                >
                                @error('event_time')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Lokasi Acara -->
                            <div class="sm:col-span-2">
                                <label for="location" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1.5">
                                    Lokasi / Tempat Acara <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="location" 
                                    wire:model="location"
                                    placeholder="Contoh: Auditorium Lt. 3 Institut Widya Pratama / Zoom Meeting"
                                    class="w-full px-3.5 py-2.5 bg-[var(--bg)] border @error('location') border-red-500 @else border-[var(--border)] @enderror rounded-lg text-sm text-[var(--text-primary)] placeholder-[var(--text-faint)] focus:outline-none focus:border-brand-blue transition-colors"
                                >
                                @error('location')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: KEBUTUHAN LIPUTAN -->
                    <div>
                        <div class="flex items-center gap-2 mb-4 pb-2 border-b border-[var(--border)]">
                            <span class="w-6 h-6 rounded-full bg-brand-blue/10 text-brand-blue font-bold text-xs flex items-center justify-center">3</span>
                            <h2 class="font-heading font-bold text-base text-[var(--text-primary)]">
                                Kebutuhan Peliputan
                            </h2>
                        </div>

                        <div>
                            <label for="description" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1.5">
                                Deskripsi Agenda &amp; Kebutuhan Liputan <span class="text-red-500">*</span>
                            </label>
                            <textarea 
                                id="description" 
                                wire:model="description"
                                rows="5"
                                placeholder="Jelaskan ringkasan acara, susunan agenda penting, kebutuhan khusus (misal: live streaming, rekaman video berita, wawancara narasumber, dokumentasi foto), dan narahubung di lokasi."
                                class="w-full px-3.5 py-2.5 bg-[var(--bg)] border @error('description') border-red-500 @else border-[var(--border)] @enderror rounded-lg text-sm text-[var(--text-primary)] placeholder-[var(--text-faint)] focus:outline-none focus:border-brand-blue transition-colors"
                            ></textarea>
                            @error('description')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- SUBMIT BUTTON -->
                    <div class="pt-4 border-t border-[var(--border)] flex flex-col sm:flex-row items-center justify-between gap-4">
                        <a href="{{ route('peliputan.index') }}" class="text-xs text-[var(--text-muted)] hover:text-brand-blue transition-colors">
                            &larr; Batalkan &amp; Kembali
                        </a>

                        <button 
                            type="submit" 
                            wire:loading.attr="disabled"
                            class="pub-btn pub-btn--primary w-full sm:w-auto inline-flex items-center justify-center gap-2"
                        >
                            <span wire:loading.remove>Kirim Permohonan Liputan &rarr;</span>
                            <span wire:loading class="inline-flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                Mengirim Pengajuan...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>
