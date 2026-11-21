# Live Streaming Integration & Browser QA

**Status terbaru Phase 3F.1 (11 November 2026): READY LOCAL.** Kedua regresi Phase 3F sudah diperbaiki pada tes, seluruh suite akhir lulus, dan backup lengkap 24 tabel telah diverifikasi. Restore belum diuji karena tidak ditemukan lingkungan disposable yang tersedia. Lihat bagian Phase 3F.1 di akhir dokumen; status dan kendala pada bagian fase sebelumnya merupakan catatan historis masing-masing fase.

Tanggal: 9 November 2026. Acuan: permintaan Phase 3C.1 **Integration & Browser QA**, melanjutkan implementasi Phase 3C.2.

**READY untuk lingkup integrasi lokal yang diuji.** Status ini bukan verifikasi siaran YouTube nyata, frontend Live publik, seluruh modul aplikasi, atau deployment produksi lain.

## Database, backup, dan isolasi

- Koneksi lokal MySQL 8.0.30 diverifikasi sebelum DDL. Awalnya kedua database berisi 22 tabel tanpa tabel Live. PK `wpitv_agendas.id`, `ms_video.id`, dan `users.id` adalah `INT UNSIGNED`; FK Live yang menunjuknya memakai tipe yang sama. Relasi antar-dua tabel Live memakai `BIGINT UNSIGNED`.
- Backup baru `wpitv` dan `wpitv_testing` dibuat sebelum DDL menggunakan mysqldump single-transaction. File dapat dibaca, seluruh 22 struktur tabel dan INSERT tercakup, footer dump lengkap serta SHA-256 diverifikasi ulang sebelum penerapan. Restore tidak dilakukan. Dump dan hash disimpan privat pada `storage/app/testing/live-backups/` dan `live-integration-state.json`, diabaikan Git.
- Hanya dua migration berikut yang diterapkan: `2026_11_09_000001_create_live_streams_table.php`, kemudian `2026_11_09_000002_create_live_stream_events_table.php`. Eksekusi terbatas pada `up()` kedua file yang hash-nya telah diperiksa, dengan koneksi DDL lokal sah dan identitas database eksplisit. Dua baris riwayat migration ditambahkan per database. Tidak menjalankan seluruh migration pending, reset, refresh, down, truncate, atau seeder. Tidak ada seeder Live baru.
- Testing diprovision lebih dahulu. Sesudah 25 tes integrasi/regresi awal lulus dan fixture bersih, migration yang sama diterapkan ke database utama. Hasil akhir kedua database: **24 tabel, 7 FK baru, 4 CHECK baru enforced**. Unique Agenda/ID YouTube/arsip, indeks, engine InnoDB, presisi DATETIME(6), dan urutan FK sesuai file migration. CHECK menggunakan SQL MySQL khusus dalam migration; rollback otomatis tetap ditolak demi histori.
- Seluruh struktur 22 tabel lama dan fingerprint multiset SHA-256 isi setiap tabel dibandingkan sebelum/sesudah. Data historis dan lima baris riwayat migration lama tetap sama; hanya dua baris riwayat baru ditambahkan. Verifikasi terakhir setelah semua tes/browser memastikan tidak ada fixture tersisa pada testing dan tidak ada perubahan data utama selain pencatatan migration baru. Penghitung AUTO_INCREMENT dikecualikan dari perbandingan DDL testing karena fixture dapat memajukannya tanpa menyisakan data.
- Test suite memakai `wpitv_test_runner@localhost`, tepat SELECT/INSERT/UPDATE/DELETE pada `wpitv_testing`. Probe SELECT ke `wpitv.users` ditolak; runner tidak mempunyai DDL. Akun DDL tidak dipakai menjalankan tes. `.env` utama dan kredensial tidak diubah; `.env.testing`, manifest, dump, dan operator helper tetap Git-ignored.
- Manifest privat `storage/app/testing/approved.json` diperbarui setelah skema nyata diverifikasi: allowlist 24 tabel, hash SHOW CREATE, referensi otorisasi pengguna, dan hash sebelumnya dipertahankan. `EquipmentConcurrencyGuard::TABLES` mengikuti allowlist tersebut tanpa melemahkan pemeriksaan identitas/privilege/runtime. Preflight testing lulus: environment testing, session/cache array, storage/cache/view terpisah, tanpa konfigurasi produksi.

## Masalah yang ditemukan dan diperbaiki

1. **Hook exception Livewire berulang sampai kehabisan memori.** Livewire menyuntikkan argumen dengan nama `e`, sedangkan concern sebelumnya memakai `$exception`. Pada konflik versi, container mencoba membangun `Throwable` dan kembali memanggil hook. `app/Livewire/Concerns/HandlesLiveStreamErrors.php` kini mengikuti kontrak `$e`; aturan konflik dan sanitasi exception tetap sama. Ditambahkan regresi unit kontrak parameter serta integrasi stale form nyata. Tidak menaikkan memory limit atau mengubah service bisnis.
2. **Tombol menu admin mobile tersembunyi.** Aturan lama Mazer tidak mencakup navbar `.admin-topbar`. `resources/css/admin.css` menampilkan burger pada breakpoint mobile existing. Browser memverifikasi tombol membuka/menutup sidebar pada lebar 390 px; tidak mengubah menu/otorisasi atau tema publik.

