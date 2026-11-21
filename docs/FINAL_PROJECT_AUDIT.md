# WPiTV Phase 6A — Audit proyek

**READY FOR FINALIZATION.** Fase ini memeriksa working tree pada branch `main` dengan HEAD `9a35b67`, bukti pengujian tersimpan, dan dua database secara read-only. Status sebelum dokumen dibuat mencatat 30 berkas tracked berubah dan 207 berkas individual untracked. Kode fase modernisasi, termasuk seluruh 18 migration additive, tersedia di working tree. Paket rilis dari HEAD saja belum mencakup perubahan tersebut. Hasil tes di bawah berasal dari fase terdahulu.

## Inventaris dan bukti

| Area | Implementasi yang ditemukan | Bukti dan batas |
| --- | --- | --- |
| Website publik dan navigasi | `routes/web.php`, Livewire Public, layout publik Blade. Homepage, Tentang dan struktur organisasi, pencarian, penghitung agregat, navigasi desktop/mobile. Banner sudah retired. | Feature Homepage, About, GlobalSearch, OrganizationStructurePublic, VisitorCounter dan BannerRetirement. UAT lintas halaman terbuka. |
| Berita | Livewire News publik/admin, model News, NewsPolicy, NewsHtmlSanitizer, upload gambar dan Blade detail. | NewsPages, NewsManagement, NewsHtmlSanitizer dan FileAuthorizationHardening. |
| Program dan Tayangan | Program/Video, policy masing-masing, Livewire CRUD dan daftar/detail publik, relasi Program, player YouTube dan featured video. | ProgramPages, VideoPages, ProgramManagement, VideoManagement dan regresi integrasi Live. |
| Agenda dan Peliputan | WpitvAgenda/Coverage, policy dan Livewire publik/admin. Pengajuan, receipt terikat sesi, lookup kode dan telepon, perubahan status dan konversi ke Agenda. | AgendaPublic, AgendaManagement, PeliputanFlow, PeliputanSecurityHardening dan PeliputanManagement. |
| TVClub | Generasi, anggota, kelompok produksi dan jabatan per generasi. Policy per entitas, Livewire CRUD dan portal publik. Organisasi WPiTV terpisah. | TVClubPortal, tes admin TVClub dan OrganizationStructureManagement. Masuk regresi Phase 5G. |
| Equipment | InventoryCategory/Unit, EquipmentLoan/Item dan audit, lima migration, layanan transaksi, Livewire inventaris/peminjaman dan laporan. Checkout, retur parsial, hilang/pulih, perpanjangan dan koreksi. | Suite terakhir yang terdokumentasi pada 3F.1 lulus 133 tes/601 assertions, termasuk delapan race. Browser Production memeriksa checkout dan retur parsial serta Super Admin memeriksa dashboard/laporan. [Verifikasi Equipment](EQUIPMENT_VERIFICATION.md) · [Verifikasi 3F.1](LIVE_STREAMING_VERIFICATION.md) |
| Live Streaming | LiveStream/Event, dua migration, LiveStreamLifecycle dan policy. Agenda menyuplai jadwal, status operator, visibility, tautan arsip Video. Halaman admin/publik, homepage, Program dan dashboard. | 3F.1 mencatat 726 Feature/3.556 assertions dan empat race Live/44 assertions. Browser memutar video YouTube contoh dan memeriksa fallback. Siaran nyata WPiTV belum diverifikasi. [Verifikasi Live](LIVE_STREAMING_VERIFICATION.md) |
| Laporan Kegiatan Bulanan | Lima model/tabel, MonthlyActivityLifecycle, kandidat hybrid, editor, submit/revisi/approve, versi, lampiran privat, widget, CSV approved dan cetak A4. | 4G lulus 23 integrasi/396 assertions dan 215 regresi/952. Browser E2E file chooser PARTIAL, dialog Save as PDF tertunda. [Verifikasi Laporan Bulanan](MONTHLY_ACTIVITY_REPORT_VERIFICATION.md) |
| Laporan Media Sosial TVClub | Enam model/tabel, TVClubSocialService dan policy, akun/konten, observasi manual, koreksi append-only, rekap WIB, versi terbit, dashboard dan dua CSV. | 5G lulus 462 unit/2.140 assertions, 16 integrasi/21.771 dan 155 regresi/1.304. Bukti Chrome 5F.1 mencakup kedua unduhan oleh kedua role. [Verifikasi Media Sosial](TVCLUB_SOCIAL_MEDIA_REPORT_VERIFICATION.md) |
| Autentikasi dan admin | `routes/auth.php` memasang login, logout, konfirmasi dan pembaruan password. Users/Roles untuk Super Admin. Middleware sesi aktif, password-HMAC, timeout dan throttling. Statistik pengunjung hanya Super Admin. | Authentication, SessionHardening, PasswordUpdate, PasswordConfirmation, RoleSecurity, AccessControl dan ProductionSecurity. Controller auth bawaan yang tersisa perlu dibedakan dari rute yang terpasang. |
| Dashboard dan ekspor | AdminDashboardMetrics, widget berbasis policy, EquipmentReportExportController, MonthlyActivityDocumentController dan TVClubSocialExportController. | DashboardTest dan integrasi per modul. CSV sosial mencapai peak PHP 92,8 MiB pada fixture 5.000 unggahan/500 akun. JSON dimuat penuh dan CSV ditulis per baris. |
| WhatsApp dan YouTube | CoverageWhatsApp membuat tautan `wa.me` pemohon/admin untuk koordinasi dengan tindakan pengguna. Tayangan dan Live memakai tautan/player YouTube. Live membentuk iframe `youtube-nocookie.com` dari ID tervalidasi dengan autoplay nonaktif. | CoverageWhatsAppTest, PeliputanFlow, unit parser dan QA Live. Tujuan WhatsApp dan pemutaran siaran WPiTV pada lingkungan rilis belum dibuktikan. Cakupan yang ditemukan berupa tautan dan embed, dengan status Live manual. |

