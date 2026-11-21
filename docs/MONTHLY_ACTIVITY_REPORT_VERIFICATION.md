# Verifikasi Phase 4B.1 — Laporan Kegiatan Bulanan

11 November 2026 · **READY LOCAL** untuk fondasi database dan backend.

## Preflight dan provisioning

- MySQL lokal mengidentifikasi `wpitv` dan `wpitv_testing` dengan baseline 24 tabel. Fingerprint dan manifest awal cocok. Akun `wpitv_test_runner@localhost` memiliki SELECT, INSERT, UPDATE, DELETE pada `wpitv_testing`. Pemeriksaan akses ke `wpitv` ditolak dan runner tidak memiliki DDL. Runtime memakai environment testing, session/cache array dan storage terisolasi.
- Skrip provisioning lokal yang diabaikan Git memeriksa identitas server dan database, hash backup, fingerprint baseline dan hash lima migration yang direview. Skrip memanggil `up()` dari lima migration `2026_11_11_000001` hingga `000005` terlebih dahulu pada testing dengan koneksi provisioning. Manifest testing diperbarui setelah skema aktual diverifikasi. Gate hasil integrasi dan fingerprint diperiksa sebelum langkah yang sama diterapkan pada database utama lokal.
- Kedua database berakhir dengan 29 tabel dan lima entri histori migration baru. Skema baru cocok pada kedua database. Pemeriksaan mencakup 5 PK, 10 FK, 5 CHECK aktif, 6 UNIQUE, indeks periode/status, tanggal kegiatan dan kronologi audit, tindakan referensial, FK legacy `INT UNSIGNED`, FK baru `BIGINT UNSIGNED`, serta `DATETIME(6)`.
- Fingerprint struktur dan data historis cocok. Pada tabel `migrations`, lima entri baru diverifikasi terpisah dari histori lama. Verifikasi ulang setelah provisioning memastikan fixture testing bersih dan tabel bulanan di database utama kosong.

## Hasil pengujian

| Pemeriksaan | Hasil |
| --- | --- |
| Unit fondasi bulanan dan guard | 78 tes, 317 assertions lulus |
| Integrasi MySQL terisolasi | 6 tes, 92 assertions lulus |
| Pemeriksaan statis | PHP lint, Pint, dan diff check lulus |

Tes integrasi mencakup Gate dan akses empat role, periode WIB, tanggal kegiatan, divisi dan laporan nihil, alur draft hingga approved, stale version, proteksi histori melalui metode quiet, hash snapshot setelah JSON round-trip, rollback saat penyimpanan snapshot atau audit gagal, CHECK/UNIQUE/FK termasuk FK komposit, sumber yang dihapus, serta ketelitian waktu UTC. Dua proses PHP dengan koneksi MySQL independen berebut kunci laporan divisi/bulan yang sama. Satu laporan dan satu event audit tersimpan, dan proses lain menerima `MONTHLY_DUPLICATE`. Cleanup terarah diverifikasi dengan fingerprint. Assertion awal disesuaikan untuk kode FK 1216/1452 dan CHECK 3819 yang dilaporkan MySQL. Seluruh tes kemudian lulus.

## Backup dan pemulihan

Backup penuh dibuat sebelum DDL. Direktori `storage/app/testing/live-final-backups` bersifat privat dan diabaikan Git. Struktur 24 tabel, jumlah baris per tabel, footer, dan SHA-256 diverifikasi.

| File | Baris | SHA-256 |
| --- | ---: | --- |
| `wpitv-before-monthly-20261111-065815-6d8f39.sql` | 36.397 | `ee08a13df5037ee4be1f23a8c150573c1c7e356e52df978481ff0240b97cc000` |
| `wpitv_testing-before-monthly-20261111-065817-b399fa.sql` | 7 | `109be119db04ae6344210f8010663bb31b8588d86458b2fb4846ceae8efa8a29` |

Pemulihan backup belum diuji. Status READY LOCAL berlaku untuk fondasi yang diverifikasi di Laragon. Editor kegiatan, lampiran, dan ekspor tetap berada pada fase berikutnya.

## Phase 4C — Editor draft dan kandidat

**READY LOCAL** untuk editor draft dan pemilihan kandidat. Daftar Mazer memakai filter periode, divisi dan status serta paginasi. Production dan News menyusun laporan divisi sendiri. Super Admin membaca laporan kedua divisi dan TVClub ditolak. Editor tersedia untuk draft dan revisi. Laporan submitted dan approved tampil dalam mode baca. UI penyerahan dan review, unggah, ekspor serta KPI berada di luar fase ini.

