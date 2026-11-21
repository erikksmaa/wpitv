# Phase 5A — Desain Laporan Media Sosial TVClub

## Audit repository

Audit membaca source, route, policy, Blade dan metadata DDL yang tersimpan dari verifikasi 29 tabel. Rujukan akses dan baseline skema ada pada [matriks RBAC](RBAC_ACCESS_MATRIX.md) dan [verifikasi Laporan Bulanan](MONTHLY_ACTIVITY_REPORT_VERIFICATION.md).

| Area | Temuan |
| --- | --- |
| Struktur TVClub | `TVClubGeneration` menaungi anggota, kelompok, dan struktur organisasi. `TVClubGroup` terhubung ke Program melalui `program_id`. Keanggotaan tim memakai `tvclub_group_members`. Struktur organisasi memakai posisi dan penugasan tersendiri. |
| Program dan Tayangan | `Program` berelasi ke kelompok, Tayangan, dan Agenda. `Video` mengacu ke Program dan pengguna serta memakai `youtube_id`. Relasi ke TVClub berjalan lewat Program/kelompok. Tayangan YouTube tidak menyediakan identitas unggahan TikTok, Instagram, atau Facebook. |
| Route dan UI | `/admin/tvclub` menaungi halaman Livewire Generasi, Anggota, Kelompok Produksi, dan Struktur Organisasi dalam panel Mazer. Daftar kelompok sudah memberi pola pencarian, filter generasi/Program/status, dan paginasi. Portal publik berada di `/tv-club`. Tautan platform di footer publik hanya mengarah ke situs platform. |
| Policy | Policy Program dan pengelolaan TVClub memakai `canManageProgramsAndTVClub()` untuk Super Admin, Admin Production, dan TVClub aktif. Policy Tayangan memakai `canPublishVideos()` dan aturan hapus tersendiri. TVClub tidak memiliki akses administratif Tayangan. |
| Infrastruktur laporan | Laporan Bulanan memberi pola snapshot dengan hash, event beraktor, transaksi, PDF melalui browser, CSV dengan perlindungan formula, dan unduhan lampiran berpolicy. Fondasi ini menjadi rujukan teknis. Workflow review, lampiran, dan cetak dapat dinilai terpisah. |

## Workflow yang diusulkan

1. Super Admin atau TVClub mendaftarkan akun resmi per platform. TVClub mencatat konten secara manual dengan akun, waktu publikasi WIB, judul, deskripsi opsional, URL, jenis, dan penanggung jawab. Konten yang sama di beberapa platform memiliki catatan per unggahan.
2. TVClub menambahkan pembacaan metrik per konten atau per akun beserta waktu observasi dan catatan sumber. Pembacaan baru menambah histori. Koreksi menaut ke pembacaan lama, mewajibkan alasan, dan menyimpan pembacaan sebelumnya.
3. TVClub memilih bulan dan tahun untuk melihat rekap berjalan. Saat periode selesai, TVClub menerbitkan snapshot internal. Koreksi setelah terbit menghasilkan nomor revisi baru dengan alasan. Versi lama tetap dapat ditelusuri. Satu tindakan penerbitan menyimpan versi dan event dalam transaksi yang sama.
4. Super Admin melihat rekap, histori, dan ekspor untuk pengawasan. Usulan MVP tidak mewajibkan persetujuan formal karena tujuan awalnya pencatatan internal. Tambahkan review hanya jika laporan dipakai sebagai dokumen pertanggungjawaban yang membutuhkan pengesahan.

### Otorisasi modul baru

| Role aktif | Baca dan ekspor | Kelola akun | Kelola konten dan observasi | Terbitkan versi |
| --- | --- | --- | --- | --- |
| Super Admin | Semua akun TVClub | Ya | Baca untuk pengawasan | — |
| Admin Production | — | — | — | — |
| Admin News | — | — | — | — |
| TVClub | Akun TVClub yang dikelola | Akun TVClub | Ya | Ya |

Akun TVClub yang dikelola berarti akun media sosial resmi milik TVClub dalam modul ini. Akses MVP diberikan pada role TVClub secara kolektif sesuai akun Ketua Umum yang ada. Policy baru dipakai pada daftar, pencarian, detail, mutasi, ekspor, dan unduhan, termasuk request Livewire yang menyertakan ID. Filter query dipasang sebelum pemuatan data. Rute berada di grup admin aktif dan sesi valid. Hubungan ke Program atau kelompok tidak memberikan akses tambahan pada role lain. Dashboard Production dan News tidak menjalankan query modul.

