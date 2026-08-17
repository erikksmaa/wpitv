<<<<<<< HEAD
# WPiTV / iTV Laravel 12 Core Structure

## Project Overview
Modernisasi website media digital kampus **WPiTV / iTV** milik **STMIK Widya Pratama Pekalongan** berbasis **Laravel 12** dan database eksisting MySQL (`wpitv`).

## Technology Stack
- **Backend:** PHP 8.4, Laravel 12, Composer 2.x, MySQL 8.0.30 (Engine: InnoDB, Charset: utf8mb4), Eloquent ORM, Blade
- **Authentication:** Laravel Breeze (Session-based, disesuaikan menggunakan `username` + `password` & status aktif `status = 1`)
- **Reactive UI:** Livewire 4.x / 3.x
- **Public Frontend:** Blade + Livewire + Tailwind CSS 3.4
- **Admin Frontend:** Blade + Livewire + Bootstrap 5.3 + Mazer Admin Template
- **Build System:** Vite (Entry point publik dan admin dipisah)

## Environment & Database Configuration
Isi konfigurasi pada file `.env` (pastikan tidak memicu migrasi ulang database):
```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wpitv
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=file
```

## Running the Application
### Development Server
```bash
php artisan serve
```
Akses Aplikasi:
- **Public Website:** `http://127.0.0.1:8000/`
- **Login Admin:** `http://127.0.0.1:8000/login`
- **Admin Dashboard:** `http://127.0.0.1:8000/admin` (memerlukan autentikasi pengguna aktif)

### Build Frontend Assets
```bash
npm run build
```
Atau mode watch development:
```bash
npm run dev
```

## Architecture & Rules
1. **Pemisahan Asset Frontend:**
   - Asset Publik: `resources/css/public.css` & `resources/js/public.js` (Tailwind CSS)
   - Asset Admin: `resources/css/admin.css` & `resources/js/admin.js` (Bootstrap 5 & Mazer UI)
2. **Aturan Database & Keamanan Data:**
   - DILARANG menjalankan `php artisan migrate:fresh` atau `php artisan db:wipe`.
   - Table name legacy diakomodasi langsung via Eloquent Model `$table` property (`ms_banner`, `ms_program`, `ms_news`, `ms_video`, `tr_perliputan`, `tr_usercounter`).
   - Autentikasi menggunakan field `username` (bukan email). User non-aktif (`status = 0`) diblokir dari login.
=======
# wpitv
moderenisasi website wpitv
>>>>>>> 9e488494db8ac2e4e11a467fd09d6487acd39dbe
