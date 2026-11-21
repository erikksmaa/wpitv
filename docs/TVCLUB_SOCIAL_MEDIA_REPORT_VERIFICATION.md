# Phase 5B.1 — Verifikasi Fondasi Media Sosial TVClub

12 November 2026 · **READY LOCAL**.

Sesi sebelumnya terinterupsi setelah preflight. Saat pekerjaan dilanjutkan, kedua database teridentifikasi dengan 29 tabel, fingerprint sesuai baseline, dan enam migration belum tercatat. Runner testing memakai akun DML-only dan tidak dapat membaca database utama. Guard memeriksa identitas server, manifest, transaksi, session/cache array, dan storage testing terisolasi.

## Backup sebelum provisioning

Backup privat berada dalam direktori backup yang diabaikan Git. Setiap dump dicocokkan dengan DDL baseline, jumlah INSERT per tabel, footer, dan SHA-256. Restore belum diuji.

| File | Tabel | Baris | SHA-256 |
| --- | ---: | ---: | --- |
| `wpitv-before-social-20261112-045416-a1d308.sql` | 29 | 36.402 | `66f907c390697514053e5b797ff3028cebc41ba56a996efd5220a4f67a641669` |
| `wpitv_testing-before-social-20261112-045418-a64525.sql` | 29 | 12 | `0690b0c3bb79be96e71f65a990b0bb03acf1579176f7ae674be9a6d1507ead8a` |

## Provisioning dan hasil

Skrip operator privat memanggil `up()` hanya pada enam file Phase 5B yang telah direview. Koneksi provisioning terpisah dari runner. Manifest diperbarui setelah struktur `wpitv_testing` diverifikasi. Setelah integrasi lulus, skrip mencocokkan ulang backup, fingerprint, identitas, serta hash migration sebelum menerapkan keenam file pada `wpitv`. Kedua database berakhir dengan 35 tabel dan enam entri histori baru. Fingerprint akhir cocok dan fixture sintetis bersih.

Metadata MySQL memverifikasi enam PK, 11 FK, 10 CHECK aktif, dan sembilan UNIQUE. DDL aktual menampilkan indeks tanggal unggah, periode, urutan observasi dan kronologi event. FK legacy memakai INT UNSIGNED, FK internal memakai BIGINT UNSIGNED, dan timestamp memakai DATETIME(6). Relasi histori memakai RESTRICT, relasi aktor dan referensi legacy memakai SET NULL saat penghapusan. Tabel memakai InnoDB dan `utf8mb4_unicode_ci`, dengan collation biner untuk key unggahan dan hash.

| Pemeriksaan | Hasil |
| --- | --- |
| Integrasi MySQL terisolasi | 6 tes, 72 assertions lulus |
| Unit fondasi dan guard | 78 tes, 281 assertions lulus |
| PHP lint, Pint, diff check | Lulus |

Integrasi menguji policy empat role, akun tiga platform, URL valid dan tiruan, deduplikasi, kepemilikan konten, NULL dan nol, batas numerik, cakupan metrik, waktu WIB/UTC, koreksi, pemilihan observasi, SHA-256, proyeksi snapshot tanpa kolom anggota/aktor, metode quiet, CHECK, dan rollback saat snapshot atau audit ditolak. Dua proses dengan ID koneksi berbeda pada UUID server yang sama mencapai batas insert sebelum dilepas. Satu proses menyimpan revisi pertama dan satu event, sedangkan proses kedua menerima `SOCIAL_DUPLICATE`. Collision nama helper pada percobaan awal diperbaiki di tes sebelum suite dijalankan ulang.

Perubahan fase ini mencakup allowlist guard 35 tabel, tes integrasi, worker konkurensi, manifest privat, dan dokumentasi. UAT manual tetap mengikuti jadwal proyek setelah seluruh fitur selesai.

## Phase 5C — Manajemen akun dan konten

**READY LOCAL.**

Panel TVClub menyediakan daftar dan form akun resmi untuk TikTok, Instagram, dan Facebook. Super Admin dan TVClub dapat mendaftar, memperbarui, dan mengarsipkan akun dengan identitas stabil dan kontrol versi. TVClub dapat mencatat, mengubah, dan mengarsipkan unggahan. Super Admin dapat membuka detail unggahan untuk membaca. Route, policy, dan aksi Livewire menolak Admin Production serta Admin News.