## Model data additive

Gunakan PK baru `BIGINT UNSIGNED`. FK ke `users`, `ms_program`, `tvclub_groups` dan `tvclub_members` mengikuti PK existing `INT UNSIGNED`. Kolom waktu kejadian dan audit memakai UTC `DATETIME(6)`, periode memakai tanggal awal bulan WIB. Jenis platform dan jenis konten memakai daftar nilai yang divalidasi aplikasi dan CHECK.

| Tabel | Kolom inti, constraint, dan indeks |
| --- | --- |
| `tvclub_social_accounts` | `platform`, `account_key` stabil, handle/nama tampilan, URL profil opsional, `archived_at`, timestamp. UNIQUE platform dan account_key. Handle dapat berubah tanpa mengganti identitas akun. |
| `tvclub_social_posts` | FK akun, `platform_content_key` bila tersedia, URL HTTPS kanonis, hash URL, `published_at`, judul, deskripsi opsional, jenis, label penanggung jawab, FK opsional anggota dan Program, `archived_at`, `lock_version`, timestamp. UNIQUE akun dan hash URL. UNIQUE akun dan ID konten saat terisi. Indeks akun, waktu publikasi, ID. |
| `tvclub_social_post_observations` | FK konten, `observed_at`, `views`, `likes`, `comments`, `shares` nullable, catatan definisi/sumber, FK aktor, referensi pembacaan yang dikoreksi dan alasan opsional. Indeks konten, waktu, ID. |
| `tvclub_social_account_observations` | FK akun, `observed_at`, `followers` nullable, catatan sumber, FK aktor, referensi pembacaan yang dikoreksi dan alasan opsional. Indeks akun, waktu, ID. |
| `tvclub_social_report_versions` | `period_start`, `revision_number`, `schema_version`, snapshot JSON, hash SHA-256, FK penerbit, `published_at`, alasan revisi. UNIQUE periode dan nomor revisi. Snapshot menyimpan label akun, konten, angka dan waktu observasi, cakupan, serta ID referensi. Rekap berjalan dihitung langsung sehingga tabel draft terpisah tidak diperlukan. |
| `tvclub_social_events` | Aksi, jenis target dan ID, FK aktor, waktu UTC, alasan opsional dan perubahan terpilih. Indeks target dan waktu. Gunakan event append-only untuk perubahan master, koreksi, penerbitan, dan ekspor. Hindari menyimpan isi bukti atau path privat dalam event. |

FK riwayat ke akun/konten memakai RESTRICT dan master dapat diarsipkan. FK referensi organisasi atau aktor memakai SET NULL dengan label historis seperlunya. Gunakan FK komposit atau pemeriksaan transaksi untuk memastikan koreksi hanya merujuk pembacaan entitas yang sama. Penanggung jawab dapat memakai anggota opsional ditambah label historis. Kelompok dapat ditambahkan sebagai FK opsional jika laporan membutuhkan filter tim. Program hanya memberi konteks yang boleh dibaca, dan MVP tidak memerlukan FK Tayangan. Jangan membawa data kontak anggota ke rekap atau ekspor.

Kunci baris akun/konten untuk perubahan master dan observasi yang bertentangan. Cek versi formulir dan tolak submit stale. Untuk versi bulanan gunakan transaksi dengan serialisasi per periode atau UNIQUE dengan penanganan konflik yang mengharuskan pemuatan ulang. Rekap resmi menyimpan snapshot dan hash dalam transaksi yang sama dengan event penerbitan. Uji konflik dua penerbit dan koreksi bersamaan.

## Metrik dan periode

| Platform | Jenis awal | Pembacaan per konten | Statistik akun |
| --- | --- | --- | --- |
| TikTok | Video pendek, foto, lainnya | Views, likes, comments, shares jika terlihat | Followers |
| Instagram | Reel, foto, carousel, story, lainnya | Views jika tersedia, likes, comments, shares jika terlihat | Followers |
| Facebook | Reel, video, foto, tautan, teks, lainnya | Views jika tersedia, likes/reaksi sesuai definisi yang dicatat, comments, shares jika terlihat | Followers |

