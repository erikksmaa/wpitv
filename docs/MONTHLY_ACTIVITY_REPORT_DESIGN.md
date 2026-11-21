# Laporan Kegiatan Bulanan WPiTV

Phase 4A · Audit dan desain · 11 November 2026

## 1. Temuan audit

Audit memakai source repository dan snapshot DDL lokal yang diverifikasi pada Phase 3F.1. Baseline tersebut mencatat 24 tabel pada `wpitv` dan `wpitv_testing`. Pemeriksaan fase ini tidak membuka koneksi database. Rujukan baseline dan hasil pengujian sebelumnya ada di [LIVE_STREAMING_VERIFICATION.md](LIVE_STREAMING_VERIFICATION.md).

| Sumber | Bukti yang dapat digunakan | Batas interpretasi |
| --- | --- | --- |
| Agenda, `WpitvAgenda` / `wpitv_agendas` | Judul, jenis, `agenda_date`, jam, deskripsi, status, relasi Program/Peliputan/kelompok | `completed` adalah status sekarang. Tanggal Agenda adalah jadwal, bukan cap waktu pelaksanaan aktual. |
| Peliputan, `Coverage` / `tr_perliputan` | Nama kegiatan, `event_date`, jam dan status `pending`, `approved`, `in_progress`, `completed`, `rejected` | Tidak ada `completed_at`. `updated_at` bukan bukti penyelesaian. Nama pemohon, telepon, kode permohonan, deskripsi mentah dan `admin_note` tidak masuk proyeksi otomatis. |
| Program, `Program` / `ms_program` | Identitas program, relasi ke Agenda dan Tayangan | Program adalah konteks kegiatan. Pembuatan atau perubahan master Program tidak otomatis dihitung sebagai kegiatan. |
| Berita, `News` / `ms_news` | Judul, ID, slug dan `created_at` sebagai tanggal pencatatan | Tidak ada `published_at` atau status editorial terpisah pada snapshot. Tanggal pencatatan perlu diberi label dan dapat diganti dengan tanggal kegiatan yang dikonfirmasi. |
| Tayangan, `Video` / `ms_video` | Judul, `air_date` nullable, `program_id`, `user_id`, ID YouTube | `user_id` bukan penanda divisi historis. `air_date` yang kosong memerlukan konfirmasi. Status video atau hasil penayangan tidak dapat disimpulkan dari ID YouTube. |
| Live | Relasi Agenda → Live dan referensi arsip Tayangan yang sudah tersedia | Gunakan Agenda sebagai entri kegiatan dan Live sebagai konteks. Arsip Tayangan dapat menjadi bukti pendukung untuk kegiatan yang sama. |

Relasi Agenda–Peliputan–Program serta Tayangan–Program sudah tersedia. FK legacy ke pengguna dan Program memakai `INT UNSIGNED` dan beberapa relasi historis memakai `ON DELETE SET NULL`. Laporan perlu menyimpan referensi dan bukti ringkas yang tetap dapat dibaca ketika sumber diubah atau hilang. Rujukan source ada pada `app/Models/{WpitvAgenda,Coverage,Program,News,Video}.php`.

`routes/web.php` menempatkan pengelolaan sumber pada kelompok admin yang memakai autentikasi, sesi valid dan pengguna aktif. `WpitvAgendaPolicy` dan `CoveragePolicy` memberi akses Super Admin dan Production. `NewsPolicy` memberi akses Super Admin dan News. `ProgramPolicy` memakai helper untuk Super Admin, Production dan TVClub. `VideoPolicy` mengizinkan Super Admin, Production dan News melihat serta mengelola publikasi, dengan penghapusan terbatas pada Super Admin dan Production. Akses Tayangan bersama membuat atribusi divisi perlu dipilih secara eksplisit. Rujukan role dan menu ada di [RBAC_ACCESS_MATRIX.md](RBAC_ACCESS_MATRIX.md), `app/Models/User.php` dan `app/Support/AdminNavigation.php`.

