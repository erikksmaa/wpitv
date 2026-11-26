# Status Pengembangan WPiTV

Tanggal verifikasi **25 November 2026**. Sumber utama: working tree `rebuild/wpitv-clean`, route/runtime, lockfile/vendor dan metadata MySQL lokal read-only. Tidak ada pengujian full suite, perubahan schema/data, commit, push atau deployment dalam pekerjaan handover ini.

## Identitas baseline dan perubahan lokal

- HEAD: `f9510da8123e2d3eb5036f293b39cc13a22bea11`, **24 November 2026 15:14:35 +0700**, `Add equipment inventory and loan reporting to admin`.
- Sebelumnya: `e3b0289` integrasi fondasi (21 November), `38acca0` restore Monthly, `2499619` rebuild Live; baseline main terdahulu `9a35b67`.
- Branch tracking `origin/rebuild/wpitv-clean`; tidak melakukan fetch atau membuktikan keadaan remote terbaru.
- Awal handover terdapat **23 file tracked modified** dan banyak untracked. Perubahan mencakup Inventory/Loans/Reports/UI/validasi/foto, routes/provider, composer.lock, Social services/controller/Livewire/views/test/config, SQL Social, template resmi, demo seeder dan panduan Equipment. Semuanya dipertahankan.
- HEAD saja belum merepresentasikan Social dan polish Equipment terbaru. Jangan menyerahkan hanya commit HEAD tanpa perubahan lokal yang telah direview. Tidak ada commit baru dibuat oleh handover.

## Definisi status

| Label | Arti |
| --- | --- |
| implemented | Implementasi ditemukan pada kode/route; belum menjamin schema, konfigurasi atau penerimaan pengguna. |
| tested | Ada tes yang benar-benar lulus, dengan tanggal/scope/bukti disebutkan. Tes historis tidak otomatis memvalidasi perubahan lokal terbaru. |
| pending configuration | Perlu pengaturan operator/lingkungan/media/akun yang belum terbukti lengkap. |
| pending SQL | Implementasi membutuhkan DDL/SQL yang terbukti belum ada pada DB yang diperiksa. Bukan izin eksekusi. |
| ready for use | Kode, schema, konfigurasi, tes relevan dan alur pengguna telah diverifikasi bersama pada lingkungan tujuan. Bukan sinonim implemented/tested. |

Label dapat bersamaan. **Belum ada klaim ready for use menyeluruh untuk working tree ini atau production**. Modul core dapat dilanjutkan dari implementasi yang tersedia; tidak perlu dibuat ulang.

## Status seluruh modul

