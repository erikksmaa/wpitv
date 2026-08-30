# PRODUCTION DEPLOYMENT CHECKLIST
**WPiTV Modernization — Release Verification**

Gunakan checklist ini sebelum dan saat melakukan deployment ke server produksi WPiTV.

---

## 1. Server Environment
- [ ] PHP versi 8.2+ terinstall (`php -v`).
- [ ] Ekstensi PHP wajib aktif: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `gd`, `intl`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `PDO`, `pdo_mysql`, `session`, `SimpleXML`, `tokenizer`, `xml`, `xmlwriter`, `zlib`.
- [ ] Web server (Nginx / Apache) terinstall dan aktif.
- [ ] DocumentRoot web server diarahkan ke folder `/public` project (BUKAN root direktori Laravel).
- [ ] Node.js & npm terpasang jika asset compilation dilakukan di server.
- [ ] Composer 2.x terpasang.

## 2. Pre-Deployment Backup
- [ ] Database existing telah di-dump dan diverifikasi (`mysqldump -u ... > backup.sql`).
- [ ] Ukuran file dump SQL diverifikasi non-zero dan dapat dibaca.
- [ ] File media legacy dan user upload yang ada di `storage/app/public` telah di-backup.
- [ ] Konfigurasi web server sebelumnya telah di-backup.

## 3. Source Code & Dependencies
- [ ] Source code WPiTV versi rilis telah ditempatkan di server (`git clone` / upload release zip).
- [ ] Composer dependencies terinstall tanpa paket development (`composer install --no-dev --optimize-autoloader`).
- [ ] Aset frontend terkompilasi (`npm run build` atau upload folder `public/build/`).
- [ ] File `public/build/manifest.json` ada dan valid.

## 4. Environment Configuration (`.env`)
- [ ] `APP_NAME="WPiTV"`
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL=https://[DOMAIN_RESMI_WPITV]` (HTTPS)
- [ ] `WPITV_WHATSAPP_NUMBER=628xxxxxxxxxx` terisi agar tombol komunikasi Peliputan aktif.
- [ ] `DB_CONNECTION=mysql`
- [ ] `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` terkonfigurasi ke database produksi.
- [ ] `SESSION_DRIVER=file`
- [ ] `CACHE_STORE=file`
- [ ] `QUEUE_CONNECTION=sync`
- [ ] `FILESYSTEM_DISK=public`
- [ ] `LOG_LEVEL=error`
- [ ] `APP_KEY` terisi dan valid.

## 5. Database & Storage Setup
- [ ] Skema database 12 tabel terverifikasi utuh tanpa menjalankan migrasi destruktif.
- [ ] Symlink storage dibuat (`php artisan storage:link`).
- [ ] Folder sub-storage tersedia (`programs`, `news`, `banners`, `tvclub/members`).
- [ ] Fallback image placeholder (`public/images/placeholders/program-placeholder.svg`) tersedia.

## 6. Permissions & Security
- [ ] Folder `storage/` dan `bootstrap/cache/` memiliki hak akses tulis untuk user web server (misal: `chmod -R 775`).
- [ ] Tidak menggunakan `chmod 777`.
- [ ] SSL / HTTPS aktif dan me-redirect seluruh trafik HTTP ke HTTPS.
- [ ] Security headers (X-Frame-Options, X-Content-Type-Options, Referrer-Policy) terkonfigurasi di web server.
- [ ] File `.env` terlindungi dari akses publik (HTTP 403/404).

## 7. Optimization & Caching
- [ ] Cache bootstrap dibersihkan (`php artisan optimize:clear`).
- [ ] Konfigurasi di-cache (`php artisan config:cache`).
- [ ] Rute di-cache (`php artisan route:cache`).
- [ ] Blade views di-cache (`php artisan view:cache`).

## 8. Post-Deployment Smoke Test
- [ ] Halaman Beranda (`/`) dapat diakses dan merender data program, video, berita, dan TV Club.
- [ ] Halaman Program (`/program` dan `/program/{slug}`) berfungsi normal.
- [ ] Halaman Tayangan (`/tayangan` dan `/tayangan/{id}`) merender player YouTube responsif.
- [ ] Halaman Berita (`/berita` dan `/berita/{slug}`) merender konten dengan benar.
- [ ] Halaman TV Club (`/tv-club`, `/tv-club/generasi/{id}`, `/tv-club/anggota/{id}`) berfungsi.
- [ ] Alur Peliputan (`/peliputan`, `/peliputan/ajukan`, `/peliputan/status`) dapat menerima input dan menampilkan tracking status.
- [ ] Halaman Login (`/login`) dan Admin Dashboard (`/admin`) dapat diakses dengan credential valid.
- [ ] Seluruh hak akses RBAC (Super Admin, Admin Production, Admin News) berfungsi sesuai kebijakan.
- [ ] File log `storage/logs/laravel.log` bersih dari error fatal.