## Hasil pemeriksaan aktual

| Pemeriksaan | Hasil |
| --- | --- |
| `LiveStreamingManagementTest` | 14 tes, 134 assertions lulus pada MySQL testing |
| Regresi `AgendaManagementTest` dan `AgendaPublicTest` | 12 tes, 76 assertions lulus; total Live/Agenda **26 tes, 210 assertions** |
| Tiga suite unit Live tanpa bootstrap Laravel/database | **146 tes, 513 assertions**, PDO dicegah |
| Suite equipment existing | **131 tes, 586 assertions**: 110 unit guard/safety, 13 integrasi database, dan 8 konkurensi multi-process dengan commit nyata |
| JavaScript tema | `node --test tests/Unit/admin-theme.test.cjs`: **7 tes lulus** |
| Sintaks dan Blade | PHP lint file terkait lulus; kompilasi Blade Live/layout di memori dengan TOKEN_PARSE tercakup unit; JS syntax check lulus |
| Pint dan diff | Lima file PHP QA lulus Pint setelah perapian import/format; `git diff --check` lulus |
| Vite | Build berhasil, 60 modul; warning PostCSS opsi `from` tetap ada dan tidak menggagalkan build |
| Browser harness | 1 tes harness/2 assertions lulus; bukti visual dan interaksi diperiksa terpisah sebagaimana di bawah |

Integrasi meliputi create, scheduled → live → ended, cancel/reschedule/penutupan administratif, jadwal WIB lintas tengah malam dan UTC, perubahan Agenda/visibility atomik, audit actor server, rollback ketika insert audit gagal, stale version lewat service/Livewire, larangan direct write/quiet write/delete dependency, jadwal ended immutable, no-op tanpa audit ganda, arsip case-sensitive dan pelepasan tanpa menghapus Video, URL YouTube tidak valid, rendering/filter, serta penolakan route dan Livewire untuk News/TVClub. UNIQUE dan CHECK diuji pada MySQL nyata. Policy Program/Agenda/Video existing dipertahankan.

Perintah utama (tanpa migration/seeder/reset di suite):

```powershell
php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --do-not-cache-result --log-junit storage/app/testing/live-integration-junit.xml tests/Feature/Admin/LiveStreamingManagementTest.php tests/Feature/Admin/AgendaManagementTest.php tests/Feature/Public/AgendaPublicTest.php
php vendor/phpunit/phpunit/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/LiveStreamingFoundationTest.php tests/Unit/AgendaLiveIntegrationTest.php tests/Unit/LiveStreamingAdminTest.php
$env:WPITV_EQUIPMENT_CONCURRENCY = 'true'
php vendor/phpunit/phpunit/phpunit --configuration phpunit.equipment.xml --do-not-cache-result --log-junit storage/app/testing/live-equipment-regression.xml
node --test tests/Unit/admin-theme.test.cjs
npm run build
```

## Browser QA aktual

Browser in-app mengakses harness loopback `127.0.0.1:8765`, database testing, actor sintetis Admin Production, storage/session privat, dan asset hasil build. Tidak memakai akun pengguna atau membuka sesi admin pada database utama. `tests/Browser/LiveStreamingBrowserTest.php` memakai fondasi isolasi existing; fixture Live/Agenda dihapus dengan ID dan actor sintetis terverifikasi sebelum fixture induk dibersihkan.

- Desktop 1440 × 900 dan mobile 390 × 844 diperiksa. Dashboard, Agenda, Live, serta Unit Inventaris dirender; kartu/form/tabel dan sidebar diperiksa pada halaman tersebut. Tidak ada overflow halaman pada pengukuran mobile Agenda/Live; tabel memakai wadah responsif existing. Menu mobile berfungsi setelah perbaikan CSS.
- Toggle mengubah tema, ikon, dan label aksesibel; pilihan Light bertahan setelah reload. Dark bertahan antar-halaman dan setelah perubahan tampilan kalender → daftar Agenda lewat Livewire. Tidak ada error console teramati dalam pemeriksaan ini.
- SweetAlert2 konfirmasi diperiksa pada kedua tema; pembatalan dialog tidak mengubah transaksi. Pembatalan siaran sintetis dengan alasan berhasil dari browser: status Live cancelled, Agenda dibatalkan, versi 2, satu audit perubahan baru, dan toast sukses. Player tidak dimuat untuk status cancelled.
- Status manual, aktual YouTube belum diverifikasi, placeholder tanpa ID, dan label jadwal `23.00–01.00 (+1 hari) WIB` terlihat. Normal lifecycle lengkap, invalid URL, akses terlarang, serta konflik versi dibuktikan oleh tes integrasi; tidak semuanya diulang melalui browser.
- Tab QA ditutup, viewport override direset, server harness dihentikan, fixture dibersihkan; fingerprint database diverifikasi sesudahnya. Tidak ada klaim bahwa seluruh halaman admin, semua browser/OS, atau seluruh komponen telah diperiksa visual.

## File dan batas verifikasi

Perubahan fase ini: concern exception Livewire; CSS burger mobile; allowlist guard testing; `tests/Feature/Admin/LiveStreamingManagementTest.php`; `tests/Unit/LiveStreamingAdminTest.php`; `tests/Browser/LiveStreamingBrowserTest.php`; status dokumen desain; dan dokumen ini. Kedua migration existing diterapkan tanpa mengubah isinya. Operator helper, backup, manifest, state dan JUnit adalah artefak lokal Git-ignored; tidak berisi kredensial dalam output atau Git diff. Working tree sudah berisi pekerjaan fase sebelumnya; tidak melakukan commit/push atau membersihkannya.