Daftar unggahan memakai filter platform, akun, bulan publikasi WIB, dan jenis, dengan paginasi dan eager loading akun. Form memakai service Phase 5B untuk normalisasi URL, deduplikasi, validasi jenis, konversi WIB ke UTC, dan audit atomik. Referensi anggota dan Program bersifat opsional. Saat sumber dihapus, FK menjadi NULL dan label historis tetap tersedia. Tayangan YouTube tidak dibuat melalui formulir ini.

Sembilan tes integrasi MySQL dengan 124 assertions dan 78 tes unit dengan 281 assertions lulus. Integrasi mencakup route langsung, role, form, rollback audit, konflik versi, URL, filter periode, dan penghapusan referensi. Tujuh tes JavaScript tema lulus. PHP lint, Pint, render Blade, dan Vite build lulus. Vite masih melaporkan peringatan PostCSS.

QA browser memakai fixture terisolasi sebagai TVClub. Daftar akun dan unggahan diperiksa pada desktop terang 1366×850. Form akun dan unggahan diperiksa pada mobile gelap 390×844. Form unggahan tidak menimbulkan overflow horizontal. Dialog arsip SweetAlert2 dibuka dan dibatalkan. Mutasi tersimpan dan pengarsipan diverifikasi melalui integrasi Livewire. Harness browser lulus 1 tes dengan 2 assertions. UAT manual mengikuti jadwal proyek.

Regresi dashboard, akses, TVClub, dan Program lulus 183 tes dengan 578 assertions. Diff check lulus. Setelah cleanup, fingerprint `wpitv` dan `wpitv_testing` cocok dengan baseline 35 tabel. Runner testing tetap DML-only dan tidak ada sisa fixture.

## Phase 5D — Metrik manual dan histori koreksi

**READY LOCAL.** Panel detail akun menampilkan pengikut. Panel detail unggahan menampilkan tayangan, suka atau reaksi Facebook, komentar, dan bagikan. Nilai kosong tampil sebagai tanda pisah dan nol sebagai 0. Panel menampilkan observasi terakhir serta histori berpaginasi, dengan waktu observasi, waktu pencatatan WIB, dan aktor. Angka berasal dari pencatatan manual.

TVClub dapat menambah dan mengoreksi observasi aktif. Super Admin membaca histori. Admin Production dan Admin News ditolak di route dan Livewire. Koreksi membawa ID observasi yang terkunci di komponen. Waktu asli diambil kembali dari server, alasan wajib, dan service menyimpan catatan pengganti bersama audit dalam satu transaksi. Histori menunjukkan tautan antar-ID koreksi. ID lintas entitas ditolak dengan pesan aman.

| Pemeriksaan | Hasil |
| --- | --- |
| Integrasi observasi dan regresi Phase 5C pada MySQL | 12 tes, 188 assertions lulus |
| Unit fondasi dan guard | 78 tes, 281 assertions lulus |
| Harness QA browser | 1 tes, 2 assertions lulus |
| PHP lint, Pint, render Blade, Vite, dan diff check | Lulus. Vite masih menampilkan peringatan PostCSS. |

Integrasi mencakup batas PHP_INT_MAX, NULL dan nol, nilai negatif, waktu sebelum publikasi, batas bulan WIB, penolakan duplikasi, rollback audit, akses empat role, dan hash snapshot terbit yang tetap cocok setelah koreksi. Dua operator TVClub dengan dua proses dan koneksi MySQL berbeda mengoreksi observasi akun yang sama. Satu koreksi dan satu event tersimpan. Request lainnya ditolak dengan `SOCIAL_STATE`.

QA browser memeriksa histori unggahan pada desktop terang, editor koreksi dan panel pengikut pada mobile gelap, serta dialog konfirmasi SweetAlert2. Dialog dibatalkan di browser. Commit koreksi diverifikasi melalui tes integrasi. Guard mengonfirmasi runner DML-only. Fingerprint kedua database cocok dengan baseline 35 tabel setelah cleanup dan fixture bersih. UAT manual tetap mengikuti jadwal proyek.
## Phase 5E — Rekap dan versi laporan media sosial

**READY LOCAL.** Rekap WIB memakai awal bulan inklusif dan akhir eksklusif. Rekap dan penerbitan berbagi pemilihan observasi berdasarkan waktu dan ID. Koreksi yang dicatat belakangan berlaku pada rekap berjalan jika waktu observasi masuk batas periode. Akun arsip tetap tersedia. Pengikut tampil per akun. Agregat unggahan mempertahankan NULL, nol, dan cakupan serta menjumlahkan angka besar secara presisi.

