# PANDUAN DEPLOYMENT WPiTV MODERNIZATION

Dokumen ini berisi panduan praktis langkah demi langkah untuk melakukan deployment proyek **WPiTV Modernization** (Laravel 12 + Livewire + Vite) ke server produksi (seperti cPanel / Shared Hosting / VPS / Cloud Server) secara aman, terstruktur, dan mudah dipahami.

---

## 1. Kebutuhan Server

Spesifikasi minimum server yang dibutuhkan:

- **PHP**: Versi 8.2 atau lebih baru
- **Ekstensi PHP**: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `curl`, `fileinfo`, `gd`, `bcmath`
- **Database**: MySQL 8.x atau MariaDB 10.6+ yang kompatibel
- **Composer**: Composer 2.x
- **Web Server**: Apache (dengan modul `mod_rewrite` aktif) atau Nginx
- **Protokol**: SSL / HTTPS aktif
- **Node.js & npm**: **Hanya diperlukan** jika kompilasi aset frontend dilakukan langsung di server. Jika folder `public/build` sudah disertakan dalam paket rilis, Node.js tidak wajib dipasang di server.

---

## 2. Upload Project

1. Unggah dan ekstrak seluruh folder project ke direktori server (contoh: `/var/www/wpitv` pada VPS atau direktori di luar `public_html` pada cPanel).
2. **PENTING**: Konfigurasikan Document Root web server agar **WAJIB** mengarah ke subfolder `<project>/public` (bukan ke root project).
   - **Contoh Apache VirtualHost:** `DocumentRoot "/var/www/wpitv/public"`
   - **Contoh cPanel / Plesk:** Arahkan Document Root domain/subdomain ke path `wpitv/public`.

> Mengarahkan Document Root ke subfolder `public` menjamin keamanan agar file `.env`, folder `storage`, dan source code aplikasi tidak dapat diakses atau diunduh langsung dari browser.

---

## 3. Install Dependency

Jalankan perintah Composer di dalam direktori project untuk memasang paket dependensi PHP produksi:

```bash
composer install --no-dev --optimize-autoloader
```

> **Catatan:**
> - Jangan gunakan `composer update` saat proses deployment produksi agar versi paket tetap stabil dan sesuai dengan yang telah diuji.
> - Jika folder `vendor` sudah disertakan lengkap di dalam paket project dan server tidak memiliki akses Composer (misal shared hosting sederhana), langkah ini dapat dilewati.

---

## 4. Frontend Asset

Aplikasi WPiTV menggunakan antarmuka modern yang dikompilasi menggunakan Tailwind CSS dan Vite.

- **Mode Development (Hanya saat pengembangan lokal):**
  Menggunakan `npm run dev`.
- **Mode Production (Server / Siap Pakai):**
  Menggunakan file statis hasil kompilasi dari `npm run build`.

> **PENTING UNTUK PRODUKSI:**
> 1. Jika folder `public/build` sudah ada dan disertakan dalam paket rilis, Anda **TIDAK PERLU** menjalankan Node.js atau npm di server produksi.
> 2. **JANGAN** menjalankan `npm run dev` di server produksi. Vite tidak perlu aktif sebagai background service di server.
> 3. Jika Anda perlu membangun ulang aset langsung di server:
>    ```bash
>    npm ci
>    npm run build
>    ```

---

## 5. Database

1. Buat database baru di MySQL/MariaDB (misal: `wpitv`).
2. Buat user database dengan hak akses yang sesuai ke database tersebut.
3. Import file SQL dump final WPiTV yang telah disediakan ke dalam database tersebut (melalui phpMyAdmin, MySQL Workbench, atau terminal `mysql -u user -p wpitv < dump.sql`).
4. Masukkan nama database, username, dan password ke file `.env`.

> ⚠️ **PERINGATAN PENTING MENGENAI DATABASE:**
> Database existing adalah **source of truth** untuk seluruh data dan skema WPiTV.
>
> **JANGAN PERNAH** menjalankan perintah migrasi Laravel berikut pada server produksi:
> - `php artisan migrate`
> - `php artisan migrate:fresh`
> - `php artisan db:wipe`
> - `php artisan migrate:reset`
>
> Menjalankan perintah migrasi di atas dapat merusak atau menghapus struktur serta data historis yang sudah ada.

---

## 6. Konfigurasi .env

Salin file `.env.example` menjadi `.env` jika belum tersedia, lalu sesuaikan nilai variabel berikut dengan konfigurasi server produksi Anda:

```ini
APP_NAME=WPiTV
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database_anda
DB_USERNAME=user_database_anda
DB_PASSWORD=password_database_anda

SESSION_DRIVER=file
SESSION_LIFETIME=30
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

ADMIN_SESSION_IDLE_TIMEOUT=30
ADMIN_SESSION_ABSOLUTE_LIFETIME=8

TRUSTED_HOSTS=domain-anda.com
TRUSTED_PROXIES=
```