UI admin yang dapat dijadikan pola meliputi kalender/daftar Agenda dengan filter jenis dan status, antrean Peliputan dengan filter tanggal dan status, daftar Berita dengan pencarian/tahun/pagination, serta daftar/form Program dan Tayangan. Gunakan komponen Blade, Livewire dan tampilan dashboard yang sudah dipakai. Alur publik Agenda, Peliputan, Berita dan Tayangan tetap menjadi sumber rujukan sesuai kebijakan aksesnya.

## 2. Usulan cara menyusun laporan

Gunakan pendekatan **hybrid**. Sistem menampilkan kandidat berdasarkan periode dan divisi. Penyusun meninjau, memilih kandidat yang relevan dan melengkapi hasil, kendala serta tindak lanjut. Penyusun dapat menambah kegiatan manual untuk rapat, latihan, persiapan produksi, riset editorial dan pekerjaan yang belum tercatat. Pendekatan ini memberi dasar yang dapat ditelusuri sekaligus memberi ruang untuk hasil kegiatan yang tidak direkam oleh modul sumber. Pembuatan draft dilakukan atas tindakan penyusun, dengan pemuatan ulang kandidat yang tidak menimpa entri yang sudah diedit.

| Divisi | Kandidat awal yang diusulkan | Perlakuan |
| --- | --- | --- |
| Production | Agenda selesai pada periode, Peliputan selesai pada tanggal kegiatan, Tayangan terkait | Agenda yang menunjuk Peliputan menjadi satu kandidat gabungan. Gunakan Peliputan mandiri bila belum ada Agenda terkait. Program dan Live menjadi konteks, arsip Live menjadi bukti untuk Agenda yang sama. |
| News | Berita berdasarkan tanggal pencatatan dan Tayangan yang dipilih untuk pekerjaan editorial | Beri label basis tanggal. Jadwal editorial atau peliputan yang tidak dapat dibaca melalui policy sumber dimasukkan manual, dengan uraian yang dapat dibagikan. |

Periode menggunakan bulan kalender WIB. Kolom `DATE` disaring langsung dengan batas awal bulan inklusif dan awal bulan berikutnya eksklusif. `created_at` Berita memakai batas waktu WIB yang dikonversi ke UTC sesuai penyimpanan setelah zona sesi dan asumsi data historis diverifikasi. Status sumber yang dibaca sekarang ditandai sebagai kondisi saat kandidat diambil. Untuk kegiatan lintas bulan, usulan awal memasukkan kegiatan berdasarkan tanggal pelaksanaan yang dikonfirmasi penyusun. Tanggal pembuatan laporan dan tanggal persetujuan tersimpan terpisah.

Untuk kandidat gabungan, pakai ID Peliputan sebagai identitas yang konsisten, dengan Agenda terkait sebagai konteks. Beberapa Agenda untuk Peliputan yang sama tampil sebagai satu kelompok untuk ditinjau. Tayangan yang sudah menjadi bukti Agenda tidak diusulkan lagi sebagai kegiatan terpisah pada laporan yang sama. Admin divisi memilih atribusi Tayangan bersama. Bila dua divisi memiliki kontribusi berbeda pada sumber sama, usulan awal mengizinkan kedua laporan mencatat kontribusi masing-masing. Ringkasan gabungan yang menghitung sumber unik harus mengelompokkan berdasarkan tipe dan ID sumber. Kegiatan manual dapat memiliki judul/tanggal sama, sehingga tampilkan peringatan duplikat dan minta konfirmasi penyusun.

## 3. Identitas dan workflow yang diusulkan

Satu laporan per `division + period_start`. Divisi bernilai `production` atau `news`, dan `period_start` adalah `DATE` pada hari pertama bulan. Judul tampilan berasal dari divisi dan periode, misalnya “Laporan Production · November 2026”. ID internal stabil, periode dan divisi dikunci setelah pembuatan. Koreksi periode dilakukan dengan membuat laporan pada periode yang tepat dan mencatat alasan pembatalan draft yang keliru jika prosedur pembatalan disetujui. Indeks unik memastikan dua request pembuatan bersamaan menghasilkan satu laporan.

