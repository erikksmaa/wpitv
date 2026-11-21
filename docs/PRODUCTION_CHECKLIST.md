# PRODUCTION DEPLOYMENT CHECKLIST

**WPiTV Modernization — release gate**

Checklist ini harus diselesaikan untuk domain dan infrastruktur produksi yang sebenarnya. Nilai contoh dalam `.env.example` bukan nilai produksi.

## PRE-DEPLOY

- [ ] Tetapkan tag/commit rilis dan catat penanggung jawab deployment serta rollback.
- [ ] Buat backup database penuh, media di `storage/app/public`, dan lampiran laporan di disk privat. Verifikasi keterbacaan, hash dan restore pada lingkungan terpisah.
- [ ] Jangan jalankan `migrate:fresh`, `migrate:reset`, `db:wipe`, atau migrasi destruktif. Database existing tetap source of truth.
- [ ] Verifikasi baseline 35 tabel, 18 catatan migration dan bentuk skema terhadap manifest rilis. Untuk target yang masih memakai baseline legacy, siapkan urutan provisioning additive serta verifikasi fingerprint dan jalur rollback di staging.
- [ ] Jalankan `composer audit --locked` dan `npm audit`; setiap advisory baru harus ditinjau sebelum rilis.
- [ ] Jalankan seluruh test suite, randomized test suite, Pint, build Vite, pemeriksaan Blade, dan audit rute.
- [ ] Pastikan `public/hot` tidak ada dan `public/build/manifest.json` berasal dari build rilis.
- [ ] Pastikan DocumentRoot hanya menunjuk ke `<project>/public`; `.env`, `vendor`, `storage`, `tests`, `docs`, `.git`, dan source project tidak boleh dapat diunduh lewat HTTP.
- [ ] Gunakan PHP 8.2+ beserta ekstensi yang diwajibkan Composer; gunakan Composer 2.x dan Node/npm hanya bila build dilakukan di server.

## DEPLOY

- [ ] Salin `.env.example` ke `.env`, lalu ganti seluruh placeholder dan secret.
- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<domain-produksi>`, `LOG_LEVEL=error`, dan `APP_KEY` yang valid.
- [ ] Gunakan akun database khusus aplikasi dengan password kuat; jangan gunakan `root`, password kosong, atau hak DDL/administratif. Hak runtime dibatasi ke `SELECT`, `INSERT`, `UPDATE`, dan `DELETE` pada database WPiTV.
- [ ] Set `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `SESSION_LIFETIME=30`, `ADMIN_SESSION_IDLE_TIMEOUT=30`, dan `ADMIN_SESSION_ABSOLUTE_LIFETIME=8`.
- [ ] Verifikasi APP_KEY tersedia melalui secret store dan konsisten untuk rilis yang berbagi sesi. Cocokkan session, cache, queue, filesystem dan mail dengan infrastruktur. Uji worker jika memakai queue asinkron dan gunakan tujuan email uji di staging.
- [ ] Set `TRUSTED_HOSTS` ke hostname literal produksi, dipisahkan koma bila lebih dari satu. Jangan memakai wildcard.
- [ ] Biarkan `TRUSTED_PROXIES` kosong bila origin diakses langsung. Bila ada proxy/load balancer, isi hanya IP/CIDR proxy yang dikelola; jangan memakai `*`.
- [ ] Pertahankan `SECURITY_CSP_REPORT_ONLY=true` pada cutover awal. Pantau pelanggaran CSP di staging/produksi, selesaikan kebutuhan inline script/style, lalu ubah ke `false` hanya setelah alur publik dan admin lolos pengujian browser.
- [ ] Pastikan HTTPS valid dan HTTP selalu dialihkan ke HTTPS. HSTS hanya dikirim pada request HTTPS produksi.
- [ ] Nonaktifkan `expose_php`; sembunyikan `X-Powered-By` pada PHP-FPM/web server; terapkan header keamanan pada respons statis dan error juga.
- [ ] Tolak eksekusi `php`, `phtml`, dan `phar` di `/storage`. Konfigurasi Apache proyek sudah menolak ekstensi tersebut; Nginx harus memiliki aturan ekuivalen sebelum handler PHP.
- [ ] Jalankan `composer install --no-dev --optimize-autoloader`.
- [ ] Jalankan `npm ci && npm run build`, atau deploy `public/build` yang dibangun oleh CI tepercaya. Node_modules tidak perlu tersedia di produksi setelah build.
- [ ] Jalankan `php artisan storage:link`. Hanya gambar tervalidasi yang boleh berada di `storage/app/public`; upload sementara Livewire tetap di disk private `storage/app/private`.
- [ ] Berikan izin tulis hanya kepada user web server pada `storage` dan `bootstrap/cache`; jangan gunakan mode `777`.
- [ ] Jalankan `php artisan optimize:clear`, lalu `php artisan config:cache`, `php artisan route:cache`, dan `php artisan view:cache`.
- [ ] Jalankan `php artisan route:list` dari release yang sudah di-cache dan pastikan tidak ada rute register, reset password publik, verifikasi email, Banner, debug, atau test-only.

