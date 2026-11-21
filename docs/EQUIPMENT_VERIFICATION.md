# Inventory & Equipment Loan verification — Phase 2E

## Pembaruan: migration aplikasi lokal wpitv

Atas permintaan pengguna untuk memperbaiki `EQ_DATABASE` pada aplikasi utama, kelima migration inventaris/peminjaman telah diterapkan ke **database aplikasi lokal `wpitv`** pada 2026-11-08 15:13 UTC. Diagnosis Laravel sebelumnya memastikan environment local, MySQL 8.0.30, 17 tabel, dan kelima tabel modul belum tersedia.

- Backup lengkap terbaru dibuat sebelum perubahan: `storage/app/testing/main-backups/wpitv-before-inventory-20261108-151215-baccd4.sql`. Dump berhasil, struktur 17 tabel dan data tersedia, serta SHA-256 dicatat pada state deployment lokal yang Git-ignored. Restore tidak dijalankan.
- Laravel menjalankan tepat lima migration yang sudah diuji, dengan koneksi `mysql` diverifikasi ulang menuju `wpitv`. Seluruh migration DONE dan tercatat; tidak menjalankan fresh/reset, rollback, seeder atau penghapusan data.
- Hasil: **22 tabel**, 10 FK baru, 10 CHECK enforced, dan skema lima tabel baru identik dengan skema testing setelah normalisasi penghitung AUTO_INCREMENT.
- Fingerprint skema dan seluruh **36.390 baris lama** pada 17 tabel baseline terjaga. Perubahan pada repository `migrations` hanya penambahan lima catatan migration; semua catatan lamanya tetap identik. Tabel modul baru masih kosong; tidak membuat data dummy di database utama.
- Smoke check read-only pada aplikasi utama berhasil: empat komponen Livewire (kategori, unit, daftar peminjaman, laporan) dirender, query laporan berhasil dan ringkasan inventaris dashboard tersedia. Tidak menjalankan test suite pada database utama.

Catatan Phase 2E berikut merekam hasil pengujian sebelum penerapan aplikasi lokal ini; pernyataan database utama masih 17 tabel di bagian historis tidak lagi menggambarkan keadaan terkini. Ini tetap bukan deployment ke server production di luar Laragon.

## Hasil Phase 2E sebelum penerapan aplikasi lokal

**READY untuk lingkungan testing terisolasi.** Ini bukan penerapan migration pada database utama atau approval deployment production.

## Environment dan skema

Administrator MySQL lokal berhasil diautentikasi memakai konfigurasi yang sudah sah, tanpa menebak password. Hanya akun `wpitv_test_runner@localhost` yang diubah: password acak baru dan privilege SELECT/INSERT/UPDATE/DELETE pada `wpitv_testing` saja. Akses `wpitv`, DDL, GRANT OPTION dan role MySQL ditolak oleh guard. `.env.testing` memiliki APP_KEY terpisah dan diabaikan Git; secret tidak ditampilkan atau disalin ke source. Semua suite/worker memakai runner, bukan administrator.

Session/cache array, log null, storage, views dan bootstrap cache CLI terisolasi. QA browser memakai session file private terpisah agar cookie/CSRF berfungsi antarreques; tidak mengubah konfigurasi utama.

Lima migration additive diterapkan berurutan melalui koneksi provisioning terpisah dengan pemanggilan `up()` lima file yang telah direview; tidak menggunakan perintah Artisan migration/reset/wipe. Hasil diverifikasi: **22 tabel, 10 FK baru, 10 CHECK baru**, generated column tersimpan dan UNIQUE `active_unit_id`. Lima entry migration tercatat pada database testing. Tidak ada rollback destruktif. Manifest lokal diperbarui dari skema aktual dengan `show-create-v1` dan referensi otorisasi eksplisit pengguna fase ini; hash baseline lama dipertahankan.

## Hasil aktual

Suite: `php vendor/bin/phpunit --configuration phpunit.equipment.xml --do-not-cache-result`, dengan opt-in proses `WPITV_EQUIPMENT_CONCURRENCY=true`. Suite default dan transaksi luar modul existing tetap terpisah.

