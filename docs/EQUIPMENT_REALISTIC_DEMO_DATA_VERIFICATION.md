# Demonstrasi Equipment 6C.4I

Paket aktif pada MySQL lokal `wpitv_testing`. Kedua database memiliki 39 tabel dan 24 migration. Manifest testing membandingkan SHOW CREATE TABLE aktual, termasuk tipe, FK, indeks, UNIQUE, generated column dan CHECK. Runner diperiksa melalui identitas sesi dan SHOW GRANTS. Fingerprint awal fase dipakai karena isi database utama sudah berbeda dari baseline fase sebelumnya.

### Katalog dan transaksi

Tujuh kategori mencakup kamera, tripod/stabilizer, audio, pencahayaan, streaming, kabel/konektor, dan baterai/daya. Sepuluh barang memiliki 51 unit fisik (10 induk dan 41 anggota). Kode induk memakai generator bersama form inventaris. Hubungan kelompok, kode turunan, stok dan pengarsipan menggunakan EquipmentQuantity. Paket lama dengan kategori dan nama bertanda UAT cocok dengan struktur WpitvUatSeeder. Paket ini tidak mengambil kepemilikan atas baris lama. Pemeriksaan idempotensi menggunakan ledger dan hash per baris.

| Tahap | Total | Tersedia | Dipinjam | Rusak | Nonaktif |
| --- | ---: | ---: | ---: | ---: | ---: |
| Stok awal | 51 | 51 | 0 | 0 | 0 |
| Sesudah mengarsipkan satu baterai cadangan | 51 | 50 | 0 | 0 | 1 |
| Setelah lima checkout dan tiga retur | 51 | 42 | 7 | 1 | 1 |

Lima transaksi mencakup dokumentasi seminar, wawancara, produksi profil program studi, liputan organisasi, dan pengambilan gambar UKM. Dua gimbal dipinjam melewati jatuh tempo. Dari tiga unit untuk wawancara, satu sudah kembali. Dua transaksi lainnya selesai, termasuk satu kamera dengan pengunci longgar. Tiga receipt mencatat tiga bukti per unit. Terdapat 71 event audit. Tanggal dihitung dalam WIB saat paket pertama dibuat. Build berikutnya mempertahankan tanggal dan histori.

Nama, NIM dan petugas merupakan identitas fiktif. Kontak memakai rentang fiktif 202-555-01xx. Sepuluh gambar katalog berupa kartu identifikasi buatan lokal. Tiga gambar pengembalian berlabel simulasi. Gambar diproses oleh validator dan disimpan pada disk privat. Data ini hanya untuk latihan.

### Menampilkan dan membersihkan paket

Gunakan panel admin lingkungan testing dengan role Production atau Super Admin. Cari A6400 di Unit Inventaris, lalu pilih dua unit pada form peminjaman. Pada daftar pinjaman, buka transaksi rekaman wawancara untuk melihat retur sebagian dan bukti privat.

Dari root proyek, gunakan `php scripts/equipment-demo.php inspect` untuk memeriksa target, lalu `php scripts/equipment-demo.php build` untuk membuat atau memverifikasi paket. ID, hash dan kunci media disimpan secara privat di `storage/app/testing/equipment-studio-ledger.json` yang diabaikan Git. Build memakai transaksi dan advisory lock. Petugas sintetis memiliki password acak yang tidak diterbitkan.

Gunakan `php scripts/equipment-demo.php cleanup` untuk membersihkan paket. Cleanup memeriksa UUID server, hash seluruh baris milik paket dan referensi FK sebelum menghapus berdasarkan ID dalam satu transaksi. Perubahan dari penggunaan manual dapat membuat cleanup ditolak. Periksa ledger dan dependensi secara terpisah bila itu terjadi. Setelah commit, hanya file privat yang sudah tidak dirujuk yang dibersihkan. Simpan ledger selama paket masih aktif.

### Verifikasi lokal

Build ulang, cleanup dan pemasangan kembali berhasil. Fingerprint 39 tabel diperiksa pada kedua database. Chrome menguji desktop 1366 px terang dan ponsel 390 px gelap, termasuk kategori, kartu inventaris, detail Sony A6400, foto privat, batas quantity dua unit, transaksi parsial serta bukti retur yang dapat dibuka. Suite Equipment mencakup stok berlebih, alokasi, rollback, return, konflik konkuren dan RBAC. PHP lint, Pint, kompilasi Blade, build Vite dan diff check berhasil. Vite memberikan peringatan PostCSS terkait opsi from. UAT pengguna dan deployment tetap terpisah.