| Modul | Implementasi yang tersedia | Bukti tes dan kesiapan/pending |
| --- | --- | --- |
| Fondasi dan autentikasi | Login username, akun aktif, sesi/CSRF/rate limit, security headers, login/logout/password | implemented; tes Auth/Security/AccessControl tersedia dan bukti baseline historis; konfigurasi production dan QA role terkini belum dibuktikan. |
| Kontrol akses/User/Role | Empat role, policy core, Gate, persistent middleware, proteksi Super Admin terakhir | implemented; tes Roles/Users/RoleAuthorization/AccessControl tersedia; perlu regresi matriks dengan domain baru sebelum ready for use. |
| Website publik/beranda/Tentang | Layout/navigasi/light-dark, konten aktual, featured player, struktur/kru, pencarian global | implemented; Feature Public dan QA lama tercatat; visi/misi/sejarah/pengelola resmi serta SEO/performa/QA lintas browser masih perlu validasi. |
| Program | CRUD aktif/slug/gambar/dependensi + daftar/detail publik dan tim produksi | implemented; ProgramManagement/ProgramPages tersedia, hasil lama historis; QA konten ekstrem belum ditutup. |
| Tayangan | CRUD/normalisasi YouTube/filter/program nullable, player publik | implemented; VideoManagement/VideoPages tersedia; uji jaringan/player lintas perangkat dan izin Admin News terbaru belum diulang. |
| Berita | CRUD TinyMCE/sanitasi/gambar/penulis + daftar/detail publik | implemented; NewsManagement/NewsPages/NewsHtmlSanitizer tersedia; QA editor/mobile/input panjang belum diverifikasi ulang. |
| Peliputan | Pengajuan, receipt sesi, kode+kontak lookup/rate limit, admin CRUD/status/catatan/WhatsApp | implemented; PeliputanFlow/SecurityHardening/Management dan CoverageWhatsApp tersedia; pending configuration bila kontak WhatsApp belum diatur, status konfigurasi kontak tidak diaudit di handover. |
| Agenda | CRUD manual/dari peliputan, kalender publik, status/visibility, koordinasi Live | implemented; AgendaManagement/AgendaPublic/AgendaLiveIntegration dan baseline rebuild; UAT lintas Live tetap perlu sebelum release. |
| Live Streaming | Publik/admin, manual atau Agenda, lifecycle operator, YouTube/arsip/audit/highlights/dashboard | implemented; tested pada baseline rebuild 21 November; kedua migration manual Live tercatat dan kolom/CHECK aktual tersedia. QA eksternal YouTube/semua breakpoint belum dibuktikan. |
| TVClub | Portal, generasi/kru/tim; CRUD/assignment/organisasi generasi | implemented; tes Feature TVClub dan QA publik lama; foto/konten resmi dan QA semua role working tree belum diulang. |
| Organisasi WPiTV | Pohon jabatan, assignment user dan halaman publik | implemented; OrganizationStructure Public/Management tersedia; kepengurusan resmi perlu konfirmasi pemilik. |
| Dashboard dan statistik | KPI sesuai role, trafik/periode/chart dan counter publik | implemented; Dashboard/VisitorStatistics/UserCounter/VisitorCounter tersedia; dashboard Equipment memiliki perubahan lokal, audit visual/performa terbaru belum ada. |
| Inventory/Equipment | Sebelas tabel aktual; kategori/model/unit/stok/foto/audit/lifecycle, UI dipisah per section | implemented; tested saat handover: unit rules/foto/permission bersama Loans 44 tes/63 assertion. Smoke 31 checks historis; polish lokal terbaru belum diuji end-to-end di clone. |
| Peminjaman/pengembalian | Checkout/retur parsial/foto/extend/void/loss/snapshot/idempotency | implemented; unit terarah lulus 25 November; smoke 57 checks historis 24 November; concurrency dua koneksi belum dijalankan, clone Equipment pending configuration. |
| Dashboard/laporan Equipment | Ringkasan Inventory/dashboard, laporan unit/loan, periode checkout/retur, filter/detail/CSV | implemented; route terdaftar; sebagian perubahan belum commit. Tidak ditemukan laporan verifikasi tersendiri yang membuktikan seluruh perubahan laporan terbaru; validasi filter/cohort/CSV dan RBAC masih pending testing. |
| Aktivitas Bulanan | Dihapus dari kode aplikasi (`/admin/monthly-reports`); tabel database & berkas privat historis dipertahankan | Retired (26 November 2026) sesuai instruksi; jangan menjalankan DROP tabel atau menghapus bukti historis tanpa izin. |
| Media Sosial akun/publikasi | Catalog manager, empat platform, metadata posting, observasi metrik posting, archive/audit/locking | implemented; schema lokal sudah mendukung 4 platform & URL nullable (`26 November`). Test SQLite tersedia tetapi skipped di CLI (`pdo_sqlite` tidak aktif); QA runtime MySQL masih perlu. |
| Laporan bulanan Media Sosial | Input metrik/analisis, hitungan publikasi, workflow reviewer berbeda, snapshot, preview/print/XLSX template resmi | implemented; tabel `tvclub_social_reports` sudah ada pada DB lokal (`26 November`), namun `SOCIAL_MEDIA_REVIEWER_IDS` masih **pending configuration**. Ekspor XLSX teruji 21/21 cek template. **Belum ready for use** untuk approval sampai reviewer dikonfigurasi. |
| Banner | Dihapus dari aplikasi, tabel/media dipertahankan | Retired; jangan memasukkan CRUD Banner ke backlog implementasi. |