1. **Draft.** Penyusun memilih periode, menambah atau memilih kegiatan, memeriksa kandidat dan menyimpan narasi. Kegiatan memuat tanggal pelaksanaan, judul, deskripsi, hasil, kendala, tindak lanjut dan dokumentasi opsional. Simpan hasil serta tindak lanjut yang nyata. Gunakan pilihan eksplisit “tidak ada” untuk kendala atau tindak lanjut bila relevan.
2. **Submitted.** Submit memvalidasi kelengkapan, mengunci isi, menyimpan salinan versi beserta referensi dokumentasi dan menulis audit dalam transaksi yang sama. Laporan nihil memerlukan opsi dan alasan eksplisit jika kebijakan tersebut disetujui.
3. **Revision.** Super Admin mengembalikan laporan dengan catatan wajib. Penyusun mengedit isi kerja dan mengirim ulang sebagai versi baru. Catatan review mengacu ke versi yang diperiksa.
4. **Approved.** Super Admin menyetujui versi submitted. Detail dan ekspor resmi membaca versi yang disetujui. Usulan awal menjaga status ini sebagai terminal. Prosedur pembetulan laporan yang sudah disetujui perlu keputusan tersendiri sebelum tersedia.

**Usulan persetujuan:** gunakan persetujuan Super Admin untuk dokumen resmi apabila laporan dipakai untuk pertanggungjawaban atau arsip institusi. Untuk ringkasan internal yang tidak membutuhkan tanda persetujuan, organisasi dapat memilih submitted sebagai status akhir. MVP dengan persetujuan menempatkan Super Admin sebagai reviewer dan admin divisi sebagai penyusun. Bila organisasi memerlukan Super Admin menyusun laporan, tetapkan reviewer lain yang sah atau prosedur pengecualian tertulis sebelum fitur tersebut dibuat.

## 4. Usulan RBAC

| Role aktif | Lihat dan unduh | Buat, edit, submit | Review |
| --- | --- | --- | --- |
| Super Admin | Kedua divisi dan histori | Sesuai usulan pemisahan tugas, penyusunan dilakukan admin divisi | Minta revisi atau approve versi submitted |
| Admin Production | Production | Production pada draft/revision, submit draft/revision | Lihat catatan review Production |
| Admin News | News | News pada draft/revision, submit draft/revision | Lihat catatan review News |
| TVClub | Tidak ada akses laporan | Tidak ada | Tidak ada |

Gunakan policy khusus laporan untuk daftar, detail, perubahan status, unduh dan ekspor. Batasi query daftar berdasarkan role/divisi sebelum mengambil data, lalu periksa policy pada setiap detail, aksi Livewire dan endpoint file. Periksa policy sumber pada kandidat dan saat menyimpan referensi. Akses laporan memberi akses ke proyeksi yang memang dibagikan di laporan. Tautan untuk membuka sumber tetap mengikuti policy sumber. Perubahan role atau status pengguna harus berlaku pada request berikutnya, termasuk pada form lama dan tautan unduhan.

## 5. Usulan skema additive

Semua nama dan kolom berikut rancangan. Gunakan InnoDB, `utf8mb4`, PK baru `BIGINT UNSIGNED`, dan FK pengguna `INT UNSIGNED` nullable dengan `ON DELETE SET NULL`. Simpan label aktor historis seperlunya untuk audit internal. Timestamp sistem UTC `DATETIME(6)` dan tanggal kegiatan `DATE` WIB.