Filter platform dan akun berlaku pada tampilan berpaginasi. Penerbitan TVClub mencakup seluruh akun dan platform dalam periode yang dipilih. Snapshot terbit dibaca dari JSON tersimpan. Histori menampilkan revisi, waktu WIB, alasan, dan selisih jumlah unggahan terhadap revisi sebelumnya. Expected revision dikunci saat periode dipilih. Super Admin memiliki akses baca, sementara Production dan News ditolak.

Integrasi MySQL terisolasi lulus 13 tes dengan 219 assertions termasuk regresi Phase 5B–5D, batas pergantian tahun, snapshot setelah koreksi dan arsip, rollback audit, stale request, serta race penerbitan dua koneksi. Unit lulus 79 tes dengan 285 assertions. PHP lint, Pint, render Blade, Vite, dan diff check lulus. Vite menampilkan peringatan PostCSS existing. Konflik nama variabel periode pada render Livewire diperbaiki sebelum suite dijalankan ulang.

QA browser memeriksa rekap desktop terang 1366×850 dan detail snapshot mobile gelap 390×844. Harness lulus 1 tes dengan 2 assertions. Publikasi dan rollback diverifikasi melalui integrasi. Fixture bersih dan fingerprint kedua database sesuai baseline 35 tabel. UAT manual mengikuti jadwal proyek.

## Phase 5F — Dasbor dan ekspor CSV

**PARTIAL.** TVClub melihat agregat unggahan dan cakupan per platform, pengikut per akun, serta revisi terbit untuk bulan WIB yang dipilih. Akun dihitung aktif jika rentang antara pencatatan dan pengarsipan beririsan dengan periode. Widget menampilkan maksimal 10 akun. Super Admin melihat volume unggahan, revisi, dan waktu terbit. Query khusus widget berjumlah lima untuk TVClub, dua untuk Super Admin, dan nol untuk Production dan News.

Dua unduhan CSV UTF-8 membaca revisi terpilih dan memeriksa hash snapshot. CSV mempertahankan nol, sel kosong untuk NULL, dan integer hingga PHP_INT_MAX. Setiap sel melalui sanitasi formula. Audit mencatat aktor, versi, tipe, dan jumlah baris sebelum respons mulai dikirim. Jika audit gagal, unduhan ditolak. Respons dialirkan per baris dengan batas 5.000 unggahan dan 500 akun. JSON snapshot masih dimuat dalam memori. Pemakaian memori pada snapshot maksimum belum diukur secara terpisah.

Integrasi MySQL dan regresi Phase 5B–5E lulus 15 tes dengan 259 assertions. Regresi dashboard lulus 15 tes dengan 68 assertions. Unit sosial lulus 7 tes dengan 119 assertions. Suite unit penuh melaporkan satu kegagalan dari 462 tes karena ekspektasi navigasi News masih mengabaikan menu Laporan Bulanan Phase 4. PHP lint, Pint, render Blade, Vite, dan diff check lulus. Vite menampilkan peringatan PostCSS.

Chrome menampilkan widget TVClub pada desktop terang dan mobile gelap 390×680. Harness lulus 1 tes dengan 2 assertions. Konsol menampilkan peringatan CSP report-only untuk style inline. CSV diuji pada endpoint, sementara unduhan melalui browser belum diuji. Fixture bersih dan fingerprint kedua database sesuai baseline 35 tabel.

## Phase 5F.1 — Regresi navigasi dan verifikasi CSV

**READY LOCAL.** Tes navigasi masih memakai daftar sebelum Phase 4C dan Phase 5C–5E. Matriks RBAC mencatat akses editorial News pada modul lama. Kontrak Laporan Bulanan dan policy Phase 4 menambahkan menu laporan divisi News. Assertion diperbarui untuk menu tersebut dan menu sosial TVClub. Tes juga mengunci pembatasan menu laporan sosial pada TVClub dan Super Admin. Policy dan navigasi aplikasi tetap sama.

**Chrome E2E.** Empat file tersimpan melalui dua tombol unduhan pada revisi terbit, masing-masing sebagai TVClub dan Super Admin di harness loopback dengan identitas fixture. Nama file, BOM UTF-8, header, isi, sel kosong, nol, dan sanitasi judul formula diverifikasi dari file. Hash masing-masing tipe identik antar-role. Harness lulus 1 tes dengan 2 assertions.

**HTTP integration.** Respons ekspor mengembalikan 200, Content-Type CSV UTF-8 dan Content-Disposition attachment dengan nama yang benar. Production dan News mendapat 403 pada endpoint ekspor di suite integrasi dan pada detail di harness loopback. Suite juga memeriksa audit, penolakan ketika audit gagal, batas baris, dan stabilitas revisi lama.