## Implementasi tersedia tetapi belum diterapkan pada DB

Metadata membuktikan **41 tabel InnoDB**, dengan sebelas Equipment dan enam Social; `tvclub_social_reports` **tidak ada**. Bukan semua tabel Social hilang.

| Artefak/kondisi kode | Kondisi MySQL aktual | Dampak |
| --- | --- | --- |
| Report dan SocialDocumentController memakai tvclub_social_reports | Tabel belum tersedia | Halaman laporan/draft tidak dapat bekerja sampai schema diselaraskan. |
| SocialReport::PLATFORMS mencakup youtube | CHECK akun hanya tiga platform | Simpan akun YouTube ditolak DB meski lolos validasi UI. |
| Catalog mengizinkan URL kosong | url/url_hash NOT NULL | Posting tanpa tautan ditolak DB. |

Ketiganya ditangani kandidat `database/sql/social_media_phase1.sql`; **pending SQL**, tidak dieksekusi. Perlu review backup/clone dan persetujuan penerapan pada target. Skrip DROP CHECK/CREATE TABLE harus memeriksa nama constraint dan keberadaan tabel agar tidak diterapkan dua kali.

Migration repository berjumlah sembilan dan semuanya tercatat pada ledger sumber. Ledger berjumlah 25 dengan sejarah Equipment/Social yang berkasnya tidak ada pada branch. Tidak ada bukti migration Live/Monthly pending pada DB lokal yang diperiksa; jangan menafsirkan ledger lama sebagai SQL rebuild yang portable.

## Konfigurasi dan fungsi yang belum lengkap

- Social manager terkonfigurasi; reviewer belum. Jangan mencantumkan ID pengguna atau menganggap role Super Admin otomatis mendapat akses. Submitter dan reviewer harus berbeda.
- Schema mendukung observasi followers akun dan koreksi observasi append-only. UI Catalog yang diperiksa belum menyediakan pencatatan/koreksi followers akun lengkap; Report hanya membaca observasi yang sudah ada sebagai referensi. Angka laporan tetap manual, tidak terisi otomatis dari API.
- Template resmi ada pada working tree tetapi awalnya untracked; PhpSpreadsheet baru masuk dependency lokal. File template/config/services/test harus ikut direview saat checkpoint kelak diotorisasi.
- Tidak ada PDF server untuk Social; print HTML/browser dan ekspor XLSX sudah diimplementasikan. Jangan melaporkan PDF sebagai selesai.
- Clone Equipment terisolasi, bukti constraint/race dua koneksi, manifest dan grant runner belum dibuktikan siap. Guard baseline Live/Monthly tidak menerima perluasan sembarang.
- Tidak ada bukti production deployment, konfigurasi HTTPS/session/cache/queue/mail/media final, restore drill, UAT seluruh modul atau readiness keseluruhan.

## Hasil pemeriksaan handover, 25 November