- **131 tes otomatis, 585 assertion: lulus** pada eksekusi akhir. Eksekusi sebelumnya juga lulus dengan 587 assertion; jumlah assertion cabang race mengikuti pemenang. JUnit terakhir: `storage/app/testing/phase2e-junit.xml` (lokal, Git-ignored).
- **13 integration tests:** CRUD kategori/unit, unik, arsip, version/stale form, kondisi/histori, checkout tunggal/multiunit, idempotensi actor/payload/token, return parsial/penuh, loss/recovery/inspeksi, extension/overdue/due history, correction terbatas, rollback audit semua lifecycle service, CHECK/UNIQUE nyata, laporan/CSV/PII/audit ekspor, lima KPI/batas lima transaksi, serta RBAC URL dan mutasi Livewire.
- **8 concurrency tests:** checkout unit sama, replay checkout, konflik token/actor/payload, return overlapping/nonoverlapping, replay return, loss melawan return dan extension melawan return. Dua proses PHP/ID koneksi MySQL berbeda, parent menahan row lock sampai kedua worker mencapai callback query FOR UPDATE, kemudian commit nyata. Bukan simulasi sekuensial atau transaksi luar test.
- **QA browser terpisah lulus:** Production melakukan pemilihan dua unit, SweetAlert, checkout dan pengembalian parsial hingga status Kembali sebagian. Laporan serta dashboard diperiksa dengan Super Admin. Navigasi mobile, lima KPI dan tidak adanya identitas/kontak peminjam pada laporan/dashboard dikonfirmasi. QA memakai autentikasi fixture `actingAs`; form login production tidak termasuk pengujian ini. Server loopback 8765 sudah dihentikan. Bukti gambar: `storage/app/testing/phase2e-qa.png`.

Fixture kategori/unit/akun/transaksi memakai ledger ID/token. Karena baseline testing kosong, hanya empat nama role aplikasi yang kanonis dibuat sebagai fixture dan dilacak; role yang sudah ada tidak diubah. Cleanup menghentikan worker dahulu lalu menghapus tepat ID fixture menurut urutan FK, termasuk role fixture setelah akun. Seluruh data testing kembali ke baseline sesudah provisioning, tanpa journal tertinggal.

## Quality gate dan batas hasil

PHP lint, Pint, parsing 14 Blade dan diff check lulus. Vite build lulus dengan dependency existing; peringatan PostCSS plugin tentang opsi `from` masih ada tanpa kegagalan build. Tidak menambah dependency.

QA mengungkap penghapusan direktori cache runtime Windows dapat tertunda akibat handle compiled PHP. Cleanup direktori private kini tidak menambahkan respons error sesudah halaman sukses. Ini hanya cleanup filesystem testing; kegagalan cleanup database tetap menggagalkan tes dan mengarantina kasus berikutnya.

Fingerprint read-only daftar tabel dan SHA-256 multiset seluruh baris per tabel sebelum/sesudah pekerjaan identik: **database utama tetap 17 tabel dan 36.390 baris**. Fingerprint 22 tabel testing juga identik dengan baseline sesudah provisioning. Dua backup SQL Downloads terbaca dengan hash sesuai pemeriksaan sebelumnya; restore belum diuji dan backup historis tidak dianggap bukti data paling mutakhir. Production masih memerlukan backup/recovery serta approval deployment tersendiri.

File utama baru: `phpunit.equipment.xml`, `tests/Support/EquipmentScenarioTestCase.php`, `tests/Integration/Equipment/EquipmentIntegrationTest.php`, `tests/Concurrency/Equipment/EquipmentConcurrencyTest.php`, `tests/Concurrency/Equipment/worker.php`, `tests/Browser/EquipmentBrowserTest.php`, `tests/Browser/router.php`. Perubahan fondasi terbatas pada kode diagnosis aman di `tests/EquipmentConcurrencyTestCase.php` dan cleanup runtime di `tests/Support/TestEnvironment.php`. Model/service/policy/route aplikasi tidak diubah pada fase ini.