Batas yang masih berlaku: restore backup belum dibuktikan; status/player YouTube siaran nyata dan akses jaringan/embed pihak ketiga belum diuji; browser memakai autentikasi fixture, bukan alur login produksi. Delapan tes konkurensi nyata adalah regresi equipment, bukan perlombaan dua proses service Live; Live memverifikasi stale version/atomicity melalui integrasi dan urutan lock Agenda → LiveStream melalui unit/static review. Perlombaan Live multi-process dan deployment lingkungan lain memerlukan verifikasi tersendiri sebelum mengklaim jaminan tersebut. Frontend Live publik dan KPI Live dashboard berada di luar lingkup ini.

## Phase 3D — Frontend Live publik (10 November 2026)

Status: READY untuk lingkup lokal yang diuji. Route guest `/live` dan `/live/{liveStream}` memakai Livewire publik. Empat bagian berhalaman (6 record/bagian) memisahkan Live, jadwal mendatang, jadwal mulai terlewat yang menunggu operator, dan arsip valid. Perbandingan jadwal memakai waktu kalender WIB; tidak mengubah lifecycle atau otomatis menyelesaikan siaran. Beberapa Live bersamaan didukung. Cancelled tidak masuk daftar tetapi detail publik konsisten menampilkan pembatalan tanpa alasan internal.

`PublicLiveStreams` memakai scope visibility/status pasangan existing dan projection kolom publik, eager loading Agenda/Program aktif/Video, pencocokan arsip case-sensitive. Komponen detail hanya mengirim ID Locked dalam state Livewire dan memeriksa ulang scope publik pada setiap render. Model actor/audit tidak dihydrate atau diserialisasikan. Internal, draft, jenis bukan Live, dan pasangan status tidak konsisten menghasilkan 404 langsung. Data judul/deskripsi/Program/jadwal berasal dari Agenda.

Player memakai helper server YouTubeVideoId (youtube-nocookie, autoplay=0), output escaped, title aksesibel, iframe responsif minimal tinggi 200px, tanpa API/player event yang mengubah status. ID kosong/tidak valid menghasilkan placeholder. Ended tanpa arsip valid memberi pesan rekaman belum siap; arsip valid ditautkan ke Tayangan existing. Tombol YouTube tetap tersedia untuk ID valid; cancelled tidak memuat player/link menonton. Tidak mendeteksi lifecycle YouTube secara otomatis.

File: `app/Support/PublicLiveStreams.php`, `app/Livewire/Public/LiveStreams/{Index,Show}.php`, dua Blade `resources/views/livewire/public/live-streams/`, route publik, layout publik (menu desktop/mobile), CSS publik scoped, komponen/Blade Agenda (eager CTA), `tests/Feature/Public/LiveStreamingPublicTest.php`, `tests/Browser/PublicLiveStreamingBrowserTest.php`, serta dokumen desain/verifikasi. Tidak mengubah migration, lifecycle, policy admin, dependency, atau source modul equipment.

Hasil aktual:

- Preflight database testing: wpitv_testing, wpitv_test_runner@localhost DML-only, session/cache array, storage terisolasi. Fixture fitur memakai rollback; fixture browser memakai guard dan cleanup ID sintetis existing. Tidak membuat fixture pada database utama.
- Tujuh tes publik baru: **7 tes/60 assertions lulus**. Termasuk 404 internal/draft/status tidak konsisten, safe embed/escaping, ID invalid/kosong, jadwal lewat/mendatang, multiple Live, arsip case-sensitive/mismatch, cancelled, pagination, CTA Agenda dan visibility yang ditarik.
- Regresi Agenda publik + Live admin: **19 tes/162 assertions lulus** (dijalankan bersama enam tes publik awal, 25/213; ketujuh tes publik akhir dijalankan ulang sesudah penambahan skenario cancelled).
- Regresi Program/Tayangan/Homepage: **75 tes/223 assertions lulus**.
- PHP lint enam file terkait lulus; Pint file terkait dan routes lulus. Empat Blade (dua baru, Agenda, layout) dikompilasi di memori/TOKEN_PARSE tanpa bootstrap. Vite build berhasil (60 modul), warning PostCSS opsi from existing tetap ada. Diff check lulus.
- Browser in-app lewat loopback testing: desktop 1440x900 dark, mobile 390x844 light setelah toggle dan refresh; menu Live mobile membuka daftar, detail dan jadwal lintas malam terbaca. Tidak ada overflow horizontal pada mobile yang diukur. Iframe terbukti menuju youtube-nocookie dengan autoplay=0; ID sintetis menampilkan pesan YouTube “Video unavailable”, dan fallback penjelasan/tombol terlihat. Ini bukan bukti pemutaran siaran YouTube nyata. Harness 1 tes/2 assertions lulus; viewport direset, tab/server dihentikan.
- Fingerprint read-only akhir: kedua database tetap 24 tabel, data historis utama sama dengan baseline, tidak ada sisa fixture testing. Tidak menjalankan migration/seeder atau mengubah skema pada Phase 3D; tidak commit/push.