> **Hal Penting:**
> - Pastikan `APP_DEBUG=false` agar detail error teknis atau informasi rahasia tidak bocor ke publik.
> - Variabel `ADMIN_SESSION_IDLE_TIMEOUT=30` (timeout idle admin 30 menit) dan `ADMIN_SESSION_ABSOLUTE_LIFETIME=8` (maksimal sesi admin 8 jam) adalah variabel pengaman sesi yang aktif di aplikasi ini.

---

## 7. APP_KEY

Aplikasi membutuhkan kunci enkripsi yang valid untuk keamanan sesi dan cookie.

Jika nilai `APP_KEY` pada file `.env` masih kosong, jalankan perintah:

```bash
php artisan key:generate
```

> **Catatan:** Jangan menjalankan perintah generate kunci ini jika aplikasi sudah beroperasi di produksi dan memiliki sesi aktif, karena akan mereset kunci enkripsi aplikasi.

---

## 8. Storage dan Gambar

Pengelolaan media gambar (Program, Berita, dan TV Club) menggunakan sistem storage publik Laravel.

1. **Jalankan Perintah Symlink Storage:**
   ```bash
   php artisan storage:link
   ```
   Perintah ini akan membuat shortcut/symlink dari folder `public/storage` ke `storage/app/public`.

2. **Struktur Folder Media di Laravel:**
   - **Program:** `storage/app/public/programs/`
   - **Berita (News):** `storage/app/public/news/`
   - **Foto Anggota TV Club:** `storage/app/public/tvclub/members/`

   Semua media tersebut dapat diakses oleh publik melalui URL `/storage/...` (contoh: `/storage/programs/nama-file.webp`).

3. **Cara Kerja Penyimpanan di Database:**
   - Kolom database (`image` pada tabel `ms_program` dan `ms_news`, serta `photo` pada `tvclub_members`) **hanya menyimpan nama file saja** (contoh: `01 AKTUAL.jpg` atau `berita-1788361740-tbpcj9.webp`), bukan path folder.
   - Saat admin mengunggah gambar baru, sistem otomatis mengoptimasi ukuran gambar menjadi format WebP dan menyimpan nama filenya ke database.

4. **Catatan Gambar Historis (Data Lama):**
   - File fisik gambar lama dari website WPiTV harus diletakkan di dalam direktori storage yang sesuai (`storage/app/public/programs/`, `storage/app/public/news/`, dll.) dengan nama file yang **cocok persis** dengan yang tersimpan di database.
   - Jika suatu record di database memiliki nama file gambar tetapi file fisiknya belum disalin ke server, sistem akan secara otomatis menampilkan gambar placeholder bawaan tanpa menimbulkan error.

---

## 9. Permission Folder

Pastikan pengguna web server memiliki izin baca dan tulis (*write permission*) pada folder berikut:
- `storage/`
- `bootstrap/cache/`

Contoh perintah permission pada server Linux / VPS:
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

> **Catatan:** Hindari memberikan izin `777` demi keamanan server, kecuali jika lingkungan hosting Anda memang memiliki batasan khusus yang mengharuskannya.

---

## 10. Cache Laravel

Setelah file `.env` selesai dikonfigurasi dan dipastikan benar, jalankan rangkaian perintah berikut untuk mengoptimalkan performa aplikasi di lingkungan produksi:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> **Tip:** Jika Anda melakukan perubahan pada file `.env` di kemudian hari, jalankan kembali perintah `php artisan optimize:clear` dan `php artisan config:cache` agar perubahan diterapkan.

---

## 11. HTTPS

1. Pastikan sertifikat SSL (HTTPS) sudah aktif pada domain server Anda.
2. Pastikan nilai `APP_URL` pada file `.env` menggunakan protokol `https://` (contoh: `https://wpitv.example.com`).
3. Pastikan `SESSION_SECURE_COOKIE=true` pada `.env` agar cookie sesi hanya dikirimkan melalui koneksi aman HTTPS.

*(Untuk verifikasi keamanan server lebih rinci seperti Content Security Policy dan HTTP Security Headers, lihat panduan di `docs/PRODUCTION_CHECKLIST.md`).*

---

## 12. Pengecekan Setelah Deployment

Lakukan pengujian cepat (*smoke test*) untuk memastikan semua fungsi berjalan normal:

