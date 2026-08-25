# SUBSEQUENT RELEASE CHECKLIST
**WPiTV Modernization — Maintenance & Update Procedure**

Gunakan checklist ini untuk rilis pembaruan atau patch di lingkungan produksi WPiTV setelah peluncuran awal.

---

## 1. Persiapan Rilis
- [ ] Buat snapshot backup database sebelum rilis (`mysqldump`).
- [ ] Verifikasi seluruh automated tests lulus di lokal / CI (`php artisan test`).
- [ ] Verifikasi format kode bersih (`./vendor/bin/pint --test`).
- [ ] Pastikan tidak ada skema migrasi destruktif.

## 2. Pelaksanaan Rilis
- [ ] Aktifkan mode pemeliharaan (jika pembaruan berskala besar):
  ```bash
  php artisan down --secret="deploy-bypass"
  ```
- [ ] Ambil kode rilis terbaru (`git pull origin main` atau upload patch).
- [ ] Perbarui dependensi PHP bila ada perubahan `composer.lock`:
  ```bash
  composer install --no-dev --optimize-autoloader
  ```
- [ ] Kompilasi aset Vite bila ada perubahan frontend:
  ```bash
  npm run build
  ```
- [ ] Rebuild cache sistem:
  ```bash
  php artisan optimize:clear
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  ```
- [ ] Nonaktifkan mode pemeliharaan:
  ```bash
  php artisan up
  ```

## 3. Verifikasi Rilis
- [ ] Jalankan smoke test cepat pada halaman publik utama (`/`, `/program`, `/tayangan`, `/berita`, `/tv-club`, `/peliputan`).
- [ ] Cek login Admin dan coba ubah/tambah 1 record pengujian jika diperlukan.
- [ ] Periksa file log terbaru di `storage/logs/laravel.log`.

## 4. Kesiapan Rollback
- [ ] Jika ditemukan kegagalan fatal, segera jalankan prosedur rollback sesuai petunjuk di `DEPLOYMENT.md`.
