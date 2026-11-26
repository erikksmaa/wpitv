# Panduan AI Coding Agent WPiTV

Baseline handover: **25 November 2026**, branch `rebuild/wpitv-clean`. Berlaku untuk Codex, Google Antigravity, dan agent lain yang bekerja pada repository ini. Jika alat tidak memuat AGENTS.md otomatis, baca berkas ini secara eksplisit sebelum bekerja.

## Mulai dari sini

1. Baca [konteks proyek](docs/PROJECT_CONTEXT.md), [arsitektur](docs/ARCHITECTURE.md), dan [status aktual](docs/DEVELOPMENT_STATUS.md).
2. Baca [keputusan](docs/DECISIONS.md) dan [roadmap](docs/ROADMAP.md) sebelum memilih pekerjaan.
3. Jalankan `git status --short --branch`, `git branch --show-current`, dan `git log -5 --oneline`. Jangan berpindah branch jika mengancam perubahan lokal.
4. Verifikasi kode dan metadata database untuk domain yang dikerjakan. Dokumentasi historis, checklist bertanda selesai, dan keberadaan migration bukan bukti kesiapan runtime.
5. Tetapkan scope dan acceptance criteria; lanjutkan pekerjaan yang telah diotorisasi. Minta keputusan hanya untuk perubahan bisnis, akses, DDL/data, atau tindakan eksternal yang belum diotorisasi.

## Stack dan lingkungan

- Windows + Laragon; workspace pengembangan `C:\laragon\www\projects\wpitv`.
- PHP 8.4.15 (constraint Composer PHP ^8.2), Laravel 12.71.0, Livewire 4.4.9, Breeze 2.4.2, MySQL 8.0.30/InnoDB. Versi rinci di ARCHITECTURE.
- Publik: Blade/Livewire/Tailwind 3.4.19. Admin: Blade/Livewire/Bootstrap/Mazer. Build Vite 6.4.3; dependensi dikunci lewat Composer dan npm lockfile.
- Waktu bisnis: WIB (`Asia/Jakarta`); timestamp domain baru disimpan UTC, jatuh tempo Equipment berupa DATE WIB. Jangan mengubah timestamp legacy secara massal.
- Jangan menyalin isi `.env`, password, APP_KEY, API key, nomor kontak, identitas peminjam, NIM, atau data akun ke dokumentasi, fixture, log publik, dan Git.

## Arsitektur dan konvensi

