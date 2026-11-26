# Arsitektur WPiTV

Inspeksi: **25 November 2026**, branch `rebuild/wpitv-clean`, termasuk perubahan lokal. Metadata MySQL diperiksa langsung melalui transaksi read-only dan rollback; tidak membaca isi data pribadi. Ini snapshot lingkungan lokal, bukan schema production yang diasumsikan sama.

## Stack dan versi

| Komponen | Versi/bukti |
| --- | --- |
| PHP | Runtime CLI 8.4.15; Composer mensyaratkan ^8.2 |
| Laravel | 12.71.0; runtime, vendor dan composer.lock cocok |
| Livewire | 4.4.9, vendor + lock; jangan mengikuti referensi lama 3.x |
| Breeze | 2.4.2 pada composer.lock; alur auth sudah dikustomisasi |
| PhpSpreadsheet | 5.12.0, vendor + lock; ekspor Social dari template XLS |
| MySQL | Server lokal 8.0.30; 41 tabel, semuanya InnoDB |
| Composer / Node | CLI 2.10.3 / v24.21.0 |
| Vite / plugin Laravel | package-lock 6.4.3 / 1.3.0 |
| Tailwind / Alpine / SweetAlert2 | package-lock 3.4.19 / 3.16.1 / 11.26.25 |
| Admin | Bootstrap 5/Mazer dari aset lokal `public/mazer`; versi patch aset belum dibuktikan, jangan menganggap versi CDN tertentu |
| PHPUnit | 11.5.56, vendor + lock |

## Struktur direktori

```text
app/
  Livewire/Public/             portal dan komponen global
  Livewire/Admin/              halaman operasional berdasarkan domain
  Models/                     Eloquent legacy, Live dan Monthly
  Services/Equipment/         InventoryService, LoanService, rules, foto, laporan
  Services/                   lifecycle Live/Monthly, Social, media, statistik
  Support/                    permission, guard, parser YouTube, CSV, aturan domain
  Policies/                   policy model core/Live/Monthly
  Http/Controllers/           auth dan download/ekspor privat
  Http/Middleware/            sesi, role/status, header keamanan
  Providers/AppServiceProvider.php  Gate dan middleware Livewire persistent
bootstrap/app.php             routing, middleware, exception mapping
config/                       auth, storage, security, social-media, wpitv
routes/web.php, auth.php       route publik/admin dan auth yang benar-benar aktif
resources/views/               layout publik/admin, komponen dan dokumen cetak
resources/css/, resources/js/  entry publik/admin terpisah
public/mazer/                  aset admin lokal; extensions termasuk TinyMCE
database/migrations/          sembilan migration Live/Monthly/manual Live
database/sql/                 SQL penyesuaian Social manual, belum diterapkan
database/seeders/              seeder existing; bukan perintah setup otomatis
docs/templates/               template resmi XLS laporan Social
tests/                        Unit, Feature, Integration, Browser, Concurrency
scripts/                      setup/verify harness tes terisolasi
storage/app/private/          media privat; bukan web root
storage/app/public/           gambar publik melalui public/storage
```

`references/` dan storage privat bukan jaminan tersedianya schema portable. Jangan memasukkan backup/manifest privat ke Git agar setup terlihat lengkap.

## Peta domain, model, service dan UI