| Tabel | Kolom inti dan constraint |
| --- | --- |
| `monthly_activity_reports` | `id`, `division VARCHAR(16)`, `period_start DATE`, `status VARCHAR(16)`, `version INT UNSIGNED`, `created_by`, timestamps. `UNIQUE(division, period_start)`. CHECK divisi/status/hari pertama bulan, indeks `(status, period_start, division)` untuk antrean. |
| `monthly_activity_items` | `id`, `report_id`, `activity_date DATE`, `title VARCHAR(200)`, `description TEXT`, `outcome TEXT`, `obstacles TEXT`, `follow_up TEXT`, `source_type VARCHAR(24) NULL`, `source_id INT UNSIGNED NULL`, `source_context JSON NULL`, urutan. CHECK pasangan tipe/ID keduanya null atau keduanya terisi, allowlist tipe, `UNIQUE(report_id, source_type, source_id)` dan indeks `(report_id, activity_date, id)`. Nilai null memungkinkan beberapa entri manual. |
| `monthly_activity_attachments` | `id`, `report_id`, `item_id` opsional, nama tampilan, disk/key unik, MIME, ukuran, SHA-256, status file, pengunggah, waktu. FK komposit `(report_id,item_id)` ke item dengan indeks unik `(report_id,id)` memastikan item milik laporan yang sama. Lampiran tingkat laporan memiliki `item_id NULL`. |
| `monthly_activity_versions` | `id`, `report_id`, `revision_number`, `schema_version`, `snapshot JSON`, hash snapshot, `submitted_by`, `submitted_at`. `UNIQUE(report_id, revision_number)`. Snapshot berisi isi terpilih dan ID/hash lampiran yang dikunci saat submit. |
| `monthly_activity_events` | `id`, `report_id`, `report_version_id` opsional, aksi, status sebelum/sesudah, aktor, alasan, ringkasan perubahan yang diizinkan, UTC timestamp. Indeks `(report_id, created_at, id)`. FK komposit memastikan versi event milik laporan yang sama. |

Relasi dari tabel baru ke laporan memakai `RESTRICT` untuk menjaga histori. Pada sumber heterogen, tipe dan ID adalah referensi lunak yang divalidasi di server melalui allowlist tetap. Penghapusan sumber legacy tetap berjalan menurut aturan existing. Tampilkan indikator sumber hilang dan pertahankan isi laporan yang sudah disubmit. Identitas sumber, basis tanggal dan konteks ringkas cukup untuk penelusuran. Salinan penuh artikel, permohonan, video atau master Program akan membuat data ganda dan memperbesar risiko kebocoran.

Gunakan transaksi dan lock baris laporan untuk perubahan isi/status. Cek `version` sebelum mutasi, tolak stale dengan pesan muat ulang, dan naikkan versi pada perubahan efektif. Submit/approve ulang dengan versi lama ditolak. Versi submitted dan event bersifat append-only melalui service dan model. Riwayat perubahan memuat aksi, aktor, waktu, versi, alasan dan ringkasan field yang berubah. Hindari mencatat payload utuh atau lokasi file privat dalam log aplikasi. File yang dirujuk versi lama harus tetap dapat dibaca sesuai policy. Lampiran baru menggunakan key baru, dengan cleanup file sementara atau yatim yang terukur karena transaksi database dan operasi filesystem terpisah.

## 6. UI, dokumen dan ekspor yang diusulkan

- **Dashboard:** menu Laporan Bulanan terlihat sesuai policy. Admin divisi melihat periode berjalan, status dan tombol lanjutkan. Super Admin melihat antrean submitted, periode dan divisi. Daftar menggunakan filter tahun/bulan/status, pencarian judul kegiatan dan pagination. Batasi pilihan divisi pada role yang berwenang.
- **Editor:** kelompok kandidat menurut sumber dengan tanggal, basis tanggal dan status saat diambil. Tampilkan penanda kandidat sudah dipilih atau sumber berubah. Form kegiatan memuat dokumentasi, validasi per bidang dan penanganan konflik versi. Detail memperlihatkan periode, kegiatan, hasil, kendala, tindak lanjut, dokumentasi, timeline review dan versi. Gunakan konfirmasi untuk submit/approve dan input alasan untuk revisi. Pertahankan label, fokus keyboard, status loading/empty/error serta tata letak mobile.
- **Validasi:** tanggal kegiatan wajib masuk periode, string wajib di-trim, ukuran judul/narasi dan jumlah kegiatan/lampiran dibatasi. Validasi nilai divisi, periode, ID sumber, transisi dan field di server. Validasi sumber memakai otorisasi serta pengecekan keberadaan. Escape narasi saat dirender. Ukuran file, jumlah file dan kebijakan retensi merupakan usulan yang perlu disetujui.
- **Dokumentasi:** prioritaskan referensi Tayangan/Berita/Agenda yang boleh dibuka dan unggahan JPEG/PNG/PDF ke storage privat. Usulan batas awal 10 MB per file. Validasi ukuran, MIME dari isi, ekstensi dan signature yang sesuai. Gunakan key acak, penyimpanan di luar web root, unduh via controller dengan policy dan `Content-Disposition: attachment`. Nama asli hanya sebagai label yang disanitasi. Batasi tautan eksternal ke HTTPS, tanpa fetch server otomatis. Tolak HTML, SVG dan file executable. Tentukan kebutuhan pemindaian malware, redaksi foto, dan retensi sebelum unggahan dipakai secara luas.
- **Ekspor:** `EquipmentReportExportController` dan `EquipmentReports` menyediakan pola CSV streaming, otorisasi, mitigasi formula injection dan audit ekspor. `composer.json` belum menyediakan library PDF/XLSX. Usulan awal adalah HTML cetak yang dapat disimpan sebagai PDF dari browser dan CSV UTF-8 untuk Excel. PDF server-side atau XLSX native menunggu kebutuhan format resmi, template, dan keputusan dependency terpisah. Ekspor resmi mengambil versi approved, membatasi baris, meng-escape formula CSV dan mencatat aktor, versi serta jumlah baris. Preview draft dapat diberi label draft jika diizinkan.

