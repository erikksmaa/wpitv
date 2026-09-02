# PANDUAN DEPLOYMENT WPiTV MODERNIZATION

Panduan ini melengkapi release gate di `docs/PRODUCTION_CHECKLIST.md`. Ganti setiap placeholder dengan nilai infrastruktur produksi yang disetujui.

## 1. Prasyarat

- PHP 8.2+ dengan ekstensi yang diwajibkan `composer.json`, MySQL 8.0+ atau MariaDB 10.6+, Composer 2.x.
- Nginx atau Apache 2.4+; DocumentRoot wajib `<project>/public`.
- Node.js/npm hanya diperlukan bila aset dibangun pada server.
- Backup database dan `storage/app/public` sudah diuji untuk restore.

Database existing adalah source of truth. Jangan jalankan `migrate:fresh`, `migrate:reset`, `db:wipe`, atau migrasi destruktif. Rilis ini tidak memerlukan migrasi.

## 2. Environment

```ini
APP_NAME=WPiTV
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<domain-produksi>
APP_KEY=<kunci-aplikasi-yang-valid>

DB_CONNECTION=mysql
DB_HOST=<host-database>
DB_PORT=3306
DB_DATABASE=wpitv
DB_USERNAME=<akun-khusus-aplikasi-bukan-root>
DB_PASSWORD=<password-kuat-dan-unik>

SESSION_DRIVER=file
SESSION_LIFETIME=30
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
ADMIN_SESSION_IDLE_TIMEOUT=30
ADMIN_SESSION_ABSOLUTE_LIFETIME=8

TRUSTED_HOSTS=<domain-produksi>
TRUSTED_PROXIES=
SECURITY_CSP_REPORT_ONLY=true
```

Isi `TRUSTED_PROXIES` hanya dengan IP/CIDR proxy yang dikelola. Jangan percaya semua proxy. Jangan membuat `APP_KEY` baru pada instalasi yang sudah memakai data terenkripsi atau sesi aktif tanpa rencana rotasi.

## 3. Instalasi release

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Alternatifnya, build `public/build` di CI dan deploy artifact yang tervalidasi. Pastikan `public/hot` tidak ikut dalam release. User web server hanya memerlukan hak tulis pada `storage` dan `bootstrap/cache`; jangan gunakan `777`.

## 4. Web server

Contoh Nginx berikut memakai placeholder dan menolak eksekusi upload. Sesuaikan socket PHP, sertifikat, dan batas ukuran request dengan server.

```nginx
server {
    listen 80;
    server_name <domain-produksi>;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name <domain-produksi>;
    root /var/www/wpitv/public;
    index index.php;

    ssl_certificate <path-fullchain>;
    ssl_certificate_key <path-private-key>;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~* ^/storage/.*\.(php[0-9]?|phtml|phar)$ {
        deny all;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Untuk Apache, aktifkan `mod_rewrite` dan `mod_headers`, arahkan `DocumentRoot` ke `public`, gunakan `AllowOverride All`, nonaktifkan directory listing, dan pertahankan `public/.htaccess`. Pada PHP, set `expose_php=Off`.

Aplikasi mengirim CSP dalam mode report-only pada cutover awal karena editor lokal dan beberapa komponen saat ini masih memakai script/style inline. Uji seluruh alur di preproduction, tinjau semua pelanggaran, lalu set `SECURITY_CSP_REPORT_ONLY=false` ketika tidak ada fungsi yang rusak. Jangan menambahkan wildcard, `unsafe-eval`, atau origin pihak ketiga tanpa bukti kebutuhan.

## 5. Verifikasi dan rollback

Ikuti semua langkah POST-DEPLOY di `docs/PRODUCTION_CHECKLIST.md`, termasuk QA empat peran, alur publik desktop/mobile, Peliputan, upload, header, CSP report, log, dan audit rute. Modul Banner sudah dihentikan dan tidak boleh muncul sebagai rute, menu, model, atau smoke test.

Jika rilis gagal, aktifkan maintenance mode, kembalikan release/tag sebelumnya, pulihkan dependency sesuai lockfile, dan restore database/media hanya jika diperlukan serta setelah dampak data baru dianalisis. Bersihkan lalu bangun ulang cache sebelum health check dan membuka trafik kembali.