| Domain | Model → tabel | Service/support dan Livewire |
| --- | --- | --- |
| Konten | Program → ms_program; Video → ms_video; News → ms_news | Public/Admin Programs, Videos, News; OptimizedImageStorage, NewsHtmlSanitizer, YouTubeVideoId |
| Peliputan | Coverage → tr_perliputan | Public Peliputan Index/Create/Success/Status; Admin Peliputan Index/Form; CoverageWhatsApp, CoverageReceiptAuthorization |
| Agenda/Live | WpitvAgenda → wpitv_agendas; LiveStream → live_streams; LiveStreamEvent → live_stream_events | LiveStreamLifecycle, LiveStreamState/Schedule, AgendaLiveGuard, PublicLiveStreams, LiveStreamHighlights; Public Agenda/LiveStreams, Admin Agenda/LiveStreams |
| TVClub | TVClubGeneration/Member/Group/GroupMember → tvclub_generations/members/groups/group_members | Public TVClub Index, Generations, Members, Groups; Admin TVClub Generations/Members/Groups; eager-loaded assignment per program |
| Organisasi | TvClubOrganizationPosition/Member → tvclub_organization_positions/members; WpitvOrganizationPosition → wpitv_organization_positions | OrganizationTree; Admin TVClub Organization dan Organization |
| Akses | User → users; Role → roles | Policies, AppServiceProvider Gates, Admin Users/Roles, middleware auth/active/role/session.valid |
| Statistik/dashboard | UserCounter → tr_usercounter | VisitorTracker, VisitorStatisticsService, AdminDashboardMetrics, LiveDashboardMetrics, MonthlyActivityDashboard; Public VisitorCounter/GlobalSearch/Home/About; Admin Dashboard/VisitorStatistics |
| Aktivitas Bulanan | MonthlyActivityReport/Item/Attachment/Version/Event → monthly_activity_reports/items/attachments/versions/events | MonthlyActivityLifecycle/Candidates/Files/Dashboard, MonthlyActivityRules; Admin MonthlyReports Index/Show; controller lampiran dan dokumen |
| Equipment | Tidak ada Eloquent Equipment; sebelas tabel memakai Query Builder | InventoryService, InventoryRules/Photos, LoanService, LoanRules, EquipmentValidation, EquipmentReports; EquipmentPermissions; Admin Inventory Index + helper InventoryActivity, EquipmentLoans Index/Show, EquipmentReports Index |
| Social | Tidak ada Eloquent Social; Query Builder `tvclub_social_*` | SocialReport, SocialDocument; Admin Social Catalog/Report; SocialDocumentController; config/social-media.php |

Policy model berada di `app/Policies` dan mengikuti konvensi Laravel: Program, Video, News, Coverage, WpitvAgenda, LiveStream, MonthlyActivityReport, TVClubGeneration/Member/Group, TvClubOrganizationPosition, WpitvOrganizationPosition, User, Role; `ContentPolicy` berisi helper akses. Equipment/Social memakai Gate eksplisit, bukan tabel permission baru atau policy model fiktif.

## Route dan batas akses

- `routes/web.php`: publik `/`, `/tentang`, `/program`, `/tayangan`, `/berita`, `/tv-club` dan subportal, `/peliputan` dan alur status, `/agenda`, `/live`. Daftar lengkap alur di [PROJECT_CONTEXT](PROJECT_CONTEXT.md).
- Grup `/admin`: `auth`, `session.valid`, `active`, allowlist empat role. Policy/Gate pada komponen tetap menentukan akses modul; masuk grup tidak berarti berhak semua halaman.
- Live admin mendapat middleware role tambahan Super Admin/Production. Inventory dan laporan dibatasi permission `equipment.*` pada aksi/render/controller. Social memakai `social.view/manage/review`.
- `/admin/inventory` mengarahkan tab lama ke categories/equipment/activity; `/admin/equipment-reports/dashboard` mengarah ke Inventory. Section laporan aktif hanya inventory/loans.
- Endpoint privat: Inventory photos model/unit, foto retur terikat loan, lampiran Monthly aktif/historis, CSV/cetak Monthly dan CSV Equipment. Social memiliki draft preview per periode serta version preview/print/xlsx; ekspor resmi hanya approved.
- `routes/auth.php` hanya login GET/POST, confirm-password GET/POST, update password PUT, logout POST. Controller register/reset/email verification yang masih ada bukan bukti route aktif. Tidak ada registrasi publik.
- Health `/up` berasal dari bootstrap. `php artisan route:list --json` berhasil memuat **103 route** saat inspeksi, termasuk endpoint framework; angka lama 86 adalah baseline terdahulu.