| Pemeriksaan | Hasil dan batas bukti |
| --- | --- |
| Git/struktur/policy/service/Livewire/dokumen | Branch dan HEAD cocok; perubahan lokal dipertahankan. README ditemukan mengandung marker konflik, diperbaiki sebagai indeks handover; checklist lama ditandai historis. |
| Metadata DB read-only | Server MySQL 8.0.30; 41 tabel InnoDB; columns/FK/UNIQUE/CHECK dan 25 nama migration dibaca, rollback. Tidak ada audit seluruh isi data/orphan/grants/production. |
| Runtime/dependency | PHP/Laravel/vendor/lockfile terverifikasi; versi frontend dari package-lock dan Node CLI. |
| Route | `php artisan route:list --json` berhasil, 103 route. Bukan smoke login/HTTP seluruh halaman. |
| Unit Equipment | `EquipmentInventoryTest` + `EquipmentLoanRulesTest`: **44 tes / 63 assertion PASS**, PHP 8.4.15 + PHPUnit 11.5.56, tanpa bootstrap aplikasi/koneksi DB; result cache dinonaktifkan. |
| Template XLS | Reader Xls berhasil membuka template: dua worksheet, satu drawing utama. Tidak menjalankan workflow ekspor atau QA visual hasil cetak. |
| Dokumentasi handover | Enam dokumen + indeks README/checklist diperiksa: nol tautan lokal hilang, nol marker konflik/trailing whitespace; `git diff --check` pada dua dokumen existing lulus. |
| Full suite/tes DB/browser/build | **Tidak dijalankan** dalam handover. Upaya awal unit/template tertolak pemeriksaan keterbacaan sandbox Windows; berhasil setelah akses terotorisasi, bukan kegagalan assertion produk. |

## Bukti pengujian historis yang relevan

- [Rebuild 21 November](WPITV_ARCHIVE_AND_REBUILD.md): gabungan **311 tes / 2.530 assertion**, termasuk unit, integration/Feature/Livewire/security/concurrency dan fixture browser Live/Monthly. Dokumen mencatat build/JS/lint/Blade/routes lulus pada baseline saat itu, bukan working tree sekarang.
- [Inventory Phase 1, 23 November](equipment-rebuild/EQUIPMENT_INVENTORY_PHASE1_IMPLEMENTATION.md): **23 tes / 27 assertion** dan **31 rollback smoke checks**; browser daftar/form. Bukan concurrency multikoneksi atau UAT seluruh modul.
- [Loans Phase 2, 24 November](equipment-rebuild/EQUIPMENT_LOANS_PHASE2_IMPLEMENTATION.md): **21 tes / 36 assertion**, **57 rollback smoke checks**, regresi Inventory dan browser checkout. Synthetic rows dibatalkan; kemungkinan gap AUTO_INCREMENT tetap ada. Tidak diulang dalam handover.
- [Checklist lama](PROJECT_PROGRESS_CHECKLIST.md) memuat angka 459 maupun 816 tes pada checkpoint berbeda tanpa baseline tunggal yang setara kondisi kini. Angka itu tidak dijadikan hasil terakhir branch ini.
- `tests/Unit/SocialPhaseOneTest.php` menguji fixture SQLite termasuk approval, otorisasi dan struktur XLSX; keberadaan kode test bukan bukti run terakhir yang lulus. Tes MySQL Social dan penerimaan visual template masih pending.

## Bug, keterbatasan dan tindak lanjut

Prioritas terdekat adalah membuka tiga blocker SQL Social dan reviewer melalui pekerjaan terpisah yang diotorisasi, kemudian menguji MySQL serta hasil template. Setelah itu tutup regresi perubahan lokal Equipment Reports/UI dan concurrency terisolasi. Jangan membangun ulang modul selesai. [ROADMAP](ROADMAP.md) menetapkan urutan dan acceptance criteria.

Informasi kelembagaan resmi, audit responsif/aksesibilitas lintas role, performa/SEO, pengujian player eksternal dan readiness deployment tetap belum ditutup. Tidak ada klaim bug lain sudah diperbaiki hanya karena tercantum pada dokumen historis.

### Pembaruan review dan perbaikan (25 November 2026)

