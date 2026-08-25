# PANDUAN DEPLOYMENT WPiTV MODERNIZATION
**Sistem Informasi & Portal Media Televisi Kampus WPiTV — Institut Widya Pratama**

Dokumen ini berisi panduan teknis dan operasional untuk melakukan deployment aplikasi WPiTV Modernization ke server produksi (VPS, Dedicated Server, atau Web Hosting).

---

## 1. Persyaratan Server (Server Requirements)

### Spesifikasi Minimum Lingkungan:
- **Web Server**: Nginx (direkomendasikan) atau Apache 2.4+ dengan modul `mod_rewrite` aktif.
- **PHP**: Versi **8.2+** (PHP 8.4 direkomendasikan).
- **Database**: MySQL **8.0+** atau MariaDB **10.6+** (InnoDB Engine, utf8mb4 collation).
- **Node.js & npm** (khusus jika asset build dilakukan di server): Node.js 20+ & npm 10+.
- **Composer**: Composer 2.x.

### Ekstensi PHP Wajib:
Pastikan ekstensi berikut aktif di server (`php -m`):
- `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `gd` (atau `imagick`), `intl`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `PDO`, `pdo_mysql`, `session`, `SimpleXML`, `tokenizer`, `xml`, `xmlwriter`, `zlib`.

---

## 2. Strategi Database (Zero Migration Policy)

> [!IMPORTANT]
> **ATURAN WAJIB**: Database existing adalah *Source of Truth*. **JANGAN PERNAH** menjalankan `php artisan migrate:fresh`, `php artisan migrate:reset`, atau `php artisan db:wipe`.

### Skenario A: Server Produksi Baru (Fresh Server)
1. Buat database baru di MySQL:
   ```sql
   CREATE DATABASE wpitv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Import file dump SQL backup data existing:
   ```bash
   mysql -u [db_user] -p wpitv < wpitv_production_backup.sql
   ```

### Skenario B: Server Produksi Sudah Memiliki Database
1. **Lakukan backup lengkap** sebelum cutover:
   ```bash
   mysqldump -u [db_user] -p wpitv > backup_wpitv_$(date +%F_%T).sql
   ```
2. Pastikan 12 tabel utama utuh: `roles`, `users`, `ms_program`, `ms_video`, `ms_news`, `ms_banner`, `tr_usercounter`, `tr_perliputan`, `tvclub_generations`, `tvclub_members`, `tvclub_groups`, `tvclub_group_members`.

---

## 3. Langkah-Langkah Deployment

### Langkah 1: Clone atau Upload Source Code
Tempatkan project di direktori server (contoh: `/var/www/wpitv`):
```bash
git clone <repository_url> /var/www/wpitv
cd /var/www/wpitv
```
*(Atau upload release zip package dan ekstrak ke direktori target).*

### Langkah 2: Konfigurasi Environment (`.env`)
Salin template `.env.example` menjadi `.env`:
```bash
cp .env.example .env
```
Sesuaikan konfigurasi produksi berikut pada file `.env`:
```ini
APP_NAME=WPiTV
APP_ENV=production
APP_DEBUG=false
APP_URL=https://wpitv.stmik-wp.ac.id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wpitv
DB_USERNAME=your_db_username
DB_PASSWORD=your_db_secure_password

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=public

LOG_CHANNEL=stack
LOG_LEVEL=error
```

> [!WARNING]
> **APP_KEY Warning**: Jika server baru dan belum memiliki `APP_KEY`, jalankan `php artisan key:generate`. Jangan me-regenerate `APP_KEY` jika server sudah menyimpan data terenkripsi atau sesi aktif sebelumnya.

### Langkah 3: Install Dependensi PHP
Jalankan Composer tanpa paket development:
```bash
composer install --no-dev --optimize-autoloader
```

### Langkah 4: Kompilasi Aset Frontend (Vite)
**Opsi A (Build di Server)**:
```bash
npm ci
npm run build
```
**Opsi B (Build Lokal / CI)**: Pastikan folder `public/build/` telah dikompilasi secara lokal lalu diupload ke server.