### Halaman Publik
- [ ] **Home** (`/`) — Carousel program, video terbaru, dan counter pengunjung tampil.
- [ ] **Program** (`/program` dan detail `/program/{slug}`) — Daftar dan halaman detail program terbuka dengan baik.
- [ ] **Tayangan** (`/tayangan` dan detail `/tayangan/{slug}`) — Video YouTube terintegrasi dan dapat diputar.
- [ ] **Berita** (`/berita` dan detail `/berita/{slug}`) — Artikel berita dan gambar cover tampil rapi.
- [ ] **Peliputan** (`/peliputan`, `/peliputan/ajukan`, `/peliputan/status`) — Form pengajuan peliputan dan fitur cek status dengan kode permohonan berfungsi.
- [ ] **Agenda** (`/agenda`) — Jadwal agenda kegiatan tampil.
- [ ] **TV Club** (`/tv-club`, `/tv-club/anggota`, `/tv-club/generasi`, `/tv-club/tim-produksi`) — Profil generasi dan daftar kru TV Club tampil.

### Panel Admin (`/login`)
- [ ] **Login & Logout** — Berhasil login dengan akun pengelola.
- [ ] **Dashboard** — Ringkasan data tampil.
- [ ] **CRUD Data** — Tambah, edit, dan hapus data sesuai hak akses peran (Program, Video, Berita, TV Club, Peliputan, Agenda, Users, Roles).
- [ ] **Interaktivitas Livewire** — Fitur filter, pencarian, dan pagination berfungsi responsif tanpa reload halaman.
- [ ] **Upload Gambar** — Unggah gambar baru di Program atau Berita berhasil diproses dan tampil.

### Verifikasi Teknis
- [ ] Tampilan CSS dan JavaScript rapi dan tidak berantakan.
- [ ] Gambar tampil normal (atau placeholder muncul jika gambar historis belum disalin).
- [ ] Tidak ada error HTTP 500, error 404 pada aset, atau error 419 (Page Expired).
- [ ] Request interaktif Livewire (`/livewire/update`) berhasil dengan status 200 OK.

---

## 13. Troubleshooting Singkat

### CSS / JS Tidak Tampil atau Tampilan Rusak
- **Penyebab:** Folder `public/build` belum ada atau belum dikompilasi untuk produksi.
- **Solusi:** Jalankan `npm run build` di komputer lokal, lalu unggah folder `public/build` ke dalam folder `public/` di server. Pastikan file `public/hot` tidak ada di server.

### Gambar Tidak Tampil
- **Penyebab:** Symlink storage belum dibuat atau file gambar belum berada di folder yang tepat.
- **Solusi:**
  1. Jalankan `php artisan storage:link`.
  2. Pastikan file gambar berada di `storage/app/public/programs/`, `storage/app/public/news/`, atau `storage/app/public/tvclub/members/`.
  3. Pastikan nama file fisik di folder storage sama persis dengan yang ada di database.

### Error 500 (Internal Server Error)
- **Penyebab:** Kesalahan konfigurasi database di `.env`, `APP_KEY` kosong, atau permission folder storage bermasalah.
- **Solusi:**
  1. Periksa kredensial database di `.env`.
  2. Pastikan permission folder `storage/` dan `bootstrap/cache/` dapat ditulis oleh web server (`chmod -R 775`).
  3. Periksa file log di `storage/logs/laravel.log` untuk melihat detail penyebab error.

### Livewire Error / 419 Page Expired
- **Penyebab:** Nilai `APP_URL` di `.env` tidak cocok dengan URL domain di browser, konfigurasi HTTPS mismatch, atau domain tidak mengarah ke subfolder `/public`.
- **Solusi:**
  1. Pastikan `APP_URL` di `.env` sesuai dengan URL yang dibuka (termasuk protokol `https://`).
  2. Jika menggunakan HTTPS, pastikan `SESSION_SECURE_COOKIE=true`.
  3. Pastikan Document Root domain mengarah tepat ke subfolder `<project>/public`.
  4. Jalankan `php artisan optimize:clear` kemudian bangun ulang cache dengan `php artisan config:cache`.

---

## 14. Ringkasan Command

### Perintah Utama di Server Produksi
```bash
# 1. Pasang dependensi PHP produksi (jika vendor belum disertakan)
composer install --no-dev --optimize-autoloader

# 2. Buat APP_KEY aplikasi (jika masih kosong di .env)
php artisan key:generate

# 3. Hubungkan folder storage publik
php artisan storage:link

# 4. Bersihkan dan bangun cache produksi
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Jika Frontend Perlu Dikompilasi di Server
```bash
npm ci
npm run build
```

---

### ⛔ JANGAN DIJALANKAN DI SERVER PRODUKSI

```bash
# Jangan jalankan server development di produksi:
php artisan serve
npm run dev

# Jangan jalankan migrasi yang dapat menghapus data:
php artisan migrate
php artisan migrate:fresh
php artisan db:wipe
php artisan migrate:reset
```