## Database aktual

Total **41 tabel** = 16 core/legacy + 2 Live + 5 Monthly + 11 Equipment + 6 Social + 1 ledger migrations. `users.id` dan FK pengguna bertipe INT UNSIGNED; PK domain baru BIGINT UNSIGNED. Jangan menyamakan tipe hanya karena model memakai integer.

| Kelompok | Tabel dan relasi penting |
| --- | --- |
| Akses/konten (6) | roles → users.role_id; ms_program → ms_video.program_id (nullable); ms_news.user_id dan ms_video.user_id → users (nullable); ms_banner dipertahankan tanpa fitur aktif |
| Operasional core (4) | tr_perliputan → wpitv_agendas.coverage_id; wpitv_agendas juga mengacu program/group/created_by; tr_usercounter menyimpan hit/date/IP/online; wpitv_organization_positions memiliki parent_id self-FK dan user_id nullable |
| TVClub (6) | tvclub_generations → tvclub_members dan tvclub_groups; groups → ms_program; tvclub_group_members menghubungkan group/member + role; tvclub_organization_positions mengacu generasi/parent; tvclub_organization_members menghubungkan posisi/anggota. |
| Live (2) | live_streams → agenda nullable, archive_video nullable, manual_program nullable dan aktor; live_stream_events → stream/aktor |
| Monthly (5) | monthly_activity_reports → items/attachments/versions/events; attachment opsional ke item; event opsional ke version; aktor nullable dengan snapshot nama |
| Equipment (11) | equipment_categories → equipment_models → equipment_units; equipment_model_photos/unit_photos; equipment_loans → equipment_loan_units dan equipment_returns; equipment_return_photos; equipment_operation_keys; equipment_audit_log |
| Social (6) | tvclub_social_accounts → posts/account_observations; posts → post_observations serta member/program opsional; tvclub_social_report_versions per periode; tvclub_social_events audit dengan target polimorfik |

Daftar core/legacy tepat: `ms_banner`, `ms_news`, `ms_program`, `ms_video`, `roles`, `users`, `tr_perliputan`, `tr_usercounter`, `wpitv_agendas`, `wpitv_organization_positions`, `tvclub_generations`, `tvclub_members`, `tvclub_groups`, `tvclub_group_members`, `tvclub_organization_positions`, `tvclub_organization_members`.

### Integritas yang wajib dipertahankan

- Slug program/berita, username, nama role, kode pengajuan, tahun generasi dan pasangan group/member serta posisi/member memiliki UNIQUE sesuai metadata. Jangan mengasumsikan semua label unik atau semua relasi boleh dihapus.
- Live: agenda_id, youtube_id, archive_video_id masing-masing UNIQUE nullable. CHECK menjaga lifecycle, panjang YouTube ID, versi >=1 dan tuple detail manual; FK dan service menjaga koordinasi agenda serta arsip. Event history tidak diedit/dihapus dari alur aplikasi.
- Monthly: UNIQUE `(division, period_start)`, `(report_id, revision_number)`, sumber item `(report_id, source_type, source_id)`, storage `(disk, storage_key)`. CHECK menjaga divisi, status, tanggal awal bulan, versi dan pasangan sumber. FK komposit report/item serta report/version mencegah lampiran/event lintas laporan. Source item adalah referensi kontekstual, bukan FK polimorfik ke seluruh sumber.
- Equipment: kategori `code`, model `model_code`, unit `asset_code`, `(model_id, unit_no)`, nomor pinjam, dan `(loan_id, unit_id)` unik. `open_unit_id` adalah kolom STORED GENERATED ber-UNIQUE untuk satu pinjaman terbuka per unit; jangan diisi aplikasi.
- FK komposit `(return_id, loan_id)` detail → returns dan `(loan_unit_id, return_id)` foto → loan_units mengikat bukti pada transaksi/retur yang benar. CHECK outcome menjaga tuple resolution: on_loan belum resolved; returned wajib return/kondisi/waktu/due/late; lost wajib waktu/due/late tanpa return; voided tanpa due/late/return.
- Tidak ada status header pinjaman, kolom original_due_date, atau tabel stok agregat. Progress diturunkan dari detail: active/partially_resolved/closed/voided; kombinasi kosong/void campuran = integrity_error. Overdue dihitung terpisah dari fakta is_late pada resolution.
- Transaksi Equipment menyimpan snapshot identitas dan fakta checkout/resolution, UUID operasi dengan actor/hash/result, version dan audit. Urutan lock konsisten: header pinjaman bila relevan → kategori checkout → model terurut → unit terurut → detail terurut. Jangan melemahkan UNIQUE atau menonaktifkan FK/CHECK.
- Kondisi unit good/minor_damage/major_damage terpisah dari status active/maintenance/lost/retired. Unit ditemukan masuk maintenance; retired terminal; outcome loss lama tidak berubah saat aset ditemukan.
- Social: UNIQUE platform/account_key dan platform/handle; posting account/content_key dan account/url_hash; versi period/revision. Koreksi observasi memakai corrects_id unik dan FK komposit pemilik yang sama. Schema mendukung histori observasi; belum ada UI lengkap koreksi/account observation yang ditemukan pada Catalog saat handover.