Batas: pemutaran siaran YouTube nyata tergantung ID, akses video, izin embed, dan jaringan; belum diuji dengan siaran aktif milik WPiTV. Tidak menambahkan API, autoplay, chat, akun publik, atau Video arsip otomatis. Screenshot browser diperiksa selama QA; tidak ada klaim seluruh browser/OS telah diuji.

### Pemeriksaan ulang Phase 3D — 10 November 2026

Ditemukan dan dihapus dua deklarasi route Live publik yang terduplikasi dalam grup admin. Kini nama live.index/live.show masing-masing tepat satu, URL `/live` dan `/live/{id}`, tanpa middleware auth. Regresi baru memeriksa URL hasil route(), keunikan nama, middleware dan akses guest langsung. Hasil ulang: **27 tes/229 assertions** (8 tes publik Live, 5 Agenda publik, 14 Live admin) lulus setelah preflight isolasi. Pint, PHP lint dua file, dan diff check lulus. Fingerprint akhir kedua database tetap 24 tabel, data utama utuh dan tidak ada fixture testing tersisa. Browser QA, Blade parsing dan Vite build mengacu hasil Phase 3D di atas; tidak diulang karena perbaikan hanya menghapus route duplikat dan menambah tes. Tidak mengubah skema, lifecycle, dependency, atau hak akses admin existing.

## Phase 3E — Homepage, Program, Dashboard Live (10 November 2026)

READY untuk integrasi lokal yang diuji. `LiveStreamHighlights` membaca scope/projection `PublicLiveStreams` di luar cache hero homepage: maksimal enam Live aktif, atau tiga jadwal WIB terdekat bila tidak ada Live aktif; tautan semua siaran selalu tersedia. Empty/error state aman disediakan. Featured Video Selector tidak diubah; tidak membuat iframe Live baru di homepage. Pada Program, query melalui Agenda.program_id memakai scope publik yang sama, tiga kelompok pagination independen (6 per halaman), eager Agenda/Program/Video, serta arsip exact-case dan ID valid yang menuju Tayangan existing. Daftar Tayangan tetap dipertahankan.

`LiveDashboardMetrics` menolak policy viewAny sebelum query untuk News/TVClub. Super Admin/Production mendapat tiga KPI: manual live (Agenda scheduled), jadwal scheduled di masa depan, ended/completed tanpa arsip valid (termasuk internal/mismatch/ID invalid). Arsip valid mengikuti pasangan publik completed dan ID Video exact-case. Agregasi satu query, jadwal maksimal lima dengan eager loading Agenda/Program: maksimal empat query Live terpisah dari dashboard existing. Hanya ID, judul, Program, jadwal dan status dimuat pada daftar. Quick actions Live/Agenda/Tayangan mengikuti policy masing-masing. Kegagalan SQL disanitasi tanpa previous exception; UI tidak mengarang angka nol ketika gagal. Widget equipment dan policy existing tidak diubah.

File fase ini: `app/Services/{LiveStreamHighlights,LiveDashboardMetrics}.php`; integrasi `AdminDashboardMetrics`, `Public/Home`, `Public/Programs/Show`; Blade baru `livewire/public/live-streams/highlights` dan `livewire/admin/dashboard/live`; include homepage/Program/dashboard; `tests/Feature/Public/LiveHighlightsTest.php`; `tests/Browser/LiveHighlightsBrowserTest.php`; assertion shell pada `DashboardTest.php` diselaraskan dengan class `d-xl-none` yang sudah ada sejak fase tema. Dua dokumen desain/verifikasi mencatat hasil ini.

Verifikasi aktual:

- Preflight wpitv_testing lulus: runner DML, session/cache array, storage terisolasi. Test fitur memakai rollback. Tiga tes highlights mencakup empty/upcoming/multiple-live homepage, pembacaan di luar cache hero, Program tanpa/dengan Live, internal/draft, arsip exact-case/mismatch, empat role, KPI, limit lima, maksimal empat query di role diizinkan dan nol query pada News/TVClub.
- Suite terarah highlights + dashboard + homepage + Program: **69 tes, 280 assertions lulus**. Regresi publik Live Phase 3D juga lulus pada run awal (8 tes); run gabungan awal menemukan satu assertion markup shell lama, kemudian diperbaiki dan seluruh suite dashboard dijalankan ulang. Bukan kegagalan fitur menu atau perubahan permission.
- PHP lint delapan file lulus; Pint scoped lulus; sembilan Blade dikompilasi di memori/TOKEN_PARSE; Vite build berhasil, 60 modul, warning PostCSS from existing tetap ada; diff check lulus.
- Browser loopback testing: homepage desktop light menampilkan jadwal dan link detail tanpa player Live tambahan; Program desktop dan mobile dark tetap menampilkan Tayangan existing serta kelompok Live; dashboard Production mobile light dan desktop dark menampilkan KPI, satu jadwal overnight WIB, dan quick actions sesuai policy. Pengukuran mobile Program 390 px tidak mengalami overflow. Skenario tanpa/satu/beberapa Live serta mismatch arsip dibuktikan lewat integration test, tidak semuanya diulang manual pada browser.
- Harness browser lulus **1 tes/2 assertions**. Fixture Program/Agenda/Live dan statistik satu kunjungan loopback homepage dibersihkan. Harness berikutnya mencatat baseline ID counter dan menolak counter loopback existing hari yang sama; cleanup hanya record counter baru dengan IP/tanggal QA terverifikasi. Pemeriksaan fingerprint akhir read-only lulus: kedua database tetap 24 tabel, data utama utuh, testing tanpa sisa fixture.