- **Equipment (Inventory, Peminjaman, Laporan)**: Perbaikan drilldown filter status/kondisi unit dari laporan (`Inventory/Index`), normalisasi huruf kapital kode unik, format `due_date` WIB pada `EquipmentReports::display`, filter kategori & serial pada pembuatan pinjaman (`EquipmentLoans/Index`), pemetaan label aktivitas bahasa Indonesia pada `EquipmentLoans/Show` (menggantikan nama event teknis), pratinjau foto retur + lightbox, dropdown mengambang, serta ringkasan kondisi/kategori yang dapat dilipat.
- **Live Streaming**: Penambahan label status dan tipe riwayat berbahasa Indonesia (`LiveStream::STATUS_LABELS`, `LiveStreamEvent::TYPE_LABELS`), breadcrumb admin, pesan validasi/tautan YouTube tidak valid pada form, serta pembersihan tampilan riwayat operasional tanpa dump JSON mentah.
- **Media Sosial**: Perbaikan ambiguitas kolom query pada `Catalog`, fallback aman jika tabel `tvclub_social_reports` belum tersedia (`Report::$schemaReady`), helper pengisian followers awal dari bulan lalu & followers akhir dari observasi, dropdown & modal Bootstrap 5, serta tombol langsung Excel/Cetak saat laporan disetujui. Blocker SQL Phase 1 (`tvclub_social_reports`, `social_accounts_check_1`, `url` nullable) dan konfigurasi `SOCIAL_MEDIA_REVIEWER_IDS` tetap **pending** karena tidak diotorisasi untuk eksekusi DDL/config.
- **Verifikasi pasca-perubahan**: `php -l` seluruh file PHP terubah lulus; **141 unit test / 371 assertion PASS** (`EquipmentInventoryTest`, `EquipmentLoanRulesTest`, `LiveStreamingFoundationTest`); `SocialPhaseOneTest` **skipped** (ekstensi `pdo_sqlite` tidak aktif di CLI); `php artisan view:cache` dan `npm run build` lulus. Pemeriksaan Browser DevTools MCP terblokir (`DevToolsActivePort` Chrome tidak aktif pada lingkungan lokal).

### Penghapusan fitur Aktivitas Bulanan (`/admin/monthly-reports`, 26 November 2026)

- Seluruh route (`7` endpoint `admin.monthly-reports.*`), controller, Livewire, model/concern, policy, service, exception, view, integrasi sidebar (`AdminNavigation`), kartu dashboard (`AdminDashboardMetrics`), exception handler (`bootstrap/app.php`), dan suite tes khusus Monthly Reports telah dihapus dari kode aplikasi; jumlah route aktif kini **96 route**.
- Tabel `monthly_activity_*`, riwayat migration, dan folder privat `storage/app/private/monthly-activity` tetap dipertahankan (tidak ada DDL/penghapusan data).
### Pembaruan Social Media & Live Streaming Description (26 November 2026)

- **Media Sosial**: Katalog (`Catalog.php`) dilengkapi tampilan metrik observasi terbaru, `perPage`, dan penanganan `QueryException`; `Report.php` memvalidasi tabel laporan + versi, menormalisasi metrik kosong vs `0`, mengisi otomatis referensi analisis terbaik/terendah dari publikasi, serta memberi pemberitahuan jika pengesah belum dikonfigurasi.
- **Live Streaming**: Dukungan deskripsi manual (`live_streams.manual_description`) dan deskripsi Agenda via `LiveStream::$display_description`, `PublicLiveStreams`, `LiveStreamLifecycle`, `Form.php`, serta preview/detail admin dan publik + fallback thumbnail (`x-on:error`).
- **SQL Terpadu**: `database/sql/wpitv_social_and_live_schema.sql` menggabungkan kebutuhan DDL kedua modul untuk target baru yang belum menerapkannya; pada DB lokal per 26 November 2026, seluruh tabel/kolom tersebut sudah terverifikasi tersedia, sementara `SOCIAL_MEDIA_REVIEWER_IDS` masih **pending configuration**.
- **Verifikasi**: `php -l` lulus, **141 unit test / 371 assertion PASS**, `php artisan view:cache` dan `npm.cmd run build` lulus. Pemeriksaan Browser DevTools MCP terblokir (`DevToolsActivePort` Chrome tidak aktif).