## RBAC dan keamanan

| Role | Hak yang terlihat pada policy dan route |
| --- | --- |
| Super Admin | Akses modul legacy pada matriks, inventaris/peminjaman dan Live. Review laporan kedua divisi. Kelola akun sosial, baca konten dan ekspor sosial. |
| Admin Production | Program/Tayangan, Agenda/Peliputan dan TVClub legacy, organisasi WPiTV, Equipment dan Live. Susun laporan divisi Production. |
| Admin News | Berita dan lihat/buat/edit Tayangan. Susun laporan divisi News. |
| TVClub | Program dan pengelolaan TVClub legacy. Kelola akun, unggahan, metrik dan versi laporan sosial. |

- Matriks RBAC masih berisi modul legacy. Lengkapi baris Equipment, Live, laporan bulanan, dan media sosial berdasarkan policy sebelum UAT. Pada rute yang ditinjau, admin memakai middleware auth/sesi/aktif/role. Beberapa rute berbagi grup admin umum dan mengandalkan policy komponen/controller, termasuk laporan bulanan, Users dan Roles. Bukti akses langsung dan Livewire tersedia pada tes terkait. Cakupan audit berupa pembacaan kode dan bukti tes, dengan konfirmasi melalui akun UAT masih terbuka. [Matriks RBAC](RBAC_ACCESS_MATRIX.md)
- Ekspor laporan bulanan dan sosial memakai POST dan CSRF. Ekspor Equipment memakai GET dengan cek role dan policy, serta menulis audit saat ekspor. Tinjau kontrak metode ini sebelum rilis karena GET menimbulkan perubahan state pada tabel audit. CSV memakai sanitasi formula. Equipment dan sosial memproyeksikan field ekspor dan tes memeriksa pengecualian data pribadi.
- Media publik diproses menjadi WebP melalui OptimizedImageStorage, HTML berita disanitasi, dan upload sementara Livewire serta lampiran laporan disimpan privat. Unduhan lampiran aktif/historis memeriksa policy laporan dan keterkaitan ID. UAT perlu membuktikan file picker, download, dan pemulihan file privat bersama database.
- `.env` lokal memakai `APP_ENV=local` dan `APP_DEBUG=true`. Testing dan contoh produksi menonaktifkan debug. File environment privat dan state backup diabaikan Git. Debug lokal perlu tetap berada pada lingkungan lokal. Konfirmasi APP_DEBUG, HTTPS, cookie, trusted hosts/proxies, DocumentRoot `public`, penolakan akses ke file privat dan log, serta CSP report-only pada staging sebelum persetujuan rilis. Konfigurasi host produksi belum diverifikasi.