## 7. Risiko dan keputusan bisnis terbuka

| Risiko atau pertanyaan | Usulan untuk dibahas |
| --- | --- |
| Tanggal historis dan status terkini tidak membuktikan kapan aktivitas selesai | Tampilkan basis tanggal, minta konfirmasi penyusun dan simpan versi saat submit. Cek sampel lama secara read-only pada fase implementasi. |
| Tayangan lintas divisi dan satu kegiatan memiliki beberapa sumber | Konfirmasi aturan atribusi, laporan lintas bulan dan definisi KPI unik sebelum ringkasan lintas divisi dibuat. |
| Data pribadi pemohon dan catatan internal ikut tertarik | Pakai proyeksi minimum, larang salinan otomatis field sensitif, periksa dokumen sebelum dibagikan. |
| Siapa reviewer, kapan tenggat, apakah laporan nihil dan koreksi pasca-approve diperlukan | Konfirmasi alur persetujuan, kewenangan pengganti, tenggat, periode awal, retensi dan pembetulan versi. |
| Format institusi dan bukti pendukung | Konfirmasi template, tanda tangan, kolom wajib, jumlah/ukuran lampiran, dan apakah PDF/XLSX native diperlukan. |

## 8. Tahapan implementasi yang diusulkan

1. **Aturan dan fondasi:** konfirmasi keputusan bisnis, review DDL additive dan policy empat role. Uji constraint periode/divisi dan FK pada testing terisolasi.
2. **Draft dan kandidat:** buat daftar/editor per divisi, query sumber terproyeksi, deduplikasi dan kegiatan manual. Uji batas bulan WIB, tanggal kosong, sumber hilang, akses silang dan paginasi.
3. **Review dan histori:** tambah submit/revisi/approve, versi immutable dan audit atomik. Uji transisi, stale form, race pembuatan/submit/review, rollback saat audit gagal dan perubahan role.
4. **Dokumentasi:** tambah penyimpanan privat dan unduh terotorisasi. Uji file tidak valid, lampiran lintas laporan, penghapusan sumber, preservasi versi lama dan cleanup kegagalan penyimpanan.
5. **Dashboard dan ekspor:** tambah antrean/status serta ekspor yang disetujui. Uji versi resmi, privasi, formula injection, batas query dan tampilan desktop/mobile. Jalankan regresi modul sumber dan verifikasi data utama tetap utuh sebelum rilis lokal.

## 9. Implementasi fondasi Phase 4B

Keputusan MVP dipakai sebagai default teknis yang masih menunggu konfirmasi bisnis. Admin Production dan News menyusun laporan divisi masing-masing, Super Admin meninjau, dan TVClub tidak memiliki akses. Divisi dan periode dikunci. Approved terminal. Laporan kosong mensyaratkan `nil_reason`, dan laporan berisi kegiatan mensyaratkan `contribution` tiap kegiatan untuk atribusi. Batas teknis awal saat submit adalah 500 kegiatan, 100 lampiran siap, judul 200 karakter, uraian 5.000 karakter per bidang, dan alasan 1.000 karakter. Batas ini dapat ditinjau bersama kebutuhan operasional.