### Phase 1: Penyempurnaan Bahasa & Konten Panel Admin (26 November 2026)

- **Standarisasi Bahasa & Terminologi**: Dibuat panduan resmi [UI_TERMINOLOGY.md](UI_TERMINOLOGY.md). Seluruh antarmuka admin (layout sidebar/breadcrumb/topbar, Dashboard, autentikasi `/login` & konfirmasi sandi, profil `/profile`, Manajemen Pengguna & Peran, Agenda, Peliputan, Program, Berita, TVClub, Live Streaming, Inventaris, Peminjaman & Laporan Peralatan, Media Sosial, serta `resources/js/admin.js`) diselaraskan ke bahasa Indonesia yang konsisten (`Dashboard`, `Inventaris`, `Peran`, `Pengguna`, `Pratinjau`, `Draf`, `Petugas`, `Berkas`/`Sampul`) tanpa mengubah schema database, nama route/kolom/event internal, ataupun data pengguna.
- **Keselarasan Tes**: Empat berkas feature test admin (`RoleManagementTest`, `TVClubGenerationManagementTest`, `TVClubGroupManagementTest`, `TVClubMemberManagementTest`) disesuaikan string assertion-nya terhadap pesan galat berbahasa Indonesia, sambil mempertahankan frasa teknis yang masih diuji (`Preview YouTube` pada form Live Streaming dan `Draft` pada `social-document`).
- **Verifikasi**: `php -l` pada seluruh file PHP terubah, `php artisan view:cache`, `npm.cmd run build`, **141 unit test / 371 assertion PASS**, dan `GET /login` HTTP 200 memuat teks terbarukan. Feature/DB test tetap membutuhkan clone `wpitv_rebuild_testing` yang terisolasi.

### Phase 2: Penyempurnaan Bahasa & Konten Website Publik (26 November 2026)

