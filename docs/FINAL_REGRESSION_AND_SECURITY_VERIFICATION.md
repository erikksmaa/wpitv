# WPiTV Phase 6C — Verifikasi regresi dan keamanan

## Baseline

HEAD cocok dengan `9a35b6725495a828c5ff3372f62f277cbb9d829c`. Seluruh 247 hash working tree Phase 6B cocok sebelum verifikasi. Laporan Phase 6B berada di luar daftar hash karena memuat manifest itu sendiri. Pemeriksaan daftar tracked/untracked dan aturan ignore menemukan `.env`, `.env.testing`, state provisioning dan build lokal di luar calon rilis. SQL historis dalam `docs/sql` adalah skrip operasional, bukan dump. Skrip tersebut dikecualikan dari eksekusi dan paket runtime.

Perubahan sejak manifest terbatas pada dokumentasi berikut. Hash Phase 6B tetap sebagai catatan baseline.

| Berkas | Perubahan dan SHA-256 |
| --- | --- |
| FINAL_PROJECT_AUDIT.md | Diperbarui · `dbcd9624001967a78404d02426b97dcc09b36f8ebe2ffffd8d0aba8fc8af8ba2` |
| FINAL_UAT_CHECKLIST.md | Diperbarui · `0cea3aeeadad9e1a03e8a77623a02721c1d7f15815b958c7fb809b3fe0edb1ec` |
| FINAL_REGRESSION_AND_SECURITY_VERIFICATION.md | Ditambahkan |

## Cakupan

| Interaksi | Bukti otomatis |
| --- | --- |
| Program, Tayangan, Agenda dan Live | Feature admin/publik untuk visibilitas, arsip, keterkaitan Program, transisi Agenda dan widget Live. |
| Agenda/Peliputan dan laporan bulanan | MonthlyActivityEditorTest untuk kandidat, deduplikasi dan scope divisi, serta integrasi workflow dan lampiran. |
| TVClub, Program dan media sosial | Feature portal dan pengelolaan TVClub, integrasi referensi Program, scope role, versi laporan, koreksi dan ekspor. |
| Inventaris, peminjaman dan dasbor | EquipmentIntegrationTest untuk checkout/retur, rollback, versi, akses dan lima KPI, beserta pengujian race. |
| Autentikasi dan hak akses lintas modul | Unit RoleAuthorizationTest, Feature Auth/AccessControl/RoleSecurity, serta integrasi route langsung, aksi Livewire, ekspor, lampiran aktif dan historis. |

Pemeriksaan CSRF menguji middleware Laravel di luar bypass PHPUnit pada 12 kombinasi token valid, kosong, dan salah untuk jalur upload, ekspor mutasi, dan Livewire. Pemeriksaan ini menguji middleware secara langsung. Suite Feature memeriksa pemasangannya dalam grup web. Pengujian juga mencakup host yang disetujui, header, konfigurasi debug produksi, respons error aman Equipment, dan root storage privat.

Tes dashboard memeriksa batas jumlah query, agregasi, dan isolasi query menurut role. Baseline 5F.1 mencakup ekspor 5.000 unggahan/500 akun dengan peak PHP sekitar 92,8 MiB. Snapshot dimuat penuh sebelum CSV dialirkan per baris. Kapasitas di atas fixture tersebut belum dibuktikan.

## Hasil eksekusi

**READY FOR UAT.** Seluruh suite fungsional lulus. Pint mencatat temuan format baseline yang perlu diselesaikan sebelum rilis.

| Pemeriksaan | Hasil |
| --- | --- |
| Unit penuh | 462 tes, 2.140 assertions lulus |
| Feature penuh | 726 tes, 3.679 assertions lulus |
| Integrasi dan konkurensi | 66 tes, 22.487 assertions lulus |
| JavaScript tema | 7 tes lulus |
| PHP lint, Blade, Vite, diff check | Lulus. Lint mencakup 322 berkas. Vite melaporkan peringatan PostCSS. |
| Pint repository | Gagal pada format lima migration Equipment. Temuan mencakup gaya deklarasi kelas, kurung, kutip, dan line ending. Hash migration dipertahankan. |

Ketiga suite PHPUnit melaporkan 1.254 tes dengan 28.306 assertions tanpa kegagalan atau skip. Suite integrasi mencakup race Equipment dan Live pada dua proses/koneksi, serta race laporan bulanan dan sosial. Uji ekspor sosial mengulang ukuran fixture 5F.1. Peak proses PHPUnit 99,2 MiB dengan tambahan sekitar 48,7 MiB pada kedua ekspor. Durasi lokal 320,7 ms untuk unggahan dan 184,3 ms untuk pengikut.

Fingerprint skema dan data cocok dengan baseline untuk 35 tabel masing-masing sebelum dan sesudah tes. Perbandingan DDL mencakup FK, CHECK, dan indeks. Guard memverifikasi runner DML-only dan isolasi akses. Cleanup tidak meninggalkan fixture. Temuan audit sebelumnya mengenai ekspor Equipment melalui GET yang menulis audit tetap perlu ditinjau sebelum rilis. Verifikasi staging untuk CSP, cookie, debug dan error response aktual juga masih terbuka.

## Browser dan UAT

Fase ini menggunakan pengujian otomatis dan bukti browser dari fase sebelumnya. Unduhan CSV sosial sudah dibuktikan di Chrome pada 5F.1. File picker lampiran bulanan, dialog Save as PDF, siaran WPiTV nyata, serta pengujian empat role pada staging tetap merupakan skenario penerimaan terbuka. Restore SQL 6B lulus. Recovery media publik dan lampiran privat masih perlu dibuktikan. [Checklist UAT](FINAL_UAT_CHECKLIST.md)