### Langkah 5: Setup Storage Symlink & Direktori
1. Buat symlink storage publik:
   ```bash
   php artisan storage:link
   ```
2. Pastikan subfolder storage publik tersedia:
   ```bash
   mkdir -p storage/app/public/programs
   mkdir -p storage/app/public/news
   mkdir -p storage/app/public/banners
   mkdir -p storage/app/public/tvclub/members
   ```

### Langkah 6: Hak Akses File (Permissions)
Pastikan webserver (contoh: `www-data` atau `nginx`) memiliki izin tulis pada folder runtime:
```bash
# Ubah ownership sesuai user web server
chown -R www-data:www-data /var/www/wpitv

# Set permission direktori dan file yang aman (bukan 777)
chmod -R 775 /var/www/wpitv/storage
chmod -R 775 /var/www/wpitv/bootstrap/cache
```

### Langkah 7: Optimasi Cache Laravel
Jalankan urutan caching produksi:
```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 4. Konfigurasi Web Server

### A. Nginx Configuration Template
Arahkan `root` ke folder `/public`:
```nginx
server {
    listen 80;
    server_name wpitv.stmik-wp.ac.id;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name wpitv.stmik-wp.ac.id;

    root /var/www/wpitv/public;
    index index.php index.html;

    ssl_certificate /etc/letsencrypt/live/wpitv.stmik-wp.ac.id/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/wpitv.stmik-wp.ac.id/privkey.pem;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    charset utf-8;
    client_max_body_size 10M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### B. Apache Configuration
Pastikan `DocumentRoot` diarahkan ke `/var/www/wpitv/public` dan `AllowOverride All` aktif agar file `public/.htaccess` dapat diproses:
```apache
<VirtualHost *:80>
    ServerName wpitv.stmik-wp.ac.id
    DocumentRoot /var/www/wpitv/public

    <Directory /var/www/wpitv/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/wpitv_error.log
    CustomLog ${APACHE_LOG_DIR}/wpitv_access.log combined
</VirtualHost>
```

---

## 5. Rencana Pengujian Pasca-Deploy (Smoke Test)
Lakukan pengujian manual segera setelah go-live:
1. **Public Smoke Test**:
   - Buka `/` (Homepage) -> Pastikan hero, daftar program, tayangan, berita, dan statistik TV Club muncul.
   - Buka `/program` & klik salah satu program -> Pastikan halaman detail program dan daftar videonya muncul.
   - Buka `/tayangan` & tonton video `/tayangan/{id}` -> Pastikan YouTube player 16:9 berputar normal.
   - Buka `/berita` & detail berita `/berita/{slug}`.
   - Buka `/tv-club` & profil anggota `/tv-club/anggota/{id}`.
   - Buka `/peliputan` & ajukan permohonan test di `/peliputan/ajukan` -> Cek halaman sukses & cek tracking di `/peliputan/status`.
2. **Admin Smoke Test**:
   - Login ke `/login` menggunakan akun admin (`username` + `password`).
   - Cek Dashboard, Program, Video, News, Banner, TV Club, Peliputan, dan Visitor Statistics.
   - Cek manajemen Users & Roles (khusus Super Admin).
   - Logout berhasil.

---

## 6. Prosedur Rollback

Jika terjadi kendala kritis saat rilis produksi:
1. **Aktifkan Maintenance Mode**:
   ```bash
   php artisan down --secret="bypass-key"
   ```
2. **Restore Codebase**: Kembalikan ke branch / tag release sebelumnya:
   ```bash
   git checkout <previous-release-tag>
   composer install --no-dev --optimize-autoloader
   ```
3. **Restore Database (jika diperlukan)**:
   ```bash
   mysql -u [db_user] -p wpitv < pre_deployment_backup.sql
   ```
4. **Rebuild Cache & Nonaktifkan Maintenance**:
   ```bash
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan up
   ```