Daftar di atas adalah pemetaan input manual. Kolom angka berupa integer nonnegatif dan nullable. Nilai 0 dipakai jika sumber memang menampilkan nol. Nilai NULL berarti tidak tersedia atau belum dicatat. Pembacaan merekam waktu observasi aktual dan waktu input dari server. Pembacaan konten harus terjadi pada atau sesudah publikasi. Koreksi tidak mengganti waktu observasi asli dan penurunan nilai memerlukan catatan ketika operator mengetahui penyebabnya. Gunakan label definisi jika sumber berbeda, misalnya reaksi Facebook. Hindari membandingkan definisi yang berbeda sebagai metrik setara.

Periode memakai batas awal bulan inklusif dan awal bulan berikutnya eksklusif di Asia/Jakarta, lalu dikonversi ke UTC untuk query. Rekap konten berisi unggahan yang terbit pada bulan itu. Untuk setiap unggahan ambil observasi terakhir yang sah hingga akhir periode, dengan waktu dan ID sebagai penentu urutan. Koreksi yang dimasukkan belakangan hanya dapat tampil pada rekap berjalan atau versi resmi berikutnya. Jumlah views pada rekap adalah akumulasi pembacaan cohort unggahan bulan itu. Kenaikan views selama bulan berjalan memerlukan pembacaan awal dan akhir yang sebanding dan ditunda dari MVP.

Statistik akun dipisah dari unggahan. Ambil pembacaan penutup per akun dan tampilkan tanggalnya. Perubahan followers hanya ditampilkan jika tersedia pembacaan pembanding periode sebelumnya dengan definisi sama. Tampilkan cakupan tiap metrik sebagai jumlah konten yang memiliki nilai dibanding total konten yang relevan. Jumlah tanpa data tampil kosong. Jangan menjumlahkan followers lintas platform sebagai jumlah audiens unik.

## Dashboard, ekspor, dan bukti

Menu admin TVClub menampung daftar akun, konten, histori pembacaan, dan laporan. Gunakan filter bulan/tahun, platform, akun, jenis, dan status pencatatan. Filter tim atau Program hanya tersedia jika hubungan tersebut dipakai. Ringkasan per platform menampilkan jumlah unggahan, metrik yang tersedia beserta cakupan dan tanggal, serta statistik akun dalam bagian tersendiri. Pakai paginasi, indeks periode, dan agregasi terproyeksi. Dashboard Super Admin menampilkan ringkasan dan tautan versi terakhir, tanpa memuat seluruh riwayat.

Ekspor CSV UTF-8 versi terbit memakai snapshot yang dipilih. Pisahkan ekspor konten dari statistik akun. Sertakan periode, revisi, platform, akun, waktu observasi, dan cakupan. NULL menjadi sel kosong. Batasi baris, gunakan streaming dan sanitasi formula pada seluruh sel teks, serta audit aktor, versi, dan jumlah baris. Hindari informasi kontak, nama aktor audit dan lokasi storage dalam CSV. Cetak dan PDF dapat dipertimbangkan saat ada kebutuhan arsip resmi.

Bukti privat opsional dapat ditambahkan setelah workflow inti stabil. Kaitkan bukti ke observasi yang didukung, simpan hash, tipe, ukuran, nama tampilan yang disanitasi dan key acak di luar web root. Ikuti pola validasi MIME/signature, staging, cleanup, akses unduhan berpolicy, dan preservasi file yang dirujuk versi lama dari Laporan Bulanan. Tentukan batas dan retensi sebelum menambah tabel lampiran.

## Risiko dan keputusan terbuka

| Risiko | Penanganan |
| --- | --- |
| Duplikasi dari URL share atau repost | Simpan ID unggahan bila format dapat dibaca secara lokal. Normalisasi URL per platform, buang parameter tracking yang dikenal, pertahankan komponen identitas, dan tolak host selain allowlist. Untuk short link yang tidak dapat dipetakan secara lokal, minta URL permanen. UNIQUE memblokir duplikasi yang dapat dibuktikan dan daftar kandidat memperingatkan kemiripan manual. |
| Angka salah atau dimanipulasi | Simpan aktor, waktu observasi, histori koreksi, dan alasan. Cek nilai di luar batas dan penurunan. Bukti opsional membantu audit angka yang dilaporkan. |
| Konten/akun dihapus, handle berubah, atau bukti hilang | Arsipkan catatan, simpan label dan angka pada snapshot, tampilkan status sumber atau file hilang jika dilaporkan/diketahui. Jangan menghapus catatan versi. |
| Kebocoran antar-role atau lewat lampiran | Terapkan query scoping dan policy pada semua jalur, termasuk ID langsung. Gunakan proyeksi minimal dan storage privat. |
| Perubahan istilah atau metrik | Simpan basis dan definisi pada observasi. Tampilkan NULL dan cakupan sehingga rekap tidak memberi kesan data lengkap. |