### Selisih database dengan repository

Ledger lokal berisi **25 migration historis**; branch hanya memuat **sembilan migration** Live/Monthly/manual Live dan tidak menyediakan migration pembentuk core/Equipment/Social existing. Metadata tabel aktual lebih otoritatif daripada nama ledger lama. Jangan menjalankan migrate untuk membangun sistem dari DB kosong dan berharap semuanya terbentuk.

`database/sql/social_media_phase1.sql` tersedia tetapi **belum diterapkan**:

1. `tvclub_social_reports` belum ada, padahal Report/controller membaca tabel tersebut.
2. `social_accounts_check_1` aktual hanya menerima tiktok/instagram/facebook; kode juga menawarkan youtube.
3. `tvclub_social_posts.url` dan `url_hash` aktual NOT NULL; validasi kode mengizinkan kosong.

Skrip memperbarui CHECK platform, membuat kedua kolom nullable, dan membuat reports dengan FK author/version. Skrip apply-once, bukan migration otomatis atau bukti deployment; handover ini tidak mengeksekusinya.

## Media dan dokumen

- Disk `public`: `storage/app/public`, URL `/storage`; gambar program/berita/kru memakai OptimizedImageStorage, decode/resize/WebP. Model mempertahankan fallback gambar legacy.
- Disk `local`: `storage/app/private`; Equipment di `equipment/photos/{uuid}.png`, decode/reencode JPEG/PNG/WebP menjadi PNG, metadata/checksum server. Maksimal 5.000.000 byte per input/output dan dimensi 64–4096 piksel; foto lama dipertahankan.
- Lampiran Monthly di `monthly-activity/{reportId}/`, JPEG/PNG/PDF maksimal 10 MiB, stage lalu finalisasi, SHA-256 dan pemeriksaan akses file aktif/historis. Jangan mengekspos root ini melalui storage link.
- `docs/templates/LAPORAN AKTIVITAS SOSIAL MEDIA.xls` adalah template resmi sumber, belum tracked saat awal handover. Pembacaan berhasil: dua worksheet dan satu drawing pada sheet utama. SocialDocument memakai sheet pertama, membuang sheet kedua dari hasil, mengisi judul/periode/metrik/analisis/penanda tangan, lalu mengekspor **XLSX**, landscape, print area A1:G36. Preview/cetak HTML terpisah; belum ada generator PDF server.
- Snapshot Social menjaga nilai kosong berbeda dari nol; total ER memakai rata-rata nilai numerik platform, growth dari followers awal/akhir. Angka bulanan diinput operator; hitungan jenis publikasi dari data posting. Tidak ada integrasi pengambilan metrik API.