- Kandidat memuat proyeksi terbatas dengan otorisasi policy sumber saat dibaca dan saat ditambahkan. Peliputan yang terhubung dikelompokkan menurut ID Peliputan. Jika ada beberapa Agenda selesai pada periode yang sama, Agenda paling awal menjadi konteks awal. Penyusun mengonfirmasi tanggal pelaksanaan. Berita memakai tanggal pencatatan, dengan batas bulan WIB dihitung dari epoch kolom MySQL `TIMESTAMP`.
- Tayangan Production memakai tanggal tayang dan keterkaitan Program sebagai kriteria awal. Arsip Live valid untuk Agenda selesai pada periode tersebut dikeluarkan dari kandidat Production. News memilih Tayangan editorial lewat pencarian judul dan pilihan eksplisit, termasuk kontribusi lintas divisi. Kandidat tanpa tanggal sumber yang dapat dipakai dapat dicatat manual.
- Penambahan, perubahan, pengurutan dan penghapusan memakai lock header, expected version dan audit dalam transaksi lifecycle. Judul dan tanggal yang berulang pada kegiatan manual memerlukan konfirmasi. Referensi dan konteks sumber disimpan ketika dipilih. Perubahan atau hilangnya sumber ditandai tanpa menyunting narasi laporan. Penghapusan memakai konfirmasi SweetAlert2 bawaan panel.

| Pemeriksaan | Hasil |
| --- | --- |
| Integrasi editor dan regresi lifecycle MySQL, termasuk konkurensi | 10 tes, 167 assertions lulus |
| Unit fondasi bulanan dan guard | 78 tes, 317 assertions lulus |
| Regresi tema admin JavaScript | 7 tes lulus |
| PHP lint, Pint, render Blade melalui integrasi, Vite dan diff check | Lulus. Vite melaporkan peringatan PostCSS terkait opsi `from`. |

Pengujian editor mencakup akses silang di route dan Livewire, status baca saja, konflik versi, tanggal kegiatan, deduplikasi, batas bulan WIB, kandidat berubah atau hilang, data pribadi pada proyeksi, serta rollback item saat audit gagal. Fingerprint `wpitv` dan `wpitv_testing` cocok sebelum dan sesudah tes. Fixture bersih. QA visual desktop/mobile dan interaksi SweetAlert2 di browser belum dijalankan.

## Phase 4D — Review, persetujuan dan histori versi

**READY LOCAL**. Penyusun mengirim laporan divisinya. Super Admin memakai antrean submitted untuk membaca versi terbaru, meminta revisi dengan alasan, atau menyetujui laporan. Approved terminal. Editor terbuka kembali setelah permintaan revisi. Keputusan tersebut mengikuti default MVP dalam dokumen desain, termasuk larangan menyetujui kiriman sendiri dan alasan eksplisit untuk laporan nihil.

Detail submitted dan approved menggunakan isi versi kiriman. Riwayat versi dan linimasa dipaginasi. Tampilan hanya memakai bidang laporan dan audit yang relevan, termasuk nama aktor, waktu WIB, dan alasan review. Snapshot, hash, nomor revisi, dan event tetap disimpan melalui lifecycle yang sama. Review terhadap versi historis diblokir di Livewire. Lock header dan expected version menolak keputusan stale, termasuk setelah kiriman ulang.

| Pemeriksaan | Hasil |
| --- | --- |
| Integrasi editor, workflow, constraint, dan konkurensi pada MySQL | 12 tes, 220 assertions lulus |
| Unit fondasi dan guard | 78 tes, 317 assertions lulus |
| Regresi tema admin JavaScript | 7 tes lulus |
| Harness QA browser | 1 tes, 2 assertions lulus |
| PHP lint, Pint, render Blade, Vite, diff check | Lulus. Vite masih melaporkan peringatan PostCSS terkait opsi `from`. |

Tes integrasi mencakup RBAC empat role, perubahan role setelah form dibuka, histori lintas divisi, laporan nihil, submit dan approve berulang, stale form setelah kiriman ulang, korelasi event dengan versi, hash, proteksi histori, serta rollback saat penulisan audit atau snapshot gagal. QA browser loopback pada 1366×850 dan 390×844 memeriksa editor, detail, antrean dan review dengan tema terang dan gelap. SweetAlert2 dipakai untuk submit Production, minta revisi Production dan approve News pada data sintetis. Perubahan status, snapshot, catatan dan identitas reviewer terlihat. Pemeriksaan mobile tidak menemukan overflow horizontal dan console tidak melaporkan error pada akhir QA.