Konfirmasi bisnis diperlukan untuk otoritas penerbit dan kebutuhan approval, daftar akun resmi, keterkaitan anggota/kelompok, jenis konten yang dibutuhkan, dan aturan retensi bukti. Input, validasi, dan pelaporan berjalan manual tanpa API eksternal maupun scraping.

## Tahapan implementasi

1. **Fondasi.** Tambahkan skema dan policy modul. Verifikasi FK, CHECK, UNIQUE, akses empat role, dan isolasi testing.
2. **Pencatatan.** Buat daftar dan formulir akun/konten serta observasi. Uji URL, nilai NULL, koreksi, konflik versi, dan audit.
3. **Rekap dan versi.** Buat rekap WIB dan snapshot terbit, histori revisi, dan akses ekspor. Uji batas periode, cakupan, konsistensi setelah perubahan sumber, dan penerbitan bersamaan.
4. **Dashboard dan ekspor.** Tambahkan ringkasan per platform dan CSV. Uji batas query, formula injection, akses langsung, tampilan mobile, dan regresi menu existing.
5. **Bukti jika disetujui.** Tambahkan lampiran privat dan pengujian cleanup serta akses historis. UAT manual mengikuti jadwal proyek setelah seluruh modul selesai.

## Implementasi fondasi Phase 5B

Enam migration additive mendefinisikan akun, unggahan, dua cakupan observasi, versi laporan, dan audit. Identitas akun memakai UNIQUE gabungan platform dan `account_key`. Handle juga unik per platform. Akun memiliki `lock_version`. Foreign key komposit membatasi koreksi ke pemilik yang sama. UNIQUE pada `corrects_id` menjaga histori koreksi tetap linear. CHECK MySQL ditambahkan lewat SQL khusus dalam migration. Model observasi, versi dan event menolak mutasi maupun penghapusan melalui metode model biasa dan quiet. Query builder dan SQL langsung berada di luar proteksi metode model.

Policy baru dipasang pada enam model. Service membaca ulang aktor dan role melalui locking read, lalu mengunci akun sebelum unggahan pada operasi yang melibatkan keduanya. Observasi awal tidak dapat memakai waktu yang sudah tercatat pada entitas yang sama. Koreksi mempertahankan waktu observasi dan menambah baris baru. URL permanen divalidasi lokal. URL pendek yang membutuhkan redirect ditolak. Nilai metrik mempertahankan NULL dan nol, dengan batas input `PHP_INT_MAX` agar representasi integer PHP dan hash snapshot tetap presisi.

Penerbitan hanya tersedia setelah bulan WIB berakhir. Service memilih observasi terakhir sebelum batas periode, dengan ID sebagai pemutus seri koreksi. Snapshot memuat maksimal 5.000 unggahan dan 500 akun serta mencatat status arsip saat penerbitan. UNIQUE periode dan revisi menyelesaikan race penerbitan. Snapshot, hash, dan event tersimpan dalam satu transaksi. Revisi membutuhkan expected revision dan alasan.

PHP lint, Pint, diff check, serta enam unit test murni dengan 115 assertions lulus. Status **READY FOR REVIEW**. Constraint MySQL, pemilihan observasi pada query aktual, atomicity dan konkurensi masih memerlukan provisioning terisolasi serta pengujian integrasi. Migration belum dijalankan.

## Integrasi MySQL Phase 5B.1

Fondasi berstatus **READY LOCAL** setelah enam migration diterapkan pada `wpitv_testing` dan `wpitv`. Kedua database memiliki 35 tabel. Pengujian MySQL memverifikasi pemilihan observasi, rollback, serta penerbitan bersamaan. Catatan sebelumnya menggambarkan kondisi sebelum provisioning. [Laporan verifikasi Phase 5B.1](TVCLUB_SOCIAL_MEDIA_REPORT_VERIFICATION.md)
