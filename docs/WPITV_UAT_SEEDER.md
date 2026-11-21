# Seeder data UAT WPiTV

## Audit dan cakupan

| Modul | Struktur dan aturan | Fixture |
| --- | --- | --- |
| Live Streaming | `live_streams.agenda_id` nullable dan UNIQUE. Agenda, Program, dan Video memakai FK. Status Live adalah `scheduled`, `live`, `ended`, dan `cancelled`. Draft adalah status Agenda. Lifecycle menulis `live_stream_events`. | 8 siaran internal, terdiri dari 3 Manual dan 5 berbasis Agenda. Ada 3 scheduled, termasuk 1 Agenda draft, 4 ended, dan 1 cancelled. Jadwal historis dan mendatang, Program opsional, lintas tengah malam, ID YouTube sintetis dan ID kosong. |
| Kategori Inventaris | `inventory_categories` menyimpan kategori aktif, dengan nama unik. | 6 kategori Kamera, Audio, Pencahayaan, Tripod & Stabilizer, Perangkat Streaming, dan Aksesori Produksi. Nama diawali penanda UAT. |
| Unit Inventaris | FK kategori, `inventory_code` unik, kondisi `good`, `minor_damage`, `major_damage`, dan flag aktif. | 24 unit fisik, 4 per kategori. Ada unit siap pakai, rusak ringan/berat, dipinjam, serta nonaktif. |
| Peminjaman Alat | `equipment_loans` menyimpan transaksi checkout dan `equipment_loan_items` menyimpan unit, waktu pengembalian, dan kehilangan. Kunci unik unit aktif mencegah checkout ganda. Status transaksi diturunkan dari detail dan jatuh tempo. | 12 transaksi, 14 detail. Ada 2 aktif tepat waktu, 1 aktif terlambat, 6 kembali penuh termasuk satu pengembalian terlambat, 2 kembali sebagian, dan 1 dengan kehilangan belum terselesaikan. |
| Laporan Inventaris & Peminjaman | `EquipmentReports` menghitung rekap dari inventaris dan detail peminjaman. | 0 record laporan tambahan. Data mengisi rekap stok, riwayat, retur, keterlambatan dan kehilangan serta filter tanggal/kategori/status. |
| Laporan Bulanan | UNIQUE pada divisi/periode, status `draft`, `submitted`, `revision`, dan `approved`. Produksi menerima Agenda/Peliputan/Tayangan, News menerima Berita/Tayangan. Kegiatan manual didukung. | 6 laporan dan 6 kegiatan pada tiga bulan WIB sebelumnya. Tiga kegiatan Production terhubung ke Agenda selesai. Tiga kegiatan News diisi manual. Ada 5 snapshot pengajuan. |

Lifecycle Equipment dimulai saat checkout. Menunggu persetujuan, disetujui tetapi belum diambil, ditolak, dan dibatalkan tidak tersedia sebagai status transaksi. Skenario tersebut dipetakan ke variasi checkout, pengembalian, dan kehilangan yang benar-benar ada. Satu Program sintetis dan lima Agenda melengkapi relasi. Siaran tetap internal dan tidak pernah ditandai sedang live.

## Menjalankan di testing

Gunakan checkout development dengan dependency dev dan `.env.testing` yang mengarah ke MySQL loopback `127.0.0.1:3306`, database `wpitv_testing`, akun `wpitv_test_runner@localhost` dengan grant DML-only dan skema yang telah diprovision. Pastikan jendela UAT terpisah dari eksekusi suite otomatis yang membandingkan fingerprint database.

```powershell
$env:WPITV_UAT_PASSWORD = Read-Host 'Password akun sintetis UAT (16-72 byte)' -MaskInput
try {
    php artisan db:seed --env=testing --class=WpitvUatSeeder
} finally {
    Remove-Item Env:WPITV_UAT_PASSWORD -ErrorAction SilentlyContinue
}
```

Seeder eksplisit ini berada di luar `DatabaseSeeder`. Seeder memverifikasi environment, database fisik, user dan grant, serta pemeriksaan FK/UNIQUE sebelum menulis. Paket menggunakan named lock dan satu transaksi. Layanan checkout, retur, dan kehilangan menggunakan jalur eksplisit untuk transaksi milik seeder, dengan otorisasi dan validasi yang sama. Layanan laporan bulanan membatasi jalur transaksi seeder ke laporan yang dibuat oleh instance tersebut. Pemanggilan normal dari aplikasi tetap menggunakan transaksi mandiri.

| Username setelah seed berhasil | Role |
| --- | --- |
| `uat_wpitv_v1_admin` | Super Admin |
| `uat_wpitv_v1_production` | Admin Production |
| `uat_wpitv_v1_news` | Admin News |
| `uat_wpitv_v1_tvclub` | TVClub |

Akun baru memakai password yang dimasukkan melalui prompt. Role kanonis yang sudah ada dipakai kembali. Role kanonis yang belum ada dibuat jika testing kosong. Rerun pada paket lengkap adalah no-op dan mempertahankan akun, password, dan edit UAT. Konflik username, kode, status paket tidak lengkap, atau bentrok divisi/periode memicu rollback. Periode dihitung saat seed pertama.

## Pelacakan dan cleanup

Gunakan penanda tetap `UAT-WPITV-V1` untuk menemukan kategori, kode unit, NIM sintetis, dan judul. Untuk cleanup persisten, verifikasi identitas `wpitv_testing` dan backup, lalu catat ID tepat dari akun dalam tabel di atas, Program dengan slug `uat-wpitv-v1-studio`, Agenda berdasarkan pembuat dan judul, Live berdasarkan pembuat dan relasi Agenda atau judul manual, pinjaman berdasarkan petugas dan NIM, serta laporan berdasarkan pembuat dan judul kegiatan. Ambil detail, audit, item dan versi dari FK ke ID induk tersebut. Periksa dependensi baru dari baris di luar paket sebelum menghapus apa pun. Jika ada, selesaikan dependensi bersama operator UAT.

Urutan cleanup yang dapat ditinjau adalah event laporan, versi dan lampiran, item, lalu laporan. Untuk Equipment, urutannya adalah audit, detail, pinjaman, unit dan kategori. Untuk Live, urutannya adalah event, Live dan Agenda. Setelah itu tinjau Program dan akun sintetis. Biarkan role kanonis tetap tersedia. Bila operator mengunggah lampiran selama UAT, periksa juga referensi file privat. Cleanup persisten adalah operasi eksplisit pada testing dengan ID yang sudah diverifikasi. Seeder tidak menghapus histori. Untuk verifikasi otomatis, tes memakai transaksi rollback sehingga seluruh fixture tes hilang saat tes selesai.

## Hasil verifikasi

Tes seeder MySQL lulus 2 tes dan 46 assertions. Cakupan mencakup dua eksekusi tanpa duplikasi, checksum baris lama termasuk akun, mode dan status Live, sumber kegiatan Production, constraint unit aktif, agregasi serta filter kategori/status/tanggal. Kegagalan simpan laporan yang disuntikkan setelah data Live dan Equipment dibuat menggulung balik seluruh paket. Regresi Equipment dan laporan bulanan lulus 38 tes dan 602 assertions. Fingerprint skema dan baris kedua database cocok sebelum dan sesudah tes. Data UAT persisten dan UAT browser tetap dijalankan oleh operator menggunakan akun sintetis.