Tidak menjalankan migration/seeder, mengubah skema, menambah dependency, membuat arsip Video otomatis, polling/API YouTube, atau mengubah lifecycle. Tidak commit/push. Pemutaran siaran YouTube nyata dan deployment di luar lokal tetap belum diverifikasi.

Tambahan verifikasi akhir Phase 3E: setelah menambah assertion homepage tepat satu Live sebelum skenario beberapa Live, suite highlights diulang dan lulus **3 tes/45 assertions**. Pint test/cleanup guard lulus; fingerprint akhir kembali terverifikasi utuh dan tanpa fixture tersisa.

## Phase 3F — Final Verification & Hardening (11 November 2026)

**Verdict: PARTIAL.** Pengujian Live terarah dan konkurensi nyata lulus. Seluruh suite regresi belum hijau karena dua kegagalan existing di luar perubahan Live; tidak mengubah permission/config utama atau modul equipment untuk menyembunyikannya. Tidak ada migration, seeder, reset, wipe, truncate, dependency baru, commit atau push.

### Lingkungan, database, backup

Awal pemeriksaan MySQL gagal 2002 juga di luar sandbox: Laragon berjalan tetapi mysqld/listener3306 tidak tersedia. Pengguna memilih menyalakan MySQL sendiri. Setelah tersedia, preflight lulus menggunakan wpitv_testing dan wpitv_test_runner@localhost, tepat DML pada testing tanpa akses utama/DDL; session/cache array dan storage privat terverifikasi. Tes Feature memakai rollback. Tes committed/concurrency memakai guard manifest24, identitas kedua koneksi, schema hash, privilege/session dan denial probe utama, suite lock eksklusif, serta cleanup ID/token sintetis. Worker memakai PDO nonpersistent terpisah, verifikasi skema/privilege/runtime sendiri dan logging null.

Metadata dibaca ulang langsung pada kedua database: **24 tabel, 7 FK Live, 4 CHECK enforced, 3 UNIQUE Live**. FK INT UNSIGNED ke Agenda/Video/users dan urutan dependensi tidak berubah. Fingerprint SHOW CREATE dan seluruh baris dibandingkan dengan baseline approved sesudah provisioning: data utama utuh dan tidak ada sisa fixture testing. Ini diperiksa kembali setelah browser, race dan seluruh regresi.

Backup terbaru yang ditemukan pada lokasi lokal yang diperiksa: `storage/app/testing/live-backups/wpitv-before-live-20261109-025824-829185.sql` (2.912.675 byte) dan dump testing pasangannya (36.564 byte), tertanggal 9 November. Keduanya readable, footer complete dan SHA-256 cocok dengan manifest operator. Dump Downloads yang ditemukan lebih lama. **Backup ini sebelum dua tabel Live (22 tabel), bukan backup final24; restore belum diuji.** Tidak membuat backup baru atau melakukan restore. Backup24 terbaru dan latihan restore terisolasi tetap pekerjaan release berikutnya.

### Bug terbukti dan perbaikan terbatas

1. **Bypass mutasi model Live melalui quiet methods.** Regresi baru mula-mula gagal: `forceFill(...)->saveQuietly()` mengganti ID YouTube existing tanpa pemeriksaan versi/audit. `LiveStream::save()` kini menolak mutasi existing di luar persistence terkoordinasi, termasuk saveQuietly. Lifecycle memakai `saveCoordinated()` dalam transaksi aktif setelah lock/version/authorization; flag instance selalu dipulihkan lewat finally. Jalur delete/deleteQuietly selalu ditolak. Pembuatan fixture/model baru tetap memakai aturan guarded existing; query builder/raw SQL bukan security boundary yang diklaim dicegah oleh model.
2. **Append-only audit dapat dilewati event suppression.** LiveStreamEvent menolak perubahan existing dan delete pada override model, sehingga saveQuietly/deleteQuietly tidak melewati callback histori. Regresi unit memverifikasi keempat kombinasi quiet tanpa query.
3. **Overflow admin detail pada layar390px.** Browser menemukan scrollWidth425 dengan archive option panjang. Track grid halaman Live dibatasi `minmax(0,1fr)`, child min-width0 dan select max-width100%. Setelah build/reload, pengukuran 382 ≤ viewport390; perubahan hanya CSS scoped Live.

Tidak mengubah transisi lifecycle, isi snapshot, lock order, kebijakan empat role atau fitur publik. Fixture publik yang sengaja membangun relasi invalid/mismatch kini memakai query builder langsung pada database testing karena model produksi sudah menolak bypass; data utama tidak disentuh.

### Integrasi dan regresi aktual