## Autentikasi dan authorization

Login session memakai username/password, hash Laravel, status aktif, rate limit dan CSRF. Remember token dinonaktifkan pada model User. Middleware sesi menetapkan batas idle/absolut (default konfigurasi 30 menit/8 jam) dan revokasi hash password; role/status diperbarui serta middleware admin didaftarkan persistent untuk Livewire. User/Role tidak memerlukan konfirmasi password tambahan pada route aktif saat ini.

Policy core dan Gate domain menentukan akses sesuai [matriks konteks](PROJECT_CONTEXT.md). Equipment mapping hanya Super Admin/Production; retire dan void default Super Admin. Social membutuhkan penugasan eksplisit `SOCIAL_MEDIA_MANAGER_IDS`/`SOCIAL_MEDIA_REVIEWER_IDS` dan role admin aktif; Super Admin tidak otomatis lolos. Saat inspeksi manager terkonfigurasi, reviewer belum; nilai ID tidak dicantumkan.

## Menjalankan secara lokal

1. Gunakan branch dan working tree yang diserahkan; review status sebelum checkout. Siapkan PHP beserta extension yang dibutuhkan lockfile (termasuk PDO MySQL, GD/WebP, fileinfo, mbstring, ZIP/XML untuk spreadsheet), Composer dan Node/npm.
2. Pada clone baru, jalankan `composer install` dan `npm ci` berdasarkan lockfile, bukan update dependency. Jangan memakai `composer create-project`: skrip post-create mencakup migrate.
3. Konfigurasikan `.env` secara privat sesuai `.env.example`: key aplikasi lokal, koneksi database yang diotorisasi, URL dan trusted host yang cocok, storage/session/cache/mail. Jangan menimpa key lingkungan existing. Tidak ada kredensial bawaan pada handover.
4. Untuk DB baru, minta schema baseline otoritatif dan rencana provisioning yang disetujui. Sembilan migration bukan bootstrap lengkap. Jangan restore/reset DB existing. Verifikasi Social terpisah sebelum membuka workflow laporan.
5. Pastikan direktori runtime writable. Buat `php artisan storage:link` hanya untuk public disk jika link belum ada dan lingkungan mengizinkan; jangan menghubungkan private disk.
6. Jalankan `php artisan serve` dan `npm run dev` pada terminal terpisah, atau `npm run build` untuk aset statis. `composer dev` juga menjalankan queue listener/Pail; gunakan jika memang dibutuhkan.
7. Akses URL server pada `/`, `/login`, `/admin`; gunakan akun yang diberikan pemilik secara privat. WhatsApp memerlukan konfigurasi kontak pemilik, Social memerlukan manager dan reviewer berbeda. Jangan menciptakan akun demo di sumber.
8. `php artisan route:list` merupakan pemeriksaan registrasi; tidak membuktikan koneksi, login atau fungsi semua halaman.

## Lingkungan tes

`tests/bootstrap.php` memanggil TestEnvironment; perlu konfigurasi tes privat dan `storage/app/testing/approved.json`, runtime/media/session/cache terisolasi serta akun DB yang disetujui. TestingDatabaseGuard mem-pin `wpitv_rebuild_testing`, menolak DB aplikasi/config cached; ReleaseConcurrencyGuard memverifikasi identitas/schema baseline. Manifest clone Live/Monthly lama tidak otomatis cocok dengan 41 tabel lokal atau Equipment baru. Jangan mengganti nama DB/guard agar lolos.

Unit murni Equipment dapat dijalankan tanpa bootstrap aplikasi sebagaimana AGENTS.md. `SocialPhaseOneTest` tersedia dengan fixture SQLite in-memory tersendiri; hasilnya tidak membuktikan constraint MySQL sumber. Smoke rollback Equipment adalah harness opt-in historis terhadap sumber, bukan pengganti clone atau perintah setup. Pengujian race dua koneksi memerlukan clone Equipment yang belum dibuktikan tersedia.