- Pertahankan pemisahan `app/Livewire/Public`, `app/Livewire/Admin`, layout dan entry asset publik/admin. Jangan mencampur Bootstrap dengan Tailwind lintas shell.
- Gunakan PSR-4 `App\`, indentasi empat spasi, UTF-8 dan LF sesuai `.editorconfig`; ikuti pola modul yang disentuh dan Pint pada berkas terkait.
- Eloquent memetakan tabel legacy secara eksplisit. Pertahankan ejaan `tr_perliputan`; jangan menggantinya dengan nama dugaan.
- Mutasi Live/Agenda melalui `LiveStreamLifecycle`/`AgendaLiveGuard`; Monthly melalui `MonthlyActivityLifecycle`; Equipment melalui `InventoryService`/`LoanService`. Jangan melewati koordinasi transaksi dengan save/query langsung dari UI.
- Equipment menggunakan Query Builder terhadap sebelas tabel aktual, bukan model Equipment rekaan. Media Sosial memakai Query Builder, `SocialReport`, `SocialDocument`, dan Gate khusus.
- Otorisasi wajib pada request, render dan aksi Livewire, serta download. Menyembunyikan menu bukan kontrol keamanan. Pertahankan middleware persistent dan pemeriksaan akun aktif.
- Pertahankan optimistic version, row lock, idempotency token, snapshot, audit append-only, dan validasi sisi server. Jangan memperbaiki inkonsistensi historis secara diam-diam.
- Gunakan eager loading, pagination, batas input, empty state, dan agregasi dari data nyata; jangan membuat metrik atau profil kru fiktif.

## Keamanan database dan media

- Database aplikasi existing bukan database tes. Dilarang `migrate:fresh`, `migrate:refresh`, `db:wipe`, truncate, drop/reset, atau seeding massal terhadap sumber.
- Jangan menjalankan migration, SQL manual, provisioning, demo seeder, atau mutasi data tanpa scope/target yang diotorisasi. Handover ini tidak memberi izin tersebut.
- Untuk inspeksi gunakan metadata `information_schema`/`SHOW CREATE TABLE` dalam transaksi read-only, lalu rollback. Ambil data minimum dan jangan mengekspor data pribadi.
- Migration repository tidak membangun seluruh schema legacy/Equipment/Social. Ledger migration sumber mengandung riwayat berkas yang tidak ada di branch; jangan merekonstruksi atau menghapusnya otomatis.
- SQL `database/sql/social_media_phase1.sql` masih pending pada snapshot handover. Review kondisi target, backup, dan buktikan di clone sebelum penerapan yang diotorisasi; skrip bukan idempotent.
- Tes database memerlukan clone disetujui, akun terbatas, manifest dan storage terisolasi. Jangan melemahkan `TestingDatabaseGuard`, `TestEnvironment`, atau `ReleaseConcurrencyGuard` agar tes lewat.
- Jangan menjalankan smoke rollback Equipment pada DB aplikasi untuk pekerjaan rutin: rollback tetap dapat menggeser AUTO_INCREMENT. Keberadaan skrip bukan izin menjalankannya.
- Foto Equipment dan lampiran Monthly tetap privat di `storage/app/private`, dilayani controller berizin. Jangan membuat symlink publik, menghapus bukti lama, atau mempublikasikan backup/ledger.

## UI/UX

- Bahasa Indonesia ringkas untuk pengguna; jangan tampilkan nama tabel, stack trace, detail implementasi, atau informasi privat yang tidak diperlukan.
- Pertahankan identitas biru WPiTV, Mazer admin, navigasi berdasarkan tanggung jawab, pola modal/konfirmasi/toast `WPiAlert`/SweetAlert2 yang sudah ada.
- Form memiliki label, validasi dekat field, fokus keyboard, loading/disabled state dan pencegahan submit ganda. Aksi berisiko memerlukan konfirmasi dan alasan sesuai kontrak domain.
- Periksa desktop/mobile, light/dark bila tersedia, overflow, empty/error state, data panjang, foto rusak dan akses langsung URL oleh role terlarang.
- NIM/kontak dan identitas peminjam tidak boleh muncul pada portal publik. Pemutar YouTube harus memiliki fallback; jangan menambahkan autoplay homepage.

## Strategi pengujian

- Utamakan tes terarah untuk aturan/fitur yang berubah; dokumentasi saja cukup diperiksa link, konsistensi dan diff. Jangan menjalankan full test suite tanpa permintaan/scope yang sesuai.
- Contoh tes murni tanpa DB: `php vendor/phpunit/phpunit/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/EquipmentInventoryTest.php tests/Unit/EquipmentLoanRulesTest.php`.
- Pengabaian konfigurasi di atas hanya untuk unit test murni yang telah diperiksa. Jangan gunakan pola ini untuk melewati guard Feature/Integration/Browser.
- Tes Feature/Livewire/Integration/Browser/Concurrency harus memakai harness dan clone yang lolos guard; lihat ARCHITECTURE. Jangan menambahkan `RefreshDatabase` ke schema sumber.
- Untuk perubahan PHP lakukan lint/Pint terarah; perubahan frontend lakukan build/QA relevan. Jangan merapikan seluruh repository sebagai efek samping.
- Laporkan tanggal, scope, jumlah tes/assertion, hasil, database/storage yang digunakan, serta keterbatasan. Pisahkan tes tersedia, tes historis, tes baru lulus, dan tes belum dijalankan.

## Git dan batas perubahan

- Pertahankan perubahan modified/untracked milik pengguna. Jangan `reset --hard`, `clean`, checkout penimpa, force push, amend atau menghapus branch tanpa izin eksplisit.
- Baseline kerja yang diminta adalah `rebuild/wpitv-clean`; jangan kembali ke implementasi Equipment arsip sebagai fallback. Jika diminta membuat branch baru, ikuti nama yang diminta; default baru `codex/`.
- Jangan commit, push, membuat PR, atau deploy otomatis. Sebelum commit yang kelak diotorisasi, review daftar file eksplisit; jangan `git add .` pada working tree campuran.
- Jangan memasukkan `.env*`, SQL dump privat, dependencies, runtime, storage privat, backup, dan identitas pengguna. `.env.example` juga harus ditinjau agar hanya placeholder.
- Setiap akhir pekerjaan perbarui status dan keputusan yang terdampak. Pisahkan `implemented`, `tested`, `pending configuration`, `pending SQL`, dan `ready for use`; definisinya di DEVELOPMENT_STATUS.

## Referensi lanjutan

- [Panduan operator Equipment](docs/equipment-rebuild/PANDUAN_EQUIPMENT.md), laporan [Inventory Phase 1](docs/equipment-rebuild/EQUIPMENT_INVENTORY_PHASE1_IMPLEMENTATION.md) dan [Loans Phase 2](docs/equipment-rebuild/EQUIPMENT_LOANS_PHASE2_IMPLEMENTATION.md).
- [RBAC core](docs/RBAC_ACCESS_MATRIX.md); tambahan Live/Monthly/Equipment/Social dirangkum pada PROJECT_CONTEXT.
- [Riwayat rebuild](docs/WPITV_ARCHIVE_AND_REBUILD.md), [deployment](DEPLOYMENT.md), [checklist produksi](docs/PRODUCTION_CHECKLIST.md).
- Dokumen v3.x Equipment adalah jejak desain; cocokkan dengan schema aktual dan DECISIONS. Label BLOCKED/approval historis bukan status implementasi terkini maupun izin DDL baru.