## POST-DEPLOY

- [ ] Verifikasi `/up`, `/`, `/tentang`, `/program`, `/tayangan`, `/berita`, `/tv-club`, `/peliputan`, `/peliputan/ajukan`, `/peliputan/status`, dan `/agenda` pada desktop serta viewport mobile.
- [ ] Verifikasi halaman detail Program, Tayangan YouTube, Berita, generasi TV Club, dan anggota menggunakan data produksi yang sah.
- [ ] Verifikasi Public Visitor Counter di Homepage menampilkan hanya agregat Hari ini, Kemarin, Bulan ini, Tahun ini, dan Online tanpa IP. Nilai dapat tertunda hingga 60 detik; Online berarti record dengan aktivitas Homepage dalam lima menit terakhir.
- [ ] Kirim satu permohonan Peliputan yang jelas diberi label smoke test; verifikasi receipt terikat sesi, lookup memerlukan kode plus nomor telepon, lalu hapus data smoke test dengan prosedur operasional yang tercatat.
- [ ] Login dan logout menggunakan akun uji terkontrol untuk keempat peran: Super Admin, Produksi, News, dan TV Club.
- [ ] Cocokkan sidebar, respons 403, dan operasi create/edit yang diizinkan dengan `docs/RBAC_ACCESS_MATRIX.md`. Users/Roles hanya untuk Super Admin.
- [ ] Verifikasi upload JPEG/PNG/WebP valid berhasil, upload non-gambar/oversize ditolak, dan hasil tersimpan sebagai WebP pada folder fitur yang benar.
- [ ] Periksa header `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, CSP report-only, HSTS, serta tidak adanya `X-Powered-By` pada halaman dinamis, aset statis, 404, dan redirect.
- [ ] Tinjau browser console/CSP reports, `storage/logs/laravel.log`, log PHP-FPM, log web server, antrean, kapasitas disk, dan koneksi database.
- [ ] Pastikan source database tidak berubah akibat test suite lokal/CI dan tidak mengandung akun atau record QA sintetis.

## ROLLBACK

- [ ] Aktifkan maintenance mode dengan secret yang disimpan aman dan batasi trafik tulis.
- [ ] Kembalikan symlink/release directory ke tag sebelumnya; jalankan `composer install --no-dev --optimize-autoloader` untuk lockfile rilis tersebut.
- [ ] Restore database hanya bila deployment memang mengubah data dan setelah dampak perubahan pasca-cutover dinilai. Gunakan backup yang telah diuji; jangan menimpa data baru tanpa keputusan pemilik data.
- [ ] Sinkronkan media publik dan lampiran laporan privat dengan keadaan database yang dipulihkan bila rilis memengaruhi berkas.
- [ ] Jalankan `php artisan optimize:clear`, kemudian bangun ulang config/route/view cache dari rilis sebelumnya.
- [ ] Ulangi health check, smoke test publik/login, dan pemeriksaan log sebelum `php artisan up`.
- [ ] Catat penyebab, waktu, commit, backup yang digunakan, dampak data, dan tindak lanjut insiden.