| Pemeriksaan | Hasil aktual |
| --- | --- |
| Feature lengkap sebelum hardening | 724 tes; 723 lulus, 1 gagal (trusted hosts) |
| Feature lengkap sesudah hardening | **725 tes, 2.880 assertions; 724 lulus, 1 gagal yang sama** |
| Live/Agenda/public/highlights terarah sesudah guard | 33 tes/298 assertions lulus |
| Live admin sesudah tambahan Video-delete | **15 tes/141 assertions lulus** |
| Unit Live murni (tanpa bootstrap database) | **147 tes/518 assertions lulus** |
| JavaScript tema | **7 tes lulus**, termasuk preferensi, navigasi simulasi dan kontras pasangan token inti |
| Equipment suite existing | **131 tes/572 assertions; 130 lulus, 1 gagal laporan today**; delapan race equipment lulus |
| Diagnosis report dengan jam proses tengah hari WIB | **1 tes/14 assertions lulus**; bukan klaim suite jam aktual hijau |
| Browser harness final | **1 tes/2 assertions lulus**; interaksi visual dijelaskan terpisah |

Kegagalan existing yang tidak diperbaiki di luar scope:

- `ProductionSecurityTest::test_trust_storage_and_route_boundaries_remain_explicit` mengharapkan tepat `^localhost$`; testing lokal juga memuat allowlist eksplisit `^127\\.0\\.0\\.1$` untuk loopback QA. Tidak mengganti trusted hosts, melemahkan guard, atau menghapus assertion. Ini tetap gagal pada run final.
- `EquipmentIntegrationTest::test_report_filters_csv_privacy_and_export_receipt` membuat borrowed_at satu jam sebelum sekarang dan memfilter period=today. Run terjadi pada jam00 WIB sehingga fixture berada pada hari sebelumnya: hasil0, ekspektasi2. Satu tes yang sama lulus ketika clock proses Carbon/CarbonImmutable dibekukan pada tengah hari WIB, tanpa perubahan source/config modul. Test fixture perlu distabilkan pada fase equipment/testing terpisah.

Live mencakup scheduled→live→ended, cancel/reschedule, administrative ending, atomic audit/rollback event failure, stale service/Livewire, guard Agenda lewat jalur save/cancel/delete, histori/quiet guard, empat role route/policy/aksi, public internal/draft404, homepage/Program/dashboard, archive exact-case/mismatch. Tambahan Video-delete memverifikasi FK SET NULL: Live dan audit tetap ada, hasValidArchive false, tanpa event fiktif penghapusan yang tidak pernah dibuat lifecycle.

### Konkurensi dua proses dengan commit nyata

File `tests/Support/LiveScenarioTestCase.php`, `tests/Concurrency/Live/{LiveConcurrencyTest,worker}.php` menambahkan empat race tanpa transaksi luar yang membungkus worker. Parent hanya memegang lock Agenda sebagai barrier, menunggu kedua worker siap dan mencapai callback locking query, kemudian melepasnya. Kedua CONNECTION_ID berbeda; worker melakukan DB::transaction dan commit nyata secara independen.

**4 tes/44 assertions lulus**, diulang sesudah hardening:

- dua operator menandai Live yang sama;
- edit judul melalui saveAgenda bersamaan dengan perubahan status;
- cancel bersamaan dengan update konfigurasi;
- dua operator mengaitkan arsip yang sama.

Setiap race membuktikan tepat satu sukses, satu LIVE_STALE, version bertambah satu, audit bertambah satu, tidak ada penggabungan sebagian judul/status/ID, dan state Agenda/Live konsisten. Callback SQL worker memverifikasi urutan lock tepat **wpitv_agendas → live_streams**, ditambah **ms_video** hanya pada archive winner; stale berhenti sebelum lock Video. Semua fixture dibersihkan setelah child process selesai. Deadlock MySQL asli belum direproduksi secara deterministik; retry transaction tiga kali ditinjau statis/unit dan tidak diklaim sebagai uji deadlock nyata.

### Browser, YouTube, keamanan

Pengguna memberi URL `https://www.youtube.com/live/BmSjbLwq-VE?si=C-YrV3C7XKCbuncm` dan mengizinkannya untuk testing. Parser mengekstrak ID yang benar; fixture hanya pada wpitv_testing. Browser iframe youtube-nocookie memuat judul “AKU MANCING HORROR SENDIRI! - DREADMOOR” dan tombol Play; **gambar video bergerak terlihat setelah klik Play**. Sebelum klik tidak autoplay. Ini memverifikasi pemutaran video URL pengguna, **bukan status broadcast real-time, kepemilikan WPiTV, atau siaran WPiTV nyata**. Tidak memakai API atau mengubah lifecycle berdasarkan player.

QA publik/admin desktop dan mobile, light/dark, reload preference, pembaruan DOM Livewire, label tombol dan keyboard Escape dilakukan. Scheduled/live/ended/cancelled, player disembunyikan saat cancelled, ended tanpa arsip memakai placeholder, link arsip ke Tayangan dan fallback YouTube diperiksa melalui kombinasi browser/integration. SweetAlert nyata diuji untuk cancel, reschedule, mulai, akhir, administrative ending, konfigurasi URL, visibility dan link/unlink arsip. Validasi alasan kosong tampil aman; dua tab membuktikan stale form ditolak dengan LIVE_STALE. Event sukses/riwayat versi terlihat. Tidak ada error JavaScript pada pembacaan console terakhir; ini bukan sertifikasi aksesibilitas/WCAG seluruh halaman.