## Database dan backup

Pemeriksaan read-only mencocokkan identitas `wpitv` dan `wpitv_testing` dengan baseline 35 tabel masing-masing. Hash skema dan baris cocok serta fixture testing bersih. Baseline yang cocok memuat 18 catatan migration per database dan definisi dengan 57 FK, 29 CHECK, serta 33 UNIQUE KEY di luar primary key. Hash 6 berkas migration sosial cocok dengan state provisioning. Runner testing DML-only dan pembacaan dari database utama ditolak untuk akun tersebut.

| Dump privat terbaru yang ditemukan di backup proyek | Tabel | SHA-256 |
| --- | ---: | --- |
| `wpitv-before-social-20261112-045416-a1d308.sql` | 29 | `66f907c390697514053e5b797ff3028cebc41ba5696efd5220a4f67a641669` |
| `wpitv_testing-before-social-20261112-045418-a64525.sql` | 29 | `0690b0c3bb79be96e71f65a990b0bb03acf1579176f7ae674be9a6d1507ead8a` |

Footer lengkap dan SHA-256 dihitung ulang. Jumlah 29 tabel menunjukkan dump mendahului provisioning sosial. Backup penuh 35 tabel belum ditemukan di lokasi backup proyek yang diperiksa. Restore drill masih terbuka dan target disposable belum terverifikasi. Siapkan instance terpisah dengan identitas dan port/socket yang diverifikasi, data directory serta kredensial tersendiri, dan tanpa koneksi aplikasi ke kedua database aktif. Drill perlu mencakup SQL dan salinan media publik serta lampiran privat, lalu mencocokkan migration, DDL, hitungan/hash baris dan keterbacaan file.

## Prioritas dan rencana Phase 6B–6F

| Fase | Prioritas dan keluaran |
| --- | --- |
| 6B — Baseline rilis | **Tinggi.** Inventaris dan review perubahan tracked/untracked untuk calon rilis. Selaraskan matriks RBAC dan checklist produksi yang masih menyebut 17 tabel, serta referensi runbook ke `DEPLOYMENT.md` di root. Tetapkan urutan provisioning additive, versi PHP/MySQL dan pemisahan akun runtime/DDL. |
| 6C — Backup dan recovery | **Gate produksi.** Buat backup 35 tabel dan file yang konsisten. Validasi manifest/hash, lalu jalankan restore drill pada target disposable terpisah. Catat hasil, durasi, serta strategi rollback bila ada data baru setelah cutover. |
| 6D — UAT dan regresi | **Gate produksi.** Jalankan checklist empat role pada staging/QA. Dahulukan lampiran, Save as PDF, integrasi eksternal dan pengujian lintas modul yang belum dibuktikan. Jalankan satu putaran regresi pada calon rilis, termasuk suite konkurensi terpisah dari default. |
| 6E — Rehearsal staging | **Gate produksi.** Uji artefak build, cache rute/view, konfigurasi server, hak DB, recovery, batas upload/ekspor, log dan pemeriksaan dependensi pada lingkungan setara produksi. Dokumentasikan hasil dan keputusan go/no-go. |
| 6F — Rilis dan pemantauan | Setelah persetujuan pemilik proyek dan gate lulus, gunakan artefak yang ditetapkan, backup terverifikasi, smoke test dan pemantauan. Tetapkan pemicu rollback serta penanggung jawab. |

[Checklist UAT](FINAL_UAT_CHECKLIST.md) · [Checklist produksi](PRODUCTION_CHECKLIST.md) · [Panduan deployment](../DEPLOYMENT.md)

## Pembaruan Phase 6B