Lima migration additive berurutan `2026_11_11_000001` hingga `000005` mengikuti skema bagian 5. FK pengguna memakai `INT UNSIGNED`, sedangkan hubungan tabel baru memakai `BIGINT UNSIGNED`. FK komposit menjaga lampiran dan versi event tetap pada laporan pemiliknya. CHECK menggunakan SQL MySQL khusus setelah pembuatan tabel. `down()` menolak rollback otomatis untuk menjaga histori. MySQL melakukan commit implisit pada DDL, sehingga kegagalan CHECK atau FK saat provisioning memerlukan inspeksi skema sebelum retry.

Lima model `MonthlyActivity*` memakai cast waktu UTC dan relasi laporan, kegiatan, lampiran, versi, event serta pengguna. `MonthlyActivityReportPolicy` mengikuti penemuan policy berdasarkan nama kelas Laravel. `MonthlyActivityRules` memvalidasi periode WIB, divisi, alasan, versi, dan status. `MonthlyActivityLifecycle` menyediakan create draft, pembaruan alasan nihil, submit, permintaan revisi dan approve. Pemanggilan service merupakan transaksi tingkat atas. Mutasi mengunci laporan sebelum consistent read, memeriksa aktor terkini, memvalidasi expected version, lalu menyimpan perubahan, snapshot dan audit dalam transaksi yang sama. Semua penulis kegiatan/lampiran di fase berikutnya harus mengikuti lock laporan yang sama dan menaikkan versi. Saat suatu operasi gagal, exception domain menyajikan pesan aman tanpa menyimpan SQL exception sebagai penyebab sebelumnya.

Snapshot berisi proyeksi kegiatan dan metadata lampiran, tanpa storage key atau konteks sumber mentah. Hash SHA-256 memakai JSON yang mengurutkan key objek secara rekursif dan mempertahankan urutan daftar. Versi serta event dilindungi pada jalur model normal dan quiet. Query builder/raw SQL berada di luar perlindungan model. Proyeksi kandidat dan validasi hak akses sumber, CRUD kegiatan, unggahan, UI serta ekspor menjadi pekerjaan fase berikutnya.

**Batas kesiapan:** fondasi tersedia di kode. Migration belum diterapkan. Sebelum provisioning, periksa backup, identitas runner dan isolasi, lalu gunakan prosedur approval skema testing untuk baseline 29 tabel. Uji MySQL untuk kelima CHECK, batas dan tipe FK, komposit lintas laporan, UNIQUE periode/sumber/revisi, beberapa entri manual nullable, ketelitian mikrodetik, serta hash setelah JSON round-trip. Uji rollback audit dan veto penyimpanan, konflik versi, perubahan role, submit/review dan pembuatan periode bersamaan menggunakan dua koneksi dengan commit nyata. Periksa juga penggunaan policy discovery melalui Gate pada aplikasi yang dibootstrap di testing. Perlu verifikasi tersebut sebelum membuka jalur admin.

Verifikasi Phase 4B mencakup PHP lint pada 17 file, Pint, dan enam tes murni dengan 151 assertions. Tes mencakup periode WIB, validasi input, matriks transisi, expected version, RBAC empat role aktif/nonaktif, perlindungan histori dan hash snapshot. PHPUnit dijalankan dengan `--no-configuration --bootstrap vendor/autoload.php`. Pemeriksaan migration, FK, transaksi dan audit dilakukan dari kode. Status fase berikutnya adalah siap untuk review dan pengujian integrasi terisolasi setelah persetujuan provisioning.

## 10. Verifikasi Phase 4B.1

Status provisioning pada bagian 9 mencatat kondisi saat Phase 4B. Kelima migration kini diterapkan pada `wpitv_testing` dan `wpitv` lokal setelah gate integrasi lulus. Kedua database memiliki 29 tabel. Fondasi backend berstatus READY LOCAL. Keputusan bisnis MVP masih menunggu konfirmasi. Hasil dan batas verifikasi dicatat dalam [laporan Phase 4B.1](MONTHLY_ACTIVITY_REPORT_VERIFICATION.md).