**Pengukuran.** Fixture pada batas 5.000 unggahan dan 500 akun berisi deskripsi sintetis 2.000 karakter per unggahan. Pada satu proses PHPUnit, ekspor unggahan mencatat 403,3 ms dan peak PHP 92,7 MiB. Ekspor pengikut mencatat 204,7 ms dan peak 92,8 MiB. Kenaikan peak sekitar 48,7 MiB per request. Pengukuran mencakup request dan penulisan stream ke file temporer, termasuk overhead proses tes. Snapshot JSON dimuat penuh sebelum CSV ditulis per baris. Semua baris, integer maksimum, nilai nol dan kosong, sanitasi formula, serta ketiadaan label privat diuji.

Unit penuh lulus 462 tes dan 2.140 assertions. Integrasi sosial lulus 16 tes dan 21.771 assertions. Regresi dashboard lulus 15 tes dan 68 assertions. PHP lint, Pint, render Blade, Vite dan diff check lulus. Peringatan PostCSS tetap muncul. Kedua fingerprint cocok dengan baseline 35 tabel dan fixture database bersih. UAT manual mengikuti jadwal proyek.

## Phase 5G — Verifikasi akhir

**READY LOCAL.** Audit mencakup akun resmi, pencatatan dan pengarsipan unggahan, metrik konten dan pengikut, koreksi append-only, rekap kalender WIB, versi laporan, dashboard, dan dua CSV dari snapshot terbit. Jalur yang diperiksa memakai input manual dan validasi URL dengan allowlist host. Tidak ditemukan pemanggilan API atau scraping eksternal pada jalur modul. Perubahan fase ini hanya pada dokumen verifikasi.

Route admin dan policy Livewire membatasi akses sosial pada Super Admin dan TVClub. TVClub dapat mencatat metrik dan menerbitkan laporan. Super Admin dapat membaca laporan dan mengelola akun resmi. Production dan News ditolak pada route, ID langsung, dan ekspor. Ekspor memakai POST dengan middleware web dan token CSRF pada form. Tes memeriksa validasi URL, sanitasi formula, serta pengecualian label privat dari CSV. Nama aktor dan catatan observasi tetap terlihat pada histori internal yang berpolicy.

| Pemeriksaan | Hasil |
| --- | --- |
| Unit penuh, termasuk navigasi empat role | 462 tes, 2.140 assertions lulus |
| Integrasi sosial pada MySQL terisolasi | 16 tes, 21.771 assertions lulus |
| Regresi dashboard, pengelolaan TVClub, portal publik, dan keamanan | 155 tes, 1.304 assertions lulus |
| PHP lint, Pint, render Blade, Vite, diff check | Lulus. Peringatan PostCSS tetap muncul. |

Fingerprint skema dan data cocok dengan baseline untuk seluruh 35 tabel di kedua database sebelum dan sesudah tes. Pemeriksaan membandingkan definisi tabel, termasuk FK, CHECK, dan UNIQUE, serta jumlah dan hash baris. Runner tetap DML-only dan fixture bersih. Integrasi memeriksa rollback audit dan snapshot, konflik versi, nilai NULL dan batas bulan WIB, serta hash dan CSV versi lama setelah koreksi atau pengarsipan. Pada race penerbitan dan koreksi, dua koneksi MySQL berkompetisi dan hanya satu perubahan valid beserta audit tersimpan.

Query sosial dashboard berjumlah lima untuk TVClub, dua untuk Super Admin, dan nol untuk Production dan News. Agregasi dan pemilihan observasi dilakukan dengan query terproyeksi. Widget pengikut menampilkan maksimal 10 akun. Pada fixture 5.000 unggahan dan 500 akun, ekspor unggahan mencatat 327,8 ms dengan peak PHP 92,7 MiB dan ekspor pengikut 187,6 ms dengan peak 92,8 MiB. Pengukuran mencakup overhead PHPUnit. JSON dimuat penuh dalam memori sebelum CSV ditulis per baris. Bukti unduhan Chrome untuk kedua role dan kedua CSV berasal dari Phase 5F.1.

UAT final proyek mencakup pencatatan akun dan unggahan oleh TVClub, koreksi metrik pada pergantian bulan WIB, penerbitan dan pembandingan revisi, review dashboard dan histori oleh Super Admin, penolakan akses untuk dua role lain, serta pembukaan kedua CSV di aplikasi spreadsheet yang dipakai operator. Restore drill backup belum diverifikasi. Kesiapan ini berlaku lokal. UAT manual dan deployment produksi tetap tertunda.