Baseline 35 tabel dan 18 catatan migration diverifikasi ulang. Dua dump lengkap privat menggantikan cakupan 29 tabel sebagai bukti backup terbaru. Matriks RBAC kini mencakup empat modul modernisasi dan checklist produksi memakai baseline aktual. Status restore, manifest working tree dan pekerjaan Phase 6C tercatat pada [verifikasi baseline dan backup](RELEASE_BASELINE_AND_BACKUP_VERIFICATION.md).

## Pembaruan Phase 6C

Phase 6C melaksanakan regresi lintas modul dan verifikasi keamanan. Catatan rencana di atas merekam urutan yang diusulkan saat Phase 6A. Backup dan restore SQL telah diselesaikan pada Phase 6B. Laporan 6C mencatat hasil suite, selisih manifest dokumentasi, dan pekerjaan penerimaan yang tersisa. [Verifikasi regresi dan keamanan](FINAL_REGRESSION_AND_SECURITY_VERIFICATION.md)

## Pembaruan Phase 6C.2

Live Streaming kini menyiapkan pembuatan Manual sebagai pilihan default dan pembuatan Berdasarkan Agenda. Perubahan memerlukan dua migration yang belum diterapkan. Tes MySQL untuk jalur baru dan calon rilis UAT bergantung pada provisioning terisolasi. Backup SQL Phase 6B tetap menjadi bukti recovery skema sebelum fitur ini. [Audit Live, prosedur provisioning dan delta manifest](LIVE_DUAL_CREATION_VERIFICATION.md)


## Equipment 6C.4B–D

Equipment memakai klasifikasi stok eksklusif, revisi foto privat, serta bukti per item dalam satu receipt. Baseline 6B merekam kondisi historis. Lihat [delta skema, backup, pengujian dan manifest](EQUIPMENT_STOCK_AND_PHOTO_VERIFICATION.md).


## Phase 6C.4F · Pengelolaan kuantitas

Equipment mendukung jumlah stok awal, penyesuaian dengan alasan, serta alokasi deterministik saat checkout. Keanggotaan barang memakai relasi tambahan. Bukti pengujian, baseline dan hash terdapat dalam [verifikasi Equipment](EQUIPMENT_STOCK_AND_PHOTO_VERIFICATION.md).

### Phase 6C.4G | Inventaris berbasis barang

Daftar inventaris mengelompokkan unit berdasarkan relasi induk eksplisit. Penyesuaian stok tersedia dari halaman detail dan edit. Pemeriksaan lokal menemukan 39 tabel dan 24 entri migration pada setiap database. Verifikasi, manifest perubahan, dan batas QA tercatat dalam [laporan Equipment](EQUIPMENT_STOCK_AND_PHOTO_VERIFICATION.md). UAT operasional dan deployment tetap terpisah.

### Phase 6C.4H | Peminjaman alat berdasarkan jumlah

Halaman Tambah Peminjaman Alat memakai baris per barang. Petugas dapat menggabungkan alokasi otomatis dan pilihan manual dalam satu transaksi. Review menampilkan kode unit yang akan diserahkan. Service checkout existing mengunci induk dan unit, memeriksa versi dan kelayakan, lalu menyimpan transaksi beserta audit. Rincian pengujian ada di [verifikasi Equipment](EQUIPMENT_STOCK_AND_PHOTO_VERIFICATION.md).

### Phase 6C.4J | Equipment schema expansion

Phase 6C.4J is PARTIAL. Both databases remain at 39 tables and 24 entries. Verify the expanded schema on an isolated testing target before reviewing rollout. [Verification](EQUIPMENT_DATABASE_RESTRUCTURING_VERIFICATION.md)

### Phase 6C.4K | UI Equipment dan POS

Kategori kini memperlihatkan jumlah master dan unit fisik melalui relasi item. Kartu inventaris, POS, detail dan dashboard memakai klasifikasi stok yang sama. Edit master dipisah dari edit unit. Pembacaan receipt mempertahankan path audit legacy. Hasil pengujian dan rincian perubahan terkait dicatat dalam [verifikasi Equipment](EQUIPMENT_STOCK_AND_PHOTO_VERIFICATION.md).