Guard memverifikasi `wpitv_testing` dan runner DML-only sebelum fixture. Setelah server QA dihentikan, fingerprint kedua database cocok dan fixture bersih. Skema tetap 29 tabel. Pemulihan backup tetap belum diuji.

## Phase 4E — Lampiran privat

Lampiran dapat ditambahkan ke laporan atau kegiatan tersimpan saat draft/revision. Batasnya 100 lampiran aktif per laporan dan 10 MiB per file. Server memeriksa ekstensi, MIME isi, dan signature JPEG/PNG/PDF. Nama tampilan disanitasi. File memakai key acak pada disk lokal privat, dengan SHA-256 dan ukuran sebagai metadata. Unggah memakai form multipart khusus sehingga aturan unggah Livewire yang sudah dipakai modul lain tetap berlaku.

Staging filesystem selesai sebelum lock header. Lifecycle memeriksa ulang role, expected version, status, jumlah lampiran, dan kepemilikan kegiatan. Metadata dan event disimpan atomik. File dibersihkan setelah rollback yang terkonfirmasi. Bila hasil commit tidak dapat dipastikan, file dipertahankan dan kode operasional dicatat tanpa path. Pelepasan menghapus file yang belum pernah disubmit setelah commit. Jika sebuah versi sudah merujuk file, event pelepasan mengecualikannya dari draft dan kiriman berikutnya. Snapshot lama tetap merujuk item dan file aslinya.

Unduhan aktif maupun historis memakai policy laporan. Endpoint historis memeriksa ID dan hash pada snapshot yang diminta. Unduhan menolak path di luar key kanonis serta memverifikasi ukuran dan SHA-256. Response memakai disposition attachment, `nosniff`, dan `private, no-store`. File hilang ditandai di UI. MVP memakai unduhan saja untuk JPEG, PNG, dan PDF. Pemeriksaan signature belum mencakup pemindaian malware.

| Pemeriksaan | Hasil |
| --- | --- |
| Integrasi MySQL, termasuk regresi Phase 4D | 17 tes, 309 assertions lulus |
| Unit fondasi dan guard | 78 tes, 317 assertions lulus |
| Regresi tema JavaScript | 7 tes lulus |
| Harness QA visual | 1 tes, 2 assertions lulus |
| PHP lint, Pint, render Blade, Vite, diff check | Lulus. Vite melaporkan peringatan PostCSS yang sama. |

Tes integrasi memeriksa unggah dan unduh ketiga format, batas ukuran, file invalid, RBAC, lintas laporan, traversal, perubahan status saat staging, stale version, rollback audit, cleanup file, dan akses versi historis. QA visual memeriksa form desktop gelap (1366×850) dan mobile terang (390×844) tanpa overflow pada mobile. Percobaan unggah melalui pemilih file browser terputus saat harness mencapai batas waktu. Alur unggah/unduh browser end-to-end masih perlu diverifikasi. Guard memastikan runner DML-only, fixture dibersihkan, dan fingerprint kedua database cocok. **Verdict Phase 4E: PARTIAL pada verifikasi browser end-to-end.**
### Phase 4E.1 — Percobaan QA browser

**PARTIAL.** Percobaan sebelumnya berakhir setelah pemilih file IAB melampaui batas waktu harness 600 detik. Bukti yang tersedia tidak menentukan penyebab pemilih file tertahan. Chrome tidak tersedia sebagai browser yang dapat dikendalikan langsung. Computer Use menghentikan percobaan melalui jendela Chrome Windows karena URL aktif tidak dapat diverifikasi dengan cukup yakin.

Harness dijalankan pada `wpitv_testing` dengan JPEG, PNG, dan PDF sintetis berukuran 695, 94, dan 329 byte. Router harness memakai disk privat yang bertahan antar-request saat opt-in QA lampiran aktif. Harness lulus 1 tes dan 2 assertions untuk startup dan penyelesaian sesi. Alur upload/download, validasi dan feedback, SweetAlert2, akses versi historis, status terkunci, dan empat role belum diverifikasi melalui browser pada fase ini. QA visual desktop/mobile dan hasil integrasi HTTP Phase 4E tetap menjadi bukti terakhir.

Fixture dan file sintetis dibersihkan. Fingerprint kedua database cocok. Service bisnis tetap memakai implementasi Phase 4E.

## Phase 4F — Dasbor, CSV, dan halaman cetak

**READY LOCAL.** Widget menggunakan periode kalender WIB. Production dan News mendapat status divisi masing-masing serta tautan melanjutkan draft atau revisi. Super Admin mendapat status kedua divisi, jumlah submitted lintas periode, dan lima laporan tertua dalam antrean. Widget menjalankan satu query untuk penyusun, maksimal tiga untuk Super Admin, dan nol untuk TVClub. Kegagalan query menampilkan status data tidak tersedia.