Header loopback dibaca: **CSP Report-Only**, frame-src mengizinkan www.youtube.com/www.youtube-nocookie.com; referrer strict-origin-when-cross-origin, permissions policy menolak kamera/mikrofon/lokasi. Iframe allow hanya encrypted-media/picture-in-picture/fullscreen, tanpa autoplay, src HTTPS terbentuk server dari ID valid. CSP report-only existing tidak diubah menjadi enforced; validasi kebijakan enforced sebelum produksi tetap diperlukan.

Fixture browser mencakup dua Agenda/Live dan satu Video existing sintetis. Cleanup memakai actor/prefix/ID, tab ditutup, viewport direset, server dihentikan; fingerprint akhir bersih. Tidak mengklaim semua ukuran layar/browser/keyboard path telah diuji.

### Quality gate, file, dan tindak lanjut

PHP lint **11 file terkait** lulus, Pint scoped lulus, sembilan Blade compile/TOKEN_PARSE lulus. Vite build ulang setelah CSS berhasil (60 modul); warning PostCSS opsi from existing masih muncul. Diff check lulus. Git status diperiksa: working tree memuat pekerjaan fase sebelumnya; tidak dihapus, commit atau push.

File aplikasi berubah pada fase ini: `app/Models/{LiveStream,LiveStreamEvent}.php`, `app/Services/LiveStreamLifecycle.php`, `resources/css/admin.css`. Tes berubah: `tests/Feature/Admin/LiveStreamingManagementTest.php`, fixture `tests/Feature/Public/{LiveStreamingPublicTest,LiveHighlightsTest}.php`, `tests/Unit/LiveStreamingFoundationTest.php`. File baru: fondasi/worker/test race di atas dan `tests/Browser/LiveFinalBrowserTest.php`. Dokumen ini diperbarui. Operator helper read-only, clock diagnostic, extra fixture dan hasil JUnit disimpan lokal Git-ignored; tidak berisi kredensial pada Git diff/output.

Langkah minimum berikutnya: selaraskan ekspektasi trusted-host test dengan konfigurasi loopback yang disetujui, stabilkan clock fixture equipment, lalu ulang dua regresi tersebut. Sediakan backup24 terbaru dan uji restore pada lingkungan disposable tersendiri; jangan restore ke utama. Verifikasi siaran WPiTV nyata dan deadlock terkontrol jika dibutuhkan sebelum mengklaim jaminan tersebut. **Tidak memberi verdict READY LOCAL untuk seluruh gate sampai kegagalan regresi ditangani.**

## Phase 3F.1 — Regression Fix & Final Local Readiness (11 November 2026)

**Verdict: READY LOCAL**, berdasarkan seluruh gate akhir di bawah. Ini tidak menyatakan restore, deployment produksi, deadlock MySQL asli, atau status siaran real-time WPiTV telah diverifikasi. Batas pengujian player Phase 3F tetap berlaku; fase ini tidak mengulang atau memperluas fitur Live.

### Dua regresi diperbaiki pada tes saja

1. `tests/Feature/ProductionSecurityTest.php`: assertion kini memeriksa tepat dua regex loopback yang telah disetujui, `^localhost$` dan `^127\.0\.0\.1$`, tanpa bergantung urutan. Entri tambahan/wildcard tetap membuat tes gagal. Tes HTTP baru menjalankan middleware TrustHosts dengan environment aplikasi sementara production (Laravel melewatinya pada testing/local), menerima kedua loopback, dan menolak host tidak sah serta host berakhiran mirip/subdomain dengan HTTP400. Environment dan state trusted hosts Request dipulihkan dalam finally. Koneksi tetap database testing; `.env`, `.env.testing`, config/security.php, proxy, role dan permission produksi/MySQL tidak diubah.
2. `tests/Integration/Equipment/EquipmentIntegrationTest.php`: tes CSV/filter today memakai waktu tetap **11 November 2026 pukul12:00 Asia/Jakarta**. Helper lokal menyimpan dan memulihkan clock Carbon serta CarbonImmutable lewat finally, termasuk ketika assertion/operasi melempar dan ketika helper bersarang. Regresi tambahan membuktikan perubahan tanggal **23:59:59 → 00:00:00 WIB**: pinjaman sebelum batas masuk hari lama, pinjaman tepat batas masuk hari baru; nilai tersimpan diperiksa pada UTC16:59 dan UTC17:00. Filter custom hari sebelumnya tetap tepat. Pemulihan clock outer dan clock semula ikut diuji, termasuk saat exception. Tidak mengubah EquipmentReports, checkout, transaksi produksi, bootstrap atau fondasi konkurensi.

### Hasil akhir aktual

| Gate | Hasil |
| --- | --- |
| ProductionSecurityTest terarah | **5 tes, 715 assertions lulus**, termasuk assertion lama dan penolakan host baru |
| Report/clock terarah | **3 tes, 29 assertions lulus** |
| Feature suite lengkap | **726 tes, 3.556 assertions lulus** |
| Equipment suite lengkap | **133 tes, 601 assertions lulus**; mencakup15 integrasi,110 unit safety/guard,8 konkurensi equipment |
| Live multiprocess concurrency | **4 tes, 44 assertions lulus**, PDO/proses independen dan commit nyata; stale/audit/lock order tetap terverifikasi |
| Unit Live tanpa database | **147 tes, 518 assertions lulus** |
| JavaScript tema | **7 tes lulus** |
| Quality gate | PHP lint kedua file tes lulus, Pint scoped lulus,9 Blade compile/TOKEN_PARSE di memori lulus, Vite build60modul berhasil, diff check lulus |