- **Cakupan Halaman Publik**: Layout header/footer ([public.blade.php](file:///c:/laragon/www/projects/wpitv/resources/views/layouts/public.blade.php)), Beranda, Live Streaming (daftar, detail, highlight), Program Acara, Tayangan, Berita, Layanan Peliputan (info, ajukan, sukses, cek status), Agenda, TV Club (landing, generasi, kelompok produksi, kru), Tentang WPiTV, serta halaman galat publik ([errors/layout.blade.php](file:///c:/laragon/www/projects/wpitv/resources/views/errors/layout.blade.php), `403/404/419/429/500/503`).
- **Perbaikan Bahasa & SEO**: Penghilangan pengulangan heading/label di Beranda, pemisahan istilah `Program Acara`/`Tayangan`/`Berita`/`Siaran`, CTA spesifik, penyeragaman `Nomor WhatsApp / Telepon`, penggantian istilah Inggris tak perlu (`WPiTV Special` -> `Tayangan Lepas WPiTV`, `Post-Production` -> `Pascaproduksi`, `Crew Member` -> `Anggota Kru`, `review` -> `peninjauan`), perbaikan empty state, serta penambahan `meta description` dinamis dan konsistensi `<title>` pada komponen Livewire publik.
- **Pedoman**: [UI_TERMINOLOGY.md](file:///c:/laragon/www/projects/wpitv/docs/UI_TERMINOLOGY.md) diperbarui dengan standar editorial publik Phase 2.

### Website Settings & Integrasi Media Sosial Publik (26 November 2026)

- **Pengaturan Website (`admin.settings.index`)**: Menu `Pengaturan Website` ditambahkan di bawah `Sistem` ([role-navigation.blade.php](../resources/views/components/admin/role-navigation.blade.php)), dibatasi Gate `manage-website-settings` (Super Admin), dengan form `Nomor WhatsApp Tujuan` (`08`/`62`/`+62`), pratinjau tautan `wa.me`, cache terpusat ([WebsiteSettings.php](../app/Services/WebsiteSettings.php)), fallback `.env` via [CoverageWhatsApp.php](../app/Support/CoverageWhatsApp.php), dan audit log (`wpitv_setting_events`).
- **Visibilitas & Integrasi Media Sosial**: [Catalog.php](../app/Livewire/Admin/Social/Catalog.php) dilengkapi toggle langsung + field `Tampilkan di Website` (`show_on_website`), `Urutan Tampilan` (`display_order`), dan filter visibilitas tanpa memengaruhi akun aktif untuk publikasi/laporan bulanan. Footer ([public.blade.php](../resources/views/layouts/public.blade.php)) dan Tentang WPiTV ([about.blade.php](../resources/views/livewire/public/about.blade.php)) memakai `<x-public.social-links />` ([social-links.blade.php](../resources/views/components/public/social-links.blade.php)) dan [PublicSocialAccounts.php](../app/Services/PublicSocialAccounts.php) (menghapus link statis placeholder serta menyembunyikan blok bila kosong).
- **Pending SQL**: Skrip phpMyAdmin aditif disiapkan di [website_settings_and_social_integration.sql](../database/sql/website_settings_and_social_integration.sql) (`wpitv_settings`, `wpitv_setting_events`, `tvclub_social_accounts.show_on_website` & `display_order`); runtime aman sebelum maupun sesudah impor SQL.

### Public Floating Actions (26 November 2026)

- Ditambahkan container terpadu `.pub-floating-dock` pada [public.blade.php](../resources/views/layouts/public.blade.php) berisi tombol **Back to Top** (`>= 400px` scroll, smooth scroll dengan dukungan `prefers-reduced-motion` dan keyboard/tooltip di [public.js](../resources/js/public.js)), tombol **Instagram WPiTV** dinamis via `PublicSocialAccounts::primaryByPlatform('instagram')`, serta widget statistik pengunjung dengan panel membuka ke kiri agar tidak menutupi tombol aksi saat dibuka/tutup ([public.css](../resources/css/public.css)).

### Penyempurnaan Pengaturan Website — Informasi Kontak Resmi (26 November 2026)

- **Implemented**: Bagian **Informasi Kontak** (`Email WPiTV`, `Nomor Telepon`, `Alamat`) ditambahkan ke [WebsiteSettings.php](../app/Services/WebsiteSettings.php), [Index.php](../app/Livewire/Admin/Settings/Index.php), dan [index.blade.php](../resources/views/livewire/admin/settings/index.blade.php) memakai tabel key-value `wpitv_settings` yang sudah ada (tanpa tabel/DDL baru). Mendukung fallback nilai awal resmi, pembersihan spasi/email/telepon kantor, penulisan audit log `wpitv_setting_events`, dan invalidasi cache instan. Website publik mengintegrasikan satu komponen [contact-info.blade.php](../resources/views/components/public/contact-info.blade.php) dengan tautan `mailto:` dan `tel:` pada Footer ([public.blade.php](../resources/views/layouts/public.blade.php)) serta Tentang WPiTV ([about.blade.php](../resources/views/livewire/public/about.blade.php)), menyembunyikan item kosong secara alami.
- **Tested / Pending DB Integration**: Unit test normalisasi email, telepon kantor, `tel:`, dan alamat pada [WebsiteSettingsAndSocialUnitTest.php](../tests/Unit/WebsiteSettingsAndSocialUnitTest.php) serta build Vite dan verifikasi rute publik dijalankan pada working tree terbaru; integration test Livewire/DB pada [WebsiteSettingsAndSocialIntegrationTest.php](../tests/Feature/Admin/WebsiteSettingsAndSocialIntegrationTest.php) menunggu lingkungan clone DB tes `wpitv_rebuild_testing`.