CSV UTF-8 dengan BOM dan halaman cetak A4 hanya tersedia untuk laporan approved yang boleh dibaca oleh aktor. Keduanya memakai snapshot yang dirujuk oleh event persetujuan dan memeriksa hash snapshot. CSV streaming membatasi hasil hingga 500 kegiatan atau satu baris laporan nihil. Sel CSV memakai proteksi formula yang sama dengan ekspor inventaris. CSV tidak mengambil identitas pengguna, metadata file, atau path penyimpanan. Ekspor tercatat sebagai operasi `report_exported` pada event `updated`, sesuai CHECK yang ada. Linimasa menampilkan label khusus untuk operasi tersebut. Halaman cetak menampilkan versi, waktu persetujuan WIB, dan tautan unduhan historis yang memeriksa policy. Draft, revision, dan submitted belum dapat dicetak atau diekspor.

| Pemeriksaan | Hasil |
| --- | --- |
| Integrasi laporan, lampiran, workflow dan konkurensi | 19 tes, 362 assertions lulus |
| Regresi dasbor existing | 15 tes, 68 assertions lulus |
| Unit fondasi dan guard | 78 tes, 317 assertions lulus |
| Harness browser | 1 tes, 2 assertions lulus |
| PHP lint, Pint, render Blade, Vite dan diff check | Lulus, dengan peringatan PostCSS existing |

Browser loopback menampilkan widget desktop terang dan mobile gelap serta halaman cetak pada kedua ukuran. Pengujian visual memakai laporan nihil sintetis. Dialog cetak dan penyimpanan PDF tidak dijalankan. Rute CSV, histori lampiran, proteksi formula, dan kontrol akses diverifikasi melalui integrasi HTTP. Fixture bersih dan fingerprint kedua database cocok. Status Browser E2E lampiran Phase 4E tetap **PARTIAL**.

## Phase 4G — Verifikasi akhir laporan bulanan

**READY LOCAL** untuk kode laporan bulanan.

Suite laporan lulus 23 tes dan 396 assertions pada `wpitv_testing` dengan guard isolasi dan runner DML-only. Cakupan meliputi lifecycle dan laporan nihil, periode WIB, deduplikasi kandidat, RBAC empat role lewat URL dan Livewire, ekspor approved, formula CSV, lampiran historis, batas 10 MiB dan 100 lampiran aktif, penolakan lintas laporan dan traversal, serta rollback jika audit atau snapshot gagal. Perubahan dan penghapusan sumber Berita setelah submit mempertahankan isi dan hash snapshot.

| Skenario konkurensi | Hasil dua proses dengan koneksi MySQL independen |
| --- | --- |
| Pembuatan laporan divisi dan periode sama | Satu laporan dan event tercatat. Request kedua ditolak sebagai duplikat. |
| Submit bersamaan dengan edit kegiatan | Satu mutasi tercatat. Versi stale ditolak. Snapshot hanya dibuat jika submit menang dan mencerminkan isi yang dikirim. |
| Approve bersamaan dengan permintaan revisi | Satu keputusan tercatat dan merujuk snapshot semula. Versi stale ditolak. |
| Dua unggahan pada versi laporan yang sama | Satu metadata dan event tersimpan. File request stale dibersihkan. |

Backup SQL privat terbaru yang ditemukan masih berasal dari sebelum provisioning Phase 4B.1 dan memuat 24 tabel. SHA-256, hitungan baris dan footer kedua dump cocok dengan catatan verifikasi. Cakupan backup skema 29 tabel dan uji restore masih terbuka. Verifikasi integritas skema dan data aktif memakai fingerprint kedua database.

Regresi Dashboard, Agenda, Peliputan, Berita, Tayangan dan Live Streaming lulus 215 tes dengan 952 assertions. Unit fondasi, guard dan Live lulus 225 tes dengan 835 assertions. PHP lint, Pint, render Blade melalui tes, build Vite dan diff check lulus. Vite masih menampilkan peringatan PostCSS. Kedua database tetap memiliki 29 tabel dengan fingerprint sesuai baseline dan tanpa sisa fixture. Perubahan kode terbatas pada worker serta tes integrasi dan lampiran.

Browser E2E lampiran tetap **PARTIAL**. Dialog Save as PDF belum diuji. UAT manual ditunda sampai seluruh modul WPiTV selesai, dan hasil lokal tidak mewakili verifikasi deployment production.