Percobaan pertama Equipment suite mengalami satu `EQCT_WORKER_TIMEOUT_ready` pada startup worker, bukan assertion bisnis. Cleanup/fingerprint diverifikasi sebelum retry. Skenario yang sama diulang tanpa mengubah timeout, lulus **1 tes/12 assertions**; suite penuh133tes lalu empat race Live juga lulus tanpa timeout. Kegagalan awal tetap dicatat di JUnit, bukan disembunyikan. Penyebab startup timeout sesekali belum dipastikan; tidak mengubah guard/deadline atau melakukan refactor di luar dua regresi. Warning PostCSS tentang opsi from tetap muncul tanpa menggagalkan build.

JUnit lokal Git-ignored: `storage/app/testing/phase3f1-feature.xml`, `phase3f1-equipment.xml` (percobaan awal), `phase3f1-equipment-final.xml` (seluruhnya lulus), dan `phase3f1-live-concurrency.xml`. Feature suite mencakup integrasi/RBAC empat role, public404 internal/draft, archive case-sensitive, homepage/Program/Agenda/dashboard serta hardening histori Phase 3F; tidak ada kegagalan yang dikecualikan dari suite akhir.

### Backup lengkap terbaru dan recovery

Setelah koneksi/fingerprint utama diverifikasi, backup baru dibuat memakai mysqldump lokal8.0.30 dengan single-transaction, quick, tanpa lock-tables, serta struktur/data/routine/event/trigger. INSERT tidak digabung agar jumlah setiap tabel dapat diperiksa. Ini hanya pembacaan database utama; tidak menjalankan migration/seeder atau restore.

- File privat: `storage/app/testing/live-final-backups/wpitv-24tables-20261111-011903-aacfca.sql`.
- Manifest pasangannya berakhiran `.manifest.json`; waktu pembuatan memakai WIB.
- **24 struktur tabel dan36.397 baris**; jumlah INSERT tiap tabel sesuai snapshot sumber, termasuk struktur tabel kosong. Seluruh INSERT tertutup, footer dump lengkap. Seluruh24 CREATE TABLE dibandingkan utuh dengan SHOW CREATE baseline sumber dan cocok.
- Ukuran **5.844.104 byte**. SHA-256: `97c190b79f4c972fc5cee2b6ae7f10e902b770a42ec7d73061a086d8a710fe4d`, diverifikasi ulang sesudah pembuatan.
- Snapshot multiset hash seluruh baris dan DDL sumber sebelum/sesudah backup identik dengan baseline utama approved. Pemeriksaan fingerprint akhir sesudah semua tes juga lulus. SHA-256 berkas dan verifikasi jumlah/DDL ini tidak menggantikan pembuktian restore atau perbandingan hasil restore per field.
- Direktori di luar public storage, Git-ignored, dengan ACL privat hanya pengguna Windows saat ini dan SYSTEM. Kredensial client hanya pada file temporer dalam direktori privat, dihapus lewat finally; pemeriksaan akhir menemukan **0 file .cnf**. Kredensial/SQL/data historis tidak dicetak ke output/log/Git diff. Backup dan manifest tidak tracked Git.

**Restore belum diuji.** Tidak ditemukan Docker/Podman CLI/binary atau konfigurasi restore disposable pada lokasi yang diperiksa. Dua proses mysqld yang terdeteksi adalah pasangan parent/child Laragon, bukan bukti dua lingkungan terisolasi. Tidak memakai instance/database yang tidak teridentifikasi, tidak membuat instance baru, dan tidak melakukan restore pada wpitv maupun wpitv_testing. Latihan restore pada lingkungan disposable terpisah tetap risiko recovery yang harus dituntaskan sebelum klaim kesiapan produksi.

### Safety akhir, file dan batas kesiapan

Preflight runner tetap wpitv_test_runner@localhost, DML-only pada wpitv_testing, denial akses utama, session/cache array dan storage privat. Seluruh tes database berjalan berurutan antar-suite committed, memakai guard identitas/privilege/schema dan cleanup existing. Pemeriksaan metadata akhir: kedua database **24 tabel**, Live **7 FK,4 CHECK enforced,3 UNIQUE**. Fingerprint utama dan skema tidak berubah; testing tidak menyisakan fixture/jurnal run.

Perubahan kode reviewable fase ini terbatas pada **dua file tes di atas** dan dokumen ini. Helper backup/verifikasi dan artefak JUnit disimpan lokal Git-ignored. Tidak mengubah source bisnis, konfigurasi utama/testing, skema, akun/hak MySQL, dependency, atau modul lain. Asset build merupakan hasil turunan existing. Working tree tetap memuat pekerjaan fase sebelumnya; tidak membersihkan perubahan tersebut, commit atau push.

Batas tersisa: restore disposable belum tersedia/diuji, startup worker pernah timeout tetapi retry terarah/penuh lulus, dan warning PostCSS existing. Verifikasi siaran WPiTV real-time serta reproduksi deadlock MySQL asli dari Phase 3F tidak ditambahkan pada fase regresi ini. Semua gate lokal yang diminta dan dapat dijalankan telah lulus; **READY LOCAL tidak sama dengan jaminan recovery atau rilis produksi**.
